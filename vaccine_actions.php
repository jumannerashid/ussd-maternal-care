<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

$host = '173.252.167.30';
$dbname = 'technol4_maternal_health';
$username = 'technol4_health_user';
$password = 'health_user';

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) die(json_encode(['success' => false, 'error' => 'Database error']));

$action = $_POST['action'] ?? '';

if ($action === 'edit') {
    $vaccine_id = intval($_POST['id']);
    $status = $_POST['status'];
    $clinic_id = intval($_POST['clinic_id']);

    // Validate clinic ID
    if ($clinic_id < 0) {
        die(json_encode(['success' => false, 'error' => 'Invalid clinic ID']));
    }

    // Update vaccine status and clinic
    $stmt = $conn->prepare("
        UPDATE vaccination_schedule vs
        JOIN pregnant_women pw ON vs.mother_id = pw.id
        SET vs.status = ?, pw.clinic_id = ?
        WHERE vs.id = ?
    ");
    $stmt->bind_param("sii", $status, $clinic_id, $vaccine_id);

    if ($stmt->execute()) {
        die(json_encode(['success' => true]));
    } else {
        die(json_encode(['success' => false, 'error' => 'Update failed']));
    }
}


// Send all vaccine/appointment reminders
if ($action === 'send_all_reminders') {
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
    
    $success = 0;
    $failed = 0;
    foreach ($results as $row) {
        $phone = preg_replace('/^255/', '0', $row['contact_number']);
        $message = "REMINDER: You have a {$row['vaccine_type']} scheduled for {$row['scheduled_date']} at {$row['clinic']}. Please attend.";
        if (sendSms($phone, $message)) $success++;
        else $failed++;
    }
    
    die(json_encode(['success' => $success, 'failed' => $failed]));
}

// Send all birth reminders
if ($action === 'send_birth_reminders') {
    $stmt = $conn->prepare("
        SELECT contact_number, expected_birth_date, c.name AS clinic
        FROM pregnant_women pw
        JOIN clinics c ON pw.clinic_id = c.id
        WHERE DATEDIFF(expected_birth_date, CURDATE()) BETWEEN 0 AND 90
    ");
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $success = 0;
    $failed = 0;
    foreach ($results as $row) {
        $phone = preg_replace('/^255/', '0', $row['contact_number']);
        $message = "Your expected birth date is {$row['expected_birth_date']}. Prepare your birth plan and contact {$row['clinic']} for guidance.";
        if (sendSms($phone, $message)) $success++;
        else $failed++;
    }
    
    die(json_encode(['success' => $success, 'failed' => $failed]));
}

// Existing sendSms() and getBearerToken() functions [[10]]



?>