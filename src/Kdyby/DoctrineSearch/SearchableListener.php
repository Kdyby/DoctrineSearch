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
use Kdyby;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class SearchableListener extends Doctrine\Search\Tools\OrmSearchableListener
{

	public function getSubscribedEvents()
	{
		return array(
			Kdyby\Doctrine\Events::prePersist => 'prePersist',
			Kdyby\Doctrine\Events::preUpdate => 'prePersist',
			Kdyby\Doctrine\Events::preRemove => 'preRemove',
			Kdyby\Doctrine\Events::postFlush => 'postFlush',
		);
	}

}
