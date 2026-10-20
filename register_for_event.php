<?php
// register_for_event.php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php?error=Please login to register for events');
    exit();
}

$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id'])) {
    $event_id = intval($_POST['event_id']);
    
    try {
        // Get event details
        $sql = "SELECT e.*, 
                (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id AND r.status = 'registered') as registered_count
                FROM events e 
                WHERE e.id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Event not found');
        }
        
        $event = $result->fetch_assoc();
        $stmt->close();
        
        // Check if already registered
        $check_sql = "SELECT id FROM event_registrations WHERE event_id = ? AND user_id = ? AND status = 'registered'";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $event_id, $user['id']);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            throw new Exception('You are already registered for this event');
        }
        $check_stmt->close();
        
        // Check registration date
        $current_date = date('Y-m-d');
        if ($event['reg_close_date'] && $event['reg_close_date'] < $current_date) {
            throw new Exception('Registration for this event is closed');
        }
        
        // Check capacity
        if ($event['max_participants'] && $event['registered_count'] >= $event['max_participants']) {
            throw new Exception('This event is full');
        }
        
        // Insert registration
        $insert_sql = "INSERT INTO event_registrations (event_id, user_id, user_email, user_name, registration_date, status) 
                      VALUES (?, ?, ?, ?, NOW(), 'registered')";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iiss", $event_id, $user['id'], $user['email'], $user['name']);
        
        if ($insert_stmt->execute()) {
            // Update event last_updated
            $update_sql = "UPDATE events SET last_updated = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $event_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Set success message
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => 'Successfully registered for ' . htmlspecialchars($event['event_name']),
                'timestamp' => time()
            ];
            
            header('Location: dashboard.php');
            exit();
        } else {
            throw new Exception('Registration failed: ' . $conn->error);
        }
        
    } catch (Exception $e) {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => $e->getMessage(),
            'timestamp' => time()
        ];
        header('Location: index.php');
        exit();
    }
} else {
    header('Location: index.php');
    exit();
}
?>