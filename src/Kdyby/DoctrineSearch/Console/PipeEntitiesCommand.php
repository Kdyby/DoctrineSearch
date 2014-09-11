<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\DoctrineSearch\Console;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Kdyby;
use Nette;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class PipeEntitiesCommand extends Command
{

	/**
	 * @var \Doctrine\Search\SearchManager
	 * @inject
	 */
	public $searchManager;

	/**
	 * @var \Doctrine\ORM\EntityManager
	 * @inject
	 */
	public $entityManager;



	protected function configure()
	{
		$this->setName('elastica:pipe-entities');
		// todo: filter types, ...
	}



	protected function execute(InputInterface $input, OutputInterface $output)
	{
		/** @var \Doctrine\Search\Mapping\ClassMetadata[] $classes */
		$classes = $this->searchManager->getClassMetadataFactory()->getAllMetadata();

		foreach ($classes as $class) {
			$output->writeln(sprintf('Indexing <info>%s</info>', $class->getName()));
			$this->indexEntities($class->getName(), $output);
		}

		$output->writeln('');
		$output->writeln('<info>Finished!</info>');
	}



	/**
	 * @param string $className
	 */
	protected function indexEntities($className, OutputInterface $output)
	{
		$class = $this->entityManager->getClassMetadata($className);
		$repository = $this->entityManager->getRepository($className);

		$qb = $repository->createQueryBuilder('e');

		$i = 0;
		foreach ($class->getAssociationMappings() as $assocMapping) {
			if (!$class->isSingleValuedAssociation($assocMapping['fieldName'])) {
				continue;
			}

			$targetClass = $this->entityManager->getClassMetadata($assocMapping['targetEntity']);

			$alias = substr($assocMapping['fieldName'], 0, 1) . ($i++);
			$qb->leftJoin('e.' . $assocMapping['fieldName'], $alias)
				->addSelect('partial ' . $alias . '.{' . implode(',', $targetClass->getIdentifierColumnNames()) . '}');

			// todo: deeper!
		}

		$countQuery = $repository->createQueryBuilder('e')
			->select('COUNT(e)')
			->getQuery();

		$paginator = new Nette\Utils\Paginator();
		$paginator->itemsPerPage = 100;
		$paginator->itemCount = $countQuery->getSingleScalarResult();

		$progress = new ProgressBar($output, $paginator->getItemCount());
		$progress->setFormat($progress::getFormatDefinition('debug'));
		$progress->start();

		$query = $qb->getQuery()->setMaxResults($paginator->getLength());
		while (1) {
			$entities = $query->setFirstResult($paginator->getOffset())->getResult();

			$this->searchManager->persist($entities);
			$this->searchManager->flush();
			$this->searchManager->clear();

			try {
				$progress->advance(count($entities));
			} catch (\Exception $e) { }

			$this->entityManager->clear();

			if ($paginator->isLast()) {
				break;
			}

			$paginator->page += 1;
		}

		$progress->finish();
		$output->writeln('');
	}

}
