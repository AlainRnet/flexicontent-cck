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

JLoader::register('FlexicontentViewBaseRecords', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/base/view_records.php');

/**
 * HTML View class for the categories backend manager
 */
class FlexicontentViewCategories extends FlexicontentViewBaseRecords
{
	var $proxy_option   = 'com_categories';
	var $title_propname = 'title';
	var $state_propname = 'published';
	var $db_tbl         = 'categories';

	public function display($tpl = null)
	{
		/**
		 * Initialise variables
		 */

		global $globalcats;
		$app      = JFactory::getApplication();
		$jinput   = $app->input;
		$document = JFactory::getDocument();
		$user     = JFactory::getUser();
		$cparams  = JComponentHelper::getParams('com_flexicontent');
		$session  = JFactory::getSession();
		$db       = JFactory::getDbo();

		$option   = $jinput->getCmd('option', '');
		$view     = $jinput->getCmd('view', '');
		$task     = $jinput->getCmd('task', '');
		$layout   = $jinput->getString('layout', 'default');

		$isAdmin  = $app->isAdmin();
		$isCtmpl  = $jinput->getCmd('tmpl') === 'component';

		// Some flags & constants
		$order_property = 'a.lft';

		// Load Joomla language files of other extension
		if (!empty($this->proxy_option))
		{
			JFactory::getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, null, true);
		}

		// Get model
		$model = $this->getModel();

		// Performance statistics
		if ($print_logging_info = $cparams->get('print_logging_info'))
		{
			global $fc_run_times;
		}


		/**
		 * Get filters and ordering
		 */

		$count_filters = 0;

		// Order and order direction
		$filter_order      = $model->getState('filter_order');
		$filter_order_Dir  = $model->getState('filter_order_Dir');

		// Various filters
		$filter_state     = $model->getState('filter_state');
		$filter_cats      = $model->getState('filter_cats');
		$filter_level     = $model->getState('filter_level');
		$filter_access    = $model->getState('filter_access');
		$filter_language  = $model->getState('filter_language');

		if ($filter_state) $count_filters++;
		if ($filter_cats) $count_filters++;
		if ($filter_level) $count_filters++;
		if ($filter_access) $count_filters++;
		if ($filter_language) $count_filters++;

		// Record ID filter
		$filter_id = $model->getState('filter_id');
		if (strlen($filter_id)) $count_filters++;


		// Text search
		$search = $model->getState('search');
		$search = StringHelper::trim(StringHelper::strtolower($search));


		/**
		 * Add css and js to document
		 */

		if ($layout !== 'indexer')
		{
			// Add css to document
			if ($isAdmin)
			{
				!JFactory::getLanguage()->isRtl()
					? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH)
					: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', FLEXI_VHASH);
				!JFactory::getLanguage()->isRtl()
					? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH)
					: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x_rtl.css', FLEXI_VHASH);
			}
			else
			{
				!JFactory::getLanguage()->isRtl()
					? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', FLEXI_VHASH)
					: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent_rtl.css', FLEXI_VHASH);
			}

			// Add JS frameworks
			flexicontent_html::loadFramework('select2');

			// Load custom behaviours: form validation, popup tooltips
			JHtml::_('behavior.formvalidation');
			JHtml::_('bootstrap.tooltip');

			// Add js function to overload the joomla submitform validation
			$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
			$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);
		}


		/**
		 * Create Submenu & Toolbar
		 */

		// Create Submenu (and also check access to current view)
		FLEXIUtilities::ManagerSideMenu('CanCats');

		// Create document/toolbar titles
		$doc_title = JText::_('FLEXI_CATEGORIES');
		$site_title = $document->getTitle();
		JToolbarHelper::title($doc_title, 'fc_categories');
		$document->setTitle($doc_title .' - '. $site_title);

		// Create the toolbar
		$this->setToolbar();


		/**
		 * Get data from the model
		 */

		if ( $print_logging_info )  $start_microtime = microtime(true);

		$rows = $this->get('Items');

		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		// Create pagination object
		$pagination = $this->get('Pagination');


		/**
		 * Get assigned items (via separate query), do this is if not already retrieved
		 */

		if (1)
		{
			$rowids = array();

			foreach ($rows as $row)
			{
				$rowids[] = $row->id;
			}

			if ( $print_logging_info )  $start_microtime = microtime(true);
			//$rowtotals = $model->getAssignedItems($rowids);
			$byStateTotals = $model->countItemsByState($rowids);
			if ( $print_logging_info ) @$fc_run_times['execute_sec_queries'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

			foreach ($rows as $row)
			{
				//$row->nrassigned = isset($rowtotals[$row->id]) ? $rowtotals[$row->id]->nrassigned : 0;
				$row->byStateTotals = isset($byStateTotals[$row->id]) ? $byStateTotals[$row->id] : array();
			}
		}

		// Parse configuration for every category
   	foreach ($rows as $cat)
		{
			$cat->config = new JRegistry($cat->config);
		}

		// Preprocess the list of items to find ordering divisions.
		foreach ($rows as $item)
		{
			$this->ordering[$item->parent_id][] = $item->id;
		}


		/**
		 * Add usage information notices if these are enabled
		 */

		$conf_link = '<a href="index.php?option=com_config&amp;view=component&amp;component=com_flexicontent&amp;path=" class="' . $this->btn_sm_class . ' btn-info">'.JText::_("FLEXI_CONFIG").'</a>';

		if ($cparams->get('show_usability_messages', 1))
		{
		}


		/**
		 * Create List Filters
		 */

		$lists = array();

		$categories = $globalcats;
		$lists['copyid'] = flexicontent_cats::buildcatselect($categories, 'copycid', '', 2, 'class="use_select2_lib"', false, true, $actions_allowed=array('core.edit'));
		$lists['destid'] = flexicontent_cats::buildcatselect($categories, 'destcid[]', '', false, 'class="use_select2_lib" size="10" multiple="true"', false, true, $actions_allowed=array('core.edit'));


		// Build category filter (it's subtree will be displayed)
		$lists['filter_cats'] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_CATEGORY'),
			'html' => flexicontent_cats::buildcatselect(
				$categories,
				'filter_cats',
				$filter_cats,
				'-',
				array(
					'class' => 'use_select2_lib',
					'onchange' => 'document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				$check_published = true,
				$check_perms = false
			)
		));


		// Build depth level filter
		$options	= array();
		$options[]	= JHtml::_('select.option', '', '-'/*JText::_('FLEXI_SELECT_MAX_DEPTH')*/);

		for ($i = 1; $i <= 10; $i++)
		{
			$options[]	= JHtml::_('select.option', $i, $i);
		}

		$fieldname = 'filter_level';
		$elementid = 'filter_level';

		$lists['filter_level'] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_MAX_DEPTH'),
			'html' => JHtml::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'class' => 'use_select2_lib',
					'onchange' => 'document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$filter_level,
				$elementid,
				$translate = true
			)
		));


		// Build publication state filter
		$options = JHtml::_('jgrid.publishedOptions');
		array_unshift($options, JHtml::_('select.option', '', '-'/*JText::_('JOPTION_SELECT_PUBLISHED')*/) );

		$fieldname = 'filter_state';
		$elementid = 'filter_state';

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_STATE'),
			'html' => JHtml::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'class' => 'use_select2_lib',
					'onchange' => 'document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$filter_state,
				$elementid,
				$translate = true
			)
		));


		// Build access level filter
		$options = JHtml::_('access.assetgroups');
		array_unshift($options, JHtml::_('select.option', '', '-'/*JText::_('JOPTION_SELECT_ACCESS')*/) );

		$fieldname = 'filter_access';
		$elementid = 'filter_access';

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_ACCESS'),
			'html' => JHtml::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'class' => 'use_select2_lib',
					'onchange' => 'document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$filter_access,
				$elementid,
				$translate = true
			)
		));


		// Build language filter
		$lists['filter_language'] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_LANGUAGE'),
			'html' => flexicontent_html::buildlanguageslist(
				'filter_language',
				array(
					'class' => 'use_select2_lib',
					'onchange' => 'document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				$filter_language,
				'-'
			)
		));


		// Build id list filter
		$lists['filter_id'] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_ID'),
			'html' => '<input type="text" name="filter_id" id="filter_id" size="6" value="' . $filter_id . '" class="inputbox" style="width:auto;" />',
		));


		// Text search filter value
		$lists['search'] = $search;


		// Table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order']     = $filter_order;

		$orderingx = $lists['order'] == $order_property && strtolower($lists['order_Dir']) == 'asc'
			? $order_property
			: '';


		/**
		 * Assign data to template
		 */

		$this->count_filters = $count_filters;

		$this->lists       = $lists;
		$this->rows        = $rows;
		$this->pagination  = $pagination;
		$this->orderingx   = $orderingx;

		$this->perms  = FlexicontentHelperPerm::getPerm();
		$this->option = $option;
		$this->view   = $view;
		$this->state  = $this->get('State');

		$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;


		/**
		 * Render view's template
		 */

		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }

		parent::display($tpl);

		if ( $print_logging_info ) @$fc_run_times['template_render'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}



	/**
	 * Method to configure the toolbar for this view.
	 *
	 * @access	public
	 * @return	void
	 */
	function setToolbar()
	{
		$user     = JFactory::getUser();
		$document = JFactory::getDocument();
		$toolbar  = JToolbar::getInstance('toolbar');
		$perms    = FlexicontentHelperPerm::getPerm();
		$session  = JFactory::getSession();

		$js = '';

		$contrl = "categories.";
		$contrl_singular = "category.";

		$loading_msg = flexicontent_html::encodeHTML(JText::_('FLEXI_LOADING') .' ... '. JText::_('FLEXI_PLEASE_WAIT'), 2);

		// Get if state filter is active
		$model = $this->getModel();
		$filter_state = $model->getState('filter_state');

		if ($user->authorise('core.create', 'com_flexicontent'))
		{
			$cancreate_cat = true;
		}
		else
		{
			$usercats = FlexicontentHelperPerm::getAllowedCats(
				$user,
				$actions_allowed = array('core.create'),
				$require_all = true,
				$check_published = true,
				$specific_catids = false,
				$find_first = true
			);
			$cancreate_cat  = count($usercats) > 0;
		}

		if ($cancreate_cat)
		{
			JToolbarHelper::addNew($contrl_singular.'add');
		}

		if ($user->authorise('core.edit', 'com_flexicontent') || $user->authorise('core.edit.own', 'com_flexicontent'))
		{
			JToolbarHelper::editList($contrl_singular.'edit');
		}

		$btn_arr = array();

		if ( $user->authorise('core.edit.state', 'com_flexicontent') || $user->authorise('core.edit.state.own', 'com_flexicontent') )
		{
			$btn_text = 'JTOOLBAR_PUBLISH';
			$btn_name = 'publish';
			$btn_task = $contrl . 'publish';
			$btn_arr[] = flexicontent_html::addToolBarButton(
				$btn_text, $btn_name, $full_js = '',
				$msg_alert = JText::_('FLEXI_NO_ITEMS_SELECTED'), $msg_confirm = '',
				$btn_task, $extra_js='', $btn_list=true, $btn_menu=true, $btn_confirm=false,
				'btn btn-fcaction', 'icon-checkbox',
				'', $auto_add = 0, $tag_type='button'
			);

			$btn_text = 'JTOOLBAR_UNPUBLISH';
			$btn_name = 'unpublish';
			$btn_task = $contrl . 'unpublish';
			$btn_arr[] = flexicontent_html::addToolBarButton(
				$btn_text, $btn_name, $full_js = '',
				$msg_alert = JText::_('FLEXI_NO_ITEMS_SELECTED'), $msg_confirm = '',
				$btn_task, $extra_js='', $btn_list=true, $btn_menu=true, $btn_confirm=false,
				'btn btn-fcaction', 'icon-cancel',
				'', $auto_add = 0, $tag_type='button'
			);

			$btn_text = 'JTOOLBAR_ARCHIVE';
			$btn_name = 'archive';
			$btn_task = $contrl . 'archive';
			$btn_arr[] = flexicontent_html::addToolBarButton(
				$btn_text, $btn_name, $full_js = '',
				$msg_alert = JText::_('FLEXI_NO_ITEMS_SELECTED'), $msg_confirm = '',
				$btn_task, $extra_js='', $btn_list=true, $btn_menu=true, $btn_confirm=false,
				'btn btn-fcaction', 'icon-archive',
				'', $auto_add = 0, $tag_type='button'
			);

			//JToolbarHelper::publishList($contrl.'publish');
			//JToolbarHelper::unpublishList($contrl.'unpublish');
			//JToolbarHelper::archiveList($contrl.'archive');
		}

		if ($filter_state == -2 && $user->authorise('core.delete', 'com_flexicontent'))
		{
			//JToolbarHelper::deleteList(JText::_('FLEXI_ARE_YOU_SURE'), $contrl.'remove');
			$msg_alert   = JText::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_DELETE'));
			$msg_confirm = JText::_('FLEXI_ARE_YOU_SURE');
			$btn_task    = $contrl.'remove';
			$extra_js    = "";
			flexicontent_html::addToolBarButton(
				'FLEXI_DELETE', 'delete', '', $msg_alert, $msg_confirm,
				$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true);
		}
		elseif ($user->authorise('core.edit.state', 'com_flexicontent'))
		{
			$btn_text = 'JTOOLBAR_TRASH';
			$btn_name = 'trash';
			$btn_task = $contrl . 'trash';
			$btn_arr[] = flexicontent_html::addToolBarButton(
				$btn_text, $btn_name, $full_js = '',
				$msg_alert = JText::_('FLEXI_NO_ITEMS_SELECTED'), $msg_confirm = '',
				$btn_task, $extra_js='', $btn_list=true, $btn_menu=true, $btn_confirm=false,
				'btn btn-fcaction', 'icon-trash',
				'', $auto_add = 0, $tag_type='button'
			);
			//JToolbarHelper::trash($contrl.'trash');
		}

		if (count($btn_arr))
		{
			$drop_btn = '
				<button type="button" class="' . $this->btn_sm_class . ' dropdown-toggle" data-toggle="dropdown">
					<span title="'.JText::_('FLEXI_CHANGE_STATE').'" class="icon-menu"></span>
					'.JText::_('FLEXI_CHANGE_STATE').'
					<span class="caret"></span>
				</button>';
			array_unshift($btn_arr, $drop_btn);
			flexicontent_html::addToolBarDropMenu($btn_arr, 'change-state-btns-group', ' ');
		}

		// Copy Parameters
		$btn_task = '';
		$popup_load_url = JUri::base(true) . '/index.php?option=com_flexicontent&view=categories&layout=params&tmpl=component';
		//$toolbar->appendButton('Popup', 'params', JText::_('FLEXI_COPY_PARAMS'), str_replace('&', '&amp;', $popup_load_url), 600, 440);
		$js .= "
			jQuery('#toolbar-params a.toolbar, #toolbar-params button')
				.attr('href', '".$popup_load_url."')
				.attr('onclick', 'var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 600, 440, function(){document.body.innerHTML=\'<span class=\"fc_loading_msg\">"
					.$loading_msg."<\/span>\'; window.location.reload(false)}, {\'title\': \'".flexicontent_html::encodeHTML(JText::_('FLEXI_COPY_PARAMS'), 2)."\'}); return false;');
		";
		JToolbarHelper::custom( $btn_task, 'params.png', 'params_f2.png', 'FLEXI_COPY_PARAMS', false );

		//$toolbar->appendButton('Popup', 'move', JText::_('FLEXI_BATCH'), JUri::base(true) . '/index.php?option=com_flexicontent&amp;view=categories&amp;layout=batch&amp;tmpl=component', 800, 440);

		JToolbarHelper::checkin($contrl.'checkin');

		$appsman_path = JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.'appsman';
		if (file_exists($appsman_path))
		{
			$btn_icon = 'icon-download';
			$btn_name = 'download';
			$btn_task = 'appsman.exportxml';
			$extra_js = " var f=document.getElementById('adminForm'); f.elements['view'].value='appsman'; jQuery('<input>').attr({type: 'hidden', name: 'table', value: '" . $this->db_tbl . "'}).appendTo(jQuery(f));";
			flexicontent_html::addToolBarButton(
				'Export now',
				$btn_name, $full_js='', $msg_alert='', $msg_confirm=JText::_('FLEXI_EXPORT_NOW_AS_XML'),
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true, $btn_class="btn-info", $btn_icon);

			$btn_icon = 'icon-box-add';
			$btn_name = 'box-add';
			$btn_task = 'appsman.addtoexport';
			$extra_js = " var f=document.getElementById('adminForm'); f.elements['view'].value='appsman'; jQuery('<input>').attr({type: 'hidden', name: 'table', value: '" . $this->db_tbl . "'}).appendTo(jQuery(f));";
			flexicontent_html::addToolBarButton(
				'Add to export',
				$btn_name, $full_js='', $msg_alert='', $msg_confirm=JText::_('FLEXI_ADD_TO_EXPORT_LIST'),
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true, $btn_class="btn-info", $btn_icon);
		}

		if ($perms->CanConfig)
		{
			JToolbarHelper::custom($contrl.'rebuild', 'refresh.png', 'refresh_f2.png', 'JTOOLBAR_REBUILD', false);
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolbarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}

		if ($js)
		{
			$document->addScriptDeclaration('
				jQuery(document).ready(function(){
					' . $js . '
				});
			');
		}
	}
}