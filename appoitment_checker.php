<?php
// Suppress error output
ini_set('display_errors', 0);
error_reporting(0);

// Start output buffering
ob_start();

// Capture USSD parameters
$sessionId = $_POST['sessionId'] ?? '';
$serviceCode = $_POST['serviceCode'] ?? '';
$phoneNumber = $_POST['phoneNumber'] ?? '';
$text = $_POST['text'] ?? '';

// Initialize response
$response = "";

// Explode text into an array to track the step
$textArray = explode("*", $text);
$level = count($textArray);

// Simulated Data for Appointment Checking (Phone Number based)
$appointments = [
    "255123456789" => "2025-01-15",  // Example phone number mapped to appointment date
    "255987654321" => "2025-01-18"   // Another example phone number with different appointment date
];

// Simulated Next Appointment Date
$nextAppointmentDate = "2025-01-22"; // Example next appointment date

// Handle USSD flow
if ($text == "") {
    // Start Menu for Appointment Checking
    $response = "CON Welcome to Maternal Health System\n";
    $response .= "1. Check Appointment\n";
    $response .= "2. Exit\n";
} elseif ($textArray[0] == "1") {
    // Check Appointment Flow
    switch ($level) {
        case 1:
            // Step 1: Show Appointment Date for the Phone Number
            if (isset($appointments[$phoneNumber])) {
                $appointmentDate = $appointments[$phoneNumber];
                $response = "CON Your appointment is scheduled on $appointmentDate.\n";
                $response .= "Next appointment is on $nextAppointmentDate.\n";
                $response .= "Thank you for using the Maternal Health System.";
            } else {
                // Always show an appointment date, regardless of whether the phone number is listed
                $appointmentDate = "2025-01-20";  // Default simulated appointment date for any number
                $response = "CON Your appointment is scheduled on $appointmentDate.\n";
                $response .= "Next appointment is on $nextAppointmentDate.\n";
                $response .= "Thank you for using the Maternal Health System.";
            }
            break;

        default:
            $response = "END Invalid input. Please try again.";
            break;
    }
} elseif ($textArray[0] == "2") {
    // Exit
    $response = "END Thank you for using the Maternal Health System.";
} else {
    $response = "END Invalid option. Please try again.";
}

// Clear any unintended output
ob_end_clean();

// Output the response
echo $response;
?>
