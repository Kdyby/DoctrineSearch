<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\DoctrineSearch;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\Search\SearchManager;
use Kdyby;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class SearchableListener extends Nette\Object implements EventSubscriber
{

	/**
	 * @var SearchManager
	 */
	private $sm;



	public function __construct(SearchManager $sm)
	{
		$this->sm = $sm;
	}



	/**
	 * Returns an array of events this subscriber wants to listen to.
	 *
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return array(
			Events::prePersist,
			Events::preUpdate,
			Events::preRemove,
			Events::postFlush,
		);
	}



	public function prePersist(LifecycleEventArgs $oArgs)
	{
		$oEntity = $oArgs->getEntity();
		if ($oEntity instanceof Searchable) {
			$this->sm->persist($oEntity);
		}
	}



	public function preUpdate(LifecycleEventArgs $oArgs)
	{
		$oEntity = $oArgs->getEntity();
		if ($oEntity instanceof Searchable) {
			$this->sm->persist($oEntity);
		}
	}



	public function preRemove(LifecycleEventArgs $oArgs)
	{
		$oEntity = $oArgs->getEntity();
		if ($oEntity instanceof Searchable) {
			$this->sm->remove($oEntity);
		}
	}



	public function postFlush()
	{
		$this->sm->flush();
	}

}
