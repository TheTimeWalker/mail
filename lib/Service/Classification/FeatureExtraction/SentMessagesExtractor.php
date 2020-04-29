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

namespace OCA\Mail\Service\Classification\FeatureExtraction;


use OCA\Mail\Account;
use OCA\Mail\Db\Mailbox;
use OCA\Mail\Db\StatisticsDao;
use function array_reduce;

class SentMessagesExtractor implements IExtractor {

	/** @var int */
	private $messagesSentTotal = 0;

	/** @var Mailbox[] */
	private $outgoingMailboxes = [];

	/** @var StatisticsDao */
	private $statisticsDao;

	public function __construct(StatisticsDao $statisticsDao) {
		$this->statisticsDao = $statisticsDao;
	}

	public function initialize(Account $account, array $incomingMailboxes, array $outgoingMailboxes): bool {
		$this->outgoingMailboxes = $outgoingMailboxes;
		$this->messagesSentTotal = array_reduce($outgoingMailboxes, function (int $carry, Mailbox $mailbox) {
			return $carry + $this->statisticsDao->getMessagesTotal($mailbox);
		}, 0);

		// This extractor is only applicable if there are sent messages
		return $this->messagesSentTotal > 0;
	}

	public function extract(string $email): float {
		$cnt = array_reduce($this->outgoingMailboxes, function (int $carry, Mailbox $mailbox) use ($email) {
			return $carry + $this->statisticsDao->getMessagesSentTo($mailbox, $email);
		}, 0);

		return $cnt / $this->messagesSentTotal;
	}

}
