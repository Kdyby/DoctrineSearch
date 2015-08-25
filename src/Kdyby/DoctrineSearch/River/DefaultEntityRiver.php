<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\DoctrineSearch\River;

use Doctrine\ORM\EntityRepository;
use Doctrine\Search\EntityRiver;
use Doctrine\Search\Mapping\ClassMetadata as SearchMetadata;
use Doctrine\ORM\Mapping\ClassMetadata as ORMMetadata;
use Doctrine\Search\SearchManager;
use Kdyby;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>

 * @method onIndexStart(DefaultEntityRiver $self, Nette\Utils\Paginator $paginator, ORMMetadata $class)
 * @method onIndexStats(DefaultEntityRiver $self, ORMMetadata $class, int $timeToIndex, int $timeToRead)
 * @method onItemsIndexed(DefaultEntityRiver $self, array $entities)
 */
class DefaultEntityRiver extends Nette\Object implements EntityRiver
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
	 * @var Kdyby\Doctrine\EntityManager
	 */
	protected $entityManager;

	/**
	 * @var SearchManager
	 */
	protected $searchManager;



	public function __construct(SearchManager $searchManager, Kdyby\Doctrine\EntityManager $em)
	{
		$this->entityManager = $em;
		$this->searchManager = $searchManager;
	}



	public function transfuse(SearchMetadata $searchMeta)
	{
		$class = $this->entityManager->getClassMetadata($searchMeta->getName());
		$repository = $this->entityManager->getRepository($searchMeta->getName());

		$this->createAndUpdate($class, $repository);
		$this->dropOld($class, $repository);
	}



	protected function createAndUpdate(ORMMetadata $class, EntityRepository $repository)
	{
		$paginator = new Nette\Utils\Paginator();
		$paginator->itemsPerPage = 1000;

		$countQuery = $this->buildCountForUpdateQuery($repository)->getQuery();
		$paginator->itemCount = $countQuery->getSingleScalarResult();

		$this->onIndexStart($this, $paginator, $class);

		if ($paginator->itemCount <= 0) {
			return;
		}

		$qb = $this->buildSelectForUpdateQuery($repository, $class);
		if ($identifier = $class->getSingleIdentifierColumnName()) {
			$qb->orderBy(sprintf('e.%s', $identifier), 'ASC');
		}

		$lastId = NULL;
		$selectQueryBuilder = $qb->setMaxResults($paginator->getLength());
		while (1) {
			if ($lastId !== NULL) {
				$qbCopy = clone $selectQueryBuilder;
				$query = $qbCopy
					->andWhere(sprintf('e.%s > :lastId', $identifier))
					->setParameter('lastId', $lastId)
					->getQuery();

			} else {
				$query = $selectQueryBuilder->getQuery();
				if (!$identifier) {
					$query->setFirstResult($paginator->getOffset());
				}
			}

			$beginTime = microtime(TRUE);

			$entities = $query->getResult();
			$this->postFetch($entities, $repository, $class);

			$loadedTime = microtime(TRUE);

			$this->doPersistEntities($entities);

			$this->searchManager->flush();
			$this->searchManager->clear();

			try {
				$this->onItemsIndexed($this, $entities);
			} catch (\Exception $e) {
			}

			if ($identifier) {
				$lastIdentifier = $class->getIdentifierValues(end($entities));  // [id => value]
				$lastId = reset($lastIdentifier);
				if (!is_numeric($lastId)) {
					trigger_error(E_USER_WARNING, 'Expected numeric identifier');
				}
			}

			$this->entityManager->clear();

			$this->onIndexStats($this, $class, microtime(TRUE) - $loadedTime, $loadedTime - $beginTime);

			if ($paginator->isLast()) {
				break;
			}

			$paginator->page += 1;
		}
	}



	protected function doPersistEntities($entities)
	{
		$this->searchManager->persist($entities);
	}



	protected function dropOld(ORMMetadata $class, EntityRepository $repository)
	{

	}



	protected function doRemoveEntities($entities)
	{
		$this->searchManager->remove($entities);
	}



	/**
	 * @param EntityRepository $repository
	 * @param ORMMetadata $class
	 * @return \Doctrine\ORM\QueryBuilder
	 */
	protected function buildSelectForUpdateQuery(EntityRepository $repository, ORMMetadata $class)
	{
		$qb = $repository->createQueryBuilder('e', 'e.id');

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

		return $qb;
	}



	/**
	 * @param EntityRepository $repository
	 * @return \Doctrine\ORM\QueryBuilder
	 */
	protected function buildCountForUpdateQuery(EntityRepository $repository)
	{
		$countQuery = $repository->createQueryBuilder('e')
			->select('COUNT(e)');

		return $countQuery;
	}



	protected function postFetch(array $entities, EntityRepository $repository, ORMMetadata $class)
	{

	}

}
