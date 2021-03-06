<?php
App::uses('Component', 'Controller');
//App::uses('Component', 'Acl');
//App::uses('Component', 'Session');
App::uses('Folder', 'Utility');

class AclCachingComponent extends Component{
		
		public $components	=	array('Acl', 'Session');

		/**
		 * Configurações do Componente
		 *
		 * @var array
		 * @access private
		 */
		private $_options = array(
				'use' => array(
						'contain' => false
				),
				'aro' => array(
						'model'        => 'Grupo',
						'primaryKey'   => 'id',
						'displayField' => 'nome',
						'foreignKey'   => 'grupo_id'
				),
				'aco' => array(
						'model' => 'Aco',
						'field' => 'alias'
				)
		);
		 
		/**
		 * Libera todas as actions
		 *
		 * @var boolean
		 * @access private
		*/
		private $_forceAllow = false;
		 
		/**
		 * Contém as permissões definidas pelo ACL
		 *
		 * @var array
		 * @access private
		 */
		private $_aclPermissions = array();
		
		/**
		 * Contém as todas as permissões
		 *
		 * @var array
		 * @access private
		*/
		private $_allPermissions = array();
		
		public function __construct(ComponentCollection $collection, $settings = array()){

			$this->_options = array_merge($this->_options, $settings);

		}
		
		public function initialize(Controller $controller){
			$this->_Controller = $controller;
			// Para continuar a utiliza normalmente Acl

			$this->Acl = $this;
		}
		
		public function startup(Controller $controller){
		
		}
		
		
		public function beforeRender(Controller $controller){
			$controller->set('AclCaching', $this);
		
			// Para continuar a utiliza normalmente Acl
			$controller->set('Acl', $this);
		}
		
		public function shutdown(Controller $controller){
		
		}
		
		public function beforeRedirect(Controller $controller, $url, $status=null, $exit=true){
		
			if (isset($controller->Auth))
			{
				//$authLogout = (is_array($controller->Auth->logoutRedirect)) ? Router::url($controller->Auth->logoutRedirect) : $controller->Auth->logoutRedirect;
				//$r = (is_array($url)) ? Router::url($url) : $url;

				$authLogout = Router::url($controller->Auth->logoutRedirect);

				$r = Router::url($url);
				//$r = Router::url($controller->Auth->logoutRedirect);

				if ($authLogout == $r)
				{
					$this->flushCache();
				}
				
			}


			return $url;
		}
		
		
		public function deny($aro, $aco, $actions = "*"){
			$perms = $this->getAclLink($aro, $aco);
			$permKeys = $this->_getAcoKeys($this->Aro->Permission->schema());
			$save = array();
		
			if ($perms == false){
				trigger_error(__('DbAcl::allow() - Invalid node', true), E_USER_WARNING);
				return false;
			}
			
			if (isset($perms[0])){
				$save = $perms[0][$this->Aro->Permission->alias];
			}
		
			$permKeys = $this->_getAcoKeys($this->Aro->Permission->schema());
			$save = array_combine($permKeys, array_pad(array(), count($permKeys), -1));
		
			list($save['aro_id'], $save['aco_id']) = array($perms['aro'], $perms['aco']);
		
			if ($perms['link'] != null && !empty($perms['link'])) {
				$save['id'] = $perms['link'][0][$this->Aro->Permission->alias]['id'];
			} else {
				return false;
			}
		
			return $this->Aro->Permission->delete($save['id']);
		}
		
		
		/**
		 * Libera acesso a todas as actions
		 *
		 * @access public
		 */
		public function forceAllow(){
			$this->_Controller->Auth->allow('*');
			$this->_forceAllow = true;
		}
		
		
		
		/**
		 * get Acl Link
		 *
		 * @param string $aro
		 * @param string $aco
		 * @return array
		 * @access public
		 */
		function getAclLink($aro, $aco){
			
			$this->Aco = ClassRegistry::init('Aco');
			$this->Aro = ClassRegistry::init('Aro');
			
			$obj = array();
			$obj['Aro'] = $this->Aro->node($aro);
			$obj['Aco'] = $this->Aco->node($aco);
		
			if (empty($obj['Aro']) || empty($obj['Aco']))
			{
				return false;
			}
		
			return array(
					'aro' => Set::extract($obj, 'Aro.0.'.$this->Aro->alias.'.id'),
					'aco'  => Set::extract($obj, 'Aco.0.'.$this->Aco->alias.'.id'),
					'link' => $this->Aro->Permission->find('all', array('conditions' => array(
							$this->Aro->Permission->alias . '.aro_id' => Set::extract($obj, 'Aro.0.'.$this->Aro->alias.'.id'),
							$this->Aro->Permission->alias . '.aco_id' => Set::extract($obj, 'Aco.0.'.$this->Aco->alias.'.id')
					)))
			);
		}
		
		
		/**
		 * Get Aco Keys
		 *
		 * @param array $keys
		 * @return array
		 * @access protected
		 */
		function _getAcoKeys($keys)
		{
			$newKeys = array();
			$keys = array_keys($keys);
			foreach ($keys as $key)
			{
				if (!in_array($key, array('id', 'aro_id', 'aco_id')))
				{
					$newKeys[] = $key;
				}
			}
			return $newKeys;
		}
		
		
		/**
		 * Create Cache
		 *
		 * Cria variável de sessão com as permissões do ARO configurado em self::_options
		 *
		 * @access protected
		 */
		function createCache()
		{
		

			$this->Aco = ClassRegistry::init('Aco');
			$this->Aro = ClassRegistry::init('Aro');
			
			$acos = $this->Aco->find('threaded');
		
			$settings = array();
			if ($this->_options['use']['contain'])
			{
				$settings['contain'] = array('Aco');
			}
			else
			{
				$settings['recursive'] = 1;
			}
		
			$settings['conditions'] = array(
					'Aro.model'       => $this->_options['aro']['model'],
					'Aro.foreign_key' => $this->_Controller->Auth->user($this->_options['aro']['foreignKey']),
			);
		

			$grupo_aro = $this->Aro->find('threaded', $settings);
			$grupo_permissoes = Set::extract('{n}.Aco', $grupo_aro);
		
		
			$gpAco = array();
			
			if (!empty($grupo_permissoes)){
				foreach($grupo_permissoes[0] as $value) {
					$gpAco[$value['id']] = $value;
				}
			}
			$this->_aclPermissions = $gpAco;
		
			$this->_addPermissions($acos, $this->_options['aco']['model'], $this->_options['aco']['field'], '');
				
			$this->_Controller->Session->write('Auth.User.Permissions', $this->_allPermissions);
		}
		
		
		/**
		 * Flush Cache
		 *
		 * Apaga todas as regras de permissões da variável de sessão
		 * bruno.arrudamenca@gmail.b
		 * @access public
		 */
		function flushCache() {
			//$this->Session->delete('Auth.Permissions');
		}
		
		
		/**
		 * Add Permissions
		 *
		 * @param string $acos
		 * @param string $modelName
		 * @param string $fieldName
		 * @param string $alias
		 * @access protected
		 */
		function _addPermissions($acos, $modelName, $fieldName, $alias)
		{
		
			foreach ($acos as $key => $val)
			{
				$thisAlias = $alias . $val[$modelName][$fieldName];
		
				if(isset($this->_aclPermissions[$val[$modelName]['id']]))
				{
					$this->_allPermissions[] = $thisAlias;
		
					if(isset($val['children']))
					{
						$newAlias = $thisAlias . '/';
						$this->_addChildrenPermissions($val['children'], $modelName, $fieldName, $newAlias);
					}
				}
		
		
				if(isset($val['children'][0]))
				{
					$newAlias = $thisAlias . '/';
					$this->_addPermissions($val['children'], $modelName, $fieldName, $newAlias);
				}
		
			}
		
		
			return;
		}
		
		
		/**
		 * Add Children Permissions
		 *
		 * @param string $acos
		 * @param string $modelName
		 * @param string $fieldName
		 * @param string $alias
		 * @access protected
		 */
		function _addChildrenPermissions($acos, $modelName, $fieldName, $alias)
		{
		
			foreach ($acos as $key => $val)
			{
				$thisAlias = $alias . $val[$modelName][$fieldName];
				$this->_allPermissions[] = $thisAlias;
		
				if(isset($val['children']))
				{
					$newAlias = $thisAlias . '/';
					$this->_addChildrenPermissions($val['children'], $modelName, $fieldName, $newAlias);
				}
			}
		
			return;
		}
		
		
		/**
		 * Check
		 *
		 * @param array $aro ARO
		 * @param array $aco ACO
		 * @param string $action Action
		 * @return boolean
		 * @access public
		 */
		function check($aro, $aco, $action = "*")
		{
			
			if ($this->_forceAllow)
			{
				return true;
			}
		
		
			if (isset($this->_Controller->Auth))
			{
				if ( !($this->_Controller->Auth->user()) )
				{
					return true;
				}
			}
		
			if(!(is_array($this->_Controller->Session->read('Auth.User.Permissions'))))
			{
				$this->createCache();
			}
		
		
			if (is_array($aco))
			{
				$controller = (isset($aco['controller'])) ? Inflector::camelize($aco['controller']) : $this->_Controller->name;
				$action = (isset($aco['action'])) ? $aco['action'] : $aco[0];
				$aco = (isset($aco['plugin'])) ? 'controllers/' . Inflector::camelize($aco['plugin']) . '/' . $controller . '/' . $action : 'controllers/' . $controller . '/' . $action;
			}
		
		
			if ((strpos($aco, '/')) === false)
			{
				$aco = $this->_Controller->name . '/' . $aco;
			}
		
			if ((strpos($aco, 'controllers')) === false)
			{
				$aco = 'controllers/' . $aco;
			}
		
		
			return in_array($aco, $this->_Controller->Session->read('Auth.User.Permissions'));
		}
		
		
		/**
		 * Check DB
		 *
		 * Verifica a permissão diretamente no banco de dados
		 *
		 * @param array $aro ARO
		 * @param array $aco ACO
		 * @param string $action Action
		 * @return boolean
		 * @access public
		 */
		public function checkDB($aro, $aco, $action = "*")
		{
			return $this->Acl->check($aro, $aco);
		}
		
		
		/**
		 * Check If All
		 *
		 * Este é um método especial.
		 * Ele retorna 'true' se todas as entradas de permissões forem satisfeitas
		 *
		 * @param array $aro ARO
		 * @param array $pairs
		 * @return boolean
		 * @access public
		 */
		public function checkIfAll($aro, $pairs)
		{
			if ($this->_forceAllow)
			{
				return true;
			}
		
			foreach ($pairs as $pair)
			{
				$aco = array();
				if (isset($pair['plugin']))
				{
					$aco['plugin'] = $pair['plugin'];
				}
				if (isset($pair['controller']))
				{
					$aco['controller'] = $pair['controller'];
				}
				$aco['action'] = $pair['action'];
		
				if (!$this->check($aro, $aco))
				{
					return false;
				}
			}
			return true;
		}
		
		
		/**
		 * Check If One
		 *
		 * Este é um método especial.
		 * Ele retorna 'true' se apenas UMA entrada de permissões for satisfeita
		 *
		 * @param array $aro ARO
		 * @param array $pairs
		 * @return boolean
		 * @access public
		 */
		function checkIfOne($aro, $pairs)
		{
		
			if ($this->_forceAllow)
			{
				return true;
			}
		
			foreach ($pairs as $pair)
			{
				$aco = array();
				if (isset($pair['plugin']))
				{
					$aco['plugin'] = $pair['plugin'];
				}
				if (isset($pair['controller']))
				{
					$aco['controller'] = $pair['controller'];
				}
				$aco['action'] = $pair['action'];
		
				if ($this->check($aro, $aco))
				{
					return true;
				}
			}
			return false;
		}
		
		
		/**
		 * Get Aro Model
		 *
		 * @return string
		 * @access public
		 */
		function getAroModel()
		{
			return $this->_options['aro']['model'];
		}
		
		
		/**
		 * Get Aro Primary Key
		 *
		 * @return string
		 * @access public
		 */
		function getAroPrimaryKey()
		{
			return $this->_options['aro']['primaryKey'];
		}
		
		
		/**
		 * Get Aro Display Field
		 *
		 * @return string
		 * @access public
		 */
		function getAroDisplayField()
		{
			return $this->_options['aro']['displayField'];
		}
		
		
		/**
		 * Get Passed Aco Path
		 *
		 * @return string
		 * @access public
		 */
		function getPassedAcoPath()
		{
			$aco_path  = isset($this->_Controller->params['named']['plugin']) ? $this->_Controller->params['named']['plugin'] : '';
			$aco_path .= empty($aco_path) ? $this->_Controller->params['named']['controller'] : '/' . $this->_Controller->params['named']['controller'];
			$aco_path .= '/' . $this->_Controller->params['named']['action'];
		
			return $aco_path;
		}
		
		
		/**
		 * Set Aco Variables
		 *
		 * @access public
		 */
		function setAcoVariables()
		{
			$this->_Controller->set('plugin', isset($this->_Controller->params['named']['plugin']) ? $this->_Controller->params['named']['plugin'] : '');
			$this->_Controller->set('controller_name', $this->_Controller->params['named']['controller']);
			$this->_Controller->set('action', $this->_Controller->params['named']['action']);
		}
		
		
		/**
		 * Get Plugin Name
		 *
		 * @param string $ctrlName
		 * @return string
		 * @access public
		 */
		function getPluginName($ctrlName = null)
		{
			$arr = String::tokenize($ctrlName, '/');
			if (count($arr) == 2)
			{
				return $arr[0];
			}
			else
			{
				return false;
			}
		}
		
		
		/**
		 * Get Plugin Controller Name
		 *
		 * @param string $ctrlName
		 * @return string
		 * @access public
		 */
		public function getPluginControllerName($ctrlName = null)
		{
			$arr = String::tokenize($ctrlName, '/');
			if (count($arr) == 2)
			{
				return $arr[1];
			}
			else
			{
				return false;
			}
		}
		
		
		/**
		 * Get Controller Classname
		 *
		 * @param string $controller_name
		 * @return string
		 * @access public
		 */
		public function get_controller_classname($controller_name)
		{
			if(strrpos($controller_name, 'Controller') !== strlen($controller_name) - strlen('Controller'))
			{
				if(stripos($controller_name, '/') === false)
				{
					$controller_classname = $controller_name . 'Controller';
				}
				else
				{
					$controller_classname = substr($controller_name, strripos($controller_name, '/') + 1) . 'Controller';
				}
				return $controller_classname;
			}
			else
			{
				return $controller_name;
			}
		}
		
		
		/**
		 * Get All Plugins Paths
		 *
		 * @return array
		 * @access public
		 */
		public function get_all_plugins_paths()
		{
			$plugin_names = array();
			$plugin_paths = App::path('plugins');
			$folder       =& new Folder();
		
			foreach($plugin_paths as $plugin_path)
			{
				$folder->cd($plugin_path);
				$app_plugins = $folder->read();
				foreach($app_plugins[0] as $plugin_name)
				{
					$plugin_names[] = $plugin_path . $plugin_name;
				}
			}
		
			return $plugin_names;
		}
		
		
		/**
		 * Get All Plugins Names
		 *
		 * @return array
		 * @access public
		 */
		public function get_all_plugins_names()
		{
			$plugin_names = array();
		
			$folder =& new Folder();
		
			$folder->cd(APP . 'plugins');
			$app_plugins = $folder->read();
			if(!empty($app_plugins))
			{
				$plugin_names = array_merge($plugin_names, $app_plugins[0]);
			}
		
			$folder->cd(ROOT . DS . 'plugins');
			$root_plugins = $folder->read();
			if(!empty($root_plugins))
			{
				$plugin_names = array_merge($plugin_names, $root_plugins[0]);
			}
		
			return $plugin_names;
		}
		
		
		/**
		 * Get All Plugins Controllers
		 *
		 * @return array
		 * @access public
		 */
		public function get_all_plugins_controllers($filter_default_controller = true)
		{
			$plugin_paths = $this->get_all_plugins_paths();
		
			$plugins_controllers = array();
			$folder =& new Folder();
		
			// Loop through the plugins
			foreach($plugin_paths as $plugin_path)
			{
				$didCD = $folder->cd($plugin_path . DS . 'Controller');
		
				if(!empty($didCD))
				{
					$files = $folder->findRecursive('.*Controller\.php');
		
					$plugin_name = substr($plugin_path, strrpos($plugin_path, DS) + 1);
		
					foreach($files as $fileName)
					{
						$file = basename($fileName);
		
						// Get the controller name
						$controller_class_name = Inflector::camelize(substr($file, 0, strlen($file) - strlen('Controller.php')));
		
						if(!$filter_default_controller || Inflector::camelize($plugin_name) != $controller_class_name)
						{
							if (!preg_match('/^'. Inflector::camelize($plugin_name) . 'App/', $controller_class_name))
							{
								if (!App::import('Controller', $plugin_name . '.' . $controller_class_name))
								{
									debug('Error importing ' . $controller_class_name . ' for plugin ' . $plugin_name);
								}
								else
								{
									$plugins_controllers[] = array('file' => $fileName, 'name' => Inflector::camelize($plugin_name) . "/" . $controller_class_name);
								}
							}
						}
					}
				}
			}
		
			sort($plugins_controllers);
		
			return $plugins_controllers;
		}
		
		
		/**
		 * Get All Plugins Controllers Actions
		 *
		 * @return array
		 * @access public
		 */
		public function get_all_plugins_controllers_actions($filter_default_controller = true)
		{
			$plugin_controllers = $this->get_all_plugins_controllers();
		
			$plugin_controllers_actions = array();
		
			foreach($plugin_controllers as $plugin_controller)
			{
				$plugin_name     = $this->getPluginName($plugin_controller['name']);
				$controller_name = $this->getPluginControllerName($plugin_controller['name']);
		
				if(!$filter_default_controller || $plugin_name != $controller_name)
				{
					$controller_class_name = $controller_name . 'Controller';
		
					$ctrl_cleaned_methods = $this->get_controller_actions($controller_class_name);
		
					foreach($ctrl_cleaned_methods as $action)
					{
						$plugin_controllers_actions[] = $plugin_name . '/' . $controller_name . '/' . $action;
					}
				}
			}
		
			sort($plugin_controllers_actions);
		
			return $plugin_controllers_actions;
		}
		
		
		/**
		 * Get All App Controllers
		 *
		 * @return array
		 * @access public
		 */
		public function get_all_app_controllers()
		{
			$controllers = array();
			$folder =& new Folder();
		
			$didCD = $folder->cd(APP . 'Controller');
			if(!empty($didCD))
			{
				$files = $folder->findRecursive('.*Controller\.php');
		
				foreach($files as $fileName)
				{
					$file = basename($fileName);
		
					// Get the controller name
					$controller_class_name = Inflector::camelize(substr($file, 0, strlen($file) - strlen('Controller.php')));
		
					if (!App::import('Controller', $controller_class_name))
					{
						debug('Error importing ' . $controller_class_name . ' from APP controllers');
					}
					else
					{
						$controllers[] = array('file' => $fileName, 'name' => $controller_class_name);
					}
				}
			}
		
			sort($controllers);
		
			return $controllers;
		}
		
		
		/**
		 * Get All APP Controllers Actions
		 *
		 * @return array
		 * @access public
		 */
		public function get_all_app_controllers_actions()
		{
			$controllers = $this->get_all_app_controllers();
		
			$controllers_actions = array();
		
			foreach($controllers as $controller)
			{
				$controller_class_name = $controller['name'] . 'Controller';
		
				$ctrl_cleaned_methods = $this->get_controller_actions($controller_class_name);
		
				foreach($ctrl_cleaned_methods as $action)
				{
					$controllers_actions[] = $controller['name'] . '/' . $action;
				}
			}
		
			sort($controllers_actions);
		
			return $controllers_actions;
		}
		
		
		/**
		 * Get All Controllers
		 *
		 * @return array
		 * @access public
		 */
		public function get_all_controllers()
		{
			$app_controllers    = $this->get_all_app_controllers();
			$plugin_controllers = $this->get_all_plugins_controllers();
		
			return array_merge($app_controllers, $plugin_controllers);
		}
		
		
		/**
		 * Get All Actions
		 *
		 * @return array
		 * @access public
		 */
		public function get_all_actions()
		{
			$app_controllers_actions     = $this->get_all_app_controllers_actions();
			$plugins_controllers_actions = $this->get_all_plugins_controllers_actions();
		
			return array_merge($app_controllers_actions, $plugins_controllers_actions);
		}
		
		
		/**
		 * Get Controller Actions
		 *
		 * @param string $controller_classname
		 * @param boolean $filter_base_methods
		 * @return array
		 * @access public
		 */
		public function get_controller_actions($controller_classname, $filter_base_methods = true)
		{
			$controller_classname = $this->get_controller_classname($controller_classname);
			$methods = get_class_methods($controller_classname);
		
			if(isset($methods) && !empty($methods))
			{
				if($filter_base_methods)
				{
					$baseMethods = get_class_methods('Controller');
		
					$ctrl_cleaned_methods = array();
					foreach($methods as $method)
					{
						if(!in_array($method, $baseMethods) && strpos($method, '_') !== 0)
						{
							$ctrl_cleaned_methods[] = $method;
						}
					}
		
					return $ctrl_cleaned_methods;
				}
				else
				{
					return $methods;
				}
			}
			else
			{
				return array();
			}
		}
		
		
	}
?>