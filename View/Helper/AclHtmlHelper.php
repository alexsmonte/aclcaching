<?php 

App::uses('AppHelper', 'View/Helper');

class AclHtmlHelper extends AppHelper{
	
	public $helpers = array('Session', 'Html');
	

	/**
	 * Has Permission
	 *
	 * Verifica permissão para a URL informada
	 *
	 * @param $url Pode ser uma string ou um array
	 * @return boolean
	 * @access
	 */
	function _hasPermission($url)
	{
	
		// Se não houver permissões, libera
		if ( !($this->Session->check('Auth.User.Permissions')) )
		{
			return true;
		}
	
		if (!is_array($url)) {
			return true;
		}
		 
		extract($url);
	
		if(isset($plugin))
		{
			$plugin = Inflector::camelize($plugin);
		}
	
		if (!isset($controller))
		{
			$controller = $this->params['controller'];
		}
		$controller = Inflector::camelize($controller);
	
		if (!isset($action))
		{
			$action = $this->params['action'];
		}
	
		if (isset($this->params['prefix']))
		{
			$action = $this->params['prefix'] . '_' . $action;
		}
		 
		if(isset($plugin) and !empty($plugin)) {
			$controller = $plugin.'/'.$controller;
		}
	
		$permission = 'controllers/'.$controller.'/'.$action;
		
		

		return in_array($permission, $this->Session->read('Auth.User.Permissions'));
		 
	}
	
	
	/**
	 * Link
	 *
	 * Método personalizado que antes de retornar o código HTML
	 * verificando se usuário possuí permissão para acessar
	 *
	 * @param string $title
	 * @param mixed $url
	 * @param array $options
	 * @param string $confirmMessage
	 * @return string
	 * @access public
	 * @link http://book.cakephp.org/view/1442/link
	 */
	function link($title, $url = null, $options = array(), $confirmMessage = false)
	{

		// Verifica permissao
		$permissao = $this->_hasPermission($url);
	
		if (isset($options['extern']))
		{
			$permissao = true;
		}
	
	
		if ($permissao)
		{
			return $this->Html->link( $title, $url, $options, $confirmMessage);
		}
		else
		{
			if (isset($options['show']))
			{
				return $title;
			}
		}
	
	}
	
	
}

?>