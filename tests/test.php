<?php 

require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using Composer autoload

use Ahmader\CouchdbPhp;


$couchdb = new CouchdbPhp('', 'localhost', 5984, '',''); // $db, $host = 'localhost', $port = 5984, $username = null, $password


print_r($couchdb->get_all_dbs());
echo"\n\n\n	";
