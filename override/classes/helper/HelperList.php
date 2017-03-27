<?php

class HelperList extends HelperListCore

{
	/** @var array Cache for query results */

	protected $_list = array();



	/** @var integer Number of results in list */

	public $listTotal = 0;



	/** @var array WHERE clause determined by filter fields */

	protected $_filter;



	/** @var array Number of results in list per page (used in select field) */

	public $_pagination = array(20, 50, 100, 300, 1000);



	/** @var integer Default number of results in list per page */

	public $_default_pagination = 50;



	/** @var string ORDER BY clause determined by field/arrows in list header */

	public $orderBy;



	/** @var string Default ORDER BY clause when $orderBy is not defined */

	public $_defaultOrderBy = false;



	/** @var array : list of vars for button delete*/

	public $tpl_delete_link_vars = array();



	/** @var string Order way (ASC, DESC) determined by arrows in list header */

	public $orderWay;



	public $identifier;



	protected $deleted = 0;



	/** @var array $cache_lang use to cache texts in current language */

	public static $cache_lang = array();



	public $is_cms = false;



	public $position_identifier;



	public $table_id;



	/**

	 * @var array Customize list display

	 *

	 * align  : determine value alignment

	 * prefix : displayed before value

	 * suffix : displayed after value

	 * image  : object image

	 * icon   : icon determined by values

	 * active : allow to toggle status

	 */

	protected $fields_list;



	/** @var boolean Content line is clickable if true */

	public $no_link = false;



	protected $header_tpl = 'list_header.tpl';

	protected $content_tpl = 'list_content.tpl';

	protected $footer_tpl = 'list_footer.tpl';



	/** @var array list of required actions for each list row */

	public $actions = array();



	/** @var array list of row ids associated with a given action for witch this action have to not be available */

	public $list_skip_actions = array();



	public $bulk_actions = false;

	public $force_show_bulk_actions = false;

	public $specificConfirmDelete = null;

	public $colorOnBackground;



	/** @var bool If true, activates color on hover */

	public $row_hover = true;



	/** @var if not null, a title will be added on that list */

	public $title = null;



	/** @var boolean ask for simple header : no filters, no paginations and no sorting */

	public $simple_header = false;



	public $ajax_params = array();



	public function __construct()

	{

		$this->base_folder = 'helpers/list/';

		$this->base_tpl = 'list.tpl';



		parent::__construct();

	}



	/**

	 * Return an html list given the data to fill it up

	 *

	 * @param array $list entries to display (rows)

	 * @param array $fields_display fields (cols)

	 * @return string html

	 */

	public function generateList($list, $fields_display)

	{

		// Append when we get a syntax error in SQL query

		if ($list === false)

		{

			$this->context->controller->warnings[] = $this->l('Bad SQL query', 'Helper');

			return false;

		}



		$this->tpl = $this->createTemplate($this->base_tpl);

		$this->header_tpl = $this->createTemplate($this->header_tpl);

		$this->content_tpl = $this->createTemplate($this->content_tpl);

		$this->footer_tpl = $this->createTemplate($this->footer_tpl);



		$this->_list = $list;

		$this->fields_list = $fields_display;



		$this->orderBy = preg_replace('/^([a-z _]*!)/Ui', '', $this->orderBy);

		$this->orderWay = preg_replace('/^([a-z _]*!)/Ui', '', $this->orderWay);



		$this->tpl->assign(array(

			'header' => $this->displayListHeader(), // Display list header (filtering, pagination and column names)

			'content' => $this->displayListContent(), // Show the content of the table

			'footer' => $this->displayListFooter() // Close list table and submit button

		));

		return parent::generate();

	}



	/**

	 * Fetch the template for action enable

	 *

	 * @param string $token

	 * @param int $id

	 * @param int $value state enabled or not

	 * @param string $active status

	 * @param int $id_category

	 * @param int $id_product

	 * @return string

	 */

	public function displayEnableLink($token, $id, $value, $active, $id_category = null, $id_product = null, $ajax = false)

	{

		$tpl_enable = $this->createTemplate('list_action_enable.tpl');

		$tpl_enable->assign(array(

			'ajax' => $ajax,

			'enabled' => (bool)$value,

			'url_enable' => $this->currentIndex.'&'.$this->identifier.'='.(int)$id.'&'.$active.$this->table.($ajax ? '&action='.$active.$this->table.'&ajax='.(int)$ajax : '').

				((int)$id_category && (int)$id_product ? '&id_category='.(int)$id_category : '').'&token='.($token != null ? $token : $this->token)

		));

		return $tpl_enable->fetch();

	}



	public function displayListContent()

	{

		if (isset($this->fields_list['position']))

		{

			if ($this->position_identifier)

				if (isset($this->position_group_identifier))

					$position_group_identifier = Tools::getIsset($this->position_group_identifier) ? Tools::getValue($this->position_group_identifier) : $this->position_group_identifier;

				else

					$position_group_identifier = (int)Tools::getValue('id_'.($this->is_cms ? 'cms_' : '').'category', ($this->is_cms ? '1' : Category::getRootCategory()->id ));

			else

				$position_group_identifier = Category::getRootCategory()->id;



			$positions = array_map(create_function('$elem', 'return (int)($elem[\'position\']);'), $this->_list);

			sort($positions);

		}



		// key_to_get is used to display the correct product category or cms category after a position change

		$identifier = in_array($this->identifier, array('id_category', 'id_cms_category')) ? '_parent' : '';

		if ($identifier)

			$key_to_get = 'id_'.($this->is_cms ? 'cms_' : '').'category'.$identifier;



		foreach ($this->_list as $index => $tr)

		{

			$id = null;

			if (isset($tr[$this->identifier]))

				$id = $tr[$this->identifier];

			$name = isset($tr['name']) ? $tr['name'] : null;



			if ($this->shopLinkType)

				$this->_list[$index]['short_shop_name'] = Tools::strlen($tr['shop_name']) > 15 ? Tools::substr($tr['shop_name'], 0, 15).'...' : $tr['shop_name'];



			$is_first = true;

			// Check all available actions to add to the current list row

			foreach ($this->actions as $action)

			{

				//Check if the action is available for the current row

				if (!array_key_exists($action, $this->list_skip_actions) || !in_array($id, $this->list_skip_actions[$action]))

				{

					$method_name = 'display'.ucfirst($action).'Link';



					if (method_exists($this->context->controller, $method_name))

						$this->_list[$index][$action] = $this->context->controller->$method_name($this->token, $id, $name);

					elseif ($this->module instanceof Module && method_exists($this->module, $method_name))

						$this->_list[$index][$action] = $this->module->$method_name($this->token, $id, $name);

					elseif (method_exists($this, $method_name))

						$this->_list[$index][$action] = $this->$method_name($this->token, $id, $name);

				}



				if ($is_first && isset($this->_list[$index][$action])) {

					$is_first = false;



					if (!preg_match('/a\s*.*class/', $this->_list[$index][$action]))

						$this->_list[$index][$action] = preg_replace('/href\s*=\s*\"([^\"]*)\"/',

							'href="$1" class="btn btn-default"', $this->_list[$index][$action]);

					elseif (!preg_match('/a\s*.*class\s*=\s*\".*btn.*\"/', $this->_list[$index][$action]))

						$this->_list[$index][$action] = preg_replace('/a(\s*.*)class\s*=\s*\"(.*)\"/',

							'a $1 class="$2 btn btn-default"', $this->_list[$index][$action]);

				}

			}



			// @todo skip action for bulk actions

			// $this->_list[$index]['has_bulk_actions'] = true;

			foreach ($this->fields_list as $key => $params)

			{

				$tmp = explode('!', $key);

				$key = isset($tmp[1]) ? $tmp[1] : $tmp[0];



				if (isset($params['active']))

				{

					// If method is defined in calling controller, use it instead of the Helper method

					if (method_exists($this->context->controller, 'displayEnableLink'))

						$calling_obj = $this->context->controller;

					elseif (isset($this->module) && method_exists($this->module, 'displayEnableLink'))

						$calling_obj = $this->module;

					else

						$calling_obj = $this;



					if (!isset($params['ajax']))

						$params['ajax'] = false;

					$this->_list[$index][$key] = $calling_obj->displayEnableLink(

						$this->token,

						$id,

						$tr[$key],

						$params['active'],

						Tools::getValue('id_category'),

						Tools::getValue('id_product'),

						$params['ajax']

					);

				}

				elseif (isset($params['activeVisu']))

					$this->_list[$index][$key] = (bool)$tr[$key];

				elseif (isset($params['position']))

				{

					$this->_list[$index][$key] = array(

						'position' => $tr[$key],

						'position_url_down' => $this->currentIndex.

							(isset($key_to_get) ? '&'.$key_to_get.'='.(int)$position_group_identifier : '').

							'&'.$this->position_identifier.'='.$id.

							'&way=1&position='.((int)$tr['position'] + 1).'&token='.$this->token,

						'position_url_up' => $this->currentIndex.

							(isset($key_to_get) ? '&'.$key_to_get.'='.(int)$position_group_identifier : '').

							'&'.$this->position_identifier.'='.$id.

							'&way=0&position='.((int)$tr['position'] - 1).'&token='.$this->token

					);

				}

				elseif (isset($params['image']))

				{

					// item_id is the product id in a product image context, else it is the image id.

					$item_id = isset($params['image_id']) ? $tr[$params['image_id']] : $id;

					if ($params['image'] != 'p' || Configuration::get('PS_LEGACY_IMAGES'))

						$path_to_image = _PS_IMG_DIR_.$params['image'].'/'.$item_id.(isset($tr['id_image']) ? '-'.(int)$tr['id_image'] : '').'.'.$this->imageType;

					else

						$path_to_image = _PS_IMG_DIR_.$params['image'].'/'.Image::getImgFolderStatic($tr['id_image']).(int)$tr['id_image'].'.'.$this->imageType;

					$this->_list[$index][$key] = ImageManager::thumbnail($path_to_image, $this->table.'_mini_'.$item_id.'_'.$this->context->shop->id.'.'.$this->imageType, 145, $this->imageType);

				}

				elseif (isset($params['icon']) && isset($tr[$key]) && (isset($params['icon'][$tr[$key]]) || isset($params['icon']['default'])))

				{

					if (!$this->bootstrap)

					{

						if (isset($params['icon'][$tr[$key]]) && is_array($params['icon'][$tr[$key]]))

							$this->_list[$index][$key] = array(

								'src' => $params['icon'][$tr[$key]]['src'],

								'alt' => $params['icon'][$tr[$key]]['alt'],

							);

						else

							$this->_list[$index][$key] = array(

								'src' =>  isset($params['icon'][$tr[$key]]) ? $params['icon'][$tr[$key]] : $params['icon']['default'],

								'alt' =>  isset($params['icon'][$tr[$key]]) ? $params['icon'][$tr[$key]] : $params['icon']['default'],

							);

					}

					else

						if (isset($params['icon'][$tr[$key]]))

							$this->_list[$index][$key] = $params['icon'][$tr[$key]];

				}

				elseif (isset($params['type']) && $params['type'] == 'float')

					$this->_list[$index][$key] = rtrim(rtrim($tr[$key], '0'), '.');

				elseif (isset($tr[$key]))

				{

					$echo = $tr[$key];

					if (isset($params['callback']))

					{

						$callback_obj = (isset($params['callback_object'])) ? $params['callback_object'] : $this->context->controller;

						$this->_list[$index][$key] = call_user_func_array(array($callback_obj, $params['callback']), array($echo, $tr));

					}

					else

						$this->_list[$index][$key] = $echo;

				}

			}

		}



		$this->content_tpl->assign(array_merge($this->tpl_vars, array(

			'shop_link_type' => $this->shopLinkType,

			'name' => isset($name) ? $name : null,

			'position_identifier' => $this->position_identifier,

			'identifier' => $this->identifier,

			'table' => $this->table,

			'token' => $this->token,

			'color_on_bg' => $this->colorOnBackground,

			'position_group_identifier' => isset($position_group_identifier) ? $position_group_identifier : false,

			'bulk_actions' => $this->bulk_actions,

			'positions' => isset($positions) ? $positions : null,

			'order_by' => $this->orderBy,

			'order_way' => $this->orderWay,

			'is_cms' => $this->is_cms,

			'fields_display' => $this->fields_list,

			'list' => $this->_list,

			'actions' => $this->actions,

			'no_link' => $this->no_link,

			'current_index' => $this->currentIndex,

			'view' => in_array('view', $this->actions),

			'edit' => in_array('edit', $this->actions),

			'has_actions' => !empty($this->actions),

			'list_skip_actions' => $this->list_skip_actions,

			'row_hover' => $this->row_hover,

			'list_id' => isset($this->list_id) ? $this->list_id : $this->table,

			'checked_boxes' => Tools::getValue((isset($this->list_id) ? $this->list_id : $this->table).'Box')

		)));

		return $this->content_tpl->fetch();

	}

}

