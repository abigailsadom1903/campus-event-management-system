<?php
// save_event.php - Handles both creating and updating events
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in first']);
    exit;
}

$userId = $_SESSION['user_id'];

// Get current user info
$user_sql = "SELECT name, email FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $userId);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

// Get POST data - using YOUR ACTUAL COLUMN NAMES
$title = $_POST['event_name'] ?? ''; // This is 'title' in database
$description = $_POST['description'] ?? '';
$event_type = $_POST['event_type'] ?? 'workshop';
$location = $_POST['venue'] ?? ''; // This is 'location' in database
$date = $_POST['event_date'] ?? ''; // This is 'date' in database
$time = $_POST['event_time'] ?? ''; // This is 'time' in database
$max_participants = isset($_POST['max_participants']) ? intval($_POST['max_participants']) : 0;
$fee = $_POST['fee'] ?? 'free';
$fee_amount = isset($_POST['fee_amount']) ? floatval($_POST['fee_amount']) : 0.00;
$reg_close_date = $_POST['reg_close_date'] ?? '';
$contact_person = $_POST['contact_person'] ?? '';
$contact_no = $_POST['contact_no'] ?? '';
$remarks = $_POST['remarks'] ?? '';

// Handle image upload
$image_path = '';
if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] == UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/events/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_ext = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $file_ext;
    $target_path = $upload_dir . $filename;
    
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    if (in_array(strtolower($file_ext), $allowed_types)) {
        if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target_path)) {
            $image_path = $target_path;
        }
    }
} elseif (isset($_POST['image_path']) && !empty($_POST['image_path'])) {
    $image_path = $_POST['image_path'];
}

// Validate required fields
if (empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Event title is required']);
    exit;
}
if (empty($date)) {
    echo json_encode(['success' => false, 'message' => 'Event date is required']);
    exit;
}
if (empty($time)) {
    echo json_encode(['success' => false, 'message' => 'Event time is required']);
    exit;
}
if (empty($location)) {
    echo json_encode(['success' => false, 'message' => 'Location is required']);
    exit;
}

// Set default registration close date if not provided
if (empty($reg_close_date)) {
    $reg_close_date = $date;
}

// Set status
$status = 'active';

// Determine action
$action = 'create';
$event_id = null;

if (isset($_POST['event_id']) && !empty($_POST['event_id'])) {
    $action = 'update';
    $event_id = intval($_POST['event_id']);
    
    // Verify the event belongs to current user
    $check_sql = "SELECT organizer_id FROM events WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $event_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows > 0) {
        $event_owner = $check_result->fetch_assoc();
        if ($event_owner['organizer_id'] != $userId) {
            echo json_encode(['success' => false, 'message' => 'You can only edit your own events']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }
    $check_stmt->close();
}

try {
    if ($action === 'create') {
        // Insert new event - USING YOUR ACTUAL COLUMN NAMES
        $sql = "INSERT INTO events (
            title, 
            description, 
            event_type,
            location, 
            date, 
            time, 
            max_participants, 
            fee, 
            fee_amount,
            reg_close_date,
            contact_person, 
            contact_no, 
            remarks, 
            image_path,
            status, 
            organizer_id,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // Bind parameters - note the order matches the INSERT statement
        $stmt->bind_param(
            "ssssssiisdssssssi",
            $title,            // s - string
            $description,      // s - string
            $event_type,       // s - string
            $location,         // s - string
            $date,             // s - string
            $time,             // s - string
            $max_participants, // i - integer
            $fee,              // s - string
            $fee_amount,       // d - double/float
            $reg_close_date,   // s - string
            $contact_person,   // s - string
            $contact_no,       // s - string
            $remarks,          // s - string
            $image_path,       // s - string
            $status,           // s - string
            $userId            // i - integer (organizer_id)
        );
        
        if ($stmt->execute()) {
            $new_event_id = $stmt->insert_id;
            
            $_SESSION['flash_message'] = 'Event created successfully!';
            $_SESSION['flash_message_type'] = 'success';
            
            echo json_encode([
                'success' => true, 
                'message' => 'Event created successfully', 
                'eventId' => $new_event_id,
                'action' => 'create',
                'redirect' => 'dashboard.php'
            ]);
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
    } elseif ($action === 'update') {
        // Update existing event
        $sql = "UPDATE events SET
            title = ?, 
            description = ?, 
            event_type = ?,
            location = ?, 
            date = ?, 
            time = ?, 
            max_participants = ?, 
            fee = ?, 
            fee_amount = ?,
            reg_close_date = ?,
            contact_person = ?, 
            contact_no = ?, 
            remarks = ?,
            image_path = COALESCE(?, image_path),
            updated_at = NOW()
            WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // Use existing image if not updating
        $image_to_use = !empty($image_path) ? $image_path : null;
        
        $stmt->bind_param(
            "ssssssiisdssssi",
            $title,
            $description,
            $event_type,
            $location,
            $date,
            $time,
            $max_participants,
            $fee,
            $fee_amount,
            $reg_close_date,
            $contact_person,
            $contact_no,
            $remarks,
            $image_to_use,
            $event_id
        );
        
        if ($stmt->execute()) {
            $_SESSION['flash_message'] = 'Event updated successfully!';
            $_SESSION['flash_message_type'] = 'success';
            
            echo json_encode([
                'success' => true, 
                'message' => 'Event updated successfully', 
                'eventId' => $event_id,
                'action' => 'update',
                'redirect' => 'dashboard.php'
            ]);
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
    
} catch (Exception $e) {
    error_log("Save Event Error: " . $e->getMessage());
    $_SESSION['flash_message'] = 'Error saving event: ' . $e->getMessage();
    $_SESSION['flash_message_type'] = 'error';
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();
}
?>