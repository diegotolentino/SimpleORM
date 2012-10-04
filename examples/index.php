<?php
header('Content-Type: text/html; charset=utf-8');

require_once 'config.php';
require_once '../SimpleORM.php';
require_once 'User.php';

/**
 * Insert
 */ 
echo '<h1>Insert</h1>';
$o = new User(array('first_name' => 'Mary', 'last_name' => 'Colyn'));
$o->email = 'teste@teste.com';

if ($o->isValid()) {
	$o->save();
	var_dump($o);
	$pk = $o->id;
} else {
	echo "Some errors are found: ";
	echo join('<br>', $o->errors->full_messages());
}

/**
 * Select and Update using pk
 */
echo '<h1>Select and Update using pk</h1>';
$o = User::find($pk);
$o->last_name = 'Tolentino ' . date('H:i.s');
$o->save();
var_dump($o);

/**
 * Select by email and Delete
 */
echo '<h1>Select by email and Delete</h1>';
$o = User::findOneBy('email', 'teste@teste.com');
var_dump($o);
$o->delete();
