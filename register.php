<?php
// register.php - COMPLETE FIXED VERSION
require_once 'config.php';

// Start session at the very beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize variables
$errors = [];
$name = $email = $organization = $phone = '';
$role = 'student';

// Check if user is already logged in (only for GET requests)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_SESSION['user_id']) && $_SESSION['is_logged_in'] === true) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $organization = trim($_POST['organization'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // Validation
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    if (empty($password)) $errors[] = 'Password is required';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match';
    if (!in_array($role, ['public', 'student', 'staff'])) $errors[] = 'Invalid role selected';

    // Check if email exists
    if (empty($errors)) {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = 'Email already registered. Please use a different email or <a href="login.php" class="alert-link">login here</a>.';
        }
        $check->close();
    }

 // Create account
if (empty($errors)) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $current_time = date('Y-m-d H:i:s');
    
    // Use transaction for data integrity
    $conn->begin_transaction();
    
    try {
        // FIXED: Removed duplicate created_at column
        $sql = "INSERT INTO users (name, email, password, role, organization, phone, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        // FIXED: Changed to 7 parameters
        $stmt->bind_param("sssssss", $name, $email, $hashed_password, $role, $organization, $phone, $current_time);
        
        if (!$stmt->execute()) {
            throw new Exception('Registration failed: ' . $conn->error);
        }
        
        $userId = $stmt->insert_id;
        $stmt->close();
        
        // Fetch the complete user data
        $userSql = "SELECT id, name, email, role, organization, phone, created_at FROM users WHERE id = ?";
        $userStmt = $conn->prepare($userSql);
        $userStmt->bind_param("i", $userId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $user = $userResult->fetch_assoc();
        $userStmt->close();
        
        if (!$user) {
            throw new Exception('Failed to retrieve user data after registration');
        }
        
        // Commit transaction
        $conn->commit();
        
        // ... rest of your session setup code ...
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = $e->getMessage();
    }
}
}

// Logo handling
$logoPath = 'img/CemsLogo.png';
$logoExists = file_exists($logoPath);
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CampusBloom</title>
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

        /* ================= NAVBAR ================= */
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

        /* ================= REGISTER HEADER ================= */
        .register-header {
            margin-top: var(--header-height);
            padding: var(--space-2xl) 0;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            position: relative;
            overflow: hidden;
            clip-path: polygon(0 0, 100% 0, 100% 92%, 0 100%);
        }

        .register-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(139, 92, 246, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(192, 38, 211, 0.3) 0%, transparent 50%);
        }

        .register-header-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .register-logo {
            width: 120px;
            height: auto;
            margin: 0 auto var(--space);
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
        }

        .register-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: var(--space);
            line-height: 1.2;
        }

        .register-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        /* ================= REGISTER CONTAINER ================= */
        .register-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-2xl);
            margin: var(--space-2xl) auto;
        }

        @media (max-width: 992px) {
            .register-container {
                grid-template-columns: 1fr;
            }
        }

        /* ================= REGISTER CARD ================= */
        .register-card {
            background: white;
            border-radius: var(--border-radius-xl);
            padding: var(--space-2xl);
            box-shadow: var(--shadow-md);
            border: var(--border);
            position: relative;
            overflow: hidden;
        }

        .register-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 6px;
            height: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .register-card:hover {
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

        /* ================= FORM STYLES ================= */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--space);
            margin-bottom: var(--space-xl);
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: var(--space);
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
            width: 20px;
        }

        .form-control {
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

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-50);
        }

        .form-control::placeholder {
            color: var(--gray-400);
        }

        .form-text {
            display: block;
            margin-top: var(--space-xs);
            font-size: 0.85rem;
            color: var(--gray-500);
        }

        /* ================= PASSWORD STRENGTH ================= */
        .password-strength {
            margin-top: var(--space-sm);
        }

        .strength-meter {
            height: 6px;
            background: var(--gray-100);
            border-radius: var(--border-radius-full);
            overflow: hidden;
            margin-bottom: var(--space-xs);
        }

        .strength-fill {
            height: 100%;
            border-radius: var(--border-radius-full);
            transition: width 0.3s ease;
        }

        .strength-weak {
            width: 33%;
            background: var(--danger);
        }

        .strength-medium {
            width: 66%;
            background: var(--warning);
        }

        .strength-strong {
            width: 100%;
            background: var(--success);
        }

        .strength-text {
            font-size: 0.85rem;
            color: var(--gray-600);
        }

        /* ================= ROLE SELECTION ================= */
        .role-selection {
            margin-bottom: var(--space-xl);
        }

        .role-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space);
            margin-top: var(--space);
        }

        @media (max-width: 768px) {
            .role-options {
                grid-template-columns: 1fr;
            }
        }

        .role-option {
            position: relative;
        }

        .role-input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .role-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: var(--space-lg);
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius-lg);
            cursor: pointer;
            transition: all var(--transition);
            text-align: center;
            background: white;
            height: 100%;
        }

        .role-label:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .role-input:checked + .role-label {
            background: var(--primary-50);
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .role-icon {
            font-size: 2rem;
            margin-bottom: var(--space);
            color: var(--primary);
        }

        .role-input:checked + .role-label .role-icon {
            color: var(--primary-dark);
        }

        .role-name {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: var(--space-xs);
            color: var(--gray-800);
        }

        .role-description {
            font-size: 0.85rem;
            color: var(--gray-600);
        }

        /* ================= ALERT STYLES ================= */
        .alert {
            padding: var(--space);
            border-radius: var(--border-radius);
            margin-bottom: var(--space);
            border: 1px solid transparent;
            display: flex;
            align-items: flex-start;
            gap: var(--space);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--danger);
            color: var(--danger);
        }

        .alert-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .alert-content {
            flex: 1;
        }

        .alert-content p {
            margin-bottom: var(--space-xs);
        }

        .alert-content p:last-child {
            margin-bottom: 0;
        }

        /* ================= TERMS CARD ================= */
        .terms-card {
            background: var(--primary-50);
            border-radius: var(--border-radius);
            padding: var(--space);
            border: 1px solid var(--primary-100);
            margin-bottom: var(--space-xl);
        }

        .terms-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: var(--space);
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .terms-title-icon {
            color: var(--primary);
        }

        .terms-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
        }

        .terms-item {
            display: flex;
            align-items: flex-start;
            gap: var(--space-sm);
            font-size: 0.9rem;
            color: var(--gray-600);
        }

        .terms-icon {
            color: var(--success);
            margin-top: 2px;
        }

        /* ================= INFO CARD ================= */
        .info-card {
            background: linear-gradient(135deg, var(--primary-50) 0%, var(--secondary-50) 100%);
            border-radius: var(--border-radius);
            padding: var(--space-xl);
            border: 1px solid var(--primary-100);
            height: 100%;
        }

        .info-card-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: var(--space);
            color: var(--gray-900);
        }

        .benefits-list {
            display: flex;
            flex-direction: column;
            gap: var(--space);
            margin-bottom: var(--space-xl);
        }

        .benefit-item {
            display: flex;
            align-items: flex-start;
            gap: var(--space);
        }

        .benefit-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-100);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .benefit-content h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: var(--space-xs);
            color: var(--gray-800);
        }

        .benefit-content p {
            font-size: 0.9rem;
            color: var(--gray-600);
        }

        /* ================= LOGIN LINK ================= */
        .login-link {
            text-align: center;
            padding-top: var(--space);
            border-top: 1px solid var(--gray-200);
            margin-top: var(--space-xl);
        }

        .login-link p {
            color: var(--gray-600);
            font-size: 0.95rem;
        }

        .login-link a {
            color: var(--primary);
            font-weight: 600;
            transition: color var(--transition-fast);
        }

        .login-link a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

        /* ================= FOOTER ================= */
        .footer {
            background: linear-gradient(135deg, var(--gray-900) 0%, #1a1d20 100%);
            color: white;
            padding: var(--space-2xl) 0 var(--space-xl);
            margin-top: var(--space-2xl);
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-2xl);
            margin-bottom: var(--space-2xl);
        }

        .footer-section h4 {
            font-size: 1.25rem;
            margin-bottom: var(--space);
            color: white;
            position: relative;
            padding-bottom: var(--space-sm);
        }

        .footer-section h4::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 2px;
        }

        .footer-links {
            display: flex;
            flex-direction: column;
            gap: var(--space);
        }

        .footer-link {
            color: rgba(255, 255, 255, 0.7);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            transition: all var(--transition-fast);
        }

        .footer-link:hover {
            color: white;
            transform: translateX(5px);
        }

        .footer-bottom {
            text-align: center;
            padding-top: var(--space-xl);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.9rem;
            line-height: 1.6;
        }

        /* ================= RESPONSIVE DESIGN ================= */
        @media (max-width: 992px) {
            :root {
                --section-spacing: 3rem;
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
            
            .register-header {
                padding: var(--space-xl) 0;
            }
            
            .register-title {
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
            
            .register-title {
                font-size: 1.75rem;
            }
            
            .register-card {
                padding: var(--space-xl);
            }
            
            .role-options {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            :root {
                --section-spacing: 1.5rem;
            }
            
            .register-title {
                font-size: 1.5rem;
            }
            
            .card-title {
                font-size: 1.25rem;
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

        /* ================= FORM VALIDATION ================= */
        .form-control.error {
            border-color: var(--danger);
            background: rgba(239, 68, 68, 0.05);
        }

        .form-control.success {
            border-color: var(--success);
        }

        .error-message {
            color: var(--danger);
            font-size: 0.85rem;
            margin-top: var(--space-xs);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .success-message {
            color: var(--success);
            font-size: 0.85rem;
            margin-top: var(--space-xs);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
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
                    <li><a href="login.php" class="nav-link"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                    <li><a href="#!" class="nav-link active"><i class="fas fa-user-plus"></i> Register</a></li>
                    <li><a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Register Header -->
    <section class="register-header fade-in">
        <div class="container">
            <div class="register-header-content">
                <?php if ($logoExists): ?>
                    <img src="<?php echo $logoPath; ?>" alt="CampusBloom Logo" class="register-logo">
                <?php endif; ?>
                <h1 class="register-title">Join CampusBloom</h1>
                <p class="register-subtitle">Create your account and start exploring campus events. Connect with your university community today!</p>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="main">
        <div class="container">
            <div class="register-container">
                <!-- Registration Form -->
                <div class="register-form-column">
                    <div class="register-card fade-in">
                        <h2 class="card-title">
                            <i class="fas fa-user-plus card-title-icon"></i>
                            Create Account
                        </h2>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle alert-icon"></i>
                                <div class="alert-content">
                                    <?php foreach ($errors as $error): ?>
                                        <p><?php echo $error; ?></p>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="register.php" id="registrationForm">
                            <div class="form-grid">
                                <!-- Name -->
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-user form-label-icon"></i>
                                        Full Name *
                                    </label>
                                    <input type="text" 
                                           name="name" 
                                           class="form-control" 
                                           required 
                                           value="<?php echo htmlspecialchars($name); ?>"
                                           placeholder="Enter your full name"
                                           id="nameInput">
                                    <div class="form-text">Your name as it appears officially</div>
                                </div>

                                <!-- Email -->
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-envelope form-label-icon"></i>
                                        Email Address *
                                    </label>
                                    <input type="email" 
                                           name="email" 
                                           class="form-control" 
                                           required 
                                           value="<?php echo htmlspecialchars($email); ?>"
                                           placeholder="your.email@example.com"
                                           id="emailInput">
                                    <div class="form-text">We'll send important notifications to this email</div>
                                </div>

                                <!-- Organization -->
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-university form-label-icon"></i>
                                        Organization
                                    </label>
                                    <input type="text" 
                                           name="organization" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($organization); ?>"
                                           placeholder="e.g., Universiti Malaysia Sabah"
                                           id="organizationInput">
                                    <div class="form-text">Your school, university, or organization</div>
                                </div>

                                <!-- Phone -->
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-phone form-label-icon"></i>
                                        Phone Number
                                    </label>
                                    <input type="tel" 
                                           name="phone" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($phone); ?>"
                                           placeholder="+60 12-345 6789"
                                           id="phoneInput">
                                    <div class="form-text">Optional - for event notifications</div>
                                </div>
                            </div>

                            <!-- Password -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-lock form-label-icon"></i>
                                    Password *
                                </label>
                                <input type="password" 
                                       name="password" 
                                       class="form-control" 
                                       required 
                                       placeholder="Create a secure password"
                                       id="passwordInput">
                                <div class="password-strength">
                                    <div class="strength-meter">
                                        <div class="strength-fill" id="strengthFill"></div>
                                    </div>
                                    <div class="strength-text" id="strengthText">Password strength will appear here</div>
                                </div>
                                <div class="form-text">Minimum 6 characters with letters and numbers</div>
                            </div>

                            <!-- Confirm Password -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-lock form-label-icon"></i>
                                    Confirm Password *
                                </label>
                                <input type="password" 
                                       name="confirm_password" 
                                       class="form-control" 
                                       required 
                                       placeholder="Re-enter your password"
                                       id="confirmPasswordInput">
                                <div class="error-message" id="passwordMatchError" style="display: none;">
                                    <i class="fas fa-times-circle"></i>
                                    Passwords do not match
                                </div>
                                <div class="success-message" id="passwordMatchSuccess" style="display: none;">
                                    <i class="fas fa-check-circle"></i>
                                    Passwords match
                                </div>
                            </div>

                            <!-- Role Selection -->
                            <div class="role-selection">
                                <label class="form-label">
                                    <i class="fas fa-user-tag form-label-icon"></i>
                                    Account Type *
                                </label>
                                <div class="role-options">
                                    <!-- Student -->
                                    <div class="role-option">
                                        <input type="radio" 
                                               name="role" 
                                               value="student" 
                                               id="student"
                                               class="role-input"
                                               <?php echo $role === 'student' ? 'checked' : ''; ?>>
                                        <label for="student" class="role-label">
                                            <div class="role-icon">
                                                <i class="fas fa-graduation-cap"></i>
                                            </div>
                                            <div class="role-name">Student</div>
                                            <div class="role-description">Join campus events, workshops, and activities</div>
                                        </label>
                                    </div>

                                    <!-- Staff -->
                                    <div class="role-option">
                                        <input type="radio" 
                                               name="role" 
                                               value="staff" 
                                               id="staff"
                                               class="role-input"
                                               <?php echo $role === 'staff' ? 'checked' : ''; ?>>
                                        <label for="staff" class="role-label">
                                            <div class="role-icon">
                                                <i class="fas fa-chalkboard-teacher"></i>
                                            </div>
                                            <div class="role-name">Staff</div>
                                            <div class="role-description">Organize and manage university events</div>
                                        </label>
                                    </div>

                                    <!-- Public -->
                                    <div class="role-option">
                                        <input type="radio" 
                                               name="role" 
                                               value="public" 
                                               id="public"
                                               class="role-input"
                                               <?php echo $role === 'public' ? 'checked' : ''; ?>>
                                        <label for="public" class="role-label">
                                            <div class="role-icon">
                                                <i class="fas fa-user-friends"></i>
                                            </div>
                                            <div class="role-name">Public</div>
                                            <div class="role-description">Browse and attend public events</div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Terms -->
                            <div class="terms-card">
                                <h3 class="terms-title">
                                    <i class="fas fa-shield-alt terms-title-icon"></i>
                                    Account Terms
                                </h3>
                                <div class="terms-list">
                                    <div class="terms-item">
                                        <i class="fas fa-check-circle terms-icon"></i>
                                        <span>By creating an account, you agree to our Terms of Service</span>
                                    </div>
                                    <div class="terms-item">
                                        <i class="fas fa-check-circle terms-icon"></i>
                                        <span>Your personal information is protected by our Privacy Policy</span>
                                    </div>
                                    <div class="terms-item">
                                        <i class="fas fa-check-circle terms-icon"></i>
                                        <span>You consent to receive event notifications and updates</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary btn-lg w-100" id="submitButton">
                                    <i class="fas fa-user-plus"></i>
                                    Create Account
                                </button>
                            </div>

                            <!-- Login Link -->
                            <div class="login-link">
                                <p>Already have an account? <a href="login.php">Login here</a></p>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Info Column -->
                <div class="info-column">
                    <div class="info-card fade-in">
                        <h3 class="info-card-title">Why Join CampusBloom?</h3>
                        
                        <div class="benefits-list">
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="benefit-content">
                                    <h4>Discover Events</h4>
                                    <p>Find and join academic workshops, social gatherings, career fairs, and campus activities that match your interests.</p>
                                </div>
                            </div>

                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="benefit-content">
                                    <h4>Build Community</h4>
                                    <p>Connect with fellow students, faculty members, and organizations within your university ecosystem.</p>
                                </div>
                            </div>

                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <i class="fas fa-trophy"></i>
                                </div>
                                <div class="benefit-content">
                                    <h4>Enhance Your Profile</h4>
                                    <p>Track your participation and build a portfolio of campus involvement for academic and career advancement.</p>
                                </div>
                            </div>

                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <i class="fas fa-bell"></i>
                                </div>
                                <div class="benefit-content">
                                    <h4>Stay Updated</h4>
                                    <p>Get personalized notifications about upcoming events, registration deadlines, and important campus announcements.</p>
                                </div>
                            </div>
                        </div>

                        <div class="terms-card">
                            <h3 class="terms-title">
                                <i class="fas fa-info-circle terms-title-icon"></i>
                                Quick Start Guide
                            </h3>
                            <div class="terms-list">
                                <div class="terms-item">
                                    <i class="fas fa-check-circle terms-icon"></i>
                                    <span><strong>Step 1:</strong> Complete registration with valid details</span>
                                </div>
                                <div class="terms-item">
                                    <i class="fas fa-check-circle terms-icon"></i>
                                    <span><strong>Step 2:</strong> Verify your email (check spam folder)</span>
                                </div>
                                <div class="terms-item">
                                    <i class="fas fa-check-circle terms-icon"></i>
                                    <span><strong>Step 3:</strong> Browse events and register for activities</span>
                                </div>
                                <div class="terms-item">
                                    <i class="fas fa-check-circle terms-icon"></i>
                                    <span><strong>Step 4:</strong> Manage your registrations in your dashboard</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>CampusBloom</h4>
                    <p>The premier event management platform for academic institutions, empowering student engagement and campus community building.</p>
                </div>
                
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <div class="footer-links">
                        <a href="index.php" class="footer-link">
                            <i class="fas fa-chevron-right"></i> Home
                        </a>
                        <a href="events.php" class="footer-link">
                            <i class="fas fa-chevron-right"></i> Events
                        </a>
                        <a href="login.php" class="footer-link">
                            <i class="fas fa-chevron-right"></i> Login
                        </a>
                        <a href="dashboard.php" class="footer-link">
                            <i class="fas fa-chevron-right"></i> Dashboard
                        </a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h4>Contact Info</h4>
                    <div class="footer-links">
                        <a href="mailto:support@campusbloom.edu" class="footer-link">
                            <i class="fas fa-envelope"></i> support@campusbloom.edu
                        </a>
                        <a href="tel:+6088320000" class="footer-link">
                            <i class="fas fa-phone"></i> +60 88-320 000
                        </a>
                        <a href="#" class="footer-link">
                            <i class="fas fa-map-marker-alt"></i> Universiti Malaysia Sabah
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2025 CampusBloom Event Management System. All rights reserved.</p>
                <p>Universiti Malaysia Sabah | Premium Campus Platform v3.0</p>
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
            
            // Password strength checker
            const passwordInput = document.getElementById('passwordInput');
            const confirmPasswordInput = document.getElementById('confirmPasswordInput');
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            const passwordMatchError = document.getElementById('passwordMatchError');
            const passwordMatchSuccess = document.getElementById('passwordMatchSuccess');
            const submitButton = document.getElementById('submitButton');
            const registrationForm = document.getElementById('registrationForm');
            
            passwordInput.addEventListener('input', checkPasswordStrength);
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
            
            function checkPasswordStrength() {
                const password = passwordInput.value;
                let strength = 0;
                
                // Check password strength criteria
                if (password.length >= 6) strength += 1;
                if (password.length >= 8) strength += 1;
                if (/[a-z]/.test(password)) strength += 1;
                if (/[A-Z]/.test(password)) strength += 1;
                if (/[0-9]/.test(password)) strength += 1;
                if (/[^A-Za-z0-9]/.test(password)) strength += 1;
                
                // Update visual indicator
                if (password.length === 0) {
                    strengthFill.className = 'strength-fill';
                    strengthFill.style.width = '0%';
                    strengthText.textContent = 'Password strength will appear here';
                    strengthText.style.color = 'var(--gray-600)';
                } else if (strength <= 2) {
                    strengthFill.className = 'strength-fill strength-weak';
                    strengthText.textContent = 'Weak - Add more characters and complexity';
                    strengthText.style.color = 'var(--danger)';
                } else if (strength <= 4) {
                    strengthFill.className = 'strength-fill strength-medium';
                    strengthText.textContent = 'Medium - Good, could be stronger';
                    strengthText.style.color = 'var(--warning)';
                } else {
                    strengthFill.className = 'strength-fill strength-strong';
                    strengthText.textContent = 'Strong - Excellent password!';
                    strengthText.style.color = 'var(--success)';
                }
                
                // Check password match as well
                checkPasswordMatch();
            }
            
            function checkPasswordMatch() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (confirmPassword.length === 0) {
                    passwordMatchError.style.display = 'none';
                    passwordMatchSuccess.style.display = 'none';
                    confirmPasswordInput.classList.remove('error', 'success');
                } else if (password === confirmPassword) {
                    passwordMatchError.style.display = 'none';
                    passwordMatchSuccess.style.display = 'flex';
                    confirmPasswordInput.classList.remove('error');
                    confirmPasswordInput.classList.add('success');
                } else {
                    passwordMatchError.style.display = 'flex';
                    passwordMatchSuccess.style.display = 'none';
                    confirmPasswordInput.classList.remove('success');
                    confirmPasswordInput.classList.add('error');
                }
            }
            
            // Form validation before submission
            registrationForm.addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                // Check password match
                if (password !== confirmPassword) {
                    e.preventDefault();
                    passwordMatchError.style.display = 'flex';
                    confirmPasswordInput.classList.add('error');
                    confirmPasswordInput.focus();
                    
                    // Show error animation
                    confirmPasswordInput.style.animation = 'none';
                    setTimeout(() => {
                        confirmPasswordInput.style.animation = 'shake 0.5s ease';
                    }, 10);
                    
                    return false;
                }
                
                // Check password strength
                if (password.length < 6) {
                    e.preventDefault();
                    passwordInput.classList.add('error');
                    passwordInput.focus();
                    
                    // Show error message
                    strengthText.textContent = 'Password must be at least 6 characters';
                    strengthText.style.color = 'var(--danger)';
                    
                    return false;
                }
                
                // Show loading state
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
                submitButton.disabled = true;
                submitButton.classList.add('btn-disabled');
                
                return true;
            });
            
            // Add animation for form elements
            const formInputs = document.querySelectorAll('.form-control');
            formInputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
            });
            
            // Role selection enhancement
            const roleOptions = document.querySelectorAll('.role-input');
            roleOptions.forEach(option => {
                option.addEventListener('change', function() {
                    // Remove all active states first
                    roleOptions.forEach(opt => {
                        const label = opt.nextElementSibling;
                        label.style.transform = '';
                        label.style.boxShadow = '';
                    });
                    
                    // Add animation to selected option
                    const selectedLabel = this.nextElementSibling;
                    selectedLabel.style.transform = 'translateY(-4px)';
                    selectedLabel.style.boxShadow = 'var(--shadow-md)';
                });
            });
            
            // Auto-focus first input
            setTimeout(() => {
                document.getElementById('nameInput').focus();
            }, 100);
            
            // Add shake animation for errors
            const style = document.createElement('style');
            style.textContent = `
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
                    20%, 40%, 60%, 80% { transform: translateX(5px); }
                }
            `;
            document.head.appendChild(style);
            
            // Add fade-in animation to elements
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((el, index) => {
                el.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>