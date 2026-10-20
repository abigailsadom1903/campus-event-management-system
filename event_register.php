<?php
// event_register.php - PURPLE THEME VERSION
require_once 'config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Get user details from database
$user_id = $_SESSION['user_id'];
$sql = "SELECT id, name, email, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit();
}

$user_name = $user['name'];
$user_email = $user['email'];
$user_role = $user['role'];

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

// Get event details
$sql = "SELECT e.*, 
        (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id AND r.status = 'registered') as registered_count,
        u.name as creator_name,
        DATEDIFF(e.reg_close_date, CURDATE()) as days_until_close
        FROM events e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = ? AND e.status = 'active'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
$event = $result->fetch_assoc();
$stmt->close();

if (!$event) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'message' => 'Event not found or not active'
    ];
    header('Location: events.php');
    exit();
}

// Check if user already registered
$sql = "SELECT id, status, payment_method, registration_date 
        FROM event_registrations 
        WHERE event_id = ? AND user_id = ? AND status IN ('registered', 'pending_payment')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $event_id, $user_id);
$stmt->execute();
$registration_result = $stmt->get_result();
$registration = $registration_result->fetch_assoc();
$stmt->close();

$already_registered = !empty($registration);

// Check if event is full
$is_full = $event['max_participants'] && 
           $event['registered_count'] >= $event['max_participants'];

// Check registration deadline
$current_date = date('Y-m-d');
$registration_closed = $event['reg_close_date'] && 
                      $event['reg_close_date'] < $current_date;

// Check if event has passed
$event_passed = $event['event_date'] && $event['event_date'] < $current_date;

// Calculate percentage of seats filled
$percentage_filled = $event['max_participants'] ? 
    min(100, ($event['registered_count'] / $event['max_participants']) * 100) : 0;

// Handle POST request for registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $conn->begin_transaction();
    
    try {
        if ($already_registered) {
            throw new Exception('You are already registered for this event');
        }
        
        if ($is_full) {
            throw new Exception('This event is full');
        }
        
        if ($registration_closed) {
            throw new Exception('Registration for this event is closed');
        }
        
        if ($event_passed) {
            throw new Exception('This event has already occurred');
        }
        
        // Get form data
        $payment_method = $_POST['payment_method'] ?? null;
        $comments = trim($_POST['comments'] ?? '');
        
        // Validate payment method for paid events
        if ($event['fee'] == 'paid' && empty($payment_method)) {
            throw new Exception('Please select a payment method');
        }
        
        // Determine status
        $status = 'registered';
        if ($event['fee'] == 'paid' && $payment_method == 'bank_transfer') {
            $status = 'pending_payment';
        }
        
        // Insert registration
        $sql = "INSERT INTO event_registrations 
                (event_id, user_id, user_email, user_name, status, payment_method, comments) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "iisssss", 
            $event_id, 
            $user_id, 
            $user_email, 
            $user_name, 
            $status,
            $payment_method, 
            $comments
        );
        
        if (!$stmt->execute()) {
            if ($conn->errno == 1062) {
                throw new Exception('You are already registered for this event');
            }
            throw new Exception('Registration failed: ' . $conn->error);
        }
        $registration_id = $stmt->insert_id;
        $stmt->close();
        
        $conn->commit();
        
        $confirmation_number = 'REG-' . str_pad($registration_id, 6, '0', STR_PAD_LEFT) . '-' . date('Ymd');
        
        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => 'Successfully registered! Confirmation #: ' . $confirmation_number
        ];
        
        header('Location: my_registrations.php');
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'message' => $e->getMessage()
        ];
        header("Location: event_register.php?event_id=$event_id");
        exit();
    }
}

// Logo handling
$logoPath = 'img/CemsLogo.png';
$logoExists = file_exists($logoPath);
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register for <?php echo htmlspecialchars($event['event_name']); ?> - CampusBloom</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ================= CSS RESET & BASE ================= */
        :root {
            /* Purple Theme Color System */
            --primary: #7e22ce;
            --primary-dark: #6b21a8;
            --primary-light: #a855f7;
            --primary-50: rgba(126, 34, 206, 0.05);
            --primary-100: rgba(126, 34, 206, 0.1);
            --primary-200: rgba(126, 34, 206, 0.2);
            
            --secondary: #c026d3;
            --secondary-dark: #a21caf;
            --secondary-light: #d946ef;
            --secondary-50: rgba(192, 38, 211, 0.05);
            
            --accent: #8b5cf6;
            --accent-dark: #7c3aed;
            --accent-light: #a78bfa;
            
            --success: #10b981;
            --success-dark: #059669;
            --success-light: #34d399;
            
            --warning: #f59e0b;
            --warning-dark: #d97706;
            --warning-light: #fbbf24;
            
            --danger: #ef4444;
            --danger-dark: #dc2626;
            --danger-light: #f87171;
            
            --dark: #1f2937;
            --dark-light: #374151;
            
            --light: #fafafa;
            --light-dark: #f4f4f5;
            
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            
            /* Border System */
            --border-radius-xs: 6px;
            --border-radius-sm: 8px;
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --border-radius-xl: 20px;
            --border-radius-2xl: 28px;
            --border-radius-full: 9999px;
            --border: 1px solid var(--gray-200);
            --border-light: 1px solid var(--gray-100);
            
            /* Shadow System */
            --shadow-xs: 0 1px 3px rgba(0,0,0,0.05);
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 20px rgba(0,0,0,0.12);
            --shadow-lg: 0 8px 40px rgba(0,0,0,0.15);
            --shadow-xl: 0 12px 60px rgba(0,0,0,0.18);
            --shadow-primary: 0 6px 25px rgba(126, 34, 206, 0.2);
            --shadow-secondary: 0 6px 25px rgba(192, 38, 211, 0.2);
            
            /* Animation & Transition System */
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition: 250ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 400ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-bounce: 500ms cubic-bezier(0.68, -0.55, 0.265, 1.55);
            
            /* Spacing System */
            --space-xs: 0.25rem;
            --space-sm: 0.5rem;
            --space: 1rem;
            --space-md: 1.5rem;
            --space-lg: 2rem;
            --space-xl: 3rem;
            --space-2xl: 4rem;
            
            /* Layout System */
            --container-max: 1200px;
            --header-height: 80px;
            --section-spacing: 4rem;
        }

        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            font-size: 16px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            line-height: 1.6;
            color: var(--dark);
            background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
            min-height: 100vh;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .no-js body {
            opacity: 1 !important;
            transform: none !important;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            line-height: 1.2;
        }

        a {
            color: inherit;
            text-decoration: none;
            transition: var(--transition-fast);
        }

        img {
            max-width: 100%;
            height: auto;
        }

        ul, ol {
            list-style: none;
        }

        button {
            font-family: inherit;
            cursor: pointer;
        }

        /* ================= UTILITY CLASSES ================= */
        .container {
            width: 100%;
            max-width: var(--container-max);
            margin: 0 auto;
            padding: 0 var(--space-md);
        }

        .section {
            padding: var(--section-spacing) 0;
        }

        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }

        .text-gradient {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .shadow-lift {
            transition: transform var(--transition), box-shadow var(--transition);
        }

        .shadow-lift:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.8s var(--transition) forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ================= NAVBAR (Matches index.php) ================= */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(229, 231, 235, 0.8);
            z-index: 1000;
            transition: all var(--transition);
        }

        .navbar.scrolled {
            box-shadow: var(--shadow-md);
            background: rgba(255, 255, 255, 0.98);
        }

        .navbar-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: var(--space);
            transition: transform var(--transition);
        }

        .navbar-brand:hover {
            transform: translateY(-1px);
        }

        .logo-wrapper {
            display: flex;
            align-items: center;
            gap: var(--space);
        }

        .logo-image {
            height: 48px;
            width: auto;
            filter: drop-shadow(var(--shadow-sm));
        }

        .logo-text {
            font-family: 'Poppins', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }

        .navbar-menu {
            display: flex;
            align-items: center;
            gap: var(--space);
        }

        .nav-links {
            display: flex;
            gap: var(--space-xs);
        }

        .nav-link {
            position: relative;
            padding: var(--space-sm) var(--space);
            border-radius: var(--border-radius-full);
            font-weight: 500;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            font-size: 0.95rem;
        }

        .nav-link:hover {
            color: var(--primary);
            background: var(--primary-50);
        }

        .nav-link.active {
            color: white;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            box-shadow: var(--shadow-primary);
            font-weight: 600;
        }

        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 50%;
            transform: translateX(-50%);
            width: 20px;
            height: 3px;
            background: var(--accent);
            border-radius: 2px;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: var(--space);
        }

        .user-badge {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm) var(--space);
            border-radius: var(--border-radius-full);
            background: var(--primary-50);
            border: 1px solid var(--primary-100);
            font-weight: 500;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--gray-700);
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* ================= BUTTON SYSTEM ================= */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-sm);
            padding: var(--space) var(--space-xl);
            border-radius: var(--border-radius-full);
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 0.95rem;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all var(--transition);
            position: relative;
            overflow: hidden;
            text-decoration: none;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-sm {
            padding: var(--space-sm) var(--space-lg);
            font-size: 0.85rem;
        }

        .btn-lg {
            padding: var(--space-md) var(--space-2xl);
            font-size: 1.1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            box-shadow: var(--shadow-primary);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border-color: var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success) 0%, var(--success-dark) 100%);
            color: white;
        }

        .btn-disabled {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* ================= EVENT HEADER ================= */
        .event-header {
            margin-top: var(--header-height);
            padding: var(--space-2xl) 0;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            position: relative;
            overflow: hidden;
            clip-path: polygon(0 0, 100% 0, 100% 92%, 0 100%);
        }

        .event-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(139, 92, 246, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(192, 38, 211, 0.3) 0%, transparent 50%);
        }

        .event-header-content {
            position: relative;
            z-index: 2;
        }

        .breadcrumb {
            display: flex;
            gap: var(--space-sm);
            margin-bottom: var(--space-lg);
            font-size: 0.9rem;
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.8);
            transition: color var(--transition-fast);
        }

        .breadcrumb-item a:hover {
            color: white;
        }

        .breadcrumb-item.active {
            color: white;
            font-weight: 500;
        }

        .breadcrumb-separator {
            color: rgba(255, 255, 255, 0.5);
        }

        .event-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: var(--space);
            line-height: 1.2;
        }

        .event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space);
            align-items: center;
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .meta-icon {
            font-size: 1.2rem;
        }

        /* ================= STATUS BADGES ================= */
        .status-badge {
            padding: var(--space-sm) var(--space-lg);
            border-radius: var(--border-radius-full);
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            border: 2px solid;
        }

        .status-available {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            border-color: var(--success);
        }

        .status-registered {
            background: rgba(126, 34, 206, 0.15);
            color: var(--primary);
            border-color: var(--primary);
        }

        .status-full {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            border-color: var(--danger);
        }

        .status-closed {
            background: rgba(139, 92, 246, 0.15);
            color: var(--accent);
            border-color: var(--accent);
        }

        /* ================= REGISTRATION LAYOUT ================= */
        .registration-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: var(--space-2xl);
            margin: var(--space-2xl) auto;
        }

        @media (max-width: 992px) {
            .registration-container {
                grid-template-columns: 1fr;
            }
        }

        /* ================= EVENT DETAILS CARD ================= */
        .event-details-card {
            background: white;
            border-radius: var(--border-radius-xl);
            padding: var(--space-2xl);
            box-shadow: var(--shadow-md);
            border: var(--border);
            transition: all var(--transition);
            position: relative;
            overflow: hidden;
        }

        .event-details-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 6px;
            height: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .event-details-card:hover {
            box-shadow: var(--shadow-lg);
        }

        .card-title {
            font-size: 1.5rem;
            margin-bottom: var(--space-xl);
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: var(--space);
        }

        .card-title-icon {
            color: var(--primary);
        }

        .event-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--space);
            margin-bottom: var(--space-xl);
        }

        .event-info-item {
            background: var(--primary-50);
            padding: var(--space);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary);
            transition: all var(--transition-fast);
        }

        .event-info-item:hover {
            transform: translateY(-2px);
            background: white;
            box-shadow: var(--shadow-sm);
        }

        .info-item-header {
            display: flex;
            align-items: center;
            gap: var(--space);
            margin-bottom: var(--space-sm);
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-50);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.2rem;
        }

        .info-title {
            font-size: 0.9rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .info-content {
            font-size: 1.1rem;
            color: var(--gray-800);
            font-weight: 500;
        }

        .info-subtitle {
            font-size: 0.85rem;
            color: var(--gray-500);
            margin-top: var(--space-xs);
        }

        /* ================= PROGRESS BAR ================= */
        .progress-container {
            margin: var(--space) 0;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: var(--space-xs);
            font-size: 0.9rem;
            color: var(--gray-600);
        }

        .progress-bar {
            height: 8px;
            background: var(--gray-100);
            border-radius: var(--border-radius-full);
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: var(--border-radius-full);
            transition: width 1s ease;
        }

        /* ================= DESCRIPTION SECTION ================= */
        .description-section {
            margin-top: var(--space-xl);
        }

        .description-content {
            background: var(--primary-50);
            padding: var(--space-xl);
            border-radius: var(--border-radius);
            border: 1px solid var(--primary-100);
        }

        /* ================= REGISTRATION CARD ================= */
        .registration-card {
            background: white;
            border-radius: var(--border-radius-xl);
            padding: var(--space-2xl);
            box-shadow: var(--shadow-md);
            border: var(--border);
            position: sticky;
            top: calc(var(--header-height) + var(--space));
            transition: all var(--transition);
            position: relative;
            overflow: hidden;
        }

        .registration-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 6px;
            height: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .registration-card:hover {
            box-shadow: var(--shadow-lg);
        }

        /* ================= STEP INDICATOR ================= */
        .step-indicator {
            display: flex;
            align-items: center;
            margin-bottom: var(--space-lg);
        }

        .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: var(--space);
            font-weight: 600;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .step-info {
            flex: 1;
        }

        .step-subtitle {
            font-size: 0.8rem;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 2px;
        }

        .step-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        /* ================= FORM STYLES ================= */
        .form-group {
            margin-bottom: var(--space-lg);
        }

        .form-label {
            display: block;
            margin-bottom: var(--space-sm);
            font-weight: 600;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .form-label-icon {
            color: var(--primary);
        }

        .form-control,
        .form-select {
            width: 100%;
            padding: var(--space) var(--space);
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            color: var(--gray-800);
            background: white;
            transition: all var(--transition-fast);
        }

        .form-control:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-50);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .form-text {
            display: block;
            margin-top: var(--space-xs);
            font-size: 0.85rem;
            color: var(--gray-500);
        }

        /* ================= ALERT STYLES ================= */
        .alert {
            padding: var(--space);
            border-radius: var(--border-radius);
            margin-bottom: var(--space);
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            gap: var(--space);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-color: var(--success);
            color: var(--success);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--danger);
            color: var(--danger);
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border-color: var(--warning);
            color: var(--warning);
        }

        .alert-info {
            background: rgba(139, 92, 246, 0.1);
            border-color: var(--accent);
            color: var(--accent);
        }

        .alert-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        /* ================= COUNTDOWN BOX ================= */
        .countdown-box {
            text-align: center;
            padding: var(--space);
            background: white;
            border-radius: var(--border-radius);
            border: 2px solid var(--warning);
            box-shadow: var(--shadow-sm);
        }

        .countdown-number {
            font-family: 'Poppins', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--warning);
            line-height: 1;
            margin-bottom: var(--space-xs);
        }

        .countdown-label {
            font-size: 0.8rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* ================= QUICK INFO CARD ================= */
        .quick-info-card {
            margin-top: var(--space);
            background: var(--primary-50);
            border-radius: var(--border-radius);
            padding: var(--space);
            border: 1px solid var(--primary-100);
        }

        .quick-info-title {
            font-size: 0.9rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: var(--space-sm);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .contact-info {
            display: flex;
            flex-direction: column;
            gap: var(--space-sm);
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            color: var(--gray-700);
            font-size: 0.95rem;
        }

        .contact-icon {
            color: var(--primary);
            width: 16px;
        }

        /* ================= FLASH MESSAGE ================= */
        .flash-message {
            margin: var(--space) auto;
            max-width: var(--container-max);
            padding: 0 var(--space-md);
        }

        /* ================= RESPONSIVE DESIGN ================= */
        @media (max-width: 1200px) {
            :root {
                --section-spacing: 3rem;
                --container-max: 1000px;
            }
        }

        @media (max-width: 992px) {
            :root {
                --section-spacing: 2.5rem;
            }
            
            .navbar-menu {
                position: fixed;
                top: var(--header-height);
                left: 0;
                right: 0;
                background: white;
                padding: var(--space);
                box-shadow: var(--shadow-lg);
                border-radius: 0 0 var(--border-radius) var(--border-radius);
                flex-direction: column;
                align-items: stretch;
                transform: translateY(-100%);
                opacity: 0;
                visibility: hidden;
                transition: all var(--transition);
            }
            
            .navbar-menu.active {
                transform: translateY(0);
                opacity: 1;
                visibility: visible;
            }
            
            .nav-links {
                flex-direction: column;
                gap: var(--space-xs);
            }
            
            .user-menu {
                flex-direction: column;
                align-items: stretch;
                gap: var(--space);
            }
            
            .menu-toggle {
                display: block;
            }
            
            .registration-container {
                grid-template-columns: 1fr;
                gap: var(--space);
            }
            
            .registration-card {
                position: static;
            }
            
            .event-title {
                font-size: 2rem;
            }
        }

        @media (max-width: 768px) {
            :root {
                --section-spacing: 2rem;
                --header-height: 70px;
            }
            
            .container {
                padding: 0 var(--space);
            }
            
            .event-title {
                font-size: 1.75rem;
            }
            
            .event-info-grid {
                grid-template-columns: 1fr;
            }
            
            .event-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--space-sm);
            }
            
            .form-group {
                margin-bottom: var(--space);
            }
            
            .event-details-card,
            .registration-card {
                padding: var(--space-lg);
            }
        }

        @media (max-width: 480px) {
            :root {
                --section-spacing: 1.5rem;
            }
            
            .event-title {
                font-size: 1.5rem;
            }
            
            .card-title {
                font-size: 1.25rem;
            }
            
            .event-details-card,
            .registration-card {
                padding: var(--space);
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .logo-text {
                font-size: 1.5rem;
            }
        }

        /* ================= ANIMATIONS ================= */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-in {
            animation: fadeIn 0.5s ease forwards;
        }

        /* ================= BADGE STYLES ================= */
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: var(--border-radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .badge-success {
            background: linear-gradient(135deg, var(--success) 0%, var(--success-dark) 100%);
            color: white;
        }

        .badge-warning {
            background: linear-gradient(135deg, var(--warning) 0%, var(--warning-dark) 100%);
            color: white;
        }

        .badge-danger {
            background: linear-gradient(135deg, var(--danger) 0%, var(--danger-dark) 100%);
            color: white;
        }

        .badge-info {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navbar (Matches index.php) -->
    <nav class="navbar" id="navbar">
        <div class="container navbar-container">
            <a href="index.php" class="navbar-brand">
                <div class="logo-wrapper">
                    <?php if ($logoExists): ?>
                        <img src="<?php echo $logoPath; ?>" alt="CampusBloom Logo" class="logo-image">
                    <?php else: ?>
                        <div class="logo-fallback" style="width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 1.5rem;">CB</div>
                    <?php endif; ?>
                    <span class="logo-text">CampusBloom</span>
                </div>
            </a>

            <button class="menu-toggle" id="menuToggle" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>

            <div class="navbar-menu" id="navbarMenu">
                <ul class="nav-links">
                    <li><a href="index.php" class="nav-link"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="#!" class="nav-link active"><i class="fas fa-user-plus"></i> Register</a></li>
                </ul>

                <div class="user-menu">
                    <?php if ($isLoggedIn): ?>
                        <div class="user-badge">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                            </div>
                            <span><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span>
                        </div>
                        <a href="my_registrations.php" class="btn btn-sm btn-outline">Dashboard</a>
                        <a href="logout.php" class="btn btn-sm btn-primary">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-sm btn-outline">Login</a>
                        <a href="register.php" class="btn btn-sm btn-primary">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="flash-message">
            <div class="alert alert-<?php echo $_SESSION['flash_message']['type']; ?> fade-in">
                <i class="alert-icon fas fa-<?php echo $_SESSION['flash_message']['type'] == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <div><?php echo $_SESSION['flash_message']['message']; ?></div>
            </div>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <!-- Event Header -->
    <section class="event-header fade-in">
        <div class="container">
            <div class="event-header-content">
                <!-- Breadcrumb -->
                <nav class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="index.php"><i class="fas fa-home"></i> Home</a>
                        <span class="breadcrumb-separator">/</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="events.php"><i class="fas fa-calendar-alt"></i> Events</a>
                        <span class="breadcrumb-separator">/</span>
                    </div>
                    <div class="breadcrumb-item active">
                        <i class="fas fa-user-plus"></i> Register
                    </div>
                </nav>

                <!-- Event Title -->
                <h1 class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></h1>

                <!-- Event Meta -->
                <div class="event-meta">
                    <div class="meta-item">
                        <i class="meta-icon fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($event['venue']); ?>
                    </div>
                    <div class="meta-item">
                        <i class="meta-icon far fa-calendar"></i>
                        <?php echo date('F j, Y', strtotime($event['event_date'])); ?>
                    </div>
                    <div class="meta-item">
                        <i class="meta-icon far fa-clock"></i>
                        <?php echo date('h:i A', strtotime($event['event_time'])); ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="main">
        <div class="container">
            <!-- Status Indicator -->
            <div class="text-center mb-4 fade-in">
                <?php if ($already_registered): ?>
                    <div class="status-badge status-registered">
                        <i class="fas fa-check-circle"></i>
                        You are registered! Status: <?php echo strtoupper(str_replace('_', ' ', $registration['status'])); ?>
                    </div>
                <?php elseif ($is_full): ?>
                    <div class="status-badge status-full">
                        <i class="fas fa-times-circle"></i>
                        Event Full - Registration Closed
                    </div>
                <?php elseif ($registration_closed): ?>
                    <div class="status-badge status-closed">
                        <i class="fas fa-clock"></i>
                        Registration Closed
                    </div>
                <?php elseif ($event_passed): ?>
                    <div class="status-badge status-closed">
                        <i class="fas fa-history"></i>
                        Event Has Passed
                    </div>
                <?php else: ?>
                    <div class="status-badge status-available">
                        <i class="fas fa-calendar-check"></i>
                        Registration Open - Secure Your Spot!
                    </div>
                <?php endif; ?>
            </div>

            <!-- Registration Content -->
            <div class="registration-container">
                <!-- Left Column: Event Details -->
                <div class="event-details-column">
                    <div class="event-details-card fade-in">
                        <h3 class="card-title">
                            <i class="fas fa-info-circle card-title-icon"></i>
                            Event Details
                        </h3>

                        <div class="event-info-grid">
                            <!-- Date & Time -->
                            <div class="event-info-item">
                                <div class="info-item-header">
                                    <div class="info-icon">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div>
                                        <div class="info-title">Date & Time</div>
                                        <div class="info-content"><?php echo date('l, F j, Y', strtotime($event['event_date'])); ?></div>
                                        <div class="info-subtitle">
                                            <i class="fas fa-clock fa-xs"></i>
                                            <?php echo date('h:i A', strtotime($event['event_time'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Venue & Mode -->
                            <div class="event-info-item">
                                <div class="info-item-header">
                                    <div class="info-icon">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <div>
                                        <div class="info-title">Venue & Mode</div>
                                        <div class="info-content"><?php echo htmlspecialchars($event['venue']); ?></div>
                                        <div class="info-subtitle">
                                            <span class="badge badge-<?php echo $event['mode'] == 'online' ? 'info' : ($event['mode'] == 'hybrid' ? 'warning' : 'primary'); ?>">
                                                <i class="fas fa-<?php echo $event['mode'] == 'online' ? 'globe' : ($event['mode'] == 'hybrid' ? 'sync' : 'building'); ?> me-1"></i>
                                                <?php echo ucfirst($event['mode']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Capacity -->
                            <div class="event-info-item">
                                <div class="info-item-header">
                                    <div class="info-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div>
                                        <div class="info-title">Capacity</div>
                                        <div class="info-content">
                                            <?php echo $event['registered_count']; ?> of 
                                            <?php echo $event['max_participants'] ?: 'Unlimited'; ?> registered
                                        </div>
                                        <?php if ($event['max_participants']): ?>
                                        <div class="progress-container">
                                            <div class="progress-header">
                                                <span>Registration Progress</span>
                                                <span><?php echo round($percentage_filled); ?>%</span>
                                            </div>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo min($percentage_filled, 100); ?>%;"></div>
                                            </div>
                                            <div class="info-subtitle">
                                                <?php echo $event['max_participants'] - $event['registered_count']; ?> seats available
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Fee -->
                            <div class="event-info-item">
                                <div class="info-item-header">
                                    <div class="info-icon">
                                        <i class="fas fa-tag"></i>
                                    </div>
                                    <div>
                                        <div class="info-title">Registration Fee</div>
                                        <div class="info-content">
                                            <?php if ($event['fee'] == 'paid'): ?>
                                                <span class="text-danger fw-bold">RM <?php echo number_format($event['fee_amount'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="text-success fw-bold">FREE</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="info-subtitle">
                                            <?php echo $event['fee'] == 'paid' ? 'Payment required' : 'No payment required'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Organizer -->
                            <div class="event-info-item">
                                <div class="info-item-header">
                                    <div class="info-icon">
                                        <i class="fas fa-building"></i>
                                    </div>
                                    <div>
                                        <div class="info-title">Organizer</div>
                                        <div class="info-content"><?php echo htmlspecialchars($event['organizer']); ?></div>
                                        <div class="info-subtitle">
                                            <i class="fas fa-envelope fa-xs"></i>
                                            <?php echo htmlspecialchars($event['organizer_email']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Contact Person -->
                            <?php if ($event['contact_person']): ?>
                            <div class="event-info-item">
                                <div class="info-item-header">
                                    <div class="info-icon">
                                        <i class="fas fa-user-tie"></i>
                                    </div>
                                    <div>
                                        <div class="info-title">Contact Person</div>
                                        <div class="info-content"><?php echo htmlspecialchars($event['contact_person']); ?></div>
                                        <div class="info-subtitle">
                                            <i class="fas fa-phone fa-xs"></i>
                                            <?php echo htmlspecialchars($event['contact_no']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Event Description -->
                        <?php if (!empty($event['description'])): ?>
                        <div class="description-section">
                            <h4 class="card-title">
                                <i class="fas fa-align-left card-title-icon"></i>
                                Event Description
                            </h4>
                            <div class="description-content">
                                <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Registration Deadline -->
                        <?php if ($event['reg_close_date']): ?>
                        <div class="description-section">
                            <h4 class="card-title">
                                <i class="fas fa-calendar-times card-title-icon"></i>
                                Registration Deadline
                            </h4>
                            <div class="alert alert-warning">
                                <i class="alert-icon fas fa-exclamation-triangle"></i>
                                <div>
                                    <div class="fw-bold">Registration closes on <?php echo date('F j, Y', strtotime($event['reg_close_date'])); ?></div>
                                    <?php if ($event['days_until_close'] > 0): ?>
                                        <div class="mt-1">
                                            <span class="badge badge-warning">
                                                <?php echo $event['days_until_close']; ?> day<?php echo $event['days_until_close'] > 1 ? 's' : ''; ?> left
                                            </span>
                                            to register
                                        </div>
                                    <?php elseif ($event['days_until_close'] == 0): ?>
                                        <div class="mt-1">
                                            <span class="badge badge-danger">LAST DAY</span> to register!
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-1">
                                            <span class="badge badge-info">Closed</span> Registration deadline has passed
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column: Registration Form -->
                <div class="registration-column">
                    <div class="registration-card fade-in">
                        <h3 class="card-title">
                            <i class="fas fa-user-plus card-title-icon"></i>
                            Registration
                        </h3>

                        <?php if ($already_registered): ?>
                            <!-- Already Registered View -->
                            <div class="alert alert-success">
                                <i class="alert-icon fas fa-check-circle"></i>
                                <div>
                                    <div class="fw-bold">You're Registered!</div>
                                    <div class="mt-1 small">
                                        Status: <span class="badge badge-success"><?php echo strtoupper($registration['status']); ?></span><br>
                                        Date: <?php echo date('M j, Y h:i A', strtotime($registration['registration_date'])); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex flex-column gap-3">
                                <a href="my_registrations.php" class="btn btn-primary">
                                    <i class="fas fa-list me-2"></i>View My Registrations
                                </a>
                                <a href="events.php" class="btn btn-outline">
                                    <i class="fas fa-calendar me-2"></i>Browse More Events
                                </a>
                            </div>

                        <?php elseif ($is_full): ?>
                            <!-- Event Full View -->
                            <div class="alert alert-danger">
                                <i class="alert-icon fas fa-exclamation-triangle"></i>
                                <div>
                                    <div class="fw-bold">Event Full</div>
                                    <div class="mt-1 small">All seats have been taken for this event.</div>
                                </div>
                            </div>
                            
                            <div class="d-flex flex-column gap-2">
                                <button class="btn btn-disabled" disabled>
                                    <i class="fas fa-ban me-2"></i>Registration Closed
                                </button>
                                <a href="events.php" class="btn btn-outline">
                                    <i class="fas fa-calendar me-2"></i>Browse Other Events
                                </a>
                            </div>

                        <?php elseif ($registration_closed || $event_passed): ?>
                            <!-- Registration Closed View -->
                            <div class="alert alert-info">
                                <i class="alert-icon fas fa-clock"></i>
                                <div>
                                    <div class="fw-bold">
                                        <?php echo $registration_closed ? 'Registration Closed' : 'Event Has Passed'; ?>
                                    </div>
                                    <div class="mt-1 small">
                                        <?php if ($registration_closed): ?>
                                            The registration deadline has passed.
                                        <?php else: ?>
                                            This event has already occurred.
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex flex-column gap-2">
                                <button class="btn btn-disabled" disabled>
                                    <i class="fas fa-ban me-2"></i>Not Available
                                </button>
                                <a href="events.php" class="btn btn-outline">
                                    <i class="fas fa-calendar me-2"></i>Browse Other Events
                                </a>
                            </div>

                        <?php else: ?>
                            <!-- Registration Form -->
                            <!-- Step 1: User Information -->
                            <div class="step-indicator">
                                <div class="step-number">1</div>
                                <div class="step-info">
                                    <div class="step-subtitle">STEP 1 OF 2</div>
                                    <div class="step-title">Your Information</div>
                                </div>
                            </div>

                            <div class="alert" style="background: var(--primary-50); border-color: var(--primary-100);">
                                <i class="fas fa-user-circle text-primary"></i>
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($user_name); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($user_email); ?></div>
                                </div>
                            </div>

                            <form method="POST" action="event_register.php?event_id=<?php echo $event_id; ?>">
                                <input type="hidden" name="register" value="1">

                                <!-- Step 2: Complete Registration -->
                                <div class="step-indicator">
                                    <div class="step-number">2</div>
                                    <div class="step-info">
                                        <div class="step-subtitle">STEP 2 OF 2</div>
                                        <div class="step-title">Complete Registration</div>
                                    </div>
                                </div>

                                <?php if ($event['fee'] == 'paid'): ?>
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-credit-card form-label-icon"></i>
                                        Payment Method *
                                    </label>
                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                        <option value="">Select payment method</option>
                                        <option value="credit_card">💳 Credit Card</option>
                                        <option value="debit_card">💳 Debit Card</option>
                                        <option value="bank_transfer">🏦 Bank Transfer (Pending Verification)</option>
                                        <option value="cash">💰 Cash (Pay at Venue)</option>
                                    </select>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle"></i>
                                        <?php if ($event['fee_amount'] > 0): ?>
                                            Payment of RM <?php echo number_format($event['fee_amount'], 2); ?> is required
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-comment form-label-icon"></i>
                                        Additional Information
                                    </label>
                                    <textarea class="form-control" id="comments" name="comments" rows="3" 
                                              placeholder="Any special requirements, dietary restrictions, or comments..."></textarea>
                                    <div class="form-text">Optional</div>
                                </div>

                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary btn-lg w-100">
                                        <i class="fas fa-paper-plane me-2"></i>Complete Registration
                                    </button>
                                </div>

                                <div class="text-center">
                                    <small class="text-muted small">
                                        <i class="fas fa-lock me-1"></i>Secure registration • 
                                        <i class="fas fa-shield-alt me-1"></i>Privacy protected
                                    </small>
                                </div>
                            </form>

                            <hr class="my-4">

                            <div class="text-center">
                                <a href="events.php" class="btn btn-outline">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Events
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Info Card -->
                    <div class="quick-info-card fade-in">
                        <div class="quick-info-title">
                            <i class="fas fa-question-circle"></i>
                            Need Assistance?
                        </div>
                        <div class="contact-info">
                            <p class="small text-muted mb-2">
                                Contact the event organizer for questions:
                            </p>
                            <div class="contact-item">
                                <i class="contact-icon fas fa-envelope"></i>
                                <a href="mailto:<?php echo htmlspecialchars($event['organizer_email']); ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($event['organizer_email']); ?>
                                </a>
                            </div>
                            <?php if ($event['contact_no']): ?>
                            <div class="contact-item">
                                <i class="contact-icon fas fa-phone"></i>
                                <a href="tel:<?php echo htmlspecialchars($event['contact_no']); ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($event['contact_no']); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Seats Available Counter -->
                    <div class="countdown-box fade-in">
                        <div class="countdown-number">
                            <?php echo $event['max_participants'] ? $event['max_participants'] - $event['registered_count'] : '∞'; ?>
                        </div>
                        <div class="countdown-label">Seats Available</div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer" style="background: linear-gradient(135deg, var(--gray-900) 0%, #1a1d20 100%); color: white; padding: var(--space-2xl) 0 var(--space-xl); margin-top: var(--space-2xl);">
        <div class="container">
            <div class="text-center">
                <div class="logo-text" style="color: white; -webkit-text-fill-color: white; margin-bottom: var(--space);">CampusBloom</div>
                <p style="color: rgba(255,255,255,0.7); max-width: 600px; margin: 0 auto var(--space-lg);">
                    The leading event management platform for academic institutions, 
                    empowering student engagement and campus community building.
                </p>
                <div style="color: rgba(255,255,255,0.5); font-size: 0.9rem;">
                    &copy; <?php echo date('Y'); ?> CampusBloom Event Management System. All rights reserved.<br>
                    Universiti Malaysia Sabah | Premium Campus Platform v3.0
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Remove no-js class and enable animations
        document.documentElement.classList.remove('no-js');
        
        // Initialize components
        document.addEventListener('DOMContentLoaded', function() {
            // Navbar functionality
            const navbar = document.getElementById('navbar');
            const menuToggle = document.getElementById('menuToggle');
            const navbarMenu = document.getElementById('navbarMenu');
            
            // Scroll effect
            window.addEventListener('scroll', () => {
                if (window.scrollY > 50) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            });
            
            // Mobile menu toggle
            if (menuToggle && navbarMenu) {
                menuToggle.addEventListener('click', () => {
                    navbarMenu.classList.toggle('active');
                    const icon = menuToggle.querySelector('i');
                    if (navbarMenu.classList.contains('active')) {
                        icon.className = 'fas fa-times';
                        menuToggle.setAttribute('aria-expanded', 'true');
                    } else {
                        icon.className = 'fas fa-bars';
                        menuToggle.setAttribute('aria-expanded', 'false');
                    }
                });
                
                // Close menu when clicking outside
                document.addEventListener('click', (e) => {
                    if (!navbarMenu.contains(e.target) && !menuToggle.contains(e.target)) {
                        navbarMenu.classList.remove('active');
                        const icon = menuToggle.querySelector('i');
                        icon.className = 'fas fa-bars';
                        menuToggle.setAttribute('aria-expanded', 'false');
                    }
                });
                
                // Close menu when clicking on a link
                navbarMenu.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', () => {
                        navbarMenu.classList.remove('active');
                        const icon = menuToggle.querySelector('i');
                        icon.className = 'fas fa-bars';
                        menuToggle.setAttribute('aria-expanded', 'false');
                    });
                });
            }
            
            // Form validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const paymentSelect = document.getElementById('payment_method');
                    if (paymentSelect && paymentSelect.required && !paymentSelect.value) {
                        e.preventDefault();
                        paymentSelect.focus();
                        paymentSelect.classList.add('is-invalid');
                        
                        // Create error message
                        let errorDiv = document.getElementById('payment-error');
                        if (!errorDiv) {
                            errorDiv = document.createElement('div');
                            errorDiv.id = 'payment-error';
                            errorDiv.className = 'invalid-feedback d-block';
                            errorDiv.textContent = 'Please select a payment method';
                            paymentSelect.parentNode.appendChild(errorDiv);
                        }
                        return false;
                    }
                    return true;
                });
            }
            
            // Payment method change handler
            const paymentMethod = document.getElementById('payment_method');
            if (paymentMethod) {
                paymentMethod.addEventListener('change', function() {
                    this.classList.remove('is-invalid');
                    const errorDiv = document.getElementById('payment-error');
                    if (errorDiv) {
                        errorDiv.remove();
                    }
                });
            }
            
            // Add animation to elements with fade-in class
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((el, index) => {
                el.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Add hover effect to cards
            const cards = document.querySelectorAll('.event-details-card, .registration-card');
            cards.forEach(card => {
                card.classList.add('shadow-lift');
            });
            
            // Add loading state to submit button
            const submitBtn = document.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.addEventListener('click', function(e) {
                    if (this.classList.contains('btn-disabled')) return;
                    
                    const form = this.closest('form');
                    if (form && form.checkValidity()) {
                        this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';
                        this.classList.add('btn-disabled');
                    }
                });
            }
        });
    </script>
</body>
</html>