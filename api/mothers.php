<?php
// api/mothers.php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$host = '173.252.167.30';
$dbname = 'technol4_maternal_health';
$username = 'technol4_health_user';
$password = 'health_user';

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) die(json_encode(['error' => 'Database connection failed']));

// Recent registrations
$stmt = $conn->prepare("SELECT id, full_name, contact_number, created_at 
                        FROM pregnant_women 
                        ORDER BY created_at DESC 
                        LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode($data);
?>