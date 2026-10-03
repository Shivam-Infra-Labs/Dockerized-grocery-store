<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASSWORD');

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
?>
