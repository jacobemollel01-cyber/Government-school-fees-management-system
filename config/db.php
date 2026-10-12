
<?php

$localConfig = __DIR__ . '/db.local.php';

if (file_exists($localConfig)) {
    $config = require $localConfig;
} else {
    $config = [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'username' => getenv('DB_USERNAME') ?: '',
        'password' => getenv('DB_PASSWORD') ?: '',
        'database' => getenv('DB_DATABASE') ?: 'school_fees',
    ];
}

$host = $config['host'];
$username = $config['username'];
$password = $config['password'];
$database = $config['database'];

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    error_log('School Fees database connection failed.');
    die('Unable to connect to the database. Check the local configuration.');
}

$conn->set_charset('utf8mb4');

?>
