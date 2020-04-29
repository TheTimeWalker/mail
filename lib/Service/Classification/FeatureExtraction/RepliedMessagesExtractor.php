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

class RepliedMessagesExtractor implements IExtractor {

	/** @var Mailbox[] */
	private $incomingMailboxes = [];

	/** @var StatisticsDao */
	private $statisticsDao;

	public function __construct(StatisticsDao $statisticsDao) {
		$this->statisticsDao = $statisticsDao;
	}

	public function initialize(Account $account, array $incomingMailboxes, array $outgoingMailboxes): bool {
		$this->incomingMailboxes = $incomingMailboxes;

		return true;
	}

	public function extract(string $email): float {
		$total = array_reduce($this->incomingMailboxes, function (int $carry, Mailbox $mailbox) use ($email) {
			return $carry + $this->statisticsDao->getNumberOfMessages($mailbox, $email);
		}, 0);
		$read = array_reduce($this->incomingMailboxes, function (int $carry, Mailbox $mailbox) use ($email) {
			return $carry + $this->statisticsDao->getNrOfRepliedMessages($mailbox, $email);
		}, 0);

		// Prevent division by zero and just say no emails are replied
		if ($total === 0) {
			return 0;
		}

		return $read / $total;
	}

}
