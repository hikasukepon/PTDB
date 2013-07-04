<?php
function _log($v) {
    echo "$v<br/>\n";
}
function test($name, $result) {
    echo '<div>';
    echo '<h2>'.htmlspecialchars($name).'</h2>';
    if (is_array($result)) {
        print_r($result);
    } else if (is_numeric($result) || is_string($result)) {
        echo $result;
    } else {
        echo $result === FALSE ? 'NG' : 'ok';
    }

echo '</div>';
}
$db->addLogHandler('_log', '_warn');

$dsn = $db_config['engine'].':'.implode(';', array(
    'host='.$db_config['host'],
    'dbname='.$db_config['name'],
    'charset='.$db_config['charset']
));

$db->connect($dsn, $db_config['user'], $db_config['pass']);
test('connect', 'ok');

$ret = $db->query('create table test (
    id INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(32) NULL,
    created DATETIME NOT NULL,
    age INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8');
test('create table', $ret);

$id = $db->insert('test', array('name' => 'tsuge', 'created' => '^CURRENT_TIMESTAMP'));
test('insert', $id);

$data = $db->selectRow('test', NULL, array('id' => $id));
test('select1', $data);

$updat_ret = $db->update('test', array('age' => 20), array('id' => $id));
test('update', $updat_ret);

$age = $db->selectOne('test', 'age', array('id' => $id));
test('select new age', $age);

$cnt = $db->count('test');
test('count1', $cnt);
$id2 = $db->insert(
    'test',
    array('name' => 'PTDB', 'created' => '^NOW()', 'age' => 5)
);
test('insert2', $id2);
$cnt = $db->count('test');
test('count2', $cnt);

$select_all = $db->select('test');
test('select', $select_all);

$list = $db->selectList('test', 'name');
test('list', $list);

$select_options = $db->select('test', array('id', 'name'), array(
    'limit' => 1,
    'order' => 'id desc'
));
test('select+options', $select_options);

$delete = $db->delete('test', array('id' => $id));
test('delete', $delete);
$cnt = $db->count('test');
test('count3', $cnt);

$ret = $db->query('drop table test');
test('drop table', $ret);

echo 'finished';
?>