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

namespace OCA\Mail\Db;

use OCA\Mail\Address;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class StatisticsDao {

	/** @var IDBConnection */
	private $db;

	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	public function getMessagesTotal(Mailbox $mb): int {
		$qb = $this->db->getQueryBuilder();

		$select = $qb->select($qb->func()->count('*'))
			->from('mail_recipients', 'r')
			->join('r', 'mail_messages', 'm', $qb->expr()->eq('m.id', 'r.message_id'))
			->where($qb->expr()->eq('r.type', $qb->createNamedParameter(Address::TYPE_FROM, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT))
			->andWhere($qb->expr()->eq('m.mailbox_id', $qb->createNamedParameter($mb->getId(), IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT));
		$result = $select->execute();
		$cnt = $result->fetchColumn();
		$result->closeCursor();
		return (int)$cnt;
	}

	public function getMessagesSentTo(Mailbox $mb, string $email): int {
		$qb = $this->db->getQueryBuilder();

		$select = $qb->select($qb->func()->count('*'))
			->from('mail_recipients', 'r')
			->join('r', 'mail_messages', 'm', $qb->expr()->eq('m.id', 'r.message_id', IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('r.type', $qb->createNamedParameter(Address::TYPE_FROM), IQueryBuilder::PARAM_INT))
			->andWhere($qb->expr()->eq('r.email', $qb->createNamedParameter($email)))
			->andWhere($qb->expr()->eq('m.mailbox_id', $qb->createNamedParameter($mb->getId(), IQueryBuilder::PARAM_INT)));
		$result = $select->execute();
		$cnt = $result->fetchColumn();
		$result->closeCursor();
		return (int)$cnt;
	}

	public function getNrOfImportantMessages(Mailbox $mb, string $email): int {
		$qb = $this->db->getQueryBuilder();

		$select = $qb->select($qb->func()->count('*'))
			->from('mail_recipients', 'r')
			->join('r', 'mail_messages', 'm', $qb->expr()->eq('m.id', 'r.message_id', IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('r.type', $qb->createNamedParameter(Address::TYPE_FROM), IQueryBuilder::PARAM_INT))
			->andWhere($qb->expr()->eq('m.mailbox_id', $qb->createNamedParameter($mb->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('m.flag_important', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('r.email', $qb->createNamedParameter($email)));
		$result = $select->execute();
		$cnt = $result->fetchColumn();
		$result->closeCursor();
		return (int)$cnt;
	}

	public function getNumberOfMessages(Mailbox $mb, string $email): int {
		$qb = $this->db->getQueryBuilder();

		$select = $qb->select($qb->func()->count('*'))
			->from('mail_recipients', 'r')
			->join('r', 'mail_messages', 'm', $qb->expr()->eq('m.id', 'r.message_id', IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('r.type', $qb->createNamedParameter(Address::TYPE_FROM), IQueryBuilder::PARAM_INT))
			->andWhere($qb->expr()->eq('m.mailbox_id', $qb->createNamedParameter($mb->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('r.email', $qb->createNamedParameter($email)));
		$result = $select->execute();
		$cnt = $result->fetchColumn();
		$result->closeCursor();
		return (int)$cnt;
	}

	public function getNrOfReadMessages(Mailbox $mb, string $email): int {
		$qb = $this->db->getQueryBuilder();

		$select = $qb->select($qb->func()->count('*'))
			->from('mail_recipients', 'r')
			->join('r', 'mail_messages', 'm', $qb->expr()->eq('m.id', 'r.message_id', IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('r.type', $qb->createNamedParameter(Address::TYPE_FROM), IQueryBuilder::PARAM_INT))
			->andWhere($qb->expr()->eq('m.mailbox_id', $qb->createNamedParameter($mb->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('m.flag_seen', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('r.email', $qb->createNamedParameter($email)));
		$result = $select->execute();
		$cnt = $result->fetchColumn();
		$result->closeCursor();
		return (int)$cnt;
	}

	public function getNrOfRepliedMessages(Mailbox $mb, string $email): int {
		$qb = $this->db->getQueryBuilder();

		$select = $qb->select($qb->func()->count('*'))
			->from('mail_recipients', 'r')
			->join('r', 'mail_messages', 'm', $qb->expr()->eq('m.id', 'r.message_id', IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('r.type', $qb->createNamedParameter(Address::TYPE_FROM), IQueryBuilder::PARAM_INT))
			->andWhere($qb->expr()->eq('m.mailbox_id', $qb->createNamedParameter($mb->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('m.flag_answered', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('r.email', $qb->createNamedParameter($email)));
		$result = $select->execute();
		$cnt = $result->fetchColumn();
		$result->closeCursor();
		return (int)$cnt;
	}

}
