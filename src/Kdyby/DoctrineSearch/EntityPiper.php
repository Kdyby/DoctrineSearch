<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\DoctrineSearch;

use Doctrine\Search\EntityRiver;
use Doctrine\Search\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadata as ORMMetadata;
use Doctrine\Search\SearchManager;
use Kdyby;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>

 * @method onIndexStart(EntityPiper $self, Nette\Utils\Paginator $paginator, EntityRiver $river, ORMMetadata $class)
 * @method onIndexStats(EntityPiper $self, ORMMetadata $class, int $timeToIndex, int $timeToRead)
 * @method onItemsIndexed(EntityPiper $self, array $entities)
 * @method onChildSkipped(EntityPiper $self, ClassMetadata $meta, ClassMetadata $parentMeta)
 */
class EntityPiper extends Nette\Object
{

	/**
	 * @var array
	 */
	public $onIndexStart = array();

	/**
	 * @var array
	 */
	public $onIndexStats = array();

	/**
	 * @var array
	 */
	public $onItemsIndexed = array();

	/**
	 * @var array
	 */
	public $onChildSkipped = array();

	/**
	 * @var Kdyby\Doctrine\EntityManager
	 */
	private $entityManager;

	/**
	 * @var SearchManager
	 */
	private $searchManager;

	/**
	 * @var Nette\DI\Container
	 */
	private $serviceLocator;



	public function __construct(Kdyby\Doctrine\EntityManager $entityManager, SearchManager $searchManager, Nette\DI\Container $serviceLocator)
	{
		$this->entityManager = $entityManager;
		$this->searchManager = $searchManager;
		$this->serviceLocator = $serviceLocator;
	}



	public function indexEntities(ClassMetadata $searchMeta)
	{
		foreach ($this->searchManager->getMetadataFactory()->getAllMetadata() as $otherMeta) {
			if ($searchMeta->className === $otherMeta->className) {
				continue;
			}

			if (is_subclass_of($searchMeta->className, $otherMeta->className)) {
				$this->onChildSkipped($this, $searchMeta, $otherMeta);
				return;
			}
		}

		if ($searchMeta->riverImplementation) {
			$river = $this->serviceLocator->getByType($searchMeta->riverImplementation);

		} else {
			/** @var River\DefaultEntityRiver $river */
			$river = $this->serviceLocator->createInstance('Kdyby\DoctrineSearch\River\DefaultEntityRiver');
		}

		if (!$river instanceof EntityRiver) {
			throw new UnexpectedValueException('The river must implement Doctrine\Search\EntityRiver.');
		}

		if (property_exists($river, 'onIndexStart')) {
			$river->onIndexStart[] = function (EntityRiver $river, $paginator, ORMMetadata $class) {
				$this->onIndexStart($this, $paginator, $river, $class);
			};
		}

		if (property_exists($river, 'onItemsIndexed')) {
			$river->onItemsIndexed[] = function ($self, $entities) {
				$this->onItemsIndexed($this, $entities);
			};
		}

		if (property_exists($river, 'onIndexStats')) {
			$river->onIndexStats[] = function ($self, ORMMetadata $class, $timeToIndex, $timeToRead) {
				$this->onIndexStats($this, $class, $timeToIndex, $timeToRead);
			};
		}

		// disable logger
		$config = $this->entityManager->getConfiguration();
		$oldLogger = $config->getSQLLogger();
		$config->setSQLLogger(NULL);

		$river->transfuse($searchMeta);

		$config->setSQLLogger($oldLogger);
	}

}
