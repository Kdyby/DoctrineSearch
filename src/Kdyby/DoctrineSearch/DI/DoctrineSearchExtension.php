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
		'metadata' => array(),
		'indexPrefix' => NULL,
		'debugger' => '%debugMode%',
	);



	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$configuration = $builder->addDefinition($this->prefix('config'))
			->setClass('Doctrine\Search\Configuration')
			->addSetup('setMetadataCacheImpl', array(CacheHelpers::processCache($this, $config['metadataCache'], 'metadata', $config['debugger'])))
			->addSetup('setObjectManager', array('@Doctrine\\ORM\\EntityManager'))
			->addSetup('setIndexPrefix', array($config['indexPrefix']));

		$this->loadSerializer($config);
		$configuration->addSetup('setEntitySerializer', array($this->prefix('@serializer')));

		$builder->addDefinition($this->prefix('driver'))
			->setClass('Doctrine\Search\Mapping\Driver\DependentMappingDriver', array($this->prefix('@driverChain')))
			->setAutowired(FALSE);
		$configuration->addSetup('setMetadataDriverImpl', array($this->prefix('@driver')));

		$metadataDriverChain = $builder->addDefinition($this->prefix('driverChain'))
			->setClass('Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain')
			->setAutowired(FALSE);

		foreach ($config['metadata'] as $namespace => $directory) {
			$metadataDriverChain->addSetup('addDriver', array(
				new Nette\DI\Statement('Doctrine\Search\Mapping\Driver\NeonDriver', array($directory)),
				$namespace
			));
		}

		$builder->addDefinition($this->prefix('client'))
			->setClass('Doctrine\Search\ElasticSearch\Client', array('@Elastica\Client'));

		$builder->addDefinition($this->prefix('evm'))
			->setClass('Kdyby\Events\NamespacedEventManager', array(Kdyby\DoctrineSearch\Events::NS . '::'))
			->setAutowired(FALSE);

		$builder->addDefinition($this->prefix('manager'))
			->setClass('Doctrine\Search\SearchManager', array(
				$this->prefix('@config'),
				$this->prefix('@client'),
				$this->prefix('@evm'),
			));

		$builder->addDefinition($this->prefix('searchableListener'))
			->setClass('Kdyby\DoctrineSearch\SearchableListener')
			->addTag('kdyby.subscriber');

		$builder->addDefinition($this->prefix('schema'))
			->setClass('Kdyby\DoctrineSearch\SchemaManager', array($this->prefix('@client')));

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

				$serializer = new Nette\DI\Statement('Doctrine\Search\Serializer\JMSSerializer', array(
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
			->setClass('Doctrine\Search\Serializer\ChainSerializer')
			->addSetup('setDefaultSerializer', array($serializer));

		foreach ($config['serializers'] as $type => $impl) {
			$impl = self::filterArgs($impl);

			if (is_string($impl->entity) && substr($impl->entity, 0, 1) === '@') {
				$serializer->addSetup('addSerializer', array($type, $impl->entity));

			} else {
				$builder->addDefinition($this->prefix($name = 'serializer.' . str_replace('\\', '_', $type)))
					->setFactory($impl->entity, $impl->arguments)
					->setClass((is_string($impl->entity) && class_exists($impl->entity)) ? $impl->entity : 'Doctrine\Search\SerializerInterface')
					->setAutowired(FALSE);

				$serializer->addSetup('addSerializer', array($type, $this->prefix('@' . $name)));
			}
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



	/**
	 * @param string|Nette\DI\Statement $statement
	 * @return Nette\DI\Statement
	 */
	private static function filterArgs($statement)
	{
		$args = Nette\DI\Compiler::filterArguments(array(is_string($statement) ? new Nette\DI\Statement($statement) : $statement));
		return $args[0];
	}



	public static function register(Nette\Configurator $configurator)
	{
		$configurator->onCompile[] = function ($config, Nette\DI\Compiler $compiler) {
			$compiler->addExtension('doctrineSearch', new DoctrineSearchExtension());
		};
	}

}

