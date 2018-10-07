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

jimport('legacy.model.list');
require_once('traitbase.php');
require_once('traitlegacylist.php');

/**
 * FLEXIcontent Component BASE (list) Model
 *
 */
abstract class FCModelAdminList extends JModelList
{

	use FCModelTraitBase;
	use FCModelTraitLegacyList;

	var $records_dbtbl  = 'flexicontent_records';
	var $records_jtable = 'flexicontent_records';

	/**
	 * Column names and record name
	 */
	var $record_name    = 'record';
	var $state_col      = 'published';
	var $name_col       = 'title';
	var $parent_col     = null;
	var $created_by_col = 'created_by';

	/**
	 * (Default) Behaviour Flags
	 */
	var $listViaAccess = false;
	var $copyRelations = true;

	/**
	 * Search and ordering columns
	 */
	var $search_cols       = array('title', 'alias', 'name', 'label');
	var $default_order     = 'a.title';
	var $default_order_dir = 'ASC';

	/**
	 * List filters that are always applied
	 */
	var $hard_filters = array();

	/**
	 * Rows that can have their state modified
	 */
	var $changeable_rows = array('core.delete' => array(), 'core.edit.state' => array());

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

		// Parameters of the view, in our case it is only the component parameters
		$this->cparams = JComponentHelper::getParams('com_flexicontent');

		// Make sure this is correct if called from different component ...
		$this->option = 'com_flexicontent';


		/**
		 * View's Filters
		 */

		// Various filters
		$filter_state  = $fcform ? $jinput->get('filter_state', '', 'cmd') : $app->getUserStateFromRequest($p . 'filter_state', 'filter_state', '', 'cmd');
		$filter_access = $fcform ? $jinput->get('filter_access', '', 'int') : $app->getUserStateFromRequest($p . 'filter_access', 'filter_access', '', 'int');

		$this->setState('filter_state', $filter_state);
		$this->setState('filter_access', $filter_access);

		$app->setUserState($p . 'filter_state', $filter_state);
		$app->setUserState($p . 'filter_access', $filter_access);

		// Record ID filter
		$filter_id = $fcform ? $jinput->get('filter_id', '', 'int') : $app->getUserStateFromRequest($p . 'filter_id', 'filter_id', '', 'int');
		$filter_id = $filter_id ? $filter_id : '';  // needed to make text input field be empty

		$this->setState('filter_id', $filter_id);
		$app->setUserState($p . 'filter_id', $filter_id);

		// Text search
		$search = $fcform ? $jinput->get('search', '', 'string') : $app->getUserStateFromRequest($p . 'search', 'search', '', 'string');
		$this->setState('search', $search);
		$app->setUserState($p . 'search', $search);


		/**
		 * Ordering: filter_order, filter_order_Dir
		 */

		$this->_setStateOrder();


		/**
		 * Pagination: limit, limitstart
		 */

		$limit      = $fcform ? $jinput->get('limit', $app->getCfg('list_limit'), 'int') : $app->getUserStateFromRequest($p . 'limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $fcform ? $jinput->get('limitstart', 0, 'int') : $app->getUserStateFromRequest($p . 'limitstart', 'limitstart', 0, 'int');

		// In case limit has been changed, adjust limitstart accordingly
		$limitstart = ( $limit != 0 ? (floor($limitstart / $limit) * $limit) : 0 );
		$jinput->set('limitstart', $limitstart);

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		$app->setUserState($p . 'limit', $limit);
		$app->setUserState($p . 'limitstart', $limitstart);


		// For some model function that use single id
		$array = $jinput->get('cid', array(0), 'array');
		$this->setId((int) $array[0]);

		// Manage view permission
		$this->canManage = false;
	}


	/**
	 * Method to set the record identifier (for singular operations) and clear record rows
	 *
	 * @param		int	    $id        record identifier
	 *
	 * @since	3.3.0
	 */
	public function setId($id)
	{
		// Set record id and wipe data, if setting a different ID
		if ($this->_id != $id)
		{
			$this->_id    = $id;
			$this->_data  = null;
			$this->_total = null;
		}
	}


	/**
	 * Method to get a \JPagination object for the data set
	 *
	 * @return  \JPagination  A \JPagination object for the data set
	 *
	 * @since	1.5
	 */
	public function getPagination()
	{
		// Create pagination object if it doesn't already exist
		if (empty($this->_pagination))
		{
			require_once (JPATH_COMPONENT_SITE . DS . 'helpers' . DS . 'pagination.php');
			$this->_pagination = new FCPagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit'));
		}

		return $this->_pagination;
	}


	/**
	 * Method to get records data
	 *
	 * @return array
	 *
	 * @since	3.3.0
	 */
	public function getItems()
	{
		// Lets load the records if it doesn't already exist
		if ($this->_data === null)
		{
			$this->_data  = $this->_getList($this->_getListQuery(), $this->getState('limitstart'), $this->getState('limit'));
			$this->_total = $this->_db->setQuery('SELECT FOUND_ROWS()')->loadResult();
		}

		return $this->_data;
	}


	/**
	 * Method to get the total nr of the records
	 *
	 * @return integer
	 *
	 * @since	1.5
	 */
	public function getTotal()
	{
		// Lets load the records if it was not calculated already via using SQL_CALC_FOUND_ROWS + 'SELECT FOUND_ROWS()'
		if ($this->_total === null)
		{
			$this->_total = (int) $this->_getListCount($this->_getListQuery());
		}

		return $this->_total;
	}


	/**
	 * Method to cache the last query constructed.
	 *
	 * This method ensures that the query is constructed only once for a given state of the model.
	 *
	 * @return  \JDatabaseQuery  A \JDatabaseQuery object
	 *
	 * @since   1.6
	 */
	protected function _getListQuery()
	{
		// Create query if not already created, note: a new model instance should be created if needing different data
		if (empty($this->query))
		{
			$this->query = $this->getListQuery();
		}

		return $this->query;
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
		$table = $this->getTable($this->records_jtable, '');

		$has_checked_out_col = property_exists($table, 'checked_out');
		$has_access_col      = property_exists($table, 'access');
		$has_created_by_col  = property_exists($table, $this->created_by_col);

		// Create a query with all its clauses: WHERE, HAVING and ORDER BY, etc
		$query = $this->_db->getQuery(true)
			->select('SQL_CALC_FOUND_ROWS a.*')
			->select(($has_checked_out_col ? 'u.name' : $this->_db->Quote('')) . ' AS editor')
			->from('#__' . $this->records_dbtbl . ' AS a')
			->group('a.id');

		// Join over the users for the current editor name
		if ($has_checked_out_col)
		{
			$query->leftJoin('#__users AS u ON u.id = a.checked_out');
		}

		// Join over the access levels for access level title
		if ($has_access_col)
		{
			$query->select('CASE WHEN level.title IS NULL THEN CONCAT_WS(\'\', \'deleted:\', a.access) ELSE level.title END AS access_level')
				->leftJoin('#__viewlevels as level ON level.id = a.access');
		}

		// Join over the users for the author name
		if ($has_created_by_col)
		{
			$query->select('ua.name AS author_name')
				->leftJoin('#__users AS ua ON ua.id = a.' . $this->created_by_col);
		}

		// Get the WHERE, HAVING and ORDER BY clauses for the query
		$this->_buildContentWhere($query);
		$this->_buildContentHaving($query);
		$this->_buildContentOrderBy($query);

		// Add always-active ("hard") filters
		$this->_buildHardFiltersWhere($query);

		return $query;
	}


	/**
	 * Method to build the orderby clause of the query for the records
	 *
	 * @param		JDatabaseQuery|bool   $q   DB Query object or bool to indicate returning an array or rendering the clause
	 *
	 * @return  JDatabaseQuery|array
	 *
	 * @since 3.3.0
	 */
	protected function _buildContentOrderBy($q = false)
	{
		$filter_order     = $this->getState('filter_order');
		$filter_order_Dir = $this->getState('filter_order_Dir');

		$order = $this->_db->escape($filter_order . ' ' . $filter_order_Dir);

		if ($q instanceof \JDatabaseQuery)
		{
			return $order ? $q->order($order) : $q;
		}

		return $q
			? ' ORDER BY ' . $order
			: $order;
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
		$table = $this->getTable($this->records_jtable, '');

		// Various filters
		$filter_state  = $this->getState('filter_state');
		$filter_access = $this->getState('filter_access');
		$filter_id     = $this->getState('filter_id');

		// Text search
		$search = $this->getState('search');
		$search = StringHelper::trim(StringHelper::strtolower($search));

		$where = array();

		// Filter by state
		if (property_exists($table, $this->state_col))
		{
			switch ($filter_state)
			{
				case 'P':
					$where[] = 'a.' . $this->state_col . ' = 1';
					break;

				case 'U':
					$where[] = 'a.' . $this->state_col . ' = 0';
					break;

				case 'A':
					$where[] = 'a.' . $this->state_col . ' = 2';
					break;

				case 'T':
					$where[] = 'a.' . $this->state_col . ' = -2';
					break;

				default:
					// ALL: published & unpublished, but exclude archived, trashed
					if (!strlen($filter_state))
					{
						$where[] = 'a.' . $this->state_col . ' <> -2';
						$where[] = 'a.' . $this->state_col . ' <> 2';
					}
					elseif (is_numeric($filter_state))
					{
						$where[] = 'a.' . $this->state_col . ' = ' . (int) $filter_state;
					}
			}
		}

		// Filter by access level
		if (property_exists($table, 'access'))
		{
			if ($filter_access)
			{
				$where[] = 'a.access = ' . (int) $filter_access;
			}

			// Filter via View Level Access, if user is not super-admin
			if (!JFactory::getUser()->authorise('core.admin') && ($app->isSite() || $this->listViaAccess))
			{
				$groups  = implode(',', JAccess::getAuthorisedViewLevels($user->id));
				$where[] = 'a.access IN (' . $groups . ')';
			}
		}

		// Filter by record id
		if ($filter_id)
		{
			$where[] = 'a.id = ' . (int) $filter_id;
		}

		// Filter by search word (can be also be  id:NN  OR author:AAAAA)
		if (!empty($this->search_cols) && strlen($search))
		{
			if (stripos($search, 'id:') === 0)
			{
				$query->where('a.id = ' . (int) substr($search, 3));
			}
			elseif (stripos($search, 'author:') === 0)
			{
				$search_quoted = $this->_db->Quote('%' . $this->_db->escape(substr($search, 7), true) . '%');
				$query->where('(ua.name LIKE ' . $search_quoted . ' OR ua.username LIKE ' . $search_quoted . ')');
			}
			else
			{
				$escaped_search = str_replace(' ', '%', $this->_db->escape(trim($search), true));
				$search_quoted  = $this->_db->Quote('%' . $escaped_search . '%', false);

				$table     = $this->getTable($this->records_jtable, '');
				$textwhere = array();

				foreach ($this->search_cols as $search_col)
				{
					if (property_exists($table, $search_col))
					{
						$textwhere[] = 'LOWER(a.' . $search_col . ') LIKE ' . $search_quoted;
					}
				}

				$where[] = '(' . implode(' OR ', $textwhere) . ')';
			}
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
	 * Method to build the having clause of the query for the files
	 *
	 * @param		JDatabaseQuery|bool   $q   DB Query object or bool to indicate returning an array or rendering the clause
	 *
	 * @return  JDatabaseQuery|array
	 *
	 * @since 1.0
	 */
	protected function _buildContentHaving($q = false)
	{
		$having = array();

		if ($q instanceof \JDatabaseQuery)
		{
			return $having ? $q->having($having) : $q;
		}

		return $q
			? ' HAVING ' . (count($having) ? implode(' AND ', $having) : ' 1 ')
			: $having;
	}


	/**
	 * Method to change publication state a record
	 *
	 * @param		array			$cid          Array of record ids to set to a new state
	 * @param		integer   $state        The new state
	 *
	 * @return	boolean	True on success
	 *
	 * @since	3.3.0
	 */
	public function changestate($cid, $state = 1)
	{
		ArrayHelper::toInteger($cid);

		// Verify that records ACL has been checked
		$ids = array();

		foreach ($cid as $id)
		{
			if (isset($this->changeable_rows['core.edit.state'][$id]))
			{
				$ids[] = $id;
			}
		}

		if (count($ids))
		{
			$user = JFactory::getUser();

			ArrayHelper::toInteger($ids);
			$cid_list = implode(',', $ids);

			$query = $this->_db->getQuery(true)
				->update('#__' . $this->records_dbtbl)
				->set($this->_db->qn($this->state_col) . ' = ' . (int) $state)
				->where('id IN (' . $cid_list . ')')
				->where('(checked_out = 0 OR checked_out = ' . (int) $user->get('id') . ')');

			/**
			 * Only update records changing publication state,
			 * this is important when updating also other properties of the records ...
			 */
			$query->where($this->_db->qn($this->state_col) . ' <> ' . (int) $state);

			/**
			 * Get SET-clause to set new values to columns related to the changing state of the records
			 */
			$extra_set = $this->getExtraStateChangeProps($state);

			if ($extra_set)
			{
				$query->set($extra_set);
			}

			$this->_db->setQuery($query)->execute();
		}

		return true;
	}


	/**
	 * Method to get SET-clause to set new values to columns related to the changing state of the records
	 *
	 * @param		array			$cid          Array of record ids to set to a new state
	 * @param		integer   $state        The new state
	 *
	 * @return	boolean	True on success
	 *
	 * @since	3.3.0
	 */
	protected function getExtraStateChangeProps($state)
	{
		$set_properties = array();

		return $set_properties;
	}


	/**
	 * Method to move a record upwards or downwards
	 *
	 * @param  integer    $direction   A value of 1  or -1 to indicate moving up or down respectively
	 * @param  integer    $parent_id   The id of the parent if applicable
	 *
	 * @return	boolean	  True on success
	 *
	 * @since	3.3.0
	 */
	public function move($direction, $parent_id)
	{
		$table = $this->getTable($this->records_jtable, '');

		if (!$table->load($this->_id))
		{
			$this->setError($table->getError());
			return false;
		}

		$where = $this->parent_col
			? $this->_db->Quote($this->parent_col) . ' = ' . (int) ($parent_id ?: $table->catid)
			: '';

		if (!$table->move($direction, $where))
		{
			$this->setError($table->getError());
			return false;
		}

		return true;
	}


	/**
	 * Method to check if given records can not change state due to assignments or due to permissions
	 * This will also mark ACL changeable records into model, this is required by changestate to have an effect
	 *
	 * @param		array			$cid          array of record ids to check
	 * @param		array			$cid_noauth   (variable by reference) to return an array of non-authorized record ids
	 * @param		array			$cid_wassocs  (variable by reference) to return an array of 'locked' record ids
	 *
	 * @return	boolean	  True when at least 1 publishable record found
	 *
	 * @since	3.3.0
	 */
	public function canchangestate(& $cid, & $cid_noauth = null, & $cid_wassocs = null, $tostate = 0)
	{
		$cid_noauth  = array();
		$cid_wassocs = array();

		if (in_array('FCModelTraitNestableRecord', class_uses($this)))
		{
			// If publishing then add all parents to the list, so that they get published too
			if ($state == 1)
			{
				foreach ($cid as $_id)
				{
					$this->_addPathRecords($_id, $cid, 'parents');
				}
			}

			// If not publishing then all children to the list, so that they get the new state too
			else
			{
				foreach ($cid as $_id)
				{
					$this->_addPathRecords($_id, $cid, 'children');
				}
			}
		}

		// Find ACL disallowed
		$cid_noauth = $this->filterByPermission($cid, $tostate == -2 ? 'core.delete' : 'core.edit.state');

		// Find having blocking assignments (if applicable for this record type)
		$cid_wassocs = $this->filterByAssignments($cid, $tostate);

		return !count($cid_noauth) && !count($cid_wassocs);
	}


	/**
	 * Method to remove records
	 *
	 * @param		array			$cid          array of record ids to delete
	 *
	 * @return	boolean	True on success
	 *
	 * @since	1.0
	 */
	public function delete($cid)
	{
		ArrayHelper::toInteger($cid);

		// Verify that records ACL has been checked
		$ids = array();

		foreach ($cid as $id)
		{
			if (isset($this->changeable_rows['core.delete'][$id]))
			{
				$ids[] = $id;
			}
		}

		if (count($ids))
		{
			ArrayHelper::toInteger($ids);
			$cid_list = implode(',', $ids);

			// Delete records themselves
			$query = $this->_db->getQuery(true)
				->delete('#__' . $this->records_dbtbl)
				->where('id IN (' . $cid_list . ')');

			$this->_db->setQuery($query)->execute();

			// Also delete related Data, like 'assignments'
			$this->_deleteRelatedData($cid);
		}

		return true;
	}


	/**
	 * Method to delete related data of records
	 *
	 * @param		array			$cid          array of record ids to delete their related Data
	 *
	 * @return	void
	 *
	 * @since		3.3.0
	 */
	protected function _deleteRelatedData($cid)
	{
		
	}


	/**
	 * Method to copy records
	 *
	 * @param		array			$cid          array of record ids to copy
	 * @param		array			$copyRelations   flag to indicate copying 'related' data, like 'assignments'
	 *
	 * @return	array		Array of old-to new record ids of copied record IDs
	 *
	 * @since		1.0
	 */
	public function copy($cid, $copyRelations = null)
	{
		$copyRelations = copyValues === null ? $this->copyValues : $copyRelations;
		$ids_map       = array();
		$name          = $this->name_col;

		foreach ($cid as $id)
		{
			$table        = $this->getTable($this->records_jtable, '');
			$table->load($id);
			$table->id    = 0;
			$table->$name = $table->$name . ' [copy]';
			$table->alias = JFilterOutput::stringURLSafe($table->$name);
			$table->check();
			$table->store();

			// Add new record id to the old-to-new IDs map
			$ids_map[$id] = $table->id;
		}

		// Also copy related Data, like 'assignments'
		if ($copyRelations)
		{
			$this->_copyRelatedData($ids_map);
		}

		return $ids_map;
	}


	/**
	 * Method to copy assignments and other related data of records
	 *
	 * @param   array     $ids_map     array of old to new record ids
	 *
	 * @return	void
	 *
	 * @since		3.3.0
	 */
	protected function _copyRelatedData($ids_map)
	{
		
	}


	/**
	 * Method to set the access level of the records
	 *
	 * @param		integer		id of the record
	 * @param		integer		access level
	 *
	 * @return	boolean		True on success
	 *
	 * @since		1.5
	 */
	public function saveaccess($id, $access)
	{
		$table = $this->getTable($this->records_jtable, '');

		$cid      = is_array($id) ? $id : array($id);
		$accesses = is_array($access) ? $access : array($access);

		foreach ($cid as $id)
		{
			$table->load($id);
			$table->id     = $id;
			$table->access = $accesses[$id];

			if (!$table->check())
			{
				$this->setError($table->getError());

				return false;
			}

			if (!$table->store())
			{
				$this->setError($table->getError());

				return false;
			}
		}

		return true;
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
		ArrayHelper::toInteger($cid);

		// If cannot manage then all records are not changeable
		if (!$this->canManage)
		{
			return $cid;
		}

		$cid_noauth = array();

		// All records changeable
		$ids                     = array_flip($cid);
		$this->changeable_rows[$rule] = $ids;

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
		ArrayHelper::toInteger($cid);
		$cid_wassocs = array();

		switch ($tostate)
		{
			// Trash
			case -2:
				break;

			// Unpublish
			case 0:
				break;
		}

		return $cid_wassocs;
	}


	/**
	 * Method to set order into state
	 *
	 * @return	void
	 *
	 * @since 3.3.0
	 */
	protected function _setStateOrder()
	{
		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');
		$view   = $jinput->get('view', '', 'cmd');
		$fcform = $jinput->get('fcform', 0, 'int');
		$p      = $option . '.' . $view . '.';

		$default_order     = $this->default_order;
		$default_order_dir = $this->default_order_dir;

		$filter_order     = $fcform ? $jinput->get('filter_order', $default_order, 'cmd') : $app->getUserStateFromRequest($p . 'filter_order', 'filter_order', $default_order, 'cmd');
		$filter_order_Dir = $fcform ? $jinput->get('filter_order_Dir', $default_order_dir, 'word') : $app->getUserStateFromRequest($p . 'filter_order_Dir', 'filter_order_Dir', $default_order_dir, 'word');

		if (!$filter_order)
		{
			$filter_order     = $default_order;
		}

		if (!$filter_order_Dir)
		{
			$filter_order_Dir = $default_order_dir;
		}

		$this->setState('filter_order', $filter_order);
		$this->setState('filter_order_Dir', $filter_order_Dir);

		$app->setUserState($p . 'filter_order', $filter_order);
		$app->setUserState($p . 'filter_order_Dir', $filter_order_Dir);
	}


	/**
	 * Method to get records matching specific conditions (SQL query clauses)
	 *
	 * @param   array   $clauses   Array of SQL clauses (each of them a array) like, where, order, etc
	 * @param   bool    $useMain   If true the use query created by _getListQuery()
	 *
	 * @return array
	 *
	 * @since 3.3.0
	 */
	public function getItemsByConditions($clauses = array(), $useMain = false)
	{
		// Either use main Query
		if ($useMain)
		{
			$query = $this->_getListQuery()
				->clear('where')
				->clear('order')
				->setLimit($limit = 0, $offset = 0);
		}
		else
		{
			$query = $this->_db->getQuery(true)
				->select('t.*')
				->from('#__' . $this->records_dbtbl . ' AS t');
		}

		// Add the given SQL clauses
		foreach ($clauses as $clause_name => $clause_value)
		{
			$query->{$clause_name}($clause_value);
		}

		return $this->_db->setQuery($query)->loadObjectList('id');
	}


	/**
	 * Method to save the reordered nested set tree.
	 * First we save the new order values in the lft values of the changed ids.
	 * Then we invoke the table rebuild to implement the new ordering.
	 *
	 * @param   array    $pks       An array of primary key ids.
	 * @param   integer  $order     The lft or ordering value
	 * @param   integer  $group_id  The parent ID of the group / category being reorder, this is needed when records are assigned to multiple groups / categories
	 *
	 * @return  boolean   Boolean true on success, false on failure
	 *
	 * @since   3.3.0
	 */
	public function saveorder($pks, $order, $group_id = 0)
	{
		// Get an instance of the table object.
		$table = $this->getTable();

		if (!$table->saveorder($pks, $order))
		{
			$this->setError($table->getError());
			return false;
		}

		// Clear the cache
		$this->cleanCache();

		return true;
	}


	/**
	 * START OF MODEL SPECIFIC METHODS
	 */

}
