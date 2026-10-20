<?php
// update_profile.php - FIXED VERSION
session_start();
require_once 'config.php';

if (!isset($_SESSION['isLoggedIn']) || $_SESSION['isLoggedIn'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['currentUser']['id'] ?? 0;
    
    if ($userId === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    // Get POST data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Validation
    if (empty($name) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Name and email are required']);
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit();
    }
    
    try {
        // Check if email already exists (for another user)
        $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkEmail->bind_param("si", $email, $userId);
        $checkEmail->execute();
        $result = $checkEmail->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already in use by another account']);
            exit();
        }
        
        // Update user in database
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, organization = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $email, $organization, $phone, $userId);
        
        if ($stmt->execute()) {
            // Update session data
            $_SESSION['currentUser']['name'] = $name;
            $_SESSION['currentUser']['email'] = $email;
            $_SESSION['currentUser']['organization'] = $organization;
            $_SESSION['currentUser']['phone'] = $phone;
            
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        }
    } catch (Exception $e) {
        error_log("Profile update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>