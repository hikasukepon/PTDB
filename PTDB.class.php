<?php
class PTDB {
	var $table_prefix;
	var $log_handler;
	var $debug = FALSE;
	static $type_map;

	function __construct($table_prefix='') {
		$this->table_prefix = $table_prefix;
		$this->log_handler = array(
			'debug' => array(),
			'info' => array(),
			'error' => array(),
			'warn' => array()
		);
		if (! PTDB::$type_map) {
			PTDB::$type_map = array(
				'i' => PDO::PARAM_INT,
				's' => PDO::PARAM_STR,
				'n' => PDO::PARAM_NULL,
				'b' => PDO::PARAM_BOOL,
				'lob' => PDO::PARAM_LOB,
				'stmt' => PDO::PARAM_STMT
			);
		}
	}

	function connect($con, $user, $pass) {
		try {
			$this->_d = new PDO($con, $user, $pass);
			//$this->_d->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->log('connected database to '.$con, 'info');
		} catch (PDOException $e) {
			var_dump('error?');
			$this->log($e->getMessage());
		}
	}

	function close() {
		unset($this->_d);
	}

	function query($sql, $params=array()) {
		try {
			if ($params) {
				$stmt = $this->_d->prepare($sql);
				if (is_array($params)) {
					$k = key($params);
					if (is_int($k)) {	//fastest, but don't support parameter type
						$i = 1;
						foreach ($params as $v)
							$stmt->bindValue($i++, $v);
					} else {	//slowest, support parameter type.
						foreach ($params as $k => $v) {
							@list($k_name, $k_type) = explode(':', $k);
							if ($k_type) {
								$stmt->bindValue($k_name, $v, PTDB::$type_map[$k_type]);
							} else {
								$stmt->bindValue($k_name, $v);
							}
						}
					}
				} else {	//support parameter type(no support lob, stmt)
					if (is_string($params)) {
						$stmt->bindValue(1, $params, PDO::PARAM_STR);
					} else if (is_int($params)) {
						$stmt->bindValue(1, $params, PDO::PARAM_INT);
					} else if (is_null($params)) {
						$stmt->bindValue(1, $params, PDO::PARAM_NULL);
					} else if (is_bool($params)) {
						$stmt->bindValue(1, $params, PDO::PARAM_BOOL);
					} else {
						$stmt->bindValue(1, $params);
					}
				}
				if ($this->debug)
					$this->log($sql. ' ('.serialize($params).')', 'debug');

				$ret = $stmt->execute();
				if ($ret === FALSE)
					return FALSE;

				return $stmt;
			}
			if ($this->debug)
				$this->log($sql);
			return $this->_d->query($sql);
		} catch (PDOException $e) {
			$this->log($e->getMessage(), 'warn');
			return FALSE;
		}
	}

	function execute($sql, $params=array()) {
		$tmp_ret = $this->query($sql, $params);
		if ($tmp_ret === FALSE)
			return FALSE;

		//return $tmp_ret->row_count() == 0 ? FALSE : TRUE;

		return TRUE;
	}

	function fetchAll($stmt, $options = NULL) {
		if ($stmt === FALSE)
			return FALSE;
		$ret = array();
		$key_field = ($options && !empty($options['key_field'])) ? $options['key_field'] : NULL;
		if ($key_field) {
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
				$ret[$row[$key_field]] = $row;
		} else {
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
				$ret[] = $row;
		}
		return $ret;
	}

	function listAll($stmt, $one_field, $options = NULL) {
		if ($stmt === FALSE)
			return FALSE;
		$ret = array();
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
			$ret[] = $row[$one_field];
		return $ret;
	}

	function getAll($sql, $params=array(), $options = NULL) {
		return $this->fetchAll($this->query($sql, $params), $options);
	}

	function makeSelectSql($table, $fields, $where=NULL, $options=array()) {
		$sql = 'select ';
		if (!empty($options['distinct']))
			$sql .= 'distinct ';

		if (empty($fields))
			$fields = '^*';

		if (empty($where) && !empty($options['where']))
			$where = $options['where'];

		$sql .= $this->escapeField($fields).' from `'.$this->getTableName($table).'`';
		if (!empty($where)) {
			if (is_array($where)) {
				$sql .= ' where '.$this->getKeyValueSql($where, ' and ');
			} else {
				$sql.=' where '.$where;
			}
		}
		if (!empty($options['group']))
			$sql.=' group by '.$options['group'];
		if (!empty($options['order']))
			$sql.=' order by '.$options['order'];
		if (!empty($options['limit']))
			$sql.=' limit '.$options['limit'];
		return $sql;
	}

	function log($val, $type='error') {
		if (empty($this->log_handler[$type]))
			return FALSE;

		foreach ($this->log_handler[$type] as $v) {
			$v($val, $type);
		}

		return TRUE;
	}

	function addLogHandler($func, $type) {
		if ($type === FALSE) {
			foreach ($this->log_handler as $t => $tmp) {
				$this->log_handler[$t][] = $func;
			}
		} else if ($type === '_debug') {
			$this->log_handler['error'][] = $func;
			$this->log_handler['warn'][] = $func;
			$this->log_handler['info'][] = $func;
			$this->log_handler['debug'][] = $func;
		} else if ($type === '_info') {
			$this->log_handler['error'][] = $func;
			$this->log_handler['warn'][] = $func;
			$this->log_handler['info'][] = $func;
		} else if ($type === '_warn') {
			$this->log_handler['error'][] = $func;
			$this->log_handler['warn'][] = $func;
		} else {
			$this->log_handler[$type][] = $func;
		}
		if (! empty($this->log_handler['debug'])) {
			$this->debug = TRUE;
		}
	}

	function removeLogHandler($func, $type) {
		if ($type === FALSE) {
			foreach ($this->log_handler[$type] as $t => $tmp) {
				$this->removeLogHandler($func, $t);
			}
			return;
		}

		$new_handler = array();
		foreach ($this->log_handler[$type] as $v) {
			if ($v != $func) {
				$new_handler[] = $v;
			}
		}
		$this->log_handler[$type] = $new_handler;
	}

	function escapeField($src) {
		if (is_array($src)) {
			$target = $src;
			foreach ($target as &$v) {
				$v = $this->escapeField($v);
			}
			return implode(',', $target);
		} else {
			if (strpos($src, '^') === 0)
				return substr($src, 1);
			$target = explode('.', $src);
			foreach ($target as &$v) {
				$v = '`'.$v.'`';
			}
			return implode('.', $target);
		}
	}

	function getTableName($table) {
		return $this->table_prefix.$table;
	}

	function getKeyValueParams($key_value_map) {
		$ret = array();
		foreach ($key_value_map as $v) {
			if (is_null($v) || @substr($v, 0, 1) == '^')
				continue;
			$ret[] = $v;
		}
		return $ret;
	}

	function getKeyValueSql($key_value_map, $sep = ',') {
		$sql_array = array();
		foreach ($key_value_map as $k => $v) {
			if (is_null($v)) {
				$sql_array[] = $this->escapeField($k).'=null';
			} else {
				if (@substr($v, 0, 1) == '^') {
					$sql_array[] = $this->escapeField($k).'='.substr($v,1);
				} else {
					$sql_array[] = $this->escapeField($k).'=?';
				}
			}
		}
		return implode($sep, $sql_array);
	}

	function escape($src, $type=PDO::PARAM_STR) {
		return $this->_d->quote($src, $type);
	}

	function begin() {
		return $this->_d->beginTransaction();
	}

	function commit() {
		return $this->_d->commit();
	}

	function rollback() {
		return $this->_d->rollBack();
	}

	function select($table, $field=NULL, $where=NULL, $params=array(), $options=NULL) {
		if (is_array($where)) {
			$options = $where;
			$where = NULL;
		}
		$sql = $this->makeSelectSql($table, $field, $where, $options);
		return $this->getAll($sql, $params, $options);
	}

	function selectList($table, $one_field, $where=NULL, $params=array(), $options=NULL) {
		if (is_array($where)) {
			$options = $where;
			$where = NULL;
		}
		$sql = $this->makeSelectSql($table, $one_field, $where, $options);
		return $this->listAll($this->query($sql, $params), $one_field, $options);
	}

	function selectRow($table, $field=NULL, $key_value_where=array(), $options=array()) {
		$options = array_merge(array('limit' => 1), $options);
		$sql = $this->makeSelectSql($table, $field, $key_value_where, $options);
		$ret = $this->getAll($sql, $this->getKeyValueParams($key_value_where), $options);
		if (empty($ret))
			return FALSE;
		return $ret[0];
	}

	function pureSelectRow($table, $field=NULL, $where=NULL, $params=array(), $options=array()) {
		if (is_array($where)) {
			$options = $where;
			$where = NULL;
		}
		$options = array_merge(array('limit' => 1), $options);
		$sql = $this->makeSelectSql($table, $field, $where, $options);
		$ret = $this->getAll($sql, $params, $options);
		if (empty($ret))
			return FALSE;
		return $ret[0];
	}

	function selectOne($table, $field=NULL, $key_value_where=array(), $options=array()) {
		$ret = $this->selectRow($table, $field, $key_value_where, $options);
		if ($ret === FALSE)
			return FALSE;
		return current($ret);
	}

	function pureSelectOne($table, $field=NULL, $where=NULL, $params=array(), $options=array()) {
		if (is_array($where)) {
			$options = $where;
			$where = NULL;
		}
		$ret = $this->pureSelectRow($table, $field, $where, $params, $options);
		if ($ret === FALSE)
			return FALSE;
		return current($ret);
	}

	function count($table, $where=NULL, $params=array()) {
		return $this->pureSelectOne(
			$table,
			array('^count(*) as c'),
			$where,
			$params
		);
	}

	function insert($table, $key_value_map) {
		$sql = 'insert into `'.$this->getTableName($table)
			.'` set '.$this->getKeyValueSql($key_value_map);

		$params = $this->getKeyValueParams($key_value_map);
		$tmp_ret = $this->execute($sql, $params);
		if ($tmp_ret === FALSE)
			return $tmp_ret;

		return $this->_d->lastInsertId();
	}

	function update($table, $key_value_map, $key_value_where=array()) {
		$sql = 'update `'.$this->getTableName($table).'` set '.$this->getKeyValueSql($key_value_map);
		$sql .= ' where '.$this->getKeyValueSql($key_value_where);
		$params = array_merge(
			$this->getKeyValueParams($key_value_map),
			$this->getKeyValueParams($key_value_where)
		);

		return $this->execute($sql, $params);
	}

	function pureUpdate($table, $key_value_map, $where=NULL, $params=array()) {
		$sql = 'update `'.$this->getTableName($table).'` set '.$this->getKeyValueSql($key_value_map);
		$sql .= ' where '.($where ? $where : '1=1');
		$params = array_merge($this->getKeyValueParams($key_value_map), $params);

		return $this->execute($sql, $params);
	}

	function delete($table, $key_value_where=array()) {
		$sql = 'delete from `'.$this->getTableName($table).'`';
		$sql .= ' where '.$this->getKeyValueSql($key_value_where);

		return $this->execute($sql, $this->getKeyValueParams($key_value_where));
	}

	function pureDelete($table, $where=NULL, $params=array()) {
		$sql = 'delete from '.$this->getTableName($table);
		$sql .= ' where '.($where ? $where : '1=1');

		return $this->execute($sql, $params);
	}
}
