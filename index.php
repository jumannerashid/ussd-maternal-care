<?php
// Database credentials (replace with your hosting provider details)
$host = '173.252.167.30';
$dbname = 'technol4_maternal_health';
$username = 'technol4_health_user';
$password = 'health_user';

// Create a connection to the database
$conn = new mysqli($host, $username, $password, $dbname);

// Check the connection
if ($conn->connect_error) {
    die("CON Connection failed: " . $conn->connect_error);
}
?>