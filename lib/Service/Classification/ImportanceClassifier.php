<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Mail\Service\Classification;

use Horde_Imap_Client;
use OCA\Mail\Account;
use OCA\Mail\Address;
use OCA\Mail\AddressList;
use OCA\Mail\Db\Mailbox;
use OCA\Mail\Db\MailboxMapper;
use OCA\Mail\Db\Message;
use OCA\Mail\Db\MessageMapper;
use OCA\Mail\Service\Classification\FeatureExtraction\CompositeExtractor;
use OCA\Mail\Support\PerformanceLogger;
use OCA\Mail\Vendor\Phpml\Classification\NaiveBayes;
use OCA\Mail\Vendor\Phpml\FeatureExtraction\TokenCountVectorizer;
use OCA\Mail\Vendor\Phpml\Tokenization\WhitespaceTokenizer;
use OCP\ILogger;
use RuntimeException;
use function array_column;
use function array_filter;
use function array_map;

class ImportanceClassifier {

	/**
	 * Mailbox special uses to exclude from the training
	 */
	private const EXEMPT_FROM_TRAINING = [
		Horde_Imap_Client::SPECIALUSE_ALL,
		Horde_Imap_Client::SPECIALUSE_DRAFTS,
		Horde_Imap_Client::SPECIALUSE_FLAGGED,
		Horde_Imap_Client::SPECIALUSE_JUNK,
		Horde_Imap_Client::SPECIALUSE_SENT,
		Horde_Imap_Client::SPECIALUSE_TRASH,
	];

	/**
	 * @var string label for data sets that are classified as important
	 */
	private const LABEL_IMPORTANT = 'i';

	/**
	 * @var string label for data sets that are classified as not important
	 */
	private const LABEL_NOT_IMPORTANT = 'ni';

	/**
	 * The maximum number of data sets to train the classifier with
	 */
	private const MAX_TRAINING_SET_SIZE = 1000;

	/** @var MailboxMapper */
	private $mailboxMapper;

	/** @var MessageMapper */
	private $messageMapper;

	/** @var CompositeExtractor */
	private $extractor;

	/** @var PerformanceLogger */
	private $performanceLogger;

	/** @var ILogger */
	private $logger;

	public function __construct(MailboxMapper $mailboxMapper,
								MessageMapper $messageMapper,
								CompositeExtractor $extractor,
								PerformanceLogger $performanceLogger,
								ILogger $logger) {
		$this->mailboxMapper = $mailboxMapper;
		$this->messageMapper = $messageMapper;
		$this->extractor = $extractor;
		$this->performanceLogger = $performanceLogger;
		$this->logger = $logger;
	}

	/**
	 * Train an account's classifier of important messages
	 *
	 * Train a classifier based on a user's existing messages to be able to derive
	 * importance markers for new incoming messages.
	 *
	 * To factor in (server-side) filtering into multiple mailboxes, the algorithm
	 * will not only look for messages in the inbox but also other non-special
	 * mailboxes.
	 *
	 * To prevent memory exhaustion, the process will only load a fixed maximum
	 * number of messages per account.
	 *
	 * @param Account $account
	 */
	public function train(Account $account): void {
		$perf = $this->performanceLogger->start('importance classifier training');
		$incomingMailboxes = array_filter($this->mailboxMapper->findAll($account), function (Mailbox $mailbox) {
			foreach (self::EXEMPT_FROM_TRAINING as $excluded) {
				if ($mailbox->isSpecialUse($excluded)) {
					return false;
				}
			}
			return true;
		});
		$perf->step('find incoming mailboxes');
		// TODO: allow more than one outgoing mailbox
		$sentMailbox = $this->mailboxMapper->findSpecial($account, 'sent');
		$outgoingMailboexs = $sentMailbox === null ? [] : [$sentMailbox];
		$perf->step('find outgoing mailboxes');

		$mailboxIds = array_map(function (Mailbox $mailbox) {
			return $mailbox->getId();
		}, $incomingMailboxes);
		$messages = $this->messageMapper->findLatestMessages($mailboxIds, self::MAX_TRAINING_SET_SIZE);
		$perf->step('find latest ' . self::MAX_TRAINING_SET_SIZE . ' messages');

		$features = $this->getFeaturesAndImportance($account, $incomingMailboxes, $outgoingMailboexs, $messages);
		$perf->step('extract features from messages');

		$classifier = new NaiveBayes();
		$classifier->train(array_column($features, 'features'), array_column($features, 'label'));
		$perf->step('train classifier');

		$p = $classifier->predict(array_column($features, 'features'));
		$perf->step('predict importance of training data');
		$impo = [];
		$notimpo = [];
		for ($i = 0, $iMax = count($messages); $i < $iMax; $i++) {
			if ($p[$i] === 'i') {
				$impo[] = $messages[$i];
			} else {
				$notimpo[] = $messages[$i];
			}
		}
	}

	/**
	 * Get the feature vector of every message
	 *
	 * @param Account $account
	 * @param Mailbox[] $incomingMailboxes
	 * @param Mailbox[] $outgoingMailboxes
	 * @param Message[] $messages
	 *
	 * @return array
	 */
	private function getFeaturesAndImportance(Account $account,
											  array $incomingMailboxes,
											  array $outgoingMailboxes,
											  array $messages): array {
		$this->extractor->prepare($account, $incomingMailboxes, $outgoingMailboxes, $messages);

		return array_map(function (Message $message) {
			$sender = $message->getFrom()->first();
			if ($sender === null) {
				throw new RuntimeException("This should not happen");
			}

			return [
				'features' => $this->extractor->extract($sender->getEmail()),
				'label' => $message->getFlagImportant() ? self::LABEL_IMPORTANT : self::LABEL_NOT_IMPORTANT,
				'sender' => $sender->getEmail(),
			];
		}, $messages);
	}

	public function isImportant(Account $account, Mailbox $mailbox, Message $message): bool {

	}

}
