<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\DoctrineSearch\Serializer;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\Search\SerializerInterface;
use Kdyby;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ChainSerializer extends Nette\Object implements SerializerInterface
{

    /**
     * @var SerializerInterface[]
     */
    private $serializers = [];

    /**
     * @var SerializerInterface
     */
    private $defaultSerializer;



    public function addSerializer($classType, SerializerInterface $serializer)
    {
        $this->serializers[strtolower($classType)] = $serializer;
    }



    public function setDefaultSerializer(SerializerInterface $serializer)
    {
        $this->defaultSerializer = $serializer;
    }



    /**
     * @param object $object
     * @return string
     */
    public function serialize($object)
    {
        $lName = strtolower(ClassUtils::getClass($object));
        if (isset($this->serializers[$lName])) {
            return $this->serializers[$lName]->serialize($object);
        }

        if (!$this->defaultSerializer) {
            throw new Kdyby\DoctrineSearch\DefaultSerializerNotProvidedException();
        }

        return $this->defaultSerializer->serialize($object);
    }



    /**
     * @param string $entityName
     * @param string $data
     * @return object
     */
    public function deserialize($entityName, $data)
    {
        $lName = strtolower($entityName);
        if (isset($this->serializers[$lName])) {
            return $this->serializers[$lName]->deserialize($entityName, $data);
        }

        if (!$this->defaultSerializer) {
            throw new Kdyby\DoctrineSearch\DefaultSerializerNotProvidedException();
        }

        return $this->defaultSerializer->deserialize($entityName, $data);
    }

}
