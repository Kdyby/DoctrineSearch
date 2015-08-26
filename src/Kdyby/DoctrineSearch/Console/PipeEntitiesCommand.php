<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\DoctrineSearch\Console;

use Doctrine\ORM\Mapping\ClassMetadata as ORMMetadata;
use Doctrine\Search\EntityRiver;
use Doctrine\Search\Mapping\ClassMetadata;
use Kdyby;
use Nette;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
	 * @var \Kdyby\DoctrineSearch\EntityPiper
	 * @inject
	 */
	public $entityPiper;



	protected function configure()
	{
		$this->setName('elastica:pipe-entities')
			->addOption('entity', 'e', InputOption::VALUE_OPTIONAL, 'Synchronizes only specified entity')
			->addOption('stats', NULL, InputOption::VALUE_NONE, 'Show stats of progress')
			->addArgument('index-aliases', InputArgument::IS_ARRAY, 'Alias map of alias=original for indexes');

		// todo: filter types, ...
	}



	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		parent::initialize($input, $output);

		/** @var \Doctrine\Search\ElasticSearch\Client $searchClient */
		$searchClient = $this->searchManager->getClient();

		/** @var Kdyby\ElasticSearch\Client $apiClient */
		$apiClient = $searchClient->getClient();
		$apiClient->onError = [];
		$apiClient->onSuccess = [];
	}



	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$metaFactory = $this->searchManager->getClassMetadataFactory();

		/** @var \Doctrine\Search\Mapping\ClassMetadata[] $classes */
		if ($onlyEntity = $input->getOption('entity')) {
			$classes = [$metaFactory->getMetadataFor($onlyEntity)];

		} else {
			$classes = $metaFactory->getAllMetadata();
		}

		/** @var ProgressBar $progress */
		$progress = NULL;
		$this->entityPiper->onIndexStart[] = function ($ep, Nette\Utils\Paginator $paginator, EntityRiver $river, ORMMetadata $class) use ($output, &$progress) {
			$output->writeln(sprintf('Indexing <info>%s</info> using <info>%s</info>', $class->getName(), get_class($river)));

			if ($paginator->getItemCount() <= 0) {
				$output->writeln(" 0/0 River didn't return any results");
				return;
			};

			$progress = new ProgressBar($output, $paginator->getItemCount());
			$progress->setFormat($progress::getFormatDefinition('debug'));
			$progress->start();
		};
		$this->entityPiper->onIndexStats[] = function ($ep, ORMMetadata $meta, $timeToIndex, $timeToRead) use ($input, $output) {
			if (!$input->getOption('stats')) {
				return;
			}

			$format = function ($time) {
				if ($time < 10) {
					return number_format($time * 1000, 6, '.', '') . ' ms';
				} else {
					return number_format($time, 2, '.', '') . ' s';
				}
			};
			$output->writeln(sprintf(" ... Loading data took %s, indexing took %s", $format($timeToRead), $format($timeToIndex)));
		};
		$this->entityPiper->onItemsIndexed[] = function ($ep, $entities) use ($output, &$progress) {
			$progress->advance(count($entities));
		};
		$this->entityPiper->onChildSkipped[] = function ($ep, ClassMetadata $meta, ClassMetadata $parent) use ($output, &$progress) {
			$output->writeln(sprintf('<info>%s</info> is a subclass of <info>%s</info> (being piped into <info>%s</info> type), ignoring.', $meta->className, $parent->className, $parent->type->name));
			$progress = NULL;
		};

		$aliases = array(/* original => alias */);
		$indexAliases = $input->getArgument('index-aliases');
		foreach ($indexAliases as $tmp) {
			list($alias, $original) = explode('=', $tmp, 2);
			$aliases[$original] = $alias;
		}

		foreach ($classes as $class) {
			$output->writeln('');

			if (isset($aliases[$class->getIndexName()])) {
				$output->writeln(sprintf('Redirecting data from <info>%s</info> to <info>%s</info>', $class->getIndexName(), $aliases[$class->getIndexName()]));
				$class->index->name = $aliases[$class->getIndexName()];
			}

			unset($e);
			try {
				$this->entityPiper->indexEntities($class);

			} catch (\Exception $e) { }

			// fix the metadata
			if ($old = array_search($class->getIndexName(), $aliases, TRUE)) {
				$class->index->name = $old;
			}

			if (isset($e)) {
				throw $e;
			}

			if ($progress !== NULL) {
				$progress->finish();
			}
			$output->writeln('');
		}
	}

}
