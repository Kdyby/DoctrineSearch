<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\DoctrineSearch;

use Doctrine\Search\Mapping\ClassMetadata;
use Doctrine\Search\SearchManager;
use Elastica\Exception\ResponseException;
use Elastica\Request;
use Kdyby;
use Nette;
use Tracy\Debugger;



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
class SchemaManager extends Nette\Object
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

	/**
	 * @var SearchManager
	 */
	private $searchManager;

	/**
	 * @var \Doctrine\Search\ElasticSearch\Client
	 */
	private $client;

	/**
	 * @var \Elastica\Client
	 */
	private $elastica;

	/**
	 * @var array
	 */
	private $indexAnalysis = array();



	public function __construct(SearchManager $searchManager)
	{
		$this->searchManager = $searchManager;
		$this->client = $this->searchManager->getClient();
		$this->elastica = $this->client->getClient();
	}



	public function setIndexAnalysis($indexName, array $analysis)
	{
		$this->indexAnalysis[$indexName] = $analysis + array('analyzer' => array(), 'filter' => array());
	}



	public function dropMappings()
	{
		$metadataFactory = $this->searchManager->getMetadataFactory();
		foreach ($metadataFactory->getAllMetadata() as $class) {
			if (!$this->client->getIndex($class->index)->exists()) {
				continue;
			}

			$index = $this->elastica->getIndex($class->index);
			if (!$index->getType($class->type)->exists()) {
				continue;
			}

			$this->client->deleteType($class);
			$this->onTypeDropped($this, $class);
		}

		foreach ($metadataFactory->getAllMetadata() as $class) {
			if (!$this->client->getIndex($class->index)->exists()) {
				continue;
			}

			$this->client->deleteIndex($class->index);
			$this->onIndexDropped($this, $class->index);
		}
	}



	public function createMappings()
	{
		$aliases = [];

		$metadataFactory = $this->searchManager->getMetadataFactory();
		foreach ($metadataFactory->getAllMetadata() as $class) {
			$indexAlias = $class->index . '_' . date('YmdHis');
			$aliases[$indexAlias] = $class->index;

			$fakeMetadata = clone $class;
			$fakeMetadata->index = $indexAlias;

			if (!$this->client->getIndex($fakeMetadata->index)->exists()) {
				$this->client->createIndex($fakeMetadata->index, array(
					'number_of_shards' => $fakeMetadata->numberOfShards,
					'number_of_replicas' => $fakeMetadata->numberOfReplicas,
					'analysis' => isset($this->indexAnalysis[$fakeMetadata->index]) ? $this->indexAnalysis[$fakeMetadata->index] : array(),
				));

				$this->onIndexCreated($this, $fakeMetadata->index);
			}

			$this->client->createType($fakeMetadata);
			$this->onTypeCreated($this, $fakeMetadata);
		}

		return $aliases;
	}



	public function createAliases(array $aliases)
	{
		foreach ($aliases as $alias => $original) {
			try {
				$this->elastica->request(sprintf('_all/_alias/%s', $original), Request::DELETE);

			} catch (ResponseException $e) {
				if (stripos($e->getMessage(), 'AliasesMissingException') === FALSE) {
					throw $e;
				}
			}

			try {
				$this->elastica->request(sprintf('/%s/_alias/%s', $alias, $original), Request::PUT);
				$this->onAliasCreated($this, $original, $alias);

			} catch (ResponseException $e) {
				Debugger::log($e);
				$this->onAliasError($this, $e, $original, $alias);
			}
		}
	}

}
