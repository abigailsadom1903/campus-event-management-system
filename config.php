<?php
// config.php - Database and session configuration

// Enhanced session configuration
if (session_status() === PHP_SESSION_NONE) {
    // Session security settings
    ini_set('session.cookie_secure', 0);          // 0 for localhost, 1 for HTTPS
    ini_set('session.cookie_httponly', 1);        // Prevent JavaScript access
    ini_set('session.use_only_cookies', 1);       // Only use cookies
    ini_set('session.cookie_samesite', 'Lax');    // CSRF protection
    
    // Session file settings
    ini_set('session.gc_maxlifetime', 86400);     // 24 hours session lifetime
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);
    
    session_start();
}

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';  // Change to your MySQL password
$database = 'cemsindv';  // Make sure this matches your database name

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed. Please try again later.");
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Error reporting - more detailed for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// ==================== ADD MISSING FUNCTIONS ====================

// ADD THIS FUNCTION - dashboard.php calls setFlashMessage()
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message,
        'timestamp' => time()
    ];
}

// FIXED: tableExists function - Fixed SQL syntax
function tableExists($tableName) {
    global $conn;
    
    // FIX: Use direct query instead of prepared statement for SHOW TABLES
    $sql = "SHOW TABLES LIKE '$tableName'";
    $result = $conn->query($sql);
    
    if (!$result) {
        error_log("Error checking table existence: " . $conn->error);
        return false;
    }
    
    $exists = $result->num_rows > 0;
    $result->free();
    
    return $exists;
}

// ==================== END OF ADDED FUNCTIONS ====================

// Helper function to check if user is logged in
function isLoggedIn() {
    // Additional security: check session regeneration
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    } elseif (time() - $_SESSION['CREATED'] > 3600) {
        // Session started more than 1 hour ago
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time();
    }
    
    return isset($_SESSION['isLoggedIn']) && $_SESSION['isLoggedIn'] === true && isset($_SESSION['user_id']);
}

// Fixed Helper function to get current user
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    // Check if we need to refresh user data from database
    global $conn;
    $userId = $_SESSION['user_id'];
    
    // Fetch fresh data from database to ensure consistency
    $stmt = $conn->prepare("SELECT id, name, email, role, organization, phone, created_at FROM users WHERE id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return null;
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        // Store in session for efficiency
        $_SESSION['currentUser'] = $user;
        return $user;
    }
    
    // If user not found in DB but session exists, clear session
    session_destroy();
    return null;
}

// Helper function to check if user can create events
function canCreateEvents() {
    $user = getCurrentUser();
    if (!$user) return false;
    
    return in_array($user['role'], ['staff', 'student', 'organizer', 'admin']);
}

// Helper function to redirect with message
function redirectWithMessage($url, $type, $message) {
    setFlashMessage($type, $message); // Use the new function
    
    // Clear any output buffer
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    header("Location: $url");
    exit();
}

// Helper function to display flash message (SINGULAR - for backward compatibility)
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        // Check if message is expired (5 minutes)
        if (time() - $_SESSION['flash_message']['timestamp'] > 300) {
            unset($_SESSION['flash_message']);
            return;
        }
        
        $type = $_SESSION['flash_message']['type'];
        $message = $_SESSION['flash_message']['message'];
        
        // Use dashboard.php's CSS classes
        $cssClass = '';
        switch($type) {
            case 'success': $cssClass = 'message-success'; break;
            case 'error': $cssClass = 'message-error'; break;
            case 'warning': $cssClass = 'message-warning'; break;
            case 'info': $cssClass = 'message-info'; break;
            default: $cssClass = 'message-info';
        }
        
        echo '<div class="message ' . htmlspecialchars($cssClass) . '">'
             . '<i class="fas fa-info-circle"></i>'
             . htmlspecialchars($message)
             . '</div>';
        
        unset($_SESSION['flash_message']);
    }
}

// Helper function to display flash messages (PLURAL - matches dashboard.php call)
function displayFlashMessages() {
    displayFlashMessage();
}

// Check if user is authenticated (for pages that require login)
function requireAuth() {
    if (!isLoggedIn()) {
        setFlashMessage('error', 'Please login to access this page');
        header('Location: login.php');
        exit();
    }
}

// Check if user has specific role
function requireRole($requiredRole) {
    requireAuth();
    $user = getCurrentUser();
    
    if (!$user || !in_array($user['role'], (array)$requiredRole)) {
        setFlashMessage('error', 'Access denied. Required role: ' . implode(', ', (array)$requiredRole));
        header('Location: dashboard.php');
        exit();
    }
}

// Sanitize input data
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Debug function to check session
function debugSession() {
    if (isset($_SESSION['currentUser'])) {
        error_log("Session User: " . print_r($_SESSION['currentUser'], true));
    }
    error_log("isLoggedIn: " . (isset($_SESSION['isLoggedIn']) ? 'true' : 'false'));
}

// Auto-clean old flash messages
if (isset($_SESSION['flash_message']) && isset($_SESSION['flash_message']['timestamp'])) {
    if (time() - $_SESSION['flash_message']['timestamp'] > 300) {
        unset($_SESSION['flash_message']);
    }
}

// Also, add this to ensure session is working
function checkSession() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        error_log("Session not active!");
        return false;
    }
    return true;
}

// Initialize session time if not set
if (!isset($_SESSION['CREATED'])) {
    $_SESSION['CREATED'] = time();
}

// Add these missing functions that dashboard.php needs:

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'F j, Y') {
    return date($format, strtotime($date));
}

/**
 * Format time for display
 */
function formatTime($time, $format = 'g:i A') {
    return date($format, strtotime($time));
}

/**
 * Format currency (Malaysian Ringgit)
 */
function formatCurrency($amount) {
    return 'RM ' . number_format($amount, 2);
}

/**
 * Get event registration count
 */
function getEventRegistrationCount($eventId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM event_registrations WHERE event_id = ? AND status = 'registered'");
    if (!$stmt) {
        return 0;
    }
    
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row ? $row['count'] : 0;
    $stmt->close();
    
    return $count;
}

/**
 * Check if column exists in a table
 */
function columnExists($table, $column) {
    global $conn;
    
    // FIX: Use direct query instead of prepared statement for SHOW COLUMNS
    $sql = "SHOW COLUMNS FROM $table LIKE '$column'";
    $result = $conn->query($sql);
    
    if (!$result) {
        error_log("Error checking column existence: " . $conn->error);
        return false;
    }
    
    $exists = $result->num_rows > 0;
    $result->free();
    
    return $exists;
}

/**
 * Validate user session and get user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? 0;
}

/**
 * Check if current user is admin
 */
function isAdmin() {
    $user = getCurrentUser();
    return $user && $user['role'] === 'admin';
}

/**
 * Check if current user is event organizer
 */
function isEventOrganizer($eventId) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM events WHERE id = ? AND organizer_id = ?");
    if (!$stmt) return false;
    
    $stmt->bind_param("ii", $eventId, $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $isOrganizer = $result->num_rows > 0;
    $stmt->close();
    
    return $isOrganizer;
}

/**
 * Get user by ID
 */
function getUserById($userId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT id, name, email, role, organization, phone FROM users WHERE id = ?");
    if (!$stmt) return null;
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
    
    return $user;
}

/**
 * Log activity for debugging
 */
function logActivity($message, $userId = null) {
    $user = $userId ?: (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'guest');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $timestamp = date('Y-m-d H:i:s');
    
    $logMessage = "[$timestamp] [User: $user] [IP: $ip] $message" . PHP_EOL;
    error_log($logMessage, 3, __DIR__ . '/activity.log');
}

// End of config.php
?>