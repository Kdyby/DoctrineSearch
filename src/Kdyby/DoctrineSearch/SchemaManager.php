<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\DoctrineSearch;

use Doctrine;
use Doctrine\Search\Mapping\ClassMetadata;
use Elastica\Exception\ResponseException;
use Kdyby;
use Nette;
use Nette\Utils\ObjectMixin;



/**
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @method onTypeDropped(SchemaManager $self, ClassMetadata $class)
 * @method onIndexDropped(SchemaManager $self, string $indexName)
 * @method onIndexCreated(SchemaManager $self, string $indexName)
 * @method onTypeCreated(SchemaManager $self, ClassMetadata $class)
 * @method onAliasCreated(SchemaManager $self, string $original, string $alias)
 * @method onAliasError(SchemaManager $self, ResponseException $e, string $original, string $alias)
 */
class SchemaManager extends Doctrine\Search\ElasticSearch\SchemaManager
{

	/**
	 * @var array
	 */
	public $onIndexDropped = array();

	/**
	 * @var array
	 */
	public $onTypeDropped = array();

	/**
	 * @var array
	 */
	public $onIndexCreated = array();

	/**
	 * @var array
	 */
	public $onAliasCreated = array();

	/**
	 * @var array
	 */
	public $onAliasError = array();

	/**
	 * @var array
	 */
	public $onTypeCreated = array();



	public function createIndex(ClassMetadata $class)
	{
		$result = parent::createIndex($class);
		$this->onIndexCreated($this, $class->getIndexName());
		return $result;
	}



	public function dropIndex($index)
	{
		$result = parent::dropIndex($index);
		$this->onIndexDropped($this, $index);
		return $result;
	}



	public function createType(ClassMetadata $class)
	{
		$result = parent::createType($class);
		$this->onTypeCreated($this, $class);
		return $result;
	}



	public function dropType(ClassMetadata $class)
	{
		$result = parent::dropType($class);
		$this->onTypeDropped($this, $class);
		return $result;
	}



	public function createAlias($alias, $original)
	{
		try {
			parent::createAlias($alias, $original);
			$this->onAliasCreated($this, $original, $alias);

		} catch (\Exception $e) {
			$this->onAliasError($this, $e, $original, $alias);
			throw $e;
		}
	}



	/*************************** Nette\Object ***************************/



	/**
	 * Call to undefined method.
	 *
	 * @param string $name
	 * @param array $args
	 *
	 * @throws \Nette\MemberAccessException
	 * @return mixed
	 */
	public function __call($name, $args)
	{
		return ObjectMixin::call($this, $name, $args);
	}

}
