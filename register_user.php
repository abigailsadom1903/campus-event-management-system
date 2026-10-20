<?php
// register_user.php
require_once 'config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit();
}

try {
    $category = $data['category'] ?? 'public';
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $phone = $data['phone'] ?? '';
    $organization = $data['organization'] ?? '';
    $events = isset($data['events']) ? json_encode($data['events']) : '[]';
    $password = $data['password'] ?? '';
    $role = $data['category']; // Same as category for now
    
    // Hash password (in real app, use password_hash())
    $hashedPassword = md5($password); // Simple hash for demo
    
    // Check if email exists
    $checkSql = "SELECT id FROM users WHERE email = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit();
    }
    
    // Insert user
    $sql = "INSERT INTO users (category, name, email, phone, organization, events, password, role) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", $category, $name, $email, $phone, $organization, $events, $hashedPassword, $role);
    
    if ($stmt->execute()) {
        // Also set session
        $_SESSION['isLoggedIn'] = true;
        $_SESSION['currentUser'] = [
            'id' => $stmt->insert_id,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'organization' => $organization,
            'phone' => $phone
        ];
        
        echo json_encode(['success' => true, 'message' => 'User registered successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>