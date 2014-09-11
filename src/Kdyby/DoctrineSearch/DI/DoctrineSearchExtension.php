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
		'serializer' => 'callback',
		'debugger' => '%debugMode%',
	);



	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$configuration = $builder->addDefinition($this->prefix('config'))
			->setClass('Doctrine\Search\Configuration')
			->addSetup('setMetadataCacheImpl', array(CacheHelpers::processCache($this, $config['metadataCache'], 'metadata', $config['debugger'])))
			->addSetup('setEntityManager', array('@Doctrine\\ORM\\EntityManager'));

		switch ($config['serializer']) {
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
					sprintf('Serializer "%s" is not supported', $config['serializer'])
				);
		}

		$configuration->addSetup('setEntitySerializer', array($serializer));

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
	}



	public static function register(Nette\Configurator $configurator)
	{
		$configurator->onCompile[] = function ($config, Nette\DI\Compiler $compiler) {
			$compiler->addExtension('doctrineSearch', new DoctrineSearchExtension());
		};
	}

}

