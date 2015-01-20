<?php

App::uses('BaseAuthorize', 'Controller/Component/Auth');


class AclCachingAuthorize extends BaseAuthorize {

	
	public function authorize($user, CakeRequest $request) {
		//print_r($_SESSION["Auth"]);
		$AclCaching	=	$this->_Collection->load('AclCaching');

		return $AclCaching->check(null, $request->params);
	}

}


?>