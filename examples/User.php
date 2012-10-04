<?php

/**
 * Model of Users
 * 
 * @author Diego
 */
class User extends SimpleORM {
	protected static $table_name = 'user';

	protected static $validates_presence_of = array(array('first_name', 'name' => 'Owner Name', 'message' => 'you need fill this field'),
													array('last_name'),
													array('email', 'name' => 'E-Mail'),);

	protected static $fields = array('first_name', 'last_name', 'email');

	/**
	 * Function with your business rules
	 */
	protected function validate() {
		/*check if email is valid*/ 
		if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
			$this->errors->add('E-Mail', 'inválid email');
		}

		/*check if have anoter record with same email */
		$o = self::findOneBy('email', $this->email);
		if ($o && $o->id != $this->id) {
			$this->errors->add('E-Mail', 'email was found in db');
		}
	}
}

