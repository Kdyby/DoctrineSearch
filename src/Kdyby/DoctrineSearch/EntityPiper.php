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
use Doctrine\Search\SearchManager;
use Kdyby;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @method onIndexStart(EntityPiper $self, Nette\Utils\Paginator $paginator)
 * @method onItemsIndexed(EntityPiper $self, array $entities)
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
	public $onItemsIndexed = array();

	/**
	 * @var Kdyby\Doctrine\EntityManager
	 */
	private $entityManager;

	/**
	 * @var Nette\DI\Container
	 */
	private $serviceLocator;



	public function __construct(Kdyby\Doctrine\EntityManager $entityManager, Nette\DI\Container $serviceLocator)
	{
		$this->entityManager = $entityManager;
		$this->serviceLocator = $serviceLocator;
	}



	public function indexEntities(ClassMetadata $searchMeta)
	{
		if ($searchMeta->riverImplementation) {
			$river = $this->serviceLocator->getByType($searchMeta->riverImplementation);

		} else {
			$river = $this->serviceLocator->createInstance('Kdyby\DoctrineSearch\River\DefaultEntityRiver');
		}

		if (!$river instanceof EntityRiver) {
			throw new UnexpectedValueException('The river must implement Doctrine\Search\EntityRiver.');
		}

		if (property_exists($river, 'onIndexStart')) {
			$river->onIndexStart[] = function ($self, $paginator) {
				$this->onIndexStart($this, $paginator);
			};
		}

		if (property_exists($river, 'onItemsIndexed')) {
			$river->onItemsIndexed[] = function ($self, $entities) {
				$this->onItemsIndexed($this, $entities);
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
