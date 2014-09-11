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
		'debugger' => '%debugMode%',
	);



	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$builder->addDefinition($this->prefix('config'))
			->setClass('Doctrine\Search\Configuration')
			->addSetup('setMetadataCacheImpl', array(CacheHelpers::processCache($this, $config['metadataCache'], 'metadata', $config['debugger'])))
			->addSetup('setEntitySerializer', array(new Nette\DI\Statement('Doctrine\Search\Serializer\CallbackSerializer')))
			->addSetup('setEntityManager', array('@Doctrine\\ORM\\EntityManager'));

		$builder->addDefinition($this->prefix('client'))
			->setClass('Doctrine\Search\ElasticSearch\Client', array('@Elastica\Client'));

		$builder->addDefinition($this->prefix('manager'))
			->setClass('Doctrine\Search\SearchManager', array(
				$this->prefix('@config'),
				$this->prefix('@client'),
				// @eventManager
			));
	}



	public static function register(Nette\Configurator $configurator)
	{
		$configurator->onCompile[] = function ($config, Nette\DI\Compiler $compiler) {
			$compiler->addExtension('doctrineSearch', new DoctrineSearchExtension());
		};
	}

}

