PTDB
====

Simplified PDO Wrapper class

### Support ###
Only php5. (>=5.1.0)

### Policy ###
I dont like access modifier.

### Example ###

#### Sample DB ####
```sql
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
```

#### Sample Code ####
```php
<?php
require_once('PTDB.class.php');

//create instance
$db = new PTDB();

//log handler
function _log($v,$log_type) {
  echo '<div>'.htmlspecialchars($v).'</div>';
}
$db->addLogHandler('_log', '_error');

//connect to db
$db->connect('mysql:host=localhost;dbname=dbname;charset=utf8','user','pass');

//insert data
$db->insert('users', array('name' => 'test user', 'created' => '^CURRENT_TIMESTAMP'));

//select data
$db->select('users');
$db->select('users', array('id', 'name', 'created');

//select data use where
$db->select('users', array('id', 'name', 'created'), 'name=?', array('tsuge'));
$db->select('users', array('id', 'name', 'created'), array('where' => 'name=?'), array('tsuge'));
$db->select('users', array('id', 'name', 'created'), array('where' => 'name=?', 'limit' => 2, 'order' => 'id desc'), array('tsuge'));

//update data
$db->update('users', array('name' => 'test user udpated'), array('id' => 1));

//select one record
$db->selectRow('users', array('id', 'name'), array('id' => 1));

//select one column
$db->selectOne('users', 'name', array('id' => 1));

//count record
$db->count('users', "name like '%?%'", array('test'));

//delete record
$db->delete('users', array('id' => 1));

//other functions
//$db->begin();
//$db->rollback();
//$db->commit();
//$db->escape('source');
//$db->close();
//$db->pureSelectRow(...)
//$db->pureSelectOne(...)
//$db->pureUpdate(...)
//$db->pureDelete(...)

//expert or private functions
//$db->query(...)
//$db->execute(...)
//$db->fetchAll(...)
//$db->getAll(...)
//$db->makeSelectSql(...)
//$db->log(...)
//$db->removeLogHandler
//$db->escapeField
//$db->getTableName
//$db->getKeyValueParams
//$db->getKeyValueSql
//$db->listAll(...)
//$db->selectList(...)
?>
```
