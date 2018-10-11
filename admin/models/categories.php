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
use Joomla\CMS\Table\User;

require_once('base/baselist.php');
require_once('base/traitnestable.php');

/**
 * FLEXIcontent Component Categories Model
 *
 */
class FlexicontentModelCategories extends FCModelAdminList
{

	use FCModelTraitNestableRecord;

	/**
	 * Record name
	 *
	 * @var string
	 */
	var $record_name = 'category';

	/**
	 * Record database table
	 *
	 * @var string
	 */
	var $records_dbtbl = 'categories';

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = 'flexicontent_categories';

	/**
	 * Column names
	 */
	var $state_col      = 'published';
	var $name_col       = 'title';
	var $parent_col     = 'parent_id';

	/**
	 * (Default) Behaviour Flags
	 */
	var $listViaAccess = true;
	var $copyRelations = false;

	/**
	 * Search and ordering columns
	 */
	var $search_cols       = array('title', 'alias', 'note');
	var $default_order     = 'a.lft';
	var $default_order_dir = 'ASC';

	/**
	 * List filters that are always applied
	 */
	var $hard_filters = array('extension' => FLEXI_CAT_EXTENSION);

	/**
	 * Record rows
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Rows total
	 *
	 * @var integer
	 */
	var $_total = null;

	/**
	 * Pagination object
	 *
	 * @var object
	 */
	var $_pagination = null;

	/**
	 * Single record id (used in operations)
	 *
	 * @var int
	 */
	var $_id = null;


	/**
	 * Constructor
	 *
	 * @since 3.3.0
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');
		$view   = $jinput->get('view', '', 'cmd');
		$fcform = $jinput->get('fcform', 0, 'int');
		$p      = $option . '.' . $view . '.';


		/**
		 * View's Filters
		 * Inherited filters : filter_state, filter_access, filter_id, search
		 */

		// Various filters
		$filter_cats     = $fcform ? $jinput->get('filter_cats', 0, 'int') : $app->getUserStateFromRequest($p . 'filter_cats', 'filter_cats', 0, 'int');
		$filter_level    = $fcform ? $jinput->get('filter_level', '', 'int') : $app->getUserStateFromRequest($p . 'filter_level', 'filter_level', '', 'int');
		$filter_language = $fcform ? $jinput->get('filter_language', '', 'string') : $app->getUserStateFromRequest($p . 'filter_language', 'filter_language', '', 'string');

		$this->setState('filter_cats', $filter_cats);
		$this->setState('filter_level', $filter_level);
		$this->setState('filter_language', $filter_language);

		$app->setUserState($p . 'filter_cats', $filter_cats);
		$app->setUserState($p . 'filter_level', $filter_level);
		$app->setUserState($p . 'filter_language', $filter_language);


		// Manage view permission
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanCats;
	}


	/**
	 * Method to build the query for the records
	 *
	 * @return JDatabaseQuery   The DB Query object
	 *
	 * @since 3.3.0
	 */
	protected function getListQuery()
	{
		// Create a query with all its clauses: WHERE, HAVING and ORDER BY, etc
		$query = parent::getListQuery()
			->select('a.params AS config')
			->select('l.title AS language_title')
			->leftJoin('#__languages AS l ON l.lang_code = a.language')
			->where('a.extension = ' . $this->_db->Quote(FLEXI_CAT_EXTENSION))
		;

		/**
		 * Because of multi-multi category-item relation it is faster to calculate ITEM COUNT with a seperate query
		 * if it was single mapping e.g. like it is 'item' TO 'content type' or 'item' TO 'creator' we could use a subquery
		 * the more categories are listed (query LIMIT) the bigger the performance difference ...
		 */
		//$query->select('(COUNT(*) FROM #__flexicontent_cats_item_relations AS rel WHERE rel.catid = a.id) AS nrassigned');

		return $query;
	}


	/**
	 * Method to build the where clause of the query for the records
	 *
	 * @param		JDatabaseQuery|bool   $q   DB Query object or bool to indicate returning an array or rendering the clause
	 *
	 * @return  JDatabaseQuery|array
	 *
	 * @since 1.0
	 */
	protected function _buildContentWhere($q = false)
	{
		// Inherited filters : filter_state, filter_access, filter_id, search
		$where = parent::_buildContentWhere(false);

		// Various filters
		$filter_cats     = $this->getState('filter_cats');
		$filter_level    = $this->getState('filter_level');
		$filter_language = $this->getState('filter_language');

		// Limit category list to those contain in the subtree of the choosen category
		if ($filter_cats)
		{
			$where[] = 'a.id IN (SELECT cat.id FROM #__categories AS cat JOIN #__categories AS parent ON cat.lft BETWEEN parent.lft AND parent.rgt WHERE parent.id=' . (int) $filter_cats . ')';
		}

		// Limit category list to those containing CONTENT (joomla articles)
		else
		{
			$where[] = '(a.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND a.rgt <= ' . $this->_db->Quote(FLEXI_RGT_CATEGORY) . ')';
		}

		// Filter on the level.
		if ($filter_level)
		{
			$where[] = 'a.level <= ' . (int) $filter_level;
		}

		// Filter by language
		if ($filter_language)
		{
			$where[] = 'a.language = ' . $this->_db->Quote($filter_language);
		}

		if ($q instanceof \JDatabaseQuery)
		{
			return $where ? $q->where($where) : $q;
		}

		return $q
			? ' WHERE ' . (count($where) ? implode(' AND ', $where) : ' 1 ')
			: $where;
	}


	/**
	 * Method to find which records are not authorized
	 *
	 * @param   array     $cid     array of record ids to check
	 * @param   string    $rule    string of the ACL rule to check
	 *
	 * @return	array     The records having assignments
	 *
	 * @since	3.3.0
	 */
	public function filterByPermission($cid, $rule)
	{
		$cid = ArrayHelper::toInteger($cid);

		// If cannot manage then all records are not changeable
		if (!$this->canManage)
		{
			return $cid;
		}

		// Get record owners, needed for *.own ACL
		$query = $this->_db->getQuery(true)
			->select('c.id, c.created_user_id')
			->from('#__' . $this->records_dbtbl . ' AS c')
			->where('c.id IN (' . implode(',', $cid) . ')')
		;
		$rows = $this->_db->setQuery($query)->loadObjectList();

		$mapped_rule = $rule;
		$user        = JFactory::getUser();
		$cid_noauth  = array();

		foreach ($rows as $row)
		{
			$id = $row->id;

			$canDo    = $user->authorise($mapped_rule, 'com_content.category.' . $id);
			$canDoOwn = $user->authorise($mapped_rule . '.own', 'com_content.category.' . $id) && $row->created_user_id == $user->get('id');

			if (!$canDo && !$canDoOwn)
			{
				$cid_noauth[] = $id;
			}
			else
			{
				$this->changeable_rows[$rule][$id] = 1;
			}
		}

		return $cid_noauth;
	}


	/**
	 * Method to find which records having assignments blocking a state change
	 *
	 * @param		array     $cid      array of record ids to check
	 * @param		string    $tostate  action related to assignments
	 *
	 * @return	array     The records having assignments
	 */
	public function filterByAssignments($cid = array(), $tostate = -2)
	{
		$cid = ArrayHelper::toInteger($cid);
		$cid_wassocs = array();

		switch ($tostate)
		{
			// Trash
			case -2:
				$query = 'SELECT DISTINCT rel.catid'
					. ' FROM #__flexicontent_cats_item_relations'
					. ' WHERE type_id IN (' . implode(',', $cid) . ')'
				;

				$cid_wassocs = $this->_db->setQuery($query)->loadColumn();
				break;

			// Unpublish
			case 0:
				break;
		}

		return $cid_wassocs;
	}


	/**
	 * START OF MODEL SPECIFIC METHODS
	 */


	/**
	 * Method to count assigned items for the given categories
	 *
	 * @access public
	 * @return	string
	 * @since	1.6
	 */
	public function getAssignedItems($cids)
	{
		if (empty($cids))
		{
			return array();
		}

		$query = ' SELECT rel.catid, COUNT(rel.itemid) AS nrassigned'
			. ' FROM #__flexicontent_cats_item_relations AS rel'
			. ' WHERE rel.catid IN (' . implode(',', $cids) . ')'
			. ' GROUP BY rel.catid'
		;

		return $this->_db->setQuery($query)->loadObjectList('catid');
	}


	/**
	 * Method to get parameters of parent categories
	 *
	 * @param   integer  $pk  The category id
	 * @return	string   An array of JSON strings
	 *
	 * @since	3.3.0
	 */
	public function getParentParams($pk)
	{
		if (empty($pk))
		{
			return array();
		}

		global $globalcats;

		$query = 'SELECT id, params'
			. ' FROM #__categories'
			. ' WHERE id IN (' . $globalcats[$pk]->ancestors . ')'
			. ' ORDER BY level ASC'
		;
		return $this->_db->setQuery($query)->loadObjectList('id');
	}


	/**
	 * Method to count assigned items for the given categories
	 *
	 * @access public
	 * @return	string
	 * @since	1.6
	 */
	function countItemsByState($cids)
	{
		if (empty($cids))
		{
			return array();
		}

		$query = ' SELECT rel.catid, i.state, COUNT(rel.itemid) AS nrassigned'
			. ' FROM #__flexicontent_cats_item_relations AS rel'
			. ' JOIN #__content AS i ON i.id=rel.itemid'
			. ' WHERE rel.catid IN (' . implode(',', $cids) . ')'
			. ' GROUP BY rel.catid, i.state'
		;
		$data = $this->_db->setQuery($query)->loadObjectList();

		$assigned = array();
		foreach ($data as $catid => $d)
		{
			$assigned[$d->catid][$d->state] = $d->nrassigned;
		}

		return $assigned;
	}
}
