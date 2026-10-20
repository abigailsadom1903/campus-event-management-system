<?php
// set_session.php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the JSON data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($data) {
        // Store user data in session
        $_SESSION['currentUser'] = [
            'id' => $data['id'] ?? '',
            'email' => $data['email'] ?? '',
            'name' => $data['name'] ?? '',
            'role' => $data['role'] ?? '',
            'organization' => $data['organization'] ?? '',
            'phone' => $data['phone'] ?? ''
        ];
        
        $_SESSION['isLoggedIn'] = $data['isLoggedIn'] ?? true;
        $_SESSION['is_admin'] = $data['is_admin'] ?? ($data['role'] === 'admin');
        
        echo json_encode(['success' => true, 'message' => 'Session set successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>