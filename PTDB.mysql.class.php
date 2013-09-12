<?php
require_once('PTDB.class.php');
class PTDBMySQL extends PTDB {
	static function getInstance($table_prefix='') {
		static $instance = NULL;
		if (is_null($instance))
			$instance = new PTDBMySQL($table_prefix);
		return $instance;
	}

	function connect($con, $user, $pass) {
		try {
			$test = explode(';', substr($con, strpos($con, ':')+1));
			$values = array();
			foreach ($test as $v) {
				list($k, $value) = explode('=', $v);
				$values[$k] = $value;
			}
			$this->_d = new mysqli($values['host'], $user, $pass, $values['dbname']);
			$this->_d->set_charset($values['charset']);
			$this->log('connected database to '.$con, 'info');
		} catch (Exception $e) {
			$this->log($e->getMessage());
		}
	}

	function close() {
		$this->_d->close();
		unset($this->_d);
	}

	function buildQuery($sql, $params) {
		if (is_array($params)) {
			$k = key($params);
			$index = 0;
			if (is_int($k)) {
				foreach ($params as $v) {
					$p = strpos($sql, '?', $index);
					if ($p === FALSE) {
						$this->log('parameter dont match. paramter count: '.count($params).', '.$sql, 'warn');
						return $sql;
					}
					if (is_null($v)) {
						$val = 'NULL';
					} else {
						$val = "'".$this->escape($v)."'";
					}
					$index = $p + strlen($val);
					$sql = substr($sql, 0, $p).$val.substr($sql, $p+1);
				}
			} else {
				foreach ($params as $k => $v) {
					$p = strpos($sql, '?', $index);
					if ($p === FALSE) {
						$this->log('parameter dont match. paramter count: '.count($params).', '.$sql, 'warn');
						return $sql;
					}
					@list($k_name, $k_type) = explode(':', $k);

					if ($k_type && $k_type == 'i') {
						$val = (int)$v;
					} else if ($k_type && $k_type == 'n') {
						$val = 'NULL';
					} else {
						$val = "'".$this->escape($v)."'";
					}
					$sql = substr($sql, 0, $p).$val.substr($sql, $p+1);
					$index = $p + strlen($val);
				}
			}
		} else {
			$p = strpos($sql, '?', $index);
			if ($p === FALSE) {
				$this->log('parameter dont match. paramter count: '.count($params).', '.$sql, 'warn');
				return $sql;
			}
			if (is_null($params)) {
				$val = 'NULL';
			} else if (is_numeric($params) && !is_string($params)) {
				$val = $params;
			} else {
				$val = "'".$this->escape($params)."'";
			}
			$sql = substr($sql, 0, $p).$val.substr($sql, $p+1);
		}
		return $sql;
	}

	function query($sql, $params=array(), $is_exec=FALSE) {
		try {
			if ($params) {
				if (is_array($params)) {
					$sql = $this->buildQuery($sql, $params);
				} else {	//support parameter type(no support lob, stmt)
					$sql = $this->buildQuery($sql, array($params));
				}
			}
			if ($this->debug)
				$this->log($sql);
			return $this->_d->query($sql);
		} catch (Exception $e) {
			$this->log($e->getMessage(), 'warn');
			return FALSE;
		}
	}

	function execute($sql, $params=array(), $return_row_count=FALSE) {
		$tmp_ret = $this->query($sql, $params, true);
		if ($tmp_ret === FALSE)
			return FALSE;

		return TRUE;
	}

	function fetchAll($stmt, $options = NULL) {
		if ($stmt === FALSE)
			return FALSE;
		$ret = array();
		$key_field = ($options && !empty($options['key_field'])) ? $options['key_field'] : NULL;
		if ($key_field) {
			while ($row = mysqli_fetch_array($stmt, MYSQLI_ASSOC))
				$ret[$row[$key_field]] = $row;
		} else {
			while ($row = mysqli_fetch_array($stmt, MYSQLI_ASSOC))
				$ret[] = $row;
		}
		return $ret;
	}

	function listAll($stmt, $field, $options = NULL) {
		if ($stmt === FALSE)
			return FALSE;
		$ret = array();
		$key_field = ($options && !empty($options['key_field'])) ? $options['key_field'] : NULL;
		$data_field =  empty($options['data_field']) ? $field : $options['data_field'];
		if ($key_field) {
			while ($row = mysqli_fetch_array($stmt, MYSQLI_ASSOC))
				$ret[$row[$key_field]] = $row[$data_field];
		} else {
			while ($row = mysqli_fetch_array($stmt, MYSQLI_ASSOC))
				$ret[] = $row[$data_field];
		}
		return $ret;
	}

	function escape($src, $type=PDO::PARAM_STR) {
		return mysqli_escape_string($this->_d, $src);
	}

	function begin() {
		return $this->_d->autocommit(FALSE);
	}

	function commit() {
		$ret = $this->_d->commit();
		$this->_d->autocommit(TRUE);
		return $ret;
	}

	function rollback() {
		return $this->_d->rollBack();
	}

	function insert($table, $key_value_map) {
		$sql = 'insert into `'.$this->getTableName($table)
			.'` set '.$this->getKeyValueSql($key_value_map);

		$params = $this->getKeyValueParams($key_value_map);
		$tmp_ret = $this->execute($sql, $params);
		if ($tmp_ret === FALSE)
			return $tmp_ret;

		return $this->lastInsertId();
	}

	function lastInsertId() {
		return $this->_d->insert_id;
	}
}