<?php
include '/wamp64/www/VAP-final/classes/db/Db.php';
$result = Db::getInstance()->getValue('
	SELECT 
	FROM `'._DB_PREFIX_.'matable`
	WHERE `id` = 1');
echo 'Result for id 1 : '.$result;

?>