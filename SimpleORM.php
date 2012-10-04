<?php
if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50300)
	die('Requires PHP 5.3 or higher');

/**
 * Simple ORM
 * 
 *  Is a small class to implement a Active Record Design Pattern
 *  
 *  Minimum requirements
 *  - PHP 5.3+
 *  - PDO drivers for the database you are using
 * 
 * @author Diego Tolentino <diegotolentino@gmail.com>
 * @license FreeBSD
 */
abstract class SimpleORM {
	/**
	 * Connect object, implements the Singleton Design Pattern 
	 * @var PDO
	 */
	protected static $db = null;

	/**
	 * Data base table of the class
	 * 
	 * @var string
	 */
	protected static $table_name = null;

	/**
	 * Pk of the table, default 'id'
	 * 
	 * @var string
	 */
	protected static $table_pk = 'id';

	/**
	 * Fields of the table, excluding Pk
	 * Ex:
	 * <code>
	 * array('first_name', 'last_name');
	 * </code>
	 * 
	 * @var array
	 */
	protected static $fields = array();

	/**
	 * Fields to check if filled before DB::save() or when call DB::isValid()
	 * 
	 * <code>
	 * array(
	 *   //field name required (will be show as "Last Name")
	 *   array('last_name'),
	 *   
	 *   //plus affordable name to show on mensage
	 *   array('email', 'name'=>'E-Mail'),
	 *   
	 *   //affordable mensage to show on require error
	 *   array('first_name', 'message' => 'é obrigatório')
	 * );
	 * </code>
	 * 
	 * @link DB::save()
	 * @link DB::isValid()
	 * @var array
	 */
	protected static $validates_presence_of = array();

	/**
	 * Object to manage errors on bussines rules triggered by DB::save() and DB::isValid()
	 * 
	 * @link DB::save()
	 * @link DB::isValid()
	 * @var Errors
	 */
	public $errors = null;

	public function __construct($aDefaults = null) {
		/*set null for all fields*/
		foreach (static::$fields as $key) {
			$this->$key = null;
		}

		/*if have defaults, set the default value for fields*/
		if ($aDefaults) {
			foreach ($aDefaults as $key => $val) {
				$this->$key = $val;
			}
		}
		$this->errors = new Errors();
	}

	/**
	 * Display debug info
	 * 
	 * @param string $string
	 */
	static protected function debug($string) {
		/**
		 * check if show debug info
		 */
		if (ini_get('display_errors')) {
			$aux = debug_backtrace();
			$sClassFunc = $aux[1]['class'] . ':' . $aux[1]['function'];
			$sFileLine = substr(strrchr($aux[1]['file'], DIRECTORY_SEPARATOR), 1) . ':' . $aux[1]['line'];
			echo "<br><b>Debug - $sClassFunc ($sFileLine)</b><br><pre>\t" . wordwrap($string, 80) . '</pre><br>';
		}
	}

	/**
	 * DB::get_row() Seleciona um unico registro
	 * 
	 * @param $sql string - SQL
	 * @return array
	 */
	public static function findAllBy($field, $value, $limit = null, $orderBy = null) {
		$sql = 'select * from ' . static::$table_name . " where $field='$value' ";
		if ($limit)
			$sql .= "limit $limit ";
		if ($orderBy)
			$sql .= "order by $orderBy ";

		/*send debug info*/
		self::debug($sql);

		$aDados = self::findBySql($sql);
		$aResult = array();
		foreach ($aDados as $row) {
			$o = new static;
			foreach ($row as $key => $val) {
				$o->$key = $val;
			}
			$aResult[] = $o;
		}
		return $aResult;
	}

	/**
	 * Check if the record is valid and return bool, you can view the info using $o->errors->* functions
	 * 
	 * @return boolean
	 */
	public function isValid() {
		$this->errors->reset();

		/*validate required fields*/
		foreach (static::$validates_presence_of as $aValidate) {
			if (!isset($this->$aValidate[0]) || !$this->$aValidate[0]) {
				/*Define field name*/
				if (isset($aValidate['name'])) {
					$sField = $aValidate['name'];
				} else {
					$sField = ucwords(str_replace('_', ' ', $aValidate[0]));
				}

				/*Define mensage*/
				if (isset($aValidate['message'])) {
					$sMsg = $aValidate['message'];
				} else {
					$sMsg = 'is required';
				}

				$this->errors->add($sField, $sMsg);
			}
		}

		/*validate user rules*/
		$this->validate();

		return $this->errors->count() == 0;
	}

	/**
	 * DB::findBySql() Seleciona varios registros
	 * 
	 * @param $sql string - SQL
	 * @throws Exception
	 * @return array
	 */
	protected static function findBySql($sql) {
		/*run sql*/
		$oStatement = self::run($sql);

		/*fetch data*/
		$aReturn = array();
		while ($aRow = $oStatement->fetch(PDO::FETCH_ASSOC)) {
			$aReturn[] = $aRow;
		}

		return $aReturn;
	}

	/**
	 * Find record by id
	 * 
	 * @param integer|mixed $id
	 * @return static
	 */
	public static function find($id) {
		return static::findOneBy(static::$table_pk, $id);
	}

	/**
	 * Find record by $field=$value pair
	 *
	 * @param string $field
	 * @param mixed $value
	 * @return static
	 */
	public static function findOneBy($field, $value) {
		$aResult = static::findAllBy($field, $value, 1);
		return isset($aResult[0]) ? $aResult[0] : null;
	}

	/**
	 * Save the record
	 * 
	 * @throws Exception
	 */
	public function save() {
		/*if the record is not valid, throw exception*/
		if (!$this->isValid()) {
			throw new Exception(join("\n", $this->errors->full_messages()));
		}

		/*start build the field=value association*/
		$fields = '';
		foreach (static::$fields as $key) {
			$fields .= ($fields ? ', ' : '');
			if (isset($this->$key) && $this->$key !== null && $this->$key !== '') {
				$fields .= $key . ' = \'' . str_replace("'", "`", $this->$key) . '\'';
			} else {
				$fields .= $key . ' = null';
			}
		}

		/*check if the pk is defined*/
		if (isset($this->{static::$table_pk}) && $this->{static::$table_pk} != '') {
			$sql = 'UPDATE ' . static::$table_name;
			$sql .= ' SET ' . $fields;
			$sql .= ' WHERE ' . static::$table_pk . '="' . $this->{static::$table_pk} . '"';
		} else {
			$sql = 'INSERT INTO ' . static::$table_name . ' SET ' . $fields;
		}

		/*send debug info*/
		self::debug($sql);

		/*run sql*/
		self::run($sql);

		/*if not pk isset(new record)*/
		if (!isset($this->{self::$table_pk}) || !$this->{self::$table_pk}) {
			$this->{self::$table_pk} = self::$db->lastInsertId();
		}
		return $this->{self::$table_pk};
	}

	/**
	 * Delete de current row
	 * 
	 * @throws Exception
	 * @return number of affected rows
	 */
	public function delete() {
		if (!isset($this->{self::$table_pk}) || !$this->{self::$table_pk}) {
			throw new Exception(self::$table_pk . '.' . self::$table_pk . ' not is defined.');
		}

		$sql = 'DELETE FROM ' . static::$table_name . ' WHERE ' . static::$table_pk . '="' . $this->{static::$table_pk} . '"';

		/*send debug info*/
		self::debug($sql);

		/*run the statement*/
		$oStatement = self::run($sql);

		/*return the number of affected rows*/
		return $oStatement->rowCount();
	}

	/**
	 * Setup connection
	 * 
	 * @throws Exception
	 */
	protected static function setup() {
		try {
			$sConn = 'mysql:host=' . db_host . '; dbname=' . db_name;
			self::$db = new PDO($sConn, db_user, db_pass, array(PDO::ATTR_PERSISTENT => true));
		} catch (PDOException $oPDOEx) {
			throw new Exception($oPDOEx->getMessage());
		}
	}

	/**
	 * Run the sql and return the statement
	 * 
	 * @return PDOStatement
	 */
	protected static function run($sql) {
		/*if not have connection, setup it*/
		if (!self::$db) {
			self::setup();
		}

		/*prepare the statement*/
		$statement = self::$db->prepare($sql);

		/*execute the statement, throw when have error */
		if ($statement->execute() === false) {
			$aErroPDO = $statement->errorInfo();
			$sErroPDO = $aErroPDO[2] . ' - "' . $sql . '"';
			throw new Exception($sErroPDO);
		}

		return $statement;
	}

	/**
	 * Define this function with your business rules
	 */
	abstract protected function validate();
}

class Errors {
	protected $aErrors = null;

	function __construct() {
		$this->errors = array();
	}

	/**
	 * Add info to error array
	 * 
	 * @param string $field
	 * @param string $msg
	 */
	function add($field, $msg) {
		$this->aErrors[$field][] = $msg;
	}

	/**
	 * Return array with the full field+errors info
	 * 
	 * @return array
	 */
	function full_messages() {
		$sResult = array();
		if ($this->aErrors) {
			foreach ($this->aErrors as $field => $aErros) {
				foreach ($aErros as $sError) {
					$sResult[] = ucfirst($field) . ': ' . $sError;
				}
			}
		}
		return $sResult;
	}

	/**
	 * Reset the error info
	 */
	function reset() {
		$this->aErrors = array();
	}

	/**
	 * Return the count of errors
	 * 
	 * @return number
	 */
	function count() {
		return count($this->aErrors);
	}
}
