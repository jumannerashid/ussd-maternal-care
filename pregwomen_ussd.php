<?php
// Include the database connection file
require 'index.php';

// Core USSD Response Function
function ussdResponse($type, $message) {
    if (!in_array($type, ["CON", "END"])) {
        error_log("Invalid USSD response type: " . $type);
        return "END System error. Try again later.";
    }
    return $type . " " . $message;
}

// Authentication Check for Pregnant Women
function authenticateWoman($phone) {
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM pregnant_women WHERE contact_number = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Fetch Next Appointment Date
function getNextAppointment($woman_id) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT appointment_date 
        FROM appointments 
        WHERE mother_id = ? AND status = 'Scheduled' 
        ORDER BY appointment_date ASC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $woman_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Main USSD Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session_id = $_POST['sessionId'] ?? '';
    $text = $_POST['text'] ?? '';
    $phone_number = $_POST['phoneNumber'] ?? '';

    // Step 1: Authenticate User
    $woman = authenticateWoman($phone_number);
    if (!$woman) {
        echo ussdResponse("END", "Unauthorized access. Contact administrator.");
        exit;
    }

    // Parse User Input
    $input_array = explode("*", $text);
    $step = count($input_array);

    // Step 2: Display Main Menu
    if ($step == 0) {
        echo ussdResponse("CON", "Welcome to Maternal Health Services\n1. Check Next Appointment\n2. Exit");
        exit;
    }

    // Step 3: Handle Menu Selection
    $menu_option = $input_array[0];

    switch ($menu_option) {
        case '1': // Check Next Appointment
            // Fetch next appointment
            $next_appointment = getNextAppointment($woman['id']);
            if ($next_appointment) {
                $appointment_date = $next_appointment['appointment_date'];
                echo ussdResponse("END", "Your next appointment is on: " . $appointment_date);
            } else {
                echo ussdResponse("END", "No upcoming appointments found.");
            }
            break;

        case '2': // Exit
            echo ussdResponse("END", "Thank you for using Maternal Health Services. Goodbye!");
            break;

        default:
            echo ussdResponse("END", "Invalid option selected. Please try again.");
            break;
    }
}