<?php
// --- Send SMS Using Existing Authentication ---
function sendLiveSms() {
    $phone_number = "0756598665"; // Local format (no +255)
    $message = "We are live!"; // Custom message

    $token = getBearerToken(); // Use your existing authentication function
    if (!$token) {
        echo "SMS Hana Idhini: Tafadhali angalia kredenti zako.\n";
        return false;
    }

    $url = "https://mambosms.co.tz/api/v1/sms/single"; // Correct SMS endpoint
    $data = [
        "sender_id" => "InfoNotice", // Approved sender ID
        "message" => $message,
        "mobile" => $phone_number // Send local number (0756598665)
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $token // Use the retrieved token
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        echo "cURL Error: " . curl_error($ch) . "\n"; // Log cURL errors
        curl_close($ch);
        return false;
    }
    curl_close($ch);

    $responseData = json_decode($response, true);
    echo "API Response: " . print_r($responseData, true); // Full response
    return $responseData;
}

// --- Existing getBearerToken() (Assuming It Works) ---
function getBearerToken() {
    $url = "https://mambosms.co.tz/api/v1/login"; // Correct auth URL
    $data = [
        "phone_number" => "0756598665", // Local format (no +255)
        "password" => "LuisPedro19"    // Your password
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);
    $responseData = json_decode($response, true);
    
    // Check for "success": 1 (from your test response)
    if (isset($responseData['success']) && $responseData['success'] == 1) {
        return $responseData['data']['token']; // Token is nested under 'data'
    } else {
        echo "Login Failed: " . ($responseData['message'] ?? "No response") . "\n";
        return null;
    }
}

// --- Run the SMS Test ---
sendLiveSms();
?>