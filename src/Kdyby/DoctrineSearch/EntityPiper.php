<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\DoctrineSearch;

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
	 * @var SearchManager
	 */
	private $searchManager;

	/**
	 * @var Kdyby\Doctrine\EntityManager
	 */
	private $entityManager;



	public function __construct(SearchManager $searchManager, Kdyby\Doctrine\EntityManager $entityManager)
	{
		$this->searchManager = $searchManager;
		$this->entityManager = $entityManager;
	}



	public function indexEntities(ClassMetadata $searchMeta)
	{
		$class = $this->entityManager->getClassMetadata($searchMeta->getName());
		$repository = $this->entityManager->getRepository($searchMeta->getName());

		$qb = $repository->createQueryBuilder('e');

		$i = 0;
		foreach ($class->getAssociationMappings() as $assocMapping) {
			if (!$class->isSingleValuedAssociation($assocMapping['fieldName'])) {
				continue;
			}

			$targetClass = $this->entityManager->getClassMetadata($assocMapping['targetEntity']);

			$alias = substr($assocMapping['fieldName'], 0, 1) . ($i++);
			$qb->leftJoin('e.' . $assocMapping['fieldName'], $alias)->addSelect($alias);

			// todo: deeper!
		}

		$countQuery = $repository->createQueryBuilder('e')
			->select('COUNT(e)')
			->getQuery();

		$paginator = new Nette\Utils\Paginator();
		$paginator->itemsPerPage = 100;
		$paginator->itemCount = $countQuery->getSingleScalarResult();

		$this->onIndexStart($this, $paginator);

		$query = $qb->getQuery()->setMaxResults($paginator->getLength());
		while (1) {
			$entities = $query->setFirstResult($paginator->getOffset())->getResult();

			$this->searchManager->persist($entities);
			$this->searchManager->flush();
			$this->searchManager->clear();

			try {
				$this->onItemsIndexed($this, $entities);
			} catch (\Exception $e) {}

			$this->entityManager->clear();

			if ($paginator->isLast()) {
				break;
			}

			$paginator->page += 1;
		}
	}

}
