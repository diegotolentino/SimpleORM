# SimpleORM #

by Diego Tolentino

=========
This is a conceptual design and is no longer maintained.
=========

Simple ORM is a small class to implement a Active Record Design Pattern

## Introduction ##
A brief summarization of what ActiveRecord is:

> Active record is an approach to access data in a database. A database table or view is wrapped into a class,
> thus an object instance is tied to a single row in the table. After creation of an object, a new row is added to
> the table upon save. Any object loaded gets its information from the database; when an object is updated, the
> corresponding row in the table is also updated. The wrapper class implements accessor methods or properties for
> each column in the table or view.

More details can be found [here](http://en.wikipedia.org/wiki/Active_record_pattern).


This implementation is inspired in http://www.phpactiverecord.org and http://redbeanphp.com 

## Minimum Requirements ##

- PHP 5.3+
- PDO driver for your respective database

# Features ##

- Finder by pk and full fields
- Validations

### Installation ##

1. Setup file with your database config.
2. Include de SimpleORM.php
3. Include de file(s) with your Model class
3. Enjoy

Example:

    # if display_errors are true, the SimpleORM will display debug info
    ini_set('display_errors', true);
     
    define('db_host', 'localhost');
    define('db_name', 'dbname');
    define('db_user', 'user');
    define('db_pass', 'pass');

## Basic CRUD ##

### Search ###
    # Get user by pk
    $user = User::find(1);

    # Get one user by email
    $user = User::findOneBy('email', 'teste@teste.com');
  
    # Get array of first 10 users by gender order by id
    $aUsers = User::findAllBy('gender', 'male', 10, 'id');

### Create ###
You only need fill the fields and call save() method.

    $user = new User();
    $user->email = 'teste@teste.com';
    $user->first_name = 'Diego';
    $user->last_name = 'Tolentino';
    $user->save();
    # INSERT INTO `user` set email = 'teste@teste.com', first_name = 'Diego', last_name = 'Tolentino';

Or using the shortcut

    $user = new User(array('email'=>'teste@teste.com', 'first_name'=>'Diego' 'last_name'=>'Tolentino'));
    $user->save();

### Update ###
To update you would just need to find a record first and change what you need and call save().

    $user = User::find(1);
    $user->last_name = 'Silva';
    $user->save();
  
### Delete ###
To delte you would just need to find a record and call delete().

    $user = User::find(1);
    $user->delete();


## Validation ##
### Required fields ###
Define all required fields in $validates_presence_of to get a default required validation

    class User extends SimpleORM {
      protected static $validates_presence_of = array(
        #field name required (will be show as "Last Name")
        array('last_name'),
  
        //plus affordable name to show on mensage
        array('email', 'name'=>'E-Mail'),
  
        //affordable mensage to show on require error
        array('first_name', 'message' => 'incorrect fill for "First Name"')
      );
    }
    
### Advanced validations ###
Define the validate() method

    class User extends SimpleORM {
      protected function validate() {
        /*check if email is valid*/ 
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
          $this->errors->add('E-Mail', 'invalid email');
        }
        
        /*check if have anoter record with same email */
        $o = self::findOneBy('email', $this->email);
        if ($o && $o->id != $this->id) {
          $this->errors->add('E-Mail', 'email was found in db');
        }
      }
    }

### Validations and business rules ###
You can check before if your objects are correct filled

    $o = new User(array('first_name' => 'Mary', 'last_name' => 'Colyn'));
    if ($o->isValid()) {
      $o->save();
    } else {
      echo "Some errors are found: ";
      echo join('<br>', $o->errors->full_messages());
    }

If you call save() method on object with values not ok with the rules business you will get a exception:

    $o = new User();
    $o->email = 'teste@teste.com';
    try {
      $o->save();
    } catch (Exception $e) {
      echo "Errors: <ul><li>" . str_replace("\n", '</li><li>', $e->getMessage()) . '</li></ul>';
    }
