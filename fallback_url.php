<?php
// Set headers for JSON response
header('Content-Type: application/json');

// Log all incoming POST data (this will capture the event data sent by the service)
$eventData = file_get_contents('php://input');

// Capture the event details for debugging or processing
file_put_contents('event_fallback_log.txt', $eventData . "\n", FILE_APPEND);

// Decode the incoming JSON data (assuming the data is sent in JSON format)
$data = json_decode($eventData, true);

// Check if the data contains the error or event information
if ($data) {
    // You can process different types of events here based on the data received
    if (isset($data['error'])) {
        // Log the error in a separate log file
        file_put_contents('error_log.txt', "Error Event: " . json_encode($data) . "\n", FILE_APPEND);
        
        // You may choose to notify your admin or take corrective actions
        // For example, send an email alert to notify about the error
        // mail("admin@example.com", "USSD Event Error", "Error: " . json_encode($data));

        // Send a response back to the service
        echo json_encode(['status' => 'error', 'message' => 'Error occurred']);
    } else {
        // Process success events (e.g., successful data submission or delivery)
        file_put_contents('success_log.txt', "Success Event: " . json_encode($data) . "\n", FILE_APPEND);

        // Optionally, update your database or notify your team
        // Example: update a record, send notification, etc.
        
        // Send a success response back
        echo json_encode(['status' => 'success', 'message' => 'Event processed successfully']);
    }
} else {
    // If invalid or empty data is received, log the invalid event
    file_put_contents('invalid_event_log.txt', $eventData . "\n", FILE_APPEND);

    // Return a response for invalid data
    echo json_encode(['status' => 'failure', 'message' => 'Invalid event data']);
}
?>
