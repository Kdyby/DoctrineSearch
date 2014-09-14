<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\DoctrineSearch;

use Doctrine\Search\SearchManager;
use Kdyby;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>
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
		$metadataFactory = $this->searchManager->getMetadataFactory();
		foreach ($metadataFactory->getAllMetadata() as $class) {
			if (!$this->client->getIndex($class->index)->exists()) {
				$this->client->createIndex($class->index, array(
					'number_of_shards' => $class->numberOfShards,
					'number_of_replicas' => $class->numberOfReplicas,
					'analysis' => isset($this->indexAnalysis[$class->index]) ? $this->indexAnalysis[$class->index] : array(),
				));

				$this->onIndexCreated($this, $class->index);
			}

			$this->client->createType($class);
			$this->onTypeCreated($this, $class);
		}
	}

}
