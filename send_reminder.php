<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$host = '173.252.167.30';
$dbname = 'technol4_maternal_health';
$username = 'technol4_health_user';
$password = 'health_user';

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) die(json_encode(['success' => false, 'message' => 'Database error']));

$reminder_type = $_POST['type'] ?? '';
$count = 0;

// Send reminders for upcoming appointments/vaccines
if ($reminder_type === 'upcoming') {
    $stmt = $conn->prepare("
        (SELECT pw.contact_number, vs.vaccine_type, vs.scheduled_date, c.name AS clinic
         FROM vaccination_schedule vs
         JOIN pregnant_women pw ON vs.mother_id = pw.id
         JOIN clinics c ON pw.clinic_id = c.id
         WHERE vs.status = 'scheduled'
           AND DATEDIFF(vs.scheduled_date, CURDATE()) BETWEEN 0 AND 3)
        UNION
        (SELECT pw.contact_number, 'Clinic Visit' AS vaccine_type, a.appointment_date, c.name AS clinic
         FROM appointments a
         JOIN pregnant_women pw ON a.mother_id = pw.id
         JOIN clinics c ON pw.clinic_id = c.id
         WHERE a.status = 'scheduled'
           AND DATEDIFF(a.appointment_date, CURDATE()) BETWEEN 0 AND 3)
    ");
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($results as $row) {
        $phone = preg_replace('/^255/', '0', $row['contact_number']);
        $message = "Reminder: You have a {$row['vaccine_type']} scheduled for {$row['scheduled_date']} at {$row['clinic']}. Please attend.";
        sendSms($phone, $message);
        $count++;
    }
}

// Send reminders for mothers due in 3 months
if ($reminder_type === 'birth') {
    $stmt = $conn->prepare("
        SELECT contact_number, expected_birth_date, c.name AS clinic
        FROM pregnant_women pw
        JOIN clinics c ON pw.clinic_id = c.id
        WHERE DATEDIFF(expected_birth_date, CURDATE()) BETWEEN 0 AND 90
    ");
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($results as $row) {
        $phone = preg_replace('/^255/', '0', $row['contact_number']);
        $message = "Your expected birth date is {$row['expected_birth_date']}. Prepare your birth plan with {$row['clinic']}.";
        sendSms($phone, $message);
        $count++;
    }
}

$conn->close();
echo json_encode(['success' => true, 'count' => $count]);

// Mambo SMS Function [[10]]
function sendSms($phone_number, $message) {
    $token = getBearerToken();
    if (!$token) return false;

    $url = "https://mambosms.co.tz/api/v1/sms/single";
    $data = [
        "sender_id" => "InfoNotice",
        "message" => $message,
        "mobile" => $phone_number
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $token
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);

    return isset(json_decode($response, true)['status']) 
        && json_decode($response, true)['status'] === "success";
}

// Authentication [[10]]
function getBearerToken() {
    $url = "https://mambosms.co.tz/api/v1/login";
    $data = [
        "phone_number" => "0756598665",
        "password" => "LuisPedro19"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);
    return $responseData['data']['token'] ?? null;
}
?>