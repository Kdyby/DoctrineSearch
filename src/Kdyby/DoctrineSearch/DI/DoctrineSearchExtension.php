<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\DoctrineSearch\DI;

use Elastica;
use Kdyby;
use Kdyby\DoctrineCache\DI\Helpers as CacheHelpers;
use Nette;
use Nette\DI\Config;
use Nette\PhpGenerator as Code;



if (!class_exists('Nette\DI\CompilerExtension')) {
	class_alias('Nette\Config\CompilerExtension', 'Nette\DI\CompilerExtension');
	class_alias('Nette\Config\Compiler', 'Nette\DI\Compiler');
	class_alias('Nette\Config\Helpers', 'Nette\DI\Config\Helpers');
}

if (isset(Nette\Loaders\NetteLoader::getInstance()->renamed['Nette\Configurator']) || !class_exists('Nette\Configurator')) {
	unset(Nette\Loaders\NetteLoader::getInstance()->renamed['Nette\Configurator']); // fuck you
	class_alias('Nette\Config\Configurator', 'Nette\Configurator');
}

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class DoctrineSearchExtension extends Nette\DI\CompilerExtension
{

	/**
	 * @var array
	 */
	public $defaults = array(
		'metadataCache' => 'default',
		'defaultSerializer' => 'callback',
		'serializers' => array(),
		'indexes' => array(),
		'debugger' => '%debugMode%',
	);

	/**
	 * @var array
	 */
	public $indexDefaults = array(
		'char_filter' => array(),
		'analyzers' => array(),
		'filters' => array(),
	);



	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$configuration = $builder->addDefinition($this->prefix('config'))
			->setClass('Doctrine\Search\Configuration')
			->addSetup('setMetadataCacheImpl', array(CacheHelpers::processCache($this, $config['metadataCache'], 'metadata', $config['debugger'])))
			->addSetup('setEntityManager', array('@Doctrine\\ORM\\EntityManager'));

		$this->loadSerializer($config);

		$configuration->addSetup('setEntitySerializer', array($this->prefix('@serializer')));

		$builder->addDefinition($this->prefix('client'))
			->setClass('Doctrine\Search\ElasticSearch\Client', array('@Elastica\Client'));

		$builder->addDefinition($this->prefix('manager'))
			->setClass('Doctrine\Search\SearchManager', array(
				$this->prefix('@config'),
				$this->prefix('@client'),
				new Nette\DI\Statement('Doctrine\Common\EventManager') // todo: only temporary, must solve collision first
			));

		$builder->addDefinition($this->prefix('searchableListener'))
			->setClass('Kdyby\DoctrineSearch\SearchableListener')
			->addTag('kdyby.subscriber');

		$this->loadSchema($config);

		$builder->addDefinition($this->prefix('entityPiper'))
			->setClass('Kdyby\DoctrineSearch\EntityPiper');

		$this->loadConsole();
	}



	protected function loadSerializer($config)
	{
		$builder = $this->getContainerBuilder();

		switch ($config['defaultSerializer']) {
			case 'callback':
				$serializer = new Nette\DI\Statement('Doctrine\Search\Serializer\CallbackSerializer');
				break;

			case 'jms':
				$builder->addDefinition($this->prefix('jms.serializationBuilder'))
					->setClass('JMS\Serializer\SerializerBuilder')
					->addSetup('setPropertyNamingStrategy', array(
						new Nette\DI\Statement('JMS\Serializer\Naming\SerializedNameAnnotationStrategy', array(
							new Nette\DI\Statement('JMS\Serializer\Naming\IdenticalPropertyNamingStrategy')
						))
					))
					->addSetup('addDefaultHandlers')
					->addSetup('setAnnotationReader')
					->setAutowired(FALSE);

				$builder->addDefinition($this->prefix('jms.serializer'))
					->setClass('JMS\Serializer\Serializer')
					->setFactory($this->prefix('@jms.serializationBuilder::build'))
					// todo: getMetadataFactory()->setCache()
					->setAutowired(FALSE);

				$builder->addDefinition($this->prefix('jms.serializerContext'))
					->setClass('JMS\Serializer\SerializationContext')
					->addSetup('setGroups', array('search'))
					->setAutowired(FALSE);

				$serializer = new Nette\DI\Statement('Kdyby\DoctrineSearch\Serializer\JMSSerializer', array(
					$this->prefix('@jms.serializer'),
					$this->prefix('@jms.serializerContext')
				));
				break;

			default:
				throw new Kdyby\DoctrineSearch\NotImplementedException(
					sprintf('Serializer "%s" is not supported', $config['defaultSerializer'])
				);
		}

		$serializer = $builder->addDefinition($this->prefix('serializer'))
			->setClass('Kdyby\DoctrineSearch\Serializer\ChainSerializer')
			->addSetup('setDefaultSerializer', array($serializer));

		foreach ($config['serializers'] as $type => $impl) {
			$args = Nette\DI\Compiler::filterArguments(array(is_string($impl) ? new Nette\DI\Statement($impl) : $impl));
			$builder->addDefinition($this->prefix($name = 'serializer.' . str_replace('\\', '_', $type)))
				->setFactory($args[0]->entity, $args[0]->arguments)
				->setClass((is_string($args[0]->entity) && class_exists($args[0]->entity)) ? $args[0]->entity : 'Doctrine\Search\SerializerInterface')
				->setAutowired(FALSE);

			$serializer->addSetup('addSerializer', array($type, $this->prefix('@' . $name)));
		}
	}



	protected function loadSchema($config)
	{
		$builder = $this->getContainerBuilder();

		$schema = $builder->addDefinition($this->prefix('schema'))
			->setClass('Kdyby\DoctrineSearch\SchemaManager');

		foreach ($config['indexes'] as $indexName => $indexConfig) {
			$indexConfig = Config\Helpers::merge($indexConfig, $this->indexDefaults);

			unset($analysisSection);
			foreach ($indexConfig as $analysisType => &$analysisSection) {

				unset($setup);
				foreach ($analysisSection as $name => $setup) {
					if (!Config\Helpers::isInheriting($setup)) {
						continue;
					}

					$parent = Config\Helpers::takeParent($setup);

					if (!isset($analysisSection[$parent])) {
						throw new Nette\Utils\AssertionException(sprintf(
							'The %s.%s cannot inherit undefined %s.%s in %s configuration',
							$analysisType, $name, $analysisType, $parent, $this->name
						));
					}

					$analysisSection[$name] = Config\Helpers::merge($setup, $analysisSection[$parent]);
				}
			}

			if (!isset($indexConfig['analyzer'])) {
				$indexConfig['analyzer'] = $indexConfig['analyzers'];
				unset($indexConfig['analyzers']);
			}

			if (!isset($indexConfig['filter'])) {
				$indexConfig['filter'] = $indexConfig['filters'];
				unset($indexConfig['filters']);
			}

			$schema->addSetup('setIndexAnalysis', array($indexName, $indexConfig));
		}
	}



	protected function loadConsole()
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('console.createMapping'))
			->setClass('Kdyby\DoctrineSearch\Console\CreateMappingCommand')
			->addTag('kdyby.console.command');

		$builder->addDefinition($this->prefix('console.pipeEntities'))
			->setClass('Kdyby\DoctrineSearch\Console\PipeEntitiesCommand')
			->addTag('kdyby.console.command');

		$builder->addDefinition($this->prefix('console.info'))
			->setClass('Kdyby\DoctrineSearch\Console\InfoCommand')
			->addTag('kdyby.console.command');
	}



	public static function register(Nette\Configurator $configurator)
	{
		$configurator->onCompile[] = function ($config, Nette\DI\Compiler $compiler) {
			$compiler->addExtension('doctrineSearch', new DoctrineSearchExtension());
		};
	}

}

