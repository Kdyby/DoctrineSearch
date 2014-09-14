<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\DoctrineSearch\Console;

use Doctrine\Search\Mapping\ClassMetadata;
use Kdyby;
use Nette;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class CreateMappingCommand extends Command
{

	/**
	 * @var \Kdyby\DoctrineSearch\SchemaManager
	 * @inject
	 */
	public $schema;



	protected function configure()
	{
		$this->setName('elastica:mapping:create')
			->setDescription("Creates indexes and type mappings in ElasticSearch")
			->addOption('drop-before', 'd', InputOption::VALUE_NONE, "Should the indexes be dropped first, before they're created? WARNING: this drops data!");

		// todo: filtering to only one type at a time
	}



	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->schema->onIndexCreated[] = function ($sm, $index) use ($output) {
			$output->writeln(sprintf('Created index <info>%s</info>', $index));
		};
		$this->schema->onTypeCreated[] = function ($sm, ClassMetadata $type) use ($output) {
			$output->writeln(sprintf('Created type <info>%s</info>', $type->getName()));
		};
		$this->schema->onIndexDropped[] = function ($sm, $index) use ($output) {
			$output->writeln(sprintf('<error>Dropped</error> index <info>%s</info>', $index));
		};
		$this->schema->onTypeDropped[] = function ($sm, ClassMetadata $type) use ($output) {
			$output->writeln(sprintf('<error>Dropped</error> type <info>%s</info>', $type->getName()));
		};

		if ($input->getOption('drop-before')) {
			$this->schema->dropMappings();
		}

		$this->schema->createMappings();
	}

}
