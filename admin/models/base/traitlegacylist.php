<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            http://www.flexicontent.com
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Table\Table;

/**
 * FLEXIcontent List of Records Trait (legacy methods)
 *
 */
trait FCModelTraitLegacyList
{
	/**
	 * (Legacy) Method to get records data
	 *
	 * @return array
	 *
	 * @since	3.3.0
	 */
	public function getData()
	{
		return $this->getItems();
	}

	/**
	 * (Legacy) Method to publish / unpublish / etc a record
	 *
	 * @param		array			$cid          Array of record ids to set to a new state
	 * @param		integer   $state        The new state
	 *
	 * @return	boolean	True on success
	 *
	 * @since	3.3.0
	 */
	public function publish($cid, $state = 1)
	{
		return $this->changestate($cid, $state);
	}


	/**
	 * Method to check if given records can not be deleted due to assignments or due to permissions
	 *
	 * @param		array			$cid          array of record ids to check
	 * @param		array			$cid_noauth   (variable by reference) to return an array of non-authorized record ids
	 * @param		array			$cid_wassocs  (variable by reference) to return an array of 'locked' record ids
	 *
	 * @return	boolean	  True when at least 1 deleteable record found
	 *
	 * @since	3.3
	 */
	public function candelete(& $cid, & $cid_noauth = null, & $cid_wassocs = null)
	{
		return $this->changestate($cid, $cid_noauth, $cid_wassocs, $tostate = -2);
	}

	/**
	 * Method to check if given records can not be unpublished due to assignments or due to permissions
	 *
	 * @param		array			$cid          array of record ids to check
	 * @param		array			$cid_noauth   (variable by reference) to return an array of non-authorized record ids
	 * @param		array			$cid_wassocs  (variable by reference) to return an array of 'locked' record ids
	 *
	 * @return	boolean	  True when at least 1 publishable record found
	 *
	 * @since	3.3.0
	 */
	public function canunpublish(& $cid, & $cid_noauth = null, & $cid_wassocs = null)
	{
		return $this->changestate($cid, $cid_noauth, $cid_wassocs, $tostate = 0);
	}
}
