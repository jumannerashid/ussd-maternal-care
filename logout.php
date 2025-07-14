<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Clear all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Prevent caching of logged-in pages
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to login page with feedback
header("Location: https://maternal.technologygenius14.com/login.php?logout=1");
exit;
?>