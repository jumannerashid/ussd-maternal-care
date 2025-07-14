<?php
require 'index.php';

// Core USSD Response Function
function ussdResponse($type, $message) {
    if (!in_array($type, ["CON", "END"])) {
        error_log("Invalid USSD response type: " . $type);
        return "END System error. Try again later.";
    }
    return $type . " " . $message;
}

// Authentication Function
function authenticateUser($phone) {
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM health_workers WHERE phone_number = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Authentication Check
if (!authenticateUser($_POST['phoneNumber'] ?? '')) {
    echo ussdResponse("END", "Unauthorized access. Contact administrator.");
    exit;
}

// USSD Variables
$session_id = $_POST['sessionId'];
$text = $_POST['text'] ?? '';
$phone_number = $_POST['phoneNumber'] ?? '';

// Main Nurse Menu
if (empty($text)) {
    echo ussdResponse("CON", "USSD Menu for Nurses
1. Register Pregnant Woman
2. Edit Pregnant Woman Data
3. Schedule Appoitment
4. Mark Attendance
5. Schedule Vacination
6. Vaccination Update");
    exit;
}

// Parse User Input
$input_array = explode("*", $text);
$menu_option = $input_array[0];

// Handle Menu Selection
switch ($menu_option) {
    case '1':
        handleRegistration($conn, $session_id, $phone_number, $input_array);
        break;
    case '2':
        handleEdit($conn, $session_id, $phone_number, $input_array);
    
        
    case '3':
        handleAppointment($conn, $session_id, $phone_number, $input_array);    
        break;    
    case '4':
        handleAttendance($conn, $session_id, $phone_number, $input_array);    
        break;        

    case '5':
        handleVaccineSchedule_VS($conn, $session_id, $phone_number, $input_array);
        break;
    case '6':
        handleVaccinationUpdate($conn, $session_id, $phone_number, $input_array);
        break;    
    default:
        echo ussdResponse("END", "Feature in development");
}

exit;




// --- Core Registration Function ---
function handleRegistration($conn, $session_id, $phone_number, $input_array) {
    array_shift($input_array);
    $text = implode("*", $input_array);

    try {
        $lastReg = $conn->query("
            SELECT registration_id, step, data 
            FROM registration_progress 
            WHERE phone_number = '$phone_number' 
            ORDER BY updated_at DESC LIMIT 1
        ")->fetch_assoc();
    } catch (Exception $e) {
        error_log("DB Retrieval Error: " . $e->getMessage());
        echo ussdResponse("END", "Database error. Try later.");
        exit;
    }

    if (isRegistered($conn, $phone_number)) {
        echo ussdResponse("END", "Already registered.");
        exit;
    }

    $regId = $lastReg['registration_id'] ?? generateRegId($conn);
    $step = (int)($lastReg['step'] ?? 1);
    $inputs = json_decode($lastReg['data'] ?? '[]', true) ?: [];

    $steps = [
        1 => "CON Personal Information\nFull Name: (Please enter her full name)",
        2 => "CON Personal Information\nAge: (Please enter her age in years)",
        3 => "CON Personal Information\nNational ID/Hospital ID: (Please enter her ID number)",
        4 => "CON Personal Information\nMother’s Phone: (Please enter her phone number)",
        5 => "CON Personal Information\nEmergency Contact Name: (Please enter the name)",
        6 => "CON Personal Information\nEmergency Contact Number: (Please enter the phone number)",
        7 => "CON Personal Information\nResidence: (Village/Ward)",
        8 => "CON Obstetric History\nFirst Pregnancy?\n1. Yes\n2. No",
        9 => "CON Obstetric History\nPrevious Miscarriages?\n1. Yes\n2. No",
        10 => "CON Obstetric History\nExpected Birth Date\n(YYYY-MM-DD)",
        11 => "CON Clinical Assessment\nHIV Status?\n1. Yes\n2. No\n3. Unknown",
        12 => "CON Clinical Assessment\nChronic Illnesses?\n1. Diabetes\n2. Hypertension\n3. None\n4. Other",
        13 => "CON Clinical Assessment\nPast Complications?\n1. Preeclampsia\n2. Gestational Diabetes\n3. None\n4. Other",
        14 => "CON Emergency Preparedness\nTransport Access?\n1. Yes\n2. No"
    ];

    if (!empty($text)) {
        $input = performValidation($input_array, $step);
        if (!empty($input['error'])) {
            echo ussdResponse("END", $input['error']);
            exit;
        }
        $inputs[$step] = $input['value'];
        
        // Step progression logic
        if ($step == 8) {
            $step = ($input['value'] == 'Yes') ? 10 : 9;
        } elseif ($step == 12 && $inputs[8] == 'Yes') {
            $step = 14;
        } else {
            $step++;
        }
    }

    if (isset($steps[$step])) {
        echo ussdResponse("CON", $steps[$step]);
    } else {
        completeRegistration($conn, $inputs, $phone_number, $regId);
        echo ussdResponse("END", "Registration successful!");
        exit;
    }

    try {
        $sql = "
            INSERT INTO registration_progress 
            (registration_id, session_id, phone_number, step, data) 
            VALUES (?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            step = VALUES(step), 
            data = VALUES(data)
        ";
        $stmt = $conn->prepare($sql);
        $json = json_encode($inputs, JSON_UNESCAPED_UNICODE);
        $stmt->bind_param("issis", $regId, $session_id, $phone_number, $step, $json);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Tracking Error: " . $e->getMessage());
        echo ussdResponse("END", "Tracking failed");
        exit;
    }
}

// --- Validation ---
function performValidation($input_array, $step) {
    $value = trim(end($input_array));
    switch ($step) {
        case 1: // Full Name
            return empty($value) ? ['error' => 'Full name required'] 
                                : ['value' => htmlspecialchars($value)];
        case 2: // Age
            return (!is_numeric($value) || $value < 12 || $value > 50) 
                ? ['error' => 'Age must be 12-50'] 
                : ['value' => intval($value)];
        case 3: // National ID
            return (strlen($value) > 20) 
                ? ['error' => 'ID too long'] 
                : ['value' => htmlspecialchars($value)];
        case 4: // Mother’s Phone
            return (!preg_match('/^\d{10}$/', $value)) 
                ? ['error' => 'Invalid phone'] 
                : ['value' => $value];
        case 5: // Emergency Contact Name
            return empty($value) 
                ? ['error' => 'Name required'] 
                : ['value' => htmlspecialchars($value)];
        case 6: // Emergency Contact Number
            return (!preg_match('/^\d{10}$/', $value)) 
                ? ['error' => 'Invalid emergency number'] 
                : ['value' => $value];
        case 7: // Residence
            return empty($value) 
                ? ['error' => 'Residence required'] 
                : ['value' => htmlspecialchars($value)];
        case 8: // First Pregnancy
            return (!in_array($value, ['1', '2'])) 
                ? ['error' => 'Select 1/2'] 
                : ['value' => $value == '1' ? 'Yes' : 'No'];
        case 9: // Previous Miscarriages
            return (!in_array($value, ['1', '2'])) 
                ? ['error' => 'Select 1/2'] 
                : ['value' => $value == '1' ? 'Yes' : 'No'];
        case 10: // Expected Birth Date
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return ['error' => 'Invalid date format (YYYY-MM-DD)'];
            }
            return (strtotime($value) <= time()) 
                ? ['error' => 'Date must be in future'] 
                : ['value' => $value];
        case 11: // HIV Status
            return (!in_array($value, ['1', '2', '3'])) 
                ? ['error' => 'Select 1-3'] 
                : ['value' => ['1'=>'Yes','2'=>'No','3'=>'Unknown'][$value]];
        case 12: // Chronic Illnesses
            return (!in_array($value, ['1', '2', '3', '4'])) 
                ? ['error' => 'Select 1-4'] 
                : ['value' => ['1'=>'Diabetes','2'=>'Hypertension','3'=>'None','4'=>'Other'][$value]];
        case 13: // Past Complications
            return (!in_array($value, ['1', '2', '3', '4'])) 
                ? ['error' => 'Select 1-4'] 
                : ['value' => ['1'=>'Preeclampsia','2'=>'Gestational Diabetes','3'=>'None','4'=>'Other'][$value]];
        case 14: // Transport Access
            return (!in_array($value, ['1', '2'])) 
                ? ['error' => 'Select 1/2'] 
                : ['value' => $value == '1' ? 'Yes' : 'No'];
        default:
            return ['error' => 'Invalid step'];
    }
}

// --- Database Checks ---
function isRegistered($conn, $phone) {
    $stmt = $conn->prepare("SELECT 1 FROM pregnant_women WHERE contact_number = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function generateRegId($conn) {
    $result = $conn->query("SELECT MAX(registration_id) AS maxId FROM registration_progress");
    return ($result->fetch_assoc()['maxId'] ?? 0) + 1;
}

// --- Registration Completion ---
function completeRegistration($conn, $inputs, $phone, $regId) {
    try {
        // Fetch nurse’s assigned clinic
        $stmt_nurse = $conn->prepare("SELECT clinic_id FROM health_workers WHERE phone_number = ?");
        $stmt_nurse->bind_param("s", $phone);
        $stmt_nurse->execute();
        $nurse = $stmt_nurse->get_result()->fetch_assoc();
        $clinic_id = $nurse['clinic_id'] ?? null;
        $stmt_nurse->close();

        // Prepare data for pregnant_women table
        $params = [
            $inputs[1] ?? '', // Full Name
            $inputs[2] ?? 0,  // Age
            $inputs[3] ?? '', // National ID
            $inputs[4] ?? '', // Mother’s Phone (stored here)
            $inputs[5] ?? '', // Emergency Contact Name
            $inputs[6] ?? '', // Emergency Contact Number
            $inputs[7] ?? '', // Residence
            $inputs[10] ?? null, // Expected Birth Date
            $inputs[8] ?? 'No', // First Pregnancy
            ($inputs[8] == 'Yes') ? 'N/A' : ($inputs[9] ?? 'No'), // Previous Miscarriages
            $inputs[11] ?? 'Unknown', // HIV Status
            $inputs[12] ?? 'None', // Chronic Illnesses
            ($inputs[8] == 'Yes') ? 'N/A' : ($inputs[13] ?? 'None'), // Past Complications
            $inputs[14] ?? 'No', // Transport Access
            $clinic_id // Clinic ID from nurse’s record
        ];

        $types = "sissssssisssssi";
        $stmt = $conn->prepare("
            INSERT INTO pregnant_women 
            (full_name, age, national_id, contact_number, emergency_contact_name, 
             emergency_contact_number, residence, expected_birth_date, first_pregnancy, 
             previous_miscarriages, hiv_status, chronic_illnesses, past_complications, 
             transport_access, clinic_id, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        // --- Send SMS to Mother’s Phone ---
        $mother_phone = $params[3]; // Mother’s phone from inputs (step 4)
        $clinic_name = "Kliniki Haijulikani";

        if ($clinic_id) {
            $stmt_clinic = $conn->prepare("SELECT name FROM clinics WHERE id = ?");
            $stmt_clinic->bind_param("i", $clinic_id);
            $stmt_clinic->execute();
            $clinic = $stmt_clinic->get_result()->fetch_assoc();
            $clinic_name = $clinic['name'] ?? "Kliniki Haijulikani";
            $stmt_clinic->close();
        }

        // Construct Swahili message
        $message = "Usajili umekamilika! Jina: {$params[0]}, Kliniki: $clinic_name. Asante kwa kutumia huduma yetu.";

        // Send SMS using proven method
        sendSms($mother_phone, $message);

        // Cleanup registration progress
        $conn->query("DELETE FROM registration_progress WHERE registration_id = '$regId'");
        $stmt->close();
    } catch (Exception $e) {
        error_log("Completion Error: " . $e->getMessage());
        echo ussdResponse("END", "Registration Error");
        exit;
    }
}

// --- SMS Functions (Proven Method) ---
function sendSms($phone_number, $message) {
    $token = getBearerToken();
    if (!$token) {
        error_log("SMS Failed: Authentication required.");
        return false;
    }

    $url = "https://mambosms.co.tz/api/v1/sms/single"; // Correct URL from your test
    $data = [
        "sender_id" => "InfoNotice", // Your approved sender ID
        "message" => $message,
        "mobile" => $phone_number // Local format (no +255)
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
    
    if (curl_errno($ch)) {
        error_log("cURL Error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    curl_close($ch);

    $responseData = json_decode($response, true);
    if (isset($responseData['status']) && $responseData['status'] === "success") {
        return true;
    } else {
        error_log("SMS Error: " . ($responseData['message'] ?? "Unknown error"));
        return false;
    }
}

// --- Authentication (Proven Method) ---
function getBearerToken() {
    $url = "https://mambosms.co.tz/api/v1/login"; // URL that worked for you
    $data = [
        "phone_number" => "0756598665", // Nurse’s phone number (your credentials)
        "password" => "LuisPedro19"     // Nurse’s password
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);
    
    // Use your test response structure (success: 1)
    if (isset($responseData['success']) && $responseData['success'] == 1) {
        return $responseData['data']['token'];
    } else {
        error_log("Auth Failed: " . ($responseData['message'] ?? "No response"));
        return null;
    }
}















// --- EDIT FUNCTION ---
function handleEdit($conn, $session_id, $phone_number, $input_array) {
    // Remove menu option from input array
    array_shift($input_array);
    $step = count($input_array);

    // Step 1: Search Criteria Selection
    if ($step == 0) {
        echo ussdResponse("CON", "Select search method:\n"
            . "1. Phone Number\n"
            . "2. Registration ID\n"
            . "3. Mother's Name");
        exit;
    }

    // Step 2: Get Search Term
    if ($step == 1) {
        $search_type = $input_array[0];
        switch ($search_type) {
            case '1':
                echo ussdResponse("CON", "Enter mother's phone number:");
                break;
            case '2':
                echo ussdResponse("CON", "Enter registration ID:");
                break;
            case '3':
                echo ussdResponse("CON", "Enter mother's name (partial match):");
                break;
            default:
                echo ussdResponse("END", "Invalid selection");
                exit;
        }
        exit;
    }

    // Step 3: Execute Search & Display Results
    if ($step == 2) {
        $search_type = $input_array[0];
        $search_term = $input_array[1];
        
        // Fetch records based on search type
        switch ($search_type) {
            case '1': // Phone Number
                $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE contact_number = ?");
                $stmt->bind_param("s", $search_term);
                break;
            case '2': // Registration ID
                $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE id = ?");
                $stmt->bind_param("i", $search_term);
                break;
            case '3': // Name (Partial Match)
                $search_term = "%$search_term%";
                $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE full_name LIKE ?");
                $stmt->bind_param("s", $search_term);
                break;
            default:
                echo ussdResponse("END", "Invalid search type");
                exit;
        }

        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($results)) {
            echo ussdResponse("END", "No records found");
            exit;
        }

        // Display results with indices
        $response = "CON Select record:\n";
        foreach ($results as $index => $row) {
            $response .= ($index + 1) . ". " . $row['full_name']
                . " (ID: " . $row['id']
                . ", Phone: " . $row['contact_number']
                . ", Age: " . $row['age'] . ")\n";
        }
        echo ussdResponse("CON", $response);
        exit;
    }

    // Step 4: Record Selection
    if ($step == 3) {
        $search_type = $input_array[0];
        $search_term = $input_array[1];
        $record_index = $input_array[2] - 1;

        // Re-fetch records using original search criteria
        switch ($search_type) {
            case '1': // Phone Number
                $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE contact_number = ?");
                $stmt->bind_param("s", $search_term);
                break;
            case '2': // Registration ID
                $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE id = ?");
                $stmt->bind_param("i", $search_term);
                break;
            case '3': // Name (Partial Match)
                $search_term = "%$search_term%";
                $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE full_name LIKE ?");
                $stmt->bind_param("s", $search_term);
                break;
            default:
                echo ussdResponse("END", "Invalid search type");
                exit;
        }

        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!isset($results[$record_index])) {
            echo ussdResponse("END", "Invalid selection");
            exit;
        }

        $selected_record = $results[$record_index];
        $_SESSION['edit_mother_id'] = $selected_record['id']; // Store mother ID in session

        // Display editable fields
        echo ussdResponse("CON", "Editing: " . $selected_record['full_name'] . "\n"
            . "Select field to update:\n"
            . "1. Full Name\n"
            . "2. Age\n"
            . "3. Village\n"
            . "4. Gestational Age\n"
            . "5. Medical Conditions\n"
            . "6. Phone Number\n"
            . "7. Clinic\n"
            . "8. Back");
        exit;
    }

    // Step 5: Field Selection
    if ($step == 4) {
        $field_num = $input_array[3];
        $fields = [
            1 => 'full_name',
            2 => 'age',
            3 => 'village',
            4 => 'gestational_age',
            5 => 'medical_conditions',
            6 => 'contact_number',
            7 => 'clinic_id'
        ];

        if (!isset($fields[$field_num])) {
            echo ussdResponse("END", "Invalid field selection");
            exit;
        }

        // Get current value
        $mother_id = $_SESSION['edit_mother_id'];
        $current_value = getFieldValue($conn, $mother_id, $fields[$field_num]);

        echo ussdResponse("CON", "Current: $current_value\nEnter new value:");
        exit;
    }

    // Step 6: Save Changes
    if ($step == 5) {
        $field_num = $input_array[3];
        $new_value = $input_array[4];
        $mother_id = $_SESSION['edit_mother_id'];
        
        // Field mapping and validation
        $fields = [
            1 => ['name' => 'full_name', 'type' => 'string'],
            2 => ['name' => 'age', 'type' => 'integer', 'min' => 12, 'max' => 50],
            3 => ['name' => 'village', 'type' => 'string'],
            4 => ['name' => 'gestational_age', 'type' => 'integer', 'min' => 1, 'max' => 9],
            5 => ['name' => 'medical_conditions', 'type' => 'string'],
            6 => ['name' => 'contact_number', 'type' => 'phone'],
            7 => ['name' => 'clinic_id', 'type' => 'clinic']
        ];

        if (!isset($fields[$field_num])) {
            echo ussdResponse("END", "Invalid field");
            exit;
        }

        $field = $fields[$field_num];
        $validation = validateEditInput($conn, $field, $new_value);

        if ($validation['error']) {
            echo ussdResponse("END", $validation['error']);
            exit;
        }

        // Prepare update statement
        $stmt = $conn->prepare("UPDATE pregnant_women SET {$field['name']} = ? WHERE id = ?");
        
        // Bind parameters based on field type
        switch ($field['type']) {
            case 'integer':
                $stmt->bind_param("ii", $validation['value'], $mother_id);
                break;
            case 'phone':
                $stmt->bind_param("si", $validation['value'], $mother_id);
                break;
            case 'clinic':
                $stmt->bind_param("ii", $validation['value'], $mother_id);
                break;
            default:
                $stmt->bind_param("si", $validation['value'], $mother_id);
        }

        if (!$stmt->execute()) {
            error_log("Update failed: " . $stmt->error);
            echo ussdResponse("END", "Update failed. Try again.");
            exit;
        }

        unset($_SESSION['edit_mother_id']); // Clear session
        echo ussdResponse("END", "Update successful!");
        exit;
    }
}

// --- Helper Functions ---
function getFieldValue($conn, $mother_id, $field) {
    $stmt = $conn->prepare("SELECT $field FROM pregnant_women WHERE id = ?");
    $stmt->bind_param("i", $mother_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result[$field];
}

function validateEditInput($conn, $field, $value) {
    switch ($field['name']) {
        case 'full_name':
            return empty(trim($value)) 
                ? ['error' => 'Name required'] 
                : ['value' => htmlspecialchars($value)];
                
        case 'age':
            $value = intval($value);
            if ($value < $field['min'] || $value > $field['max']) {
                return ['error' => "Age must be between {$field['min']}-{$field['max']}"];
            }
            return ['value' => $value];
            
        case 'contact_number':
            $phone = preg_replace('/[^0-9]/', '', $value);
            if (strlen($phone) !== 10) {
                return ['error' => 'Invalid phone number'];
            }
            return ['value' => $phone];
            
        case 'clinic_id':
            $stmt = $conn->prepare("SELECT 1 FROM clinics WHERE id = ?");
            $stmt->bind_param("i", $value);
            $stmt->execute();
            return ($stmt->get_result()->num_rows === 0) 
                ? ['error' => 'Invalid clinic ID'] 
                : ['value' => intval($value)];
                
        case 'gestational_age':
            $value = intval($value);
            if ($value < $field['min'] || $value > $field['max']) {
                return ['error' => "Gestational age must be {$field['min']}-{$field['max']} months"];
            }
            return ['value' => $value];
            
        default:
            return ['value' => htmlspecialchars($value)];
    }
}












// --- Birth Registration Function ---
function handleBirthRegistration($conn, $phone_number, $input_array) {
    // Get current progress
    $progress = getBirthProgress($conn, $phone_number);
    $birth_id = isset($progress['birth_id']) ? $progress['birth_id'] : generateBirthId($conn, $phone_number);
    $step = $progress ? $progress['step'] + 1 : 1;
    $data = $progress ? json_decode($progress['data'], true) : [];

    // Step 1: Initiate Registration
    if ($step == 1) {
        echo ussdResponse("CON", "Birth Registration (#$birth_id)\n"
            . "Select mother identification method:\n"
            . "1. Phone Number\n"
            . "2. Registration ID\n"
            . "3. Name");
        exit;
    }

    // Step 2: Capture Search Method
    if ($step == 2) {
        $method = isset($input_array[1]) ? $input_array[1] : '';
        $valid_methods = ['1', '2', '3'];
        
        if (!in_array($method, $valid_methods)) {
            echo ussdResponse("END", "Invalid selection. Please restart.");
            exit;
        }
        
        $data['method'] = $method;
        saveBirthProgress($conn, $phone_number, $birth_id, 2, $data);
        
        $prompts = [
            1 => "Enter mother's phone number (10 digits):",
            2 => "Enter registration ID:",
            3 => "Enter mother's name (partial match):"
        ];
        
        echo ussdResponse("CON", $prompts[$method]);
        exit;
    }

    // Step 3: Execute Search
    if ($step == 3) {
        $method = $data['method'];
        $term = isset($input_array[2]) ? $input_array[2] : '';
        
        switch ($method) {
            case '1':
                $mother = getMotherByPhone($conn, $term);
                break;
            case '2':
                $mother = getMotherById($conn, $term);
                break;
            case '3':
                $mother = getMotherByName($conn, $term);
                break;
            default:
                $mother = null;
        }
        
        if (!$mother) {
            echo ussdResponse("END", "Mother not found. Check details.");
            exit;
        }
        
        $data['mother_id'] = $mother['id'];
        saveBirthProgress($conn, $phone_number, $birth_id, 3, $data);
        
        echo ussdResponse("CON", "Enter birth details for {$mother['full_name']} (#$birth_id):\n"
            . "1. Gender\n"
            . "2. Birth Weight (kg)\n"
            . "3. Delivery Date (YYYY-MM-DD)\n"
            . "4. Delivery Location");
        exit;
    }

    // Step 4: Field Selection
    if ($step == 4) {
        $field_num = isset($input_array[3]) ? $input_array[3] : '';
        $valid_fields = ['1', '2', '3', '4'];
        
        if (!in_array($field_num, $valid_fields)) {
            echo ussdResponse("END", "Invalid field selection");
            exit;
        }
        
        $data['current_field'] = $field_num;
        saveBirthProgress($conn, $phone_number, $birth_id, 4, $data);
        
        $prompts = [
            '1' => "Select gender:\n1. Male\n2. Female",
            '2' => "Enter birth weight (1-5kg):",
            '3' => "Enter delivery date (YYYY-MM-DD):",
            '4' => "Select delivery location:\n1. Health Center\n2. Hospital"
        ];
        
        echo ussdResponse("CON", isset($prompts[$field_num]) ? $prompts[$field_num] : 'Invalid field');
        exit;
    }

    // Step 5: Save Field Data
    if ($step == 5) {
        $field_num = isset($data['current_field']) ? $data['current_field'] : null;
        $value = isset($input_array[4]) ? $input_array[4] : '';
        
        $validation = validateBirthField($field_num, $value);
        if (!empty($validation['error'])) {
            echo ussdResponse("END", $validation['error']);
            exit;
        }
        
        $data[$field_num] = $validation['value'];
        
        // Check completion
        $required_fields = ['1', '2', '3', '4'];
        $completed = true;
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                $completed = false;
                break;
            }
        }
        
        if ($completed) {
            completeBirthRegistration($conn, $birth_id, $data, $phone_number);
            echo ussdResponse("END", "Birth record #{$birth_id} saved successfully!");
            exit;
        }
        
        // Prepare next field
        $remaining = array_diff($required_fields, array_keys($data));
        $next_field = reset($remaining);
        $data['current_field'] = $next_field;
        
        saveBirthProgress($conn, $phone_number, $birth_id, 4, $data);
        
        $prompts = [
            '1' => "Select gender:\n1. Male\n2. Female",
            '2' => "Enter birth weight (1-5kg):",
            '3' => "Enter delivery date (YYYY-MM-DD):",
            '4' => "Select delivery location:\n1. Health Center\n2. Hospital"
        ];
        
        echo ussdResponse("CON", "Enter next detail (#$birth_id):\n" . (isset($prompts[$next_field]) ? $prompts[$next_field] : ''));
        exit;
    }
}

// --- Helper Functions ---
function getBirthProgress($conn, $phone) {
    $stmt = $conn->prepare("
        SELECT * FROM birth_progress 
        WHERE phone_number = ?
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function generateBirthId($conn, $phone) {
    $stmt = $conn->prepare("
        INSERT INTO birth_progress 
        (phone_number, step, data) 
        VALUES (?, 1, '[]')
    ");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $birth_id = $stmt->insert_id;
    $stmt->close();
    return $birth_id;
}

function saveBirthProgress($conn, $phone, $birth_id, $step, $data) {
    $json = json_encode($data);
    $stmt = $conn->prepare("
        UPDATE birth_progress 
        SET step = ?, data = ?
        WHERE birth_id = ? AND phone_number = ?
    ");
    $stmt->bind_param("issi", $step, $json, $birth_id, $phone);
    $stmt->execute();
    $stmt->close();
}

function validateBirthField($field_num, $value) {
    switch ($field_num) {
        case '1': // Gender
            if (in_array($value, ['1','2'])) {
                return ['value' => ($value == '1') ? 'Male' : 'Female'];
            }
            return ['error' => 'Select 1 for Male or 2 for Female'];
            
        case '2': // Birth Weight
            if (is_numeric($value) && $value >= 1 && $value <= 5) {
                return ['value' => round(floatval($value), 2)];
            }
            return ['error' => 'Weight must be between 1-5kg'];
            
        case '3': // Delivery Date
            $date = DateTime::createFromFormat('Y-m-d', $value);
            if ($date && $date->format('Y-m-d') === $value) {
                return ['value' => $value];
            }
            return ['error' => 'Invalid date format (YYYY-MM-DD)'];
            
        case '4': // Delivery Location
            if (in_array($value, ['1','2'])) {
                return ['value' => ($value == '1') ? 'Health Center' : 'Hospital'];
            }
            return ['error' => 'Select 1 or 2'];
            
        default:
            return ['error' => 'Invalid field'];
    }
}

function completeBirthRegistration($conn, $birth_id, $data, $phone_number) {
    // Save final record
    $stmt = $conn->prepare("
        INSERT INTO birth_records 
        (birth_id, mother_id, gender, birth_weight, delivery_date, delivery_location) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisdss", 
        $birth_id,
        $data['mother_id'],
        isset($data['1']) ? $data['1'] : '',
        isset($data['2']) ? $data['2'] : 0,
        isset($data['3']) ? $data['3'] : '',
        isset($data['4']) ? $data['4'] : ''
    );
    $stmt->execute();
    $stmt->close();
    
    // Clear progress
    $stmt = $conn->prepare("
        DELETE FROM birth_progress 
        WHERE birth_id = ? AND phone_number = ?
    ");
    $stmt->bind_param("is", $birth_id, $phone_number);
    $stmt->execute();
    $stmt->close();
}

// --- Mother Search Functions ---
function getMotherByPhone($conn, $phone) {
    $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE contact_number = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function getMotherById($conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function getMotherByName($conn, $name) {
    $search_term = "%" . $name . "%";
    $stmt = $conn->prepare("
        SELECT * FROM pregnant_women 
        WHERE full_name LIKE ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}



// Handle Appointment Scheduling
function handleAppointment($conn, $session_id, $phone_number, $input_array) {
    // Parse user input
    $step = count($input_array);

    // Step 1: Search Criteria Selection
    if ($step == 1) {
        echo ussdResponse("CON", "Schedule Appointment\n"
            . "Select search criteria:\n"
            . "1. Phone Number\n"
            . "2. Registration ID\n"
            . "3. Name");
        exit;
    }

    // Step 2: Get Search Term
    if ($step == 2) {
        $search_type = $input_array[1] ?? '';
        switch ($search_type) {
            case '1':
                echo ussdResponse("CON", "Enter mother's phone number:");
                break;
            case '2':
                echo ussdResponse("CON", "Enter registration ID:");
                break;
            case '3':
                echo ussdResponse("CON", "Enter mother's name (partial match):");
                break;
            default:
                echo ussdResponse("END", "Invalid selection");
                exit;
        }
        exit;
    }

    // Step 3: Execute Search
    if ($step == 3) {
        $search_type = $input_array[1];
        $search_term = $input_array[2] ?? '';

        // Perform search based on selected criteria
        $results = [];
        switch ($search_type) {
            case '1': // Phone Number
                $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE contact_number = ?");
                $stmt->bind_param("s", $search_term);
                break;
            case '2': // Registration ID
                $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE registration_id = ?");
                $stmt->bind_param("i", $search_term);
                break;
            case '3': // Name (Partial Match)
                $search_term = "%$search_term%";
                $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE full_name LIKE ?");
                $stmt->bind_param("s", $search_term);
                break;
            default:
                echo ussdResponse("END", "Invalid search criteria");
                exit;
        }

        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($results)) {
            echo ussdResponse("END", "No records found");
            exit;
        }

        // Display matching records
        $response = "CON Select record:\n";
        foreach ($results as $index => $row) {
            $response .= ($index + 1) . ". " 
                . $row['full_name'] 
                . " (ID: " . $row['id'] 
                . ", Phone: " . $row['contact_number'] 
                . ", Age: " . $row['age'] . ")\n";
        }
        echo ussdResponse("CON", $response);
        exit;
    }

    // Step 4: Clinic Selection
    if ($step == 4) {
        $record_index = $input_array[3] ?? '';
        $selected_record = null;

        // Retrieve search results again
        $search_type = $input_array[1];
        $search_term = $input_array[2];
        switch ($search_type) {
            case '1': // Phone Number
                $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE contact_number = ?");
                $stmt->bind_param("s", $search_term);
                break;
            case '2': // Registration ID
                $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE registration_id = ?");
                $stmt->bind_param("i", $search_term);
                break;
            case '3': // Name (Partial Match)
                $search_term = "%$search_term%";
                $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE full_name LIKE ?");
                $stmt->bind_param("s", $search_term);
                break;
        }

        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!isset($results[$record_index - 1])) {
            echo ussdResponse("END", "Invalid selection");
            exit;
        }

        $selected_record = $results[$record_index - 1];

        // Build clinic list
        $clinics = $conn->query("SELECT id, name, location FROM clinics");
        if (!$clinics || $clinics->num_rows === 0) {
            echo ussdResponse("END", "Clinics unavailable. Try later.");
            exit;
        }

        $clinic_list = "CON Select clinic for {$selected_record['full_name']}:\n";
        while ($row = $clinics->fetch_assoc()) {
            $clinic_list .= "{$row['id']}. {$row['name']} ({$row['location']})\n";
        }

        echo ussdResponse("CON", $clinic_list);
        exit;
    }

    // Step 5: Enter Date
    if ($step == 5) {
        $clinic_id = $input_array[4] ?? '';
        $record_index = $input_array[3] ?? '';

        // Validate clinic ID
        $stmt = $conn->prepare("SELECT 1 FROM clinics WHERE id = ?");
        $stmt->bind_param("i", $clinic_id);
        $stmt->execute();
        $clinic_valid = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$clinic_valid) {
            echo ussdResponse("END", "Invalid clinic selection");
            exit;
        }

        echo ussdResponse("CON", "Enter appointment date (YYYY-MM-DD):");
        exit;
    }

    // Step 6: Save Appointment
    if ($step == 6) {
        $date = $input_array[5] ?? '';
        $clinic_id = $input_array[4] ?? '';
        $record_index = $input_array[3] ?? '';
        $search_type = $input_array[1] ?? '';
        $search_term = $input_array[2] ?? '';

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) < strtotime('today')) {
            echo ussdResponse("END", "Invalid date format or past date");
            exit;
        }

        // Retrieve selected record
        switch ($search_type) {
            case '1': // Phone Number
                $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE contact_number = ?");
                $stmt->bind_param("s", $search_term);
                break;
            case '2': // Registration ID
                $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE registration_id = ?");
                $stmt->bind_param("i", $search_term);
                break;
            case '3': // Name (Partial Match)
                $search_term = "%$search_term%";
                $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE full_name LIKE ?");
                $stmt->bind_param("s", $search_term);
                break;
        }

        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!isset($results[$record_index - 1])) {
            echo ussdResponse("END", "Record not found");
            exit;
        }

        $selected_record = $results[$record_index - 1];

        // Save appointment to database
        $mother_id = $selected_record['id'];
        $stmt = $conn->prepare("
            INSERT INTO appointments 
            (mother_id, clinic_id, appointment_date, status, created_at) 
            VALUES (?, ?, ?, 'Scheduled', NOW())
        ");
        $stmt->bind_param("iis", $mother_id, $clinic_id, $date);
        $stmt->execute();
        $stmt->close();

        echo ussdResponse("END", "Appointment scheduled successfully!");
        exit;
    }
}













// Handle Attendance Marking
function handleAttendance($conn, $session_id, $phone_number, $input_array) {
    // Parse user input
    $step = count($input_array);

    // Step 1: Search Criteria Selection
    if ($step == 1) {
        echo ussdResponse("CON", "Mark Appointment Attendance\n"
            . "Select search criteria:\n"
            . "1. Appointment ID\n"
            . "2. Phone Number\n"
            . "3. Name");
        exit;
    }

    // Step 2: Get Search Term
    if ($step == 2) {
        $search_type = $input_array[1] ?? '';
        switch ($search_type) {
            case '1':
                echo ussdResponse("CON", "Enter appointment ID:");
                break;
            case '2':
                echo ussdResponse("CON", "Enter phone number:");
                break;
            case '3':
                echo ussdResponse("CON", "Enter name (partial match):");
                break;
            default:
                echo ussdResponse("END", "Invalid selection");
                exit;
        }
        exit;
    }

    // Step 3: Execute Search
    if ($step == 3) {
        $search_type = $input_array[1];
        $search_term = $input_array[2] ?? '';

        // Perform search based on selected criteria
        $results = [];
        switch ($search_type) {
            case '1': // Appointment ID
                $stmt = $conn->prepare("
                    SELECT appointments.id AS appointment_id, pregnant_women.full_name, clinics.name AS clinic_name, 
                           appointments.appointment_date, appointments.status 
                    FROM appointments
                    JOIN pregnant_women ON appointments.mother_id = pregnant_women.id
                    JOIN clinics ON appointments.clinic_id = clinics.id
                    WHERE appointments.id = ?
                ");
                $stmt->bind_param("i", $search_term);
                break;
            case '2': // Phone Number
                $stmt = $conn->prepare("
                    SELECT appointments.id AS appointment_id, pregnant_women.full_name, clinics.name AS clinic_name, 
                           appointments.appointment_date, appointments.status 
                    FROM appointments
                    JOIN pregnant_women ON appointments.mother_id = pregnant_women.id
                    JOIN clinics ON appointments.clinic_id = clinics.id
                    WHERE pregnant_women.contact_number = ?
                ");
                $stmt->bind_param("s", $search_term);
                break;
            case '3': // Name (Partial Match)
                $search_term = "%$search_term%";
                $stmt = $conn->prepare("
                    SELECT appointments.id AS appointment_id, pregnant_women.full_name, clinics.name AS clinic_name, 
                           appointments.appointment_date, appointments.status 
                    FROM appointments
                    JOIN pregnant_women ON appointments.mother_id = pregnant_women.id
                    JOIN clinics ON appointments.clinic_id = clinics.id
                    WHERE pregnant_women.full_name LIKE ?
                ");
                $stmt->bind_param("s", $search_term);
                break;
        }

        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($results)) {
            echo ussdResponse("END", "No records found");
            exit;
        }

        // Display matching records
        $response = "CON Select appointment:\n";
        foreach ($results as $index => $row) {
            $response .= ($index + 1) . ". " 
                . $row['full_name'] 
                . " (ID: " . $row['appointment_id'] 
                . ", Clinic: " . $row['clinic_name'] 
                . ", Date: " . $row['appointment_date'] 
                . ", Status: " . $row['status'] . ")\n";
        }
        echo ussdResponse("CON", $response);
        exit;
    }

    // Step 4: Mark Attendance
    if ($step == 4) {
        $record_index = $input_array[3] ?? '';
        $search_type = $input_array[1];
        $search_term = $input_array[2];

        // Retrieve search results again
        switch ($search_type) {
            case '1': // Appointment ID
                $stmt = $conn->prepare("
                    SELECT appointments.id AS appointment_id, pregnant_women.full_name, clinics.name AS clinic_name, 
                           appointments.appointment_date, appointments.status 
                    FROM appointments
                    JOIN pregnant_women ON appointments.mother_id = pregnant_women.id
                    JOIN clinics ON appointments.clinic_id = clinics.id
                    WHERE appointments.id = ?
                ");
                $stmt->bind_param("i", $search_term);
                break;
            case '2': // Phone Number
                $stmt = $conn->prepare("
                    SELECT appointments.id AS appointment_id, pregnant_women.full_name, clinics.name AS clinic_name, 
                           appointments.appointment_date, appointments.status 
                    FROM appointments
                    JOIN pregnant_women ON appointments.mother_id = pregnant_women.id
                    JOIN clinics ON appointments.clinic_id = clinics.id
                    WHERE pregnant_women.contact_number = ?
                ");
                $stmt->bind_param("s", $search_term);
                break;
            case '3': // Name (Partial Match)
                $search_term = "%$search_term%";
                $stmt = $conn->prepare("
                    SELECT appointments.id AS appointment_id, pregnant_women.full_name, clinics.name AS clinic_name, 
                           appointments.appointment_date, appointments.status 
                    FROM appointments
                    JOIN pregnant_women ON appointments.mother_id = pregnant_women.id
                    JOIN clinics ON appointments.clinic_id = clinics.id
                    WHERE pregnant_women.full_name LIKE ?
                ");
                $stmt->bind_param("s", $search_term);
                break;
        }

        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!isset($results[$record_index - 1])) {
            echo ussdResponse("END", "Invalid selection");
            exit;
        }

        $selected_record = $results[$record_index - 1];

        // Prompt for attendance status
        echo ussdResponse("CON", "Mark attendance for {$selected_record['full_name']} (#{$selected_record['appointment_id']}):
1. Attended
2. Missed
3. Rescheduled");
        exit;
    }

    // Step 5: Confirm Attendance
    if ($step == 5) {
        $record_index = $input_array[3] ?? '';
        $attendance_status = $input_array[4] ?? '';
        $search_type = $input_array[1];
        $search_term = $input_array[2];

        // Validate attendance status
        $valid_statuses = ['1', '2', '3'];
        if (!in_array($attendance_status, $valid_statuses)) {
            echo ussdResponse("END", "Invalid status selection");
            exit;
        }

        // Retrieve search results again
        switch ($search_type) {
            case '1': // Appointment ID
                $stmt = $conn->prepare("
                    SELECT appointments.id AS appointment_id, pregnant_women.full_name, pregnant_women.contact_number 
                    FROM appointments
                    JOIN pregnant_women ON appointments.mother_id = pregnant_women.id
                    WHERE appointments.id = ?
                ");
                $stmt->bind_param("i", $search_term);
                break;
            case '2': // Phone Number
                $stmt = $conn->prepare("
                    SELECT appointments.id AS appointment_id, pregnant_women.full_name, pregnant_women.contact_number 
                    FROM appointments
                    JOIN pregnant_women ON appointments.mother_id = pregnant_women.id
                    WHERE pregnant_women.contact_number = ?
                ");
                $stmt->bind_param("s", $search_term);
                break;
            case '3': // Name (Partial Match)
                $search_term = "%$search_term%";
                $stmt = $conn->prepare("
                    SELECT appointments.id AS appointment_id, pregnant_women.full_name, pregnant_women.contact_number 
                    FROM appointments
                    JOIN pregnant_women ON appointments.mother_id = pregnant_women.id
                    WHERE pregnant_women.full_name LIKE ?
                ");
                $stmt->bind_param("s", $search_term);
                break;
        }

        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!isset($results[$record_index - 1])) {
            echo ussdResponse("END", "Record not found");
            exit;
        }

        $selected_record = $results[$record_index - 1];

        // Map status codes to meaningful values
        $status_map = [
            '1' => 'Attended',
            '2' => 'Missed',
            '3' => 'Rescheduled'
        ];
        $new_status = $status_map[$attendance_status];

        // Update the database
        $stmt = $conn->prepare("
            UPDATE appointments 
            SET status = ? 
            WHERE id = ?
        ");
        $stmt->bind_param("si", $new_status, $selected_record['appointment_id']);
        $stmt->execute();
        $stmt->close();

        // Send notification to patient
        $notification_message = "";
        switch ($attendance_status) {
            case '1':
                $notification_message = "Thank you for attending your appointment at Health Center A on 2025-03-30.";
                break;
            case '2':
                $notification_message = "You missed your appointment at Health Center A on 2025-03-30. Please contact the clinic.";
                break;
            case '3':
                $notification_message = "Your appointment at Health Center A has been rescheduled. Please check with the clinic for the new date.";
                break;
        }

    

        echo ussdResponse("END", "Attendance marked as '$new_status' successfully!");
        exit;
    }
}


































// --- Vaccine Scheduling Function ---
function handleVaccineSchedule_VS($conn, $session_id, $phone_number, $input_array) {
    // Remove main menu option from input array
    array_shift($input_array);
    $step = count($input_array);

    // Retrieve progress data
    $progress = getVaccineScheduleProgress_VS($conn, $session_id);
    $data = $progress ? json_decode($progress['data'], true) : [];

    switch ($step) {
        case 0:
            echo ussdResponse("CON", "Vaccine Scheduler\n1. Search by Phone\n2. Search by ID\n3. Search by Name");
            saveVaccineScheduleProgress_VS($conn, $session_id, []);
            exit;

        case 1:
            $search_type = $input_array[0] ?? '';
            if (!in_array($search_type, ['1', '2', '3'])) {
                echo ussdResponse("END", "Invalid selection");
                exit;
            }
            $data['search_type'] = $search_type;
            saveVaccineScheduleProgress_VS($conn, $session_id, $data);
            $prompts = [
                1 => "Enter mother's phone number (10 digits):",
                2 => "Enter registration ID:",
                3 => "Enter mother's name (partial match):"
            ];
            echo ussdResponse("CON", $prompts[$search_type]);
            exit;

        case 2:
            $search_term = $input_array[1] ?? '';
            $search_type = $data['search_type'];

            // Validate search term
            switch ($search_type) {
                case '1': // Phone
                    if (!preg_match('/^\d{10}$/', $search_term)) {
                        echo ussdResponse("END", "Invalid phone number");
                        exit;
                    }
                    break;
                case '2': // ID
                    if (!is_numeric($search_term)) {
                        echo ussdResponse("END", "Invalid registration ID");
                        exit;
                    }
                    break;
                case '3': // Name
                    if (strlen($search_term) < 3) {
                        echo ussdResponse("END", "Name must be 3+ characters");
                        exit;
                    }
                    $search_term = "%$search_term%";
                    break;
            }

            // Fetch mothers from pregnant_women table
            $mothers = searchMothers_VS($conn, $search_type, $search_term);
            if (empty($mothers)) {
                echo ussdResponse("END", "No records found");
                exit;
            }

            // Store results and prompt selection
            $data['mothers'] = $mothers;
            $data['search_term'] = $search_term;
            saveVaccineScheduleProgress_VS($conn, $session_id, $data);

            $response = "CON Select mother:\n";
            foreach ($mothers as $i => $m) {
                $response .= ($i + 1) . ". " . $m['full_name'] 
                    . " (ID:{$m['id']}, Phone:{$m['contact_number']})\n";
            }
            echo ussdResponse("CON", $response);
            exit;

        case 3:
            $mother_index = $input_array[2] - 1;
            $mothers = $data['mothers'] ?? [];
            if (!isset($mothers[$mother_index])) {
                echo ussdResponse("END", "Invalid selection");
                exit;
            }

            $selected_mother = $mothers[$mother_index];
            $data['mother_id'] = $selected_mother['id'];
            saveVaccineScheduleProgress_VS($conn, $session_id, $data);

            echo ussdResponse("CON", "Select vaccine type:\n1. Tetanus Toxoid 1\n2. Tetanus Toxoid 2\n3. Influenza\n4. Other");
            exit;

        case 4:
            $vaccine_type = [
                1 => 'Tetanus Toxoid 1',
                2 => 'Tetanus Toxoid 2',
                3 => 'Influenza',
                4 => 'Other'
            ][$input_array[3]] ?? 'Invalid';

            if ($vaccine_type == 'Invalid') {
                echo ussdResponse("END", "Invalid vaccine type");
                exit;
            }

            $data['vaccine_type'] = $vaccine_type;
            saveVaccineScheduleProgress_VS($conn, $session_id, $data);
            echo ussdResponse("CON", "Enter vaccination date (YYYY-MM-DD):");
            exit;

        case 5:
            $date = $input_array[4] ?? '';
            if (!validateDate_VS($date)) {
                echo ussdResponse("END", "Invalid date");
                exit;
            }

            // Save vaccination to database
            $stmt = $conn->prepare("
                INSERT INTO vaccination_schedule 
                (mother_id, vaccine_type, scheduled_date) 
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iss", 
                $data['mother_id'],
                $data['vaccine_type'],
                $date
            );
            $stmt->execute();
            $stmt->close();

            // Fetch clinic name for nurse
            $clinic_name = getNurseClinicName_VS($conn, $phone_number);

            // Send SMS using PROVEN registration logic
            $mother_phone = $data['mothers'][$input_array[2] - 1]['contact_number'];
            $message = "Vaccination scheduled for {$data['vaccine_type']} on $date at $clinic_name.";
            $sms_result = sendSms_VS($mother_phone, $message); // Reuse registration SMS function

            // Clear progress
            clearVaccineScheduleProgress_VS($conn, $session_id);

            if (!$sms_result) {
                echo ussdResponse("END", "Vaccination scheduled");
                exit;
            }

            echo ussdResponse("END", "Vaccine scheduled successfully!");
            exit;

        default:
            echo ussdResponse("END", "Invalid step. Restart.");
            exit;
    }
}

// --- SMS Functions ---

// Send SMS via Mambo API
function sendSms_VS($phone_number, $message) {
    $token = getBearerToken_VS();
    if (!$token) {
        error_log("SMS Hana Idhini: Tafadhali angalia kredenti zako."); // Log authentication failure
        return false;
    }

    $url = "https://mambosms.co.tz/api/v1/sms/single";
    $data = [
        "sender_id" => "InfoNotice", // Approved sender ID
        "message" => $message,
        "mobile" => $phone_number // Local format (no +255)
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

    if (curl_errno($ch)) {
        error_log("cURL Error: " . curl_error($ch)); // Log cURL errors
        curl_close($ch);
        return false;
    }
    curl_close($ch);

    error_log("SMS API Response: " . $response); // Log raw response
    $responseData = json_decode($response, true);

    if (isset($responseData['status']) && $responseData['status'] === "success") {
        return true;
    } else {
        error_log("SMS Error: " . ($responseData['message'] ?? "Unknown error")); // Log API error
        return false;
    }
}

// Authenticate with Mambo API
function getBearerToken_VS() {
    $url = "https://mambosms.co.tz/api/v1/login";
    $data = [
        "phone_number" => "0756598665", // Nurse’s phone number
        "password" => "LuisPedro19"     // Nurse’s password
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);
    if (isset($responseData['success']) && $responseData['success'] == 1) {
        return $responseData['data']['token'];
    } else {
        error_log("Auth Failed: " . ($responseData['message'] ?? "No response")); // Log authentication failure
        return null;
    }
}

// --- Helper Functions ---

// Validate date format and future date
function validateDate_VS($date) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) >= strtotime('today');
}

// Fetch clinic name for nurse
function getNurseClinicName_VS($conn, $phone_number) {
    $stmt = $conn->prepare("
        SELECT clinics.name 
        FROM health_workers 
        JOIN clinics ON health_workers.clinic_id = clinics.id 
        WHERE health_workers.phone_number = ?
    ");
    $stmt->bind_param("s", $phone_number);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['name'] ?? "Kliniki Haijulikani";
}

// Search mothers in pregnant_women table
function searchMothers_VS($conn, $type, $term) {
    switch ($type) {
        case '1': // Phone
            $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE contact_number = ?");
            $stmt->bind_param("s", $term);
            break;
        case '2': // ID
            $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE id = ?");
            $stmt->bind_param("i", $term);
            break;
        case '3': // Name
            $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE full_name LIKE ?");
            $stmt->bind_param("s", $term);
            break;
        default:
            return [];
    }
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $results;
}

// Progress tracking functions
function getVaccineScheduleProgress_VS($conn, $session_id) {
    $stmt = $conn->prepare("
        SELECT data 
        FROM vaccine_schedule_progress 
        WHERE session_id = ? 
        ORDER BY updated_at DESC LIMIT 1
    ");
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}

function saveVaccineScheduleProgress_VS($conn, $session_id, $data) {
    $json_data = json_encode($data);
    $sql = "
        INSERT INTO vaccine_schedule_progress 
        (session_id, data) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE 
        data = VALUES(data)
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $session_id, $json_data);
    $stmt->execute();
    $stmt->close();
}

function clearVaccineScheduleProgress_VS($conn, $session_id) {
    $stmt = $conn->prepare("
        DELETE FROM vaccine_schedule_progress 
        WHERE session_id = ?
    ");
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
    $stmt->close();
}





























function handleVaccinationUpdate($conn, $session_id, $phone_number, $input_array) {
    array_shift($input_array); // Remove main menu option (e.g., "5")
    $step = count($input_array);

    // Retrieve progress data
    $progress = getVaccinationUpdateProgress_VD($conn, $session_id, $phone_number);
    $data = $progress ? json_decode($progress['data'], true) : [];
    $data = is_array($data) ? $data : [];

    switch ($step) {
        case 0: // Step 1: Display search options
            echo ussdResponse("CON", "Mark Vaccination\n1. Search by Phone\n2. Search by ID\n3. Search by Name");
            saveVaccinationUpdateProgress_VD($conn, $session_id, $phone_number, 1, []);
            exit;

        case 1: // Step 2: Capture search type
            $search_type = $input_array[0] ?? '';
            if (!in_array($search_type, ['1', '2', '3'])) {
                echo ussdResponse("END", "Invalid selection");
                exit;
            }
            $data['search_type'] = $search_type;
            saveVaccinationUpdateProgress_VD($conn, $session_id, $phone_number, 2, $data);
            
            $prompts = [
                1 => "Enter phone number (10 digits):",
                2 => "Enter registration ID:",
                3 => "Enter name (partial match):"
            ];
            echo ussdResponse("CON", $prompts[$search_type]);
            exit;

        case 2: // Step 3: Validate search term and fetch mothers
            $search_term = $input_array[1] ?? '';
            $search_type = $data['search_type'] ?? '';

            // Validate search term
            switch ($search_type) {
                case '1': // Phone
                    if (!preg_match('/^\d{10}$/', $search_term)) {
                        echo ussdResponse("END", "Invalid phone number");
                        exit;
                    }
                    break;
                case '2': // ID
                    if (!is_numeric($search_term)) {
                        echo ussdResponse("END", "Invalid registration ID");
                        exit;
                    }
                    break;
                case '3': // Name
                    if (strlen($search_term) < 3) {
                        echo ussdResponse("END", "Name must be at least 3 characters");
                        exit;
                    }
                    $search_term = "%" . strtolower(trim($search_term)) . "%";
                    break;
            }

            // Fetch mothers
            try {
                switch ($search_type) {
                    case '1':
                        $stmt = $conn->prepare("SELECT id, full_name FROM pregnant_women WHERE contact_number = ?");
                        $stmt->bind_param("s", $search_term);
                        break;
                    case '2':
                        $stmt = $conn->prepare("SELECT id, full_name FROM pregnant_women WHERE id = ?");
                        $stmt->bind_param("i", $search_term);
                        break;
                    case '3':
                        $stmt = $conn->prepare("SELECT id, full_name FROM pregnant_women WHERE LOWER(TRIM(full_name)) LIKE ?");
                        $stmt->bind_param("s", $search_term);
                        break;
                }
                $stmt->execute();
                $mothers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                if (empty($mothers)) {
                    echo ussdResponse("END", "No mothers found");
                    exit;
                }

                $data['mothers'] = $mothers;
                saveVaccinationUpdateProgress_VD($conn, $session_id, $phone_number, 3, $data);

                $response = "CON Select mother:\n";
                foreach ($mothers as $i => $m) {
                    $response .= ($i + 1) . ". " . $m['full_name'] . " (ID: " . $m['id'] . ")\n";
                }
                echo ussdResponse("CON", $response);
                exit;
            } catch (Exception $e) {
                error_log("Database Error: " . $e->getMessage());
                echo ussdResponse("END", "Database error. Try later.");
                exit;
            }

        case 3: // Step 4: Select mother and fetch her vaccines
            $mother_index = $input_array[2] - 1;
            $mothers = $data['mothers'] ?? [];

            if (!isset($mothers[$mother_index])) {
                echo ussdResponse("END", "Invalid mother selection");
                exit;
            }

            $selected_mother = $mothers[$mother_index];
            $data['mother_id'] = $selected_mother['id'];

            // Fetch pending vaccines for selected mother
            try {
                $stmt = $conn->prepare("SELECT id, vaccine_type FROM vaccination_schedule WHERE mother_id = ? AND status = 'Scheduled'");
                $stmt->bind_param("i", $selected_mother['id']);
                $stmt->execute();
                $vaccines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                if (empty($vaccines)) {
                    echo ussdResponse("END", "No pending vaccines");
                    exit;
                }

                $data['vaccines'] = $vaccines;
                saveVaccinationUpdateProgress_VD($conn, $session_id, $phone_number, 4, $data);

                $response = "CON Select vaccine:\n";
                foreach ($vaccines as $i => $v) {
                    $response .= ($i + 1) . ". " . $v['vaccine_type'] . "\n";
                }
                echo ussdResponse("CON", $response);
                exit;
            } catch (Exception $e) {
                error_log("Database Error: " . $e->getMessage());
                echo ussdResponse("END", "Database error. Try later.");
                exit;
            }

        case 4: // Step 5: Select vaccine and prompt for status
            $vaccine_index = $input_array[3] - 1;
            $vaccines = $data['vaccines'] ?? [];

            if (!isset($vaccines[$vaccine_index])) {
                echo ussdResponse("END", "Invalid vaccine selection");
                exit;
            }

            $selected_vaccine = $vaccines[$vaccine_index];
            $data['vaccine_id'] = $selected_vaccine['id'];
            saveVaccinationUpdateProgress_VD($conn, $session_id, $phone_number, 5, $data);

            echo ussdResponse("CON", "Update status for " . $selected_vaccine['vaccine_type'] . ":\n1. Completed\n2. Missed\n3. Reschedule");
            exit;

        case 5: // Step 6: Update status
            $status_choice = $input_array[4] ?? '';
            $valid_choices = ['1', '2', '3'];

            if (!in_array($status_choice, $valid_choices)) {
                echo ussdResponse("END", "Invalid status selection");
                exit;
            }

            $status_map = [
                '1' => 'Completed',
                '2' => 'Missed',
                '3' => 'Rescheduled'
            ];
            $status_value = $status_map[$status_choice];

            // Update vaccination status
            try {
                $stmt = $conn->prepare("UPDATE vaccination_schedule SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $status_value, $data['vaccine_id']);
                $stmt->execute();
                $stmt->close();
            } catch (Exception $e) {
                error_log("Database Error: " . $e->getMessage());
                echo ussdResponse("END", "Update failed. Try later.");
                exit;
            }

            // Clear progress
            clearVaccinationUpdateProgress_VD($conn, $session_id, $phone_number);

            // Send SMS
            $mother_phone = $data['mothers'][$mother_index]['contact_number'] ?? '';
            $message = "Vaccination status for {$selected_vaccine['vaccine_type']} updated to $status_value.";
            $sms_result = sendSms_VD($mother_phone, $message);

            if (!$sms_result) {
                echo ussdResponse("END", "Status updated.");
                exit;
            }

            echo ussdResponse("END", "Vaccination status updated successfully!");
            exit;

        default:
            echo ussdResponse("END", "Invalid step. Restart.");
            exit;
    }
}

// --- Progress Tracking ---
function getVaccinationUpdateProgress_VD($conn, $session_id, $phone) {
    $stmt = $conn->prepare("SELECT * FROM vaccination_update_progress WHERE session_id = ? AND phone_number = ? ORDER BY updated_at DESC LIMIT 1");
    $stmt->bind_param("ss", $session_id, $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    $progress = $result->fetch_assoc();
    $stmt->close();
    return $progress ?: [];
}

function saveVaccinationUpdateProgress_VD($conn, $session_id, $phone, $step, $data) {
    $json_data = json_encode($data);
    $stmt = $conn->prepare("INSERT INTO vaccination_update_progress (session_id, phone_number, step, data) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE step = VALUES(step), data = VALUES(data)");
    $stmt->bind_param("ssis", $session_id, $phone, $step, $json_data);
    $stmt->execute();
    $stmt->close();
}

function clearVaccinationUpdateProgress_VD($conn, $session_id, $phone) {
    $stmt = $conn->prepare("DELETE FROM vaccination_update_progress WHERE session_id = ? AND phone_number = ?");
    $stmt->bind_param("ss", $session_id, $phone);
    $stmt->execute();
    $stmt->close();
}

// --- SMS ---
function sendSms_VD($phone, $message) {
    $token = getBearerToken_VD();
    if (!$token) return false;

    $url = "https://mambosms.co.tz/api/v1/sms/single";
    $data = [
        "sender_id" => "InfoNotice",
        "message" => $message,
        "mobile" => $phone
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "Authorization: Bearer " . $token]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    return isset(json_decode($response, true)['status']) && json_decode($response, true)['status'] === "success";
}

function getBearerToken_VD() {
    $url = "https://mambosms.co.tz/api/v1/login";
    $data = [
        "phone_number" => "0756598665",
        "password" => "LuisPedro19"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    return isset(json_decode($response, true)['data']['token']) ? json_decode($response, true)['data']['token'] : null;
}


?>