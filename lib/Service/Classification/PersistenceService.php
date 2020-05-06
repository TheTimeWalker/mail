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

use OCA\Mail\AppInfo\Application;
use OCA\Mail\Db\Classifier;
use OCA\Mail\Db\ClassifierMapper;
use OCA\Mail\Exception\ServiceException;
use OCA\Mail\Vendor\Phpml\Estimator;
use OCA\Mail\Vendor\Phpml\Exception\FileException;
use OCA\Mail\Vendor\Phpml\Exception\SerializeException;
use OCA\Mail\Vendor\Phpml\ModelManager;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\ITempManager;
use function file_get_contents;
use function get_class;

class PersistenceService {
	private const ADD_DATA_FOLDER = 'classifiers';

	/** @var ClassifierMapper */
	private $mapper;

	/** @var IAppData */
	private $appData;

	/** @var ITempManager */
	private $tempManager;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var ModelManager */
	private $modelManager;

	/** @var IAppManager */
	private $appManager;

	public function __construct(ClassifierMapper $mapper,
								IAppData $appData,
								ITempManager $tempManager,
								ITimeFactory $timeFactory,
								ModelManager $modelManager,
								IAppManager $appManager) {
		$this->mapper = $mapper;
		$this->appData = $appData;
		$this->tempManager = $tempManager;
		$this->timeFactory = $timeFactory;
		$this->modelManager = $modelManager;
		$this->appManager = $appManager;
	}

	/**
	 * Persist the classifier data to the database and the estimator to storage
	 *
	 * @param Classifier $classifier
	 * @param Estimator $estimator
	 *
	 * @throws ServiceException
	 */
	public function persist(Classifier $classifier, Estimator $estimator): void {
		/*
		 * First we have to insert the row to get the unique ID, but disable
		 * it until the model is persisted as well. Otherwise another process
		 * might try to load the model in the meantime and run into an error
		 * due to the missing data in app data.
		 */
		$classifier->setAppVersion($this->appManager->getAppVersion(Application::APP_ID));
		$classifier->setEstimator(get_class($estimator));
		$classifier->setActive(false);
		$classifier->setCreatedAt($this->timeFactory->getTime());
		$this->mapper->insert($classifier);

		/*
		 * Then we serialize the estimator into a temporary file
		 */
		$tmpPath = $this->tempManager->getTemporaryFile();
		try {
			$this->modelManager->saveToFile($estimator, $tmpPath);
		} catch (FileException|SerializeException $e) {
			throw new ServiceException("Could not serialize classifier: " . $e->getMessage(), 0, $e);
		}

		/*
		 * Then we store the serialized model to app data
		 */
		try {
			try {
				$folder = $this->appData->getFolder(self::ADD_DATA_FOLDER);
			} catch (NotFoundException $e) {
				$folder = $this->appData->newFolder(self::ADD_DATA_FOLDER);
			}
			$folder->newFile($classifier->getId(), file_get_contents($tmpPath));
		} catch (NotPermittedException $e) {
			throw new ServiceException("Could not create classifiers directory: " . $e->getMessage(), 0, $e);
		}

		/*
		 * Now we set the model active so it can be used by the next request
		 */
		$classifier->setActive(true);
		$this->mapper->update($classifier);
	}
}
