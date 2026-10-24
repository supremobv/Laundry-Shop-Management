<?php
// Local setup helper. Does not modify or delete existing tables.
require __DIR__.'/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$name = $_ENV['DB_DATABASE'] ?? 'laundry_shop_mvp';
if (in_array('--test',$argv,true)) $name = 'laundry_shop_test';
if (!preg_match('/^[a-zA-Z0-9_]+$/',$name)) { fwrite(STDERR,"Use a database name containing only letters, digits, and underscores.\n"); exit(1); }
try {
    $pdo = new PDO('mysql:host='.($_ENV['DB_HOST'] ?? '127.0.0.1').';port='.($_ENV['DB_PORT'] ?? '3306').';charset=utf8mb4',$_ENV['DB_USERNAME'] ?? 'root',$_ENV['DB_PASSWORD'] ?? '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    if (in_array('--create',$argv,true)) $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `'.$name.'`');
    echo 'MySQL connection verified. Server '.$pdo->query('SELECT VERSION()')->fetchColumn().'; database '.$name.".\n";
} catch (PDOException $e) {
    fwrite(STDERR,"Cannot connect to MySQL. Start MySQL in Laragon, verify DB_* in .env, and create the configured database. Database error code: ".$e->getCode()."\n"); exit(1);
}
