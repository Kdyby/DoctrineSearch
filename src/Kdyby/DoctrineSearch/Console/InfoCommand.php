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
use Tracy\Dumper;



if (!class_exists('Tracy\Dumper')) {
	class_alias('Nette\Diagnostics\Dumper', 'Tracy\Dumper');
}

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class InfoCommand extends Command
{

	/**
	 * @var \Doctrine\Search\SearchManager
	 * @inject
	 */
	public $searchManager;



	protected function configure()
	{
		$this->setName('elastica:info');
	}



	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$printMapping = $output->getVerbosity() > $output::VERBOSITY_NORMAL;
		$metadataFactory = $this->searchManager->getMetadataFactory();

		/** @var ClassMetadata $class */
		foreach ($metadataFactory->getAllMetadata() as $class) {
			$output->writeln(sprintf('Entity <info>%s</info> is searchable', $class->getName()));

			if (!$printMapping) {
				continue;
			}

			$meta = (array) $class;
			unset($meta['reflFields'], $meta['reflClass']);
			$output->writeln(Dumper::toTerminal($meta));
		}
	}

}
