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
			->addArgument('index-aliases', InputArgument::IS_ARRAY, "Alias map of alias=original for indexes");

		// todo: filter types, ...
	}



	protected function execute(InputInterface $input, OutputInterface $output)
	{
		/** @var \Doctrine\Search\Mapping\ClassMetadata[] $classes */
		$classes = $this->searchManager->getClassMetadataFactory()->getAllMetadata();

		/** @var ProgressBar $progress */
		$progress = NULL;
		$this->entityPiper->onIndexStart[] = function ($ep, Nette\Utils\Paginator $paginator) use ($output, &$progress) {
			$progress = new ProgressBar($output, $paginator->getItemCount());
			$progress->setFormat($progress::getFormatDefinition('debug'));
			$progress->start();
		};
		$this->entityPiper->onItemsIndexed[] = function ($ep, $entities) use ($output, &$progress) {
			$progress->advance(count($entities));
		};

		$aliases = array(/* original => alias */);
		$indexAliases = $input->getArgument('index-aliases');
		foreach ($indexAliases as $tmp) {
			list($alias, $original) = explode('=', $tmp, 2);
			$aliases[$original] = $alias;
		}

		foreach ($classes as $class) {
			$output->writeln('');
			$output->writeln(sprintf('Indexing <info>%s</info>', $class->getName()));

			if (isset($aliases[$class->getIndexName()])) {
				$output->writeln(sprintf('Redirecting data from <info>%s</info> to <info>%s</info>', $class->getIndexName(), $aliases[$class->getIndexName()]));
				$class->index->name = $aliases[$class->getIndexName()];
			}

			unset($e);
			try {
				$this->entityPiper->indexEntities($class);

			} catch (\Exception $e) { }

			// fix the metadata
			$class->index->name = array_search($class->getIndexName(), $aliases, TRUE);

			if (isset($e)) {
				throw $e;
			}

			$progress->finish();
			$output->writeln('');
		}
	}

}
