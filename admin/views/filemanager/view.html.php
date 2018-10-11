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
 * HTML View class for the Filemanager View
 */
class FlexicontentViewFilemanager extends FlexicontentViewBaseRecords
{
	var $proxy_option   = null;
	var $title_propname = 'filename';
	var $state_propname = 'published';
	var $db_tbl         = 'flexicontent_files';

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
		;

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

		$langs = FLEXIUtilities::getLanguages('code');
		/*
		$authorparams = flexicontent_db::getUserConfig($user->id);
		$allowed_langs = !$authorparams ? null : $authorparams->get('langs_allowed',null);
		$allowed_langs = !$allowed_langs ? null : FLEXIUtilities::paramToArray($allowed_langs);
		*/
		$allowed_langs = null;
		$display_file_lang_as = $cparams->get('display_file_lang_as', 3);


		// Get user's global permissions
		$perms = FlexicontentHelperPerm::getPerm();

		// Get field id and folder mode
		$fieldid = $view=='fileselement' ? $jinput->get('field', 0, 'int') : null;   // Force no field id for filemanager
		$folder_mode = 0;
		$assign_mode = 0;
		if ($fieldid)
		{
			$_fields = FlexicontentFields::getFieldsByIds(array($fieldid), false);
			if ( !empty($_fields[$fieldid]) )
			{
				$field = $_fields[$fieldid];
				$field->parameters = new JRegistry($field->attribs);

				if ( in_array($field->field_type, array('image')) )
				{
				 $folder_mode = $field->parameters->get('image_source')==0 ? 0 : 1;
				 $assign_mode = 1;
				}
				$layout = in_array($field->field_type, array('image', 'minigallery')) ? 'image' : $layout;
			}
			else
			{
				$fieldid = null;
			}
		}

		if (!$fieldid && $view === 'fileselement')
		{
			jexit('<div class="alert alert-info">no valid field ID</div>');
		}

		$_view = $view . $fieldid;


		/**
		 * Get filters and ordering
		 */

		$count_filters = 0;

		// Order and order direction
		$filter_order      = $model->getState('filter_order');
		$filter_order_Dir  = $model->getState('filter_order_Dir');

		$filter_state     = $app->getUserStateFromRequest( $option.'.'.$_view.'.filter_state',     'filter_state', '', 'int');
		$filter_access    = $app->getUserStateFromRequest( $option.'.'.$_view.'.filter_access',    'filter_access', '', 'int');
		$filter_lang			= $app->getUserStateFromRequest( $option.'.'.$_view.'.filter_lang',      'filter_lang',      '',          'string' );
		$filter_url       = $app->getUserStateFromRequest( $option.'.'.$_view.'.filter_url',       'filter_url',       '',          'word' );
		$filter_secure    = $app->getUserStateFromRequest( $option.'.'.$_view.'.filter_secure',    'filter_secure',    '',          'word' );
		$filter_stamp     = $app->getUserStateFromRequest( $option.'.'.$_view.'.filter_stamp',     'filter_stamp',     '',          'word' );

		$target_dir = $layout === 'image' ? 0 : 2;  // 0: Force media, 1: force secure, 2: allow selection
		$optional_cols = array('state', 'access', 'lang', 'hits', 'target', 'stamp', 'usage', 'uploader', 'upload_time', 'file_id');
		$cols = array();


		// Column disabling only applicable for FILESELEMENT view, with field in DB mode (folder_mode==0)
		if (!$folder_mode && $fieldid)
		{
			// Clear secure/media filter if field is not configured to use specific
			$target_dir = $field->parameters->get('target_dir', '');
			$filter_secure = !strlen($target_dir) || $target_dir!=2  ?  ''  :  $filter_secure;

			$filelist_cols = FLEXIUtilities::paramToArray( $field->parameters->get('filelist_cols', array('upload_time', 'hits')) );

		}


		/**
		 * Column selection of optional columns given
		 */

		if (!empty($filelist_cols))
		{
			foreach($filelist_cols as $col)
			{
				$cols[$col] = 1;
			}

			unset($cols['_SAVED_']);
		}
		
		/*
		 * Column selection of optional columns not given)
		 */

		// Filemanager view, add all columns
		elseif ($view === 'filemanager')
		{
			foreach($optional_cols as $col)
			{
				$cols[$col] = 1;
			}
		}

		// Fileselement view, add none of optional columns
		else ;

		$filter_ext       = $app->getUserStateFromRequest( $option.'.'.$_view.'.filter_ext',       'filter_ext',       '',          'alnum' );
		$filter_uploader  = $app->getUserStateFromRequest( $option.'.'.$_view.'.filter_uploader',  'filter_uploader',  '',           'int' );
		$filter_item      = $app->getUserStateFromRequest( $option.'.'.$_view.'.item_id',          'item_id',          '',           'int' );

		if ($layout !== 'image')
		{
			if ($filter_state) $count_filters++;
			if ($filter_access) $count_filters++;
			if ($filter_lang) $count_filters++;
			if ($filter_url) $count_filters++;
			if ($filter_stamp) $count_filters++;
			if ($filter_secure) $count_filters++;
		}

		// ?? Force unsetting language and target_dir columns if LAYOUT is image file list
		else
		{
			unset($cols['lang']);
			unset($cols['target']);
		}

		// Case of uploader column not applicable or not allowed
		if (!$folder_mode && !$perms->CanViewAllFiles) unset($cols['uploader']);

		if ($filter_ext) $count_filters++;
		if ($filter_uploader && !empty($cols['uploader'])) $count_filters++;
		if ($filter_item) $count_filters++;

		$u_item_id = $view === 'fileselement' ? $app->getUserStateFromRequest( $option.'.'.$_view.'.u_item_id', 'u_item_id', 0, 'string' ) : null;


		// Text search
		$scope  = $model->getState('scope');
		$search = $model->getState('search');
		$search = StringHelper::trim(StringHelper::strtolower($search));

		$filter_uploader  = $filter_uploader ? $filter_uploader : '';
		$filter_item      = $filter_item ? $filter_item : '';


		// *** TODO: (enhancement) get recently deleted file(s), and remove their assignments from current form
		$delfilename = $app->getUserState('delfilename', null);
		$app->setUserState('delfilename', null);

		$upload_context = 'fc_upload_history.item_' . $u_item_id . '_field_' . $fieldid;
		$session_files = $session->get($upload_context, array());

		$pending_file_ids = !empty($session_files['ids_pending']) ? $session_files['ids_pending'] : array();
		$pending_file_names = !empty($session_files['names_pending']) ? $session_files['names_pending'] : array();

		$pending_file_names = array_flip($pending_file_names);


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

			// Fields common CSS
			$document->addStyleSheetVersion(JUri::root(true).'/components/com_flexicontent/assets/css/flexi_form_fields.css', FLEXI_VHASH);

			// Add JS frameworks
			flexicontent_html::loadFramework('select2');
			flexicontent_html::loadFramework('prettyCheckable');
			flexicontent_html::loadFramework('flexi-lib');
			flexicontent_html::loadFramework('flexi-lib-form');

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
		if ($view !== 'fileselement')
		{
			FLEXIUtilities::ManagerSideMenu('CanFiles');
		}

		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_FILEMANAGER' );
		$site_title = $document->getTitle();

		if ($view !== 'fileselement')
		{
			JToolbarHelper::title( $doc_title, 'files' );
		}

		$document->setTitle($doc_title .' - '. $site_title);

		// Create the toolbar
		$this->setToolbar();


		/**
		 * Get data from the model
		 */

		// DB mode
		if ( !$folder_mode )
		{
			$rows_pending = $view === 'fileselement'
				? $model->getDataPending()
				: false;

			if (empty($rows_pending))
			{
				$rows = $model->getData();
			}
			$img_folder = '';
		}

		// FOLDER mode
		else
		{
			$rows_pending = $view === 'fileselement'
				? $model->getFilesFromPath($u_item_id, $fieldid, null, true)
				: false;

			if (empty($rows_pending))
			{
				$rows = $model->getFilesFromPath($u_item_id, $fieldid, null, false);
			}
			$img_folder = $model->getFieldFolderPath($u_item_id, $fieldid);
		}


		// Clear pending
		unset($session_files['ids_pending']);
		unset($session_files['names_pending']);
		$session->set($upload_context, $session_files);

		// Create pagination object
		$pagination = $this->get('Pagination');

		$assigned_fields_labels = array('image'=>'image/gallery', 'file'=>'file', 'minigallery'=>'minigallery');
		$assigned_fields_icons = array('image'=>'picture_link', 'file'=>'page_link', 'minigallery'=>'film_link');


		// *** BOF FOLDER MODE specific ***


		/**
		 * FILE UPLOAD FORM
		 */

		$ffields = array();

		// Language form field
		$elementid = 'file-lang';
		$fieldname = 'file-lang';

		$ffields['file-lang'] = flexicontent_html::buildlanguageslist(
			$fieldname,
			array(
				'class' => 'use_select2_lib',
			),
			'*',
			$display_file_lang_as,
			$allowed_langs,
			$published_only = false
		);


		// Access level form field
		$elementid = 'file-access';
		$fieldname = 'file-access';

		$options = JHtml::_('access.assetgroups');

		$ffields['file-access'] = JHtml::_('select.genericlist',
			$options,
			$fieldname,
			array(
				'class' => 'use_select2_lib',
			),
			'value',
			'text',
			null,
			$elementid,
			$translate = true
		);


		/**
		 * Create List Filters
		 */

		$lists = array();

		// Build language filter
		$lists['filter_lang'] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_LANGUAGE'),
			'html' => flexicontent_html::buildlanguageslist(
				'filter_lang',
				array(
					'class' => 'use_select2_lib',
					'onchange' => 'document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				$filter_lang,
				'-'
			)
		));

		// Build publication state filter
		$options 	= array();
		$options[] = JHtml::_('select.option',  '', '-'/*JText::_( 'FLEXI_SELECT_STATE' )*/ );
		$options[] = JHtml::_('select.option',  'P', JText::_( 'FLEXI_PUBLISHED' ) );
		$options[] = JHtml::_('select.option',  'U', JText::_( 'FLEXI_UNPUBLISHED' ) );
		//$options[] = JHtml::_('select.option',  'A', JText::_( 'FLEXI_ARCHIVED' ) );
		//$options[] = JHtml::_('select.option',  'T', JText::_( 'FLEXI_TRASHED' ) );

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


		// Build text search scope 
		$scopes = array(
			0 =>  '- ' . JText::_('FLEXI_ALL') . ' -',
			1 => JText::_('FLEXI_FILENAME'),
			2 => JText::_('FLEXI_FILE_DISPLAY_TITLE'),
			3 => JText::_('FLEXI_DESCRIPTION'),
		);

		$this->scope_title = $scopes[$scope];
		$options = array();

		foreach ($scopes as $i => $v)
		{
			$options[] = JHtml::_('select.option', $i, $v);
		}

		array_unshift($options, JHtml::_('select.option', 0, JText::_('FLEXI_SEARCH_TEXT_INSIDE'), 'value', 'text', 'disabled'));

		$lists['scope_tip'] = ''; //'<span class="hidden-phone ' . $this->tooltip_class . '" title="'.JText::_('FLEXI_SEARCH_TEXT_INSIDE').'" style="display: inline-block;"><i class="icon-info-2"></i></span>';
		$lists['scope'] = JHtml::_('select.genericlist',
			$options,
			'scope',
			array(
				'size' => '1',
				'class' => $this->select_class . ' fc_skip_highlight fc_is_selarrow ' . $this->tooltip_class,
				'onchange' => 'jQuery(\'#search\').attr(\'placeholder\', jQuery(this).find(\'option:selected\').text()); jQuery(this).blur();',
				'title' => JText::_('FLEXI_SEARCH_TEXT_INSIDE'),
			),
			'value',
			'text',
			$scope,
			'scope'
		);

		if ($layout !== 'image' || $view !== 'fileselement')
		{
			// Build url/file filter
			$url 	= array();
			$url[] 	= JHtml::_('select.option',  '', '-'/*JText::_( 'FLEXI_ALL_FILES' )*/ );
			$url[] 	= JHtml::_('select.option',  'F', JText::_( 'FLEXI_FILE' ) );
			$url[] 	= JHtml::_('select.option',  'U', JText::_( 'FLEXI_URL' ) );

			$lists['filter_url'] = $this->getFilterDisplay(array(
				'label' => JText::_('FLEXI_ALL_FILES'),
				'html' => JHtml::_('select.genericlist',
					$url,
					'filter_url',
					array(
						'class' => 'use_select2_lib',
						'onchange' => 'document.adminForm.limitstart.value=0; Joomla.submitform();',
					),
					'value',
					'text',
					$filter_url
				)
			));

			// Build stamp filter
			$stamp 	= array();
			$stamp[] 	= JHtml::_('select.option',  '', '-'/*JText::_( 'FLEXI_ALL_FILES' )*/ );
			$stamp[] 	= JHtml::_('select.option',  '0', JText::_( 'FLEXI_NO' ) );
			$stamp[] 	= JHtml::_('select.option',  '1', JText::_( 'FLEXI_YES' ) );

			$lists['filter_stamp'] = $this->getFilterDisplay(array(
				'label' => JText::_('FLEXI_DOWNLOAD_STAMPING'),
				'html' => JHtml::_('select.genericlist',
					$stamp,
					'filter_stamp',
					array(
						'class' => 'use_select2_lib',
						'onchange' => 'document.adminForm.limitstart.value=0; Joomla.submitform();',
					),
					'value',
					'text',
					$filter_stamp
				)
			));
		}

		// Build content item id filter
		$lists['item_id'] = $this->getFilterDisplay(array(
			'label' => JText::_('Item id'),
			'html' => '<input type="text" name="item_id" size="1" class="inputbox" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" value="'.$filter_item.'" />',
		));

		//build secure/media filterlist
		$_secure_info = '<i data-placement="bottom" class="icon-info hasTooltip" title="'.flexicontent_html::getToolTip('FLEXI_URL_SECURE', 'FLEXI_URL_SECURE_DESC', 1, 1).'"></i>';

		if ($target_dir==2)
		{
			$secure 	= array();
			$secure[] 	= JHtml::_('select.option',  '', '-'/*JText::_( 'FLEXI_ALL_DIRECTORIES' )*/ );
			$secure[] 	= JHtml::_('select.option',  'S', JText::_( 'FLEXI_SECURE_DIR' ) );
			$secure[] 	= JHtml::_('select.option',  'M', JText::_( 'FLEXI_MEDIA_DIR' ) );

			$lists['filter_secure'] = $this->getFilterDisplay(array(
				'label' => $_secure_info . ' ' . JText::_('FLEXI_URL_SECURE'),
				'html' => JHtml::_('select.genericlist',
					$secure,
					'filter_secure',
					array(
						'class' => 'use_select2_lib',
						'onchange' => 'document.adminForm.limitstart.value=0; Joomla.submitform();',
					),
					'value',
					'text',
					$filter_secure
				)
			));
		}
		else
		{
			$lists['filter_secure'] = $this->getFilterDisplay(array(
				'label' => $_secure_info . ' ' . JText::_('FLEXI_URL_SECURE'),
				'html' => '<span class="badge badge-info">' . JText::_($target_dir == 0 ? 'FLEXI_MEDIA_DIR' : 'FLEXI_SECURE_DIR') . '</span>',
			));
		}

		//build ext filterlist
		$lists['filter_ext'] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_ALL_EXT'),
			'html' => flexicontent_html::buildfilesextlist(
				'filter_ext',
				array(
					'class' => 'use_select2_lib',
					'onchange' => 'document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				$filter_ext,
				'-'
			)
		));

		//build uploader filterlist
		if ($perms->CanViewAllFiles && !empty($cols['uploader']))
		{
			$lists['filter_uploader'] = $this->getFilterDisplay(array(
				'label' => JText::_('FLEXI_ALL_UPLOADERS'),
				'html' => flexicontent_html::builduploaderlist(
					'filter_uploader',
					array(
						'class' => 'use_select2_lib',
						'onchange' => 'document.adminForm.limitstart.value=0; Joomla.submitform();',
					),
					$filter_uploader,
					'-'
				)
			));
		}


		// Text search filter value
		$lists['search'] = $search;


		// Table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order']     = $filter_order;

		// Uploadstuff
		jimport('joomla.client.helper');
		$require_ftp = !JClientHelper::hasCredentials('ftp');


		/**
		 * Assign data to template
		 */

		$this->target_dir = $target_dir;
		$this->optional_cols = $optional_cols;
		$this->cols = $cols;
		$this->count_filters = $count_filters;

		$this->params      = $cparams;
		$this->ffields     = $ffields;
		$this->lists       = $lists;
		$this->rows        = $rows_pending ?: $rows;
		$this->is_pending  = $rows_pending ? true : false;
		$this->langs       = $langs;

		$this->folder_mode = $folder_mode;
		$this->assign_mode = $assign_mode;
		$this->pagination  = $pagination;

		$this->perms  = FlexicontentHelperPerm::getPerm();

		$this->CanFiles   = $perms->CanFiles;
		$this->CanUpload  = $perms->CanUpload;
		$this->CanViewAllFiles = $perms->CanViewAllFiles;

		$this->assigned_fields_labels = $assigned_fields_labels;
		$this->assigned_fields_icons  = $assigned_fields_icons;

		$this->require_ftp = $require_ftp;
		$this->layout  = $layout;
		$this->field   = !empty($field) ? $field : null;
		$this->fieldid = $fieldid;
		$this->u_item_id  = $u_item_id;
		$this->pending_file_names = $pending_file_names;

		$this->option = $option;
		$this->view   = $view;
		$this->state  = $this->get('State');

		if ($view === 'fileselement')
		{
			$this->img_folder = $img_folder;
			$this->thumb_w    = $thumb_w;
			$this->thumb_h    = $thumb_h;
			$this->targetid   = $targetid;
		}
		elseif (!$jinput->getCmd('nosidebar'))
		{
			$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
		}

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

		$contrl = "filemanager.";
		$contrl_singular = "file.";

		$loading_msg = flexicontent_html::encodeHTML(JText::_('FLEXI_LOADING') .' ... '. JText::_('FLEXI_PLEASE_WAIT'), 2);
		$tip_class = ' hasTooltip';

		$user  = JFactory::getUser();

		if (1)
		{
			JToolbarHelper::editList($contrl.'edit');
		}
		JToolbarHelper::checkin($contrl.'checkin');
		JToolbarHelper::deleteList(JText::_('FLEXI_ARE_YOU_SURE'), 'filemanager.remove');

		$btn_arr = array();

		if ($perms->CanConfig)
		{
			$popup_load_url = JUri::base(true) . '/index.php?option=com_flexicontent&amp;view=filemanager&amp;layout=indexer&amp;tmpl=component&amp;indexer=filemanager_stats';
			$btn_text = JText::_('FLEXI_INDEX_FILE_STATISTICS') . ' (' . JText::_('FLEXI_SIZE') . ', ' . JText::_('FLEXI_USAGE') . ' )';
			$btn_name = 'index_files_stats';
			$full_js="if (!confirm('" . str_replace('<br>', '\n', flexicontent_html::encodeHTML(JText::_('FLEXI_INDEX_FILE_STATISTICS_DESC'), 'd')) . "')) return false; var url = jQuery(this).data('taskurl'); fc_showDialog(url, 'fc_modal_popup_container', 0, 550, 350, function(){document.body.innerHTML='<span class=\"fc_loading_msg\">"
						.$loading_msg."<\/span>'; window.location.reload(false)}, {'title': '".flexicontent_html::encodeHTML(JText::_('FLEXI_INDEX_FILE_STATISTICS'), 'd')."'}); return false;";
			$btn_arr[] = flexicontent_html::addToolBarButton(
				$btn_text, $btn_name, $full_js,
				$msg_alert = JText::_('FLEXI_NO_ITEMS_SELECTED'), $msg_confirm = '',
				$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				'btn btn-fcaction ' . $tip_class, 'icon-loop',
				'data-placement="right" data-taskurl="' . $popup_load_url .'" title="' . flexicontent_html::encodeHTML(JText::_('FLEXI_INDEX_FILE_STATISTICS_DESC'), 'd') . '"', $auto_add = 0, $tag_type='button')
				;

			$popup_load_url = JUri::base(true) . '/index.php?option=com_flexicontent&amp;view=filemanager&amp;layout=indexer&amp;tmpl=component&amp;indexer=filemanager_stats&amp;index_urls=1';
			$btn_text = JText::_('FLEXI_INDEX_FILE_STATISTICS') . ' (' . JText::_('FLEXI_SIZE') . ', ' . JText::_('FLEXI_USAGE') . ', ' . JText::_('FLEXI_URL') . ' )';
			$btn_name = 'index_files_urls_stats';
			$full_js="if (!confirm('" . str_replace('<br>', '\n', flexicontent_html::encodeHTML(JText::_('FLEXI_INDEX_FILE_STATISTICS_DESC'), 'd')) . "')) return false; var url = jQuery(this).data('taskurl'); fc_showDialog(url, 'fc_modal_popup_container', 0, 550, 350, function(){document.body.innerHTML='<span class=\"fc_loading_msg\">"
						.$loading_msg."<\/span>'; window.location.reload(false)}, {'title': '".flexicontent_html::encodeHTML(JText::_('FLEXI_INDEX_FILE_STATISTICS'), 'd')."'}); return false;";
			$btn_arr[] = flexicontent_html::addToolBarButton(
				$btn_text, $btn_name, $full_js,
				$msg_alert = JText::_('FLEXI_NO_ITEMS_SELECTED'), $msg_confirm = '',
				$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				'btn btn-fcaction ' . $tip_class, 'icon-loop',
				'data-placement="right" data-taskurl="' . $popup_load_url .'" title="' . flexicontent_html::encodeHTML(JText::_('FLEXI_INDEX_FILE_STATISTICS_DESC'), 'd') . '"', $auto_add = 0, $tag_type='button')
				;
		}

		if (count($btn_arr))
		{
			$drop_btn = '
				<button type="button" class="' . $this->btn_sm_class . ' btn-primary dropdown-toggle" data-toggle="dropdown">
					<span title="'.JText::_('FLEXI_MAINTENANCE').'" class="icon-menu"></span>
					'.JText::_('FLEXI_MAINTENANCE').'
					<span class="caret"></span>
				</button>';
			array_unshift($btn_arr, $drop_btn);
			flexicontent_html::addToolBarDropMenu($btn_arr, 'maintenance-btns-group', ' ');
		}

		/*$stats_indexer_errors = $session->get('filemanager.stats_indexer_errors', null, 'flexicontent');
		if ($stats_indexer_errors !== null)
		{
			foreach($stats_indexer_errors as $error_message)
			{
				JFactory::getApplication()->enqueueMessage($error_message, 'warning');
			}
			$session->set('filemanager.stats_indexer_errors', null, 'flexicontent');
		}*/

		$error_count = $session->get('filemanager.stats_indexer.error_count', 0, 'flexicontent');
		if ($error_count)
		{
			JFactory::getApplication()->enqueueMessage('Could not calculate file stats for: ' . $error_count . ' cases (e.g. bad URLs)', 'warning');
			$session->set('filemanager.stats_indexer.error_count', null, 'flexicontent');

			if ($log_filename = $session->get('filemanager_stats_log_filename', null, 'flexicontent'))
			{
				JFactory::getApplication()->enqueueMessage('You may see log file : <b>' . JPATH::clean(\JFactory::getConfig()->get('log_path') . DS . $log_filename) . '</b> for messages and errors', 'warning');
			}
		}
		$session->set('filemanager_stats_log_filename', null, 'flexicontent');

		if ($perms->CanConfig)
		{
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