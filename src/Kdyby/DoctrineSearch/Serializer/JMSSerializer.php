<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\DoctrineSearch\Serializer;

use Doctrine\Search\SerializerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\Serializer;
use Kdyby;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class JMSSerializer implements SerializerInterface
{

	/**
	 * @var Serializer
	 */
	protected $serializer;

	/**
	 * @var SerializationContext
	 */
	protected $context;



	public function __construct(Serializer $serializer, SerializationContext $context = NULL)
	{
		$this->context = $context;
		$this->serializer = $serializer;
	}



	public function serialize($object)
	{
		$context = $this->context ? clone $this->context : NULL;

		return json_decode($this->serializer->serialize($object, 'json', $context), TRUE);
	}



	public function deserialize($entityName, $data)
	{
		return $this->serializer->deserialize($data, $entityName, 'json');
	}

}
