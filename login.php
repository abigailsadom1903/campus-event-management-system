<?php
session_start();
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['isLoggedIn']) && $_SESSION['isLoggedIn'] === true) {
    header('Location: dashboard.php');
    exit();
}

// Auto-create demo accounts if they don't exist
function createDemoAccounts($conn) {
    $demo_accounts = [
        [
            'name' => 'Demo Student',
            'email' => 'student@campusbloom.edu',
            'password' => 'password123',
            'role' => 'student',
            'organization' => 'University Malaysia Sabah',
            'phone' => '+6012-3456789'
        ],
        [
            'name' => 'Demo Staff',
            'email' => 'staff@campusbloom.edu',
            'password' => 'password123',
            'role' => 'staff',
            'organization' => 'University Malaysia Sabah',
            'phone' => '+6012-3456790'
        ],
        [
            'name' => 'Demo Public',
            'email' => 'public@campusbloom.edu',
            'password' => 'password123',
            'role' => 'public',
            'organization' => 'General Public',
            'phone' => '+6012-3456791'
        ]
    ];

    foreach ($demo_accounts as $account) {
        $check_sql = "SELECT id FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $account['email']);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows === 0) {
            $hashed_password = password_hash($account['password'], PASSWORD_DEFAULT);
            
            $insert_sql = "INSERT INTO users (name, email, password, role, organization, phone) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("ssssss", 
                $account['name'],
                $account['email'],
                $hashed_password,
                $account['role'],
                $account['organization'],
                $account['phone']
            );
            
            $insert_stmt->execute();
        }
    }
}

createDemoAccounts($conn);

$error = '';
$email = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $sql = "SELECT id, name, email, password, role, organization, phone 
                    FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                if (password_verify($password, $user['password'])) {
                    $_SESSION['currentUser'] = [
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'organization' => $user['organization'] ?? '',
                        'phone' => $user['phone'] ?? ''
                    ];
                    $_SESSION['isLoggedIn'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    
                    session_regenerate_id(true);
                    
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid email or password.';
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'Login failed. Please try again.';
        }
    }
}

// Logo handling
$logoPath = 'img/CemsLogo.png';
$logoExists = file_exists($logoPath);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CampusBloom Event Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ================= CSS SYSTEM ================= */
        :root {
            /* Color System */
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --primary-light: #6c8aff;
            --primary-50: rgba(67, 97, 238, 0.05);
            --primary-100: rgba(67, 97, 238, 0.1);
            --primary-200: rgba(67, 97, 238, 0.2);
            
            --secondary: #7209b7;
            --secondary-dark: #5e0895;
            --secondary-light: #8a2be2;
            
            --accent: #4cc9f0;
            --accent-dark: #3ab7d9;
            
            --success: #38b000;
            --success-dark: #2d9500;
            --success-light: #4cd93d;
            
            --warning: #ff9e00;
            --warning-dark: #e68a00;
            
            --danger: #ef476f;
            --danger-dark: #d43f63;
            
            --dark: #212529;
            --dark-light: #343a40;
            
            --light: #f8f9fa;
            --light-dark: #e9ecef;
            
            --gray-50: #f8f9fa;
            --gray-100: #f1f3f5;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
            
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
            --shadow-primary: 0 6px 25px rgba(67, 97, 238, 0.2);
            
            /* Animation System */
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition: 250ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 400ms cubic-bezier(0.4, 0, 0.2, 1);
            
            /* Spacing System */
            --space-xs: 0.25rem;
            --space-sm: 0.5rem;
            --space: 1rem;
            --space-md: 1.5rem;
            --space-lg: 2rem;
            --space-xl: 3rem;
            --space-2xl: 4rem;
            
            /* Layout System */
            --container-max: 1400px;
            --header-height: 80px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: var(--header-height);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            line-height: 1.6;
            color: var(--dark);
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            line-height: 1.2;
        }

        /* ================= NAVBAR ================= */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--gray-200);
            z-index: 1000;
            box-shadow: var(--shadow-sm);
            transition: all var(--transition);
        }

        .navbar.scrolled {
            box-shadow: var(--shadow-md);
        }

        .navbar-container {
            max-width: var(--container-max);
            margin: 0 auto;
            padding: 0 var(--space-md);
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: var(--space);
            text-decoration: none;
            transition: var(--transition-fast);
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

        .nav-links {
            display: flex;
            gap: var(--space-sm);
            list-style: none;
        }

        .nav-link {
            position: relative;
            padding: var(--space-sm) var(--space);
            border-radius: var(--border-radius-full);
            font-weight: 500;
            color: var(--gray-700);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            font-size: 0.95rem;
            transition: var(--transition-fast);
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

        .nav-actions {
            display: flex;
            align-items: center;
            gap: var(--space);
        }

        .btn {
            padding: var(--space-sm) var(--space-lg);
            border-radius: var(--border-radius-full);
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 0.95rem;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-sm);
            text-decoration: none;
            position: relative;
            overflow: hidden;
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

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--gray-700);
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* ================= MAIN CONTENT ================= */
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-top: var(--header-height);
            padding-bottom: var(--space-2xl);
        }

        .login-container {
            width: 100%;
            max-width: 480px;
            padding: 0 var(--space);
        }

        /* ================= LOGIN CARD ================= */
        .login-card {
            background: white;
            border-radius: var(--border-radius-xl);
            padding: var(--space-2xl);
            box-shadow: var(--shadow-xl);
            border: var(--border);
            position: relative;
            overflow: hidden;
            animation: slideUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .login-header {
            text-align: center;
            margin-bottom: var(--space-xl);
        }

        .login-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto var(--space);
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-50) 0%, rgba(114, 9, 183, 0.1) 100%);
            border-radius: var(--border-radius-lg);
            border: 2px solid var(--primary-100);
            padding: var(--space);
        }

        .login-logo .logo-image {
            height: 48px;
        }

        .login-logo .logo-fallback {
            font-family: 'Poppins', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-title {
            font-size: 2rem;
            color: var(--gray-900);
            margin-bottom: var(--space-xs);
        }

        .login-subtitle {
            color: var(--gray-600);
            font-size: 1rem;
            line-height: 1.6;
        }

        /* ================= ALERT ================= */
        .alert {
            padding: var(--space);
            border-radius: var(--border-radius);
            margin-bottom: var(--space-xl);
            display: flex;
            align-items: center;
            gap: var(--space);
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .alert-error {
            background: linear-gradient(135deg, rgba(239, 71, 111, 0.1) 0%, rgba(212, 63, 99, 0.05) 100%);
            border: 1px solid rgba(239, 71, 111, 0.2);
            color: var(--danger-dark);
        }

        .alert-error i {
            color: var(--danger);
        }

        /* ================= FORM ================= */
        .form-group {
            margin-bottom: var(--space-lg);
        }

        .form-label {
            display: block;
            margin-bottom: var(--space-sm);
            color: var(--gray-800);
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .form-label i {
            color: var(--primary);
            width: 20px;
        }

        .input-group {
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: var(--space) var(--space) var(--space) var(--space-xl);
            border: var(--border);
            border-radius: var(--border-radius);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            color: var(--gray-900);
            background: white;
            transition: all var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-50);
        }

        .input-icon {
            position: absolute;
            left: var(--space);
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            pointer-events: none;
        }

        .form-actions {
            margin-top: var(--space-xl);
        }

        .btn-login {
            width: 100%;
            padding: var(--space) var(--space-xl);
            font-size: 1rem;
            font-weight: 600;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-sm);
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* ================= DEMO SECTION ================= */
        .demo-section {
            margin-top: var(--space-2xl);
            padding-top: var(--space-xl);
            border-top: 1px solid var(--gray-200);
        }

        .demo-title {
            color: var(--gray-700);
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: var(--space);
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .demo-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space);
            margin-bottom: var(--space-lg);
        }

        .demo-btn {
            padding: var(--space);
            background: var(--gray-50);
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-700);
            cursor: pointer;
            transition: all var(--transition);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: var(--space-sm);
        }

        .demo-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .demo-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .demo-btn i {
            font-size: 1.25rem;
        }

        .demo-credentials {
            background: var(--gray-50);
            border-radius: var(--border-radius);
            padding: var(--space);
            margin-bottom: var(--space);
            border: 1px solid var(--gray-200);
        }

        .credential-row {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            margin-bottom: var(--space-xs);
        }

        .credential-row:last-child {
            margin-bottom: 0;
        }

        .credential-label {
            font-weight: 600;
            color: var(--gray-700);
            min-width: 80px;
            font-size: 0.9rem;
        }

        .credential-value {
            color: var(--gray-800);
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 0.9rem;
            user-select: all;
        }

        .demo-note {
            background: linear-gradient(135deg, rgba(255, 158, 0, 0.1) 0%, rgba(230, 138, 0, 0.05) 100%);
            border: 1px solid rgba(255, 158, 0, 0.2);
            color: var(--warning-dark);
            padding: var(--space);
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: var(--space);
        }

        .demo-note i {
            color: var(--warning);
            flex-shrink: 0;
        }

        /* ================= LINKS ================= */
        .login-links {
            margin-top: var(--space-xl);
            padding-top: var(--space);
            border-top: 1px solid var(--gray-200);
            text-align: center;
        }

        .register-link {
            color: var(--gray-600);
            font-size: 0.95rem;
        }

        .register-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition-fast);
        }

        .register-link a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

        .home-link {
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            margin-top: var(--space);
            color: var(--gray-600);
            text-decoration: none;
            font-size: 0.9rem;
            transition: var(--transition-fast);
        }

        .home-link:hover {
            color: var(--primary);
        }

        .home-link i {
            font-size: 0.9rem;
        }

        /* ================= LOADING ================= */
        .loading {
            display: none;
            margin-top: var(--space);
            text-align: center;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--gray-200);
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto var(--space-xs);
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* ================= RESPONSIVE ================= */
        @media (max-width: 768px) {
            :root {
                --header-height: 70px;
            }
            
            .navbar-container {
                padding: 0 var(--space);
            }
            
            .nav-links {
                display: none;
            }
            
            .nav-actions {
                display: none;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .login-card {
                padding: var(--space-xl) var(--space);
            }
            
            .demo-grid {
                grid-template-columns: 1fr;
                gap: var(--space-sm);
            }
            
            .login-title {
                font-size: 1.75rem;
            }
        }

        @media (max-width: 480px) {
            .login-card {
                padding: var(--space-lg) var(--space);
            }
            
            .login-logo {
                width: 64px;
                height: 64px;
            }
            
            .login-logo .logo-image {
                height: 36px;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
            
            .logo-text {
                font-size: 1.5rem;
            }
        }

        /* ================= LOGO FALLBACK ================= */
        .logo-fallback {
            display: none;
        }

        .logo-image:not([src]), 
        .logo-image[src=""], 
        .logo-image[src*="error"] {
            display: none;
        }

        .logo-image:not([src]) + .logo-fallback,
        .logo-image[src=""] + .logo-fallback,
        .logo-image[src*="error"] + .logo-fallback {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: var(--border-radius-sm);
            color: white;
            font-weight: 800;
            font-size: 18px;
            box-shadow: var(--shadow-primary);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar" id="navbar">
        <div class="navbar-container">
            <a href="index.php" class="navbar-brand">
                <div class="logo-wrapper">
                    <?php if ($logoExists): ?>
                        <img src="<?php echo $logoPath; ?>" alt="CampusBloom Logo" class="logo-image">
                    <?php else: ?>
                        <div class="logo-fallback">CB</div>
                    <?php endif; ?>
                    <span class="logo-text">CampusBloom</span>
                </div>
            </a>

            <button class="menu-toggle" id="menuToggle" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>

            <ul class="nav-links">
                <li><a href="index.php" class="nav-link"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="index.php#events" class="nav-link"><i class="fas fa-calendar-alt"></i> Events</a></li>
                <li><a href="index.php#categories" class="nav-link"><i class="fas fa-th-large"></i> Categories</a></li>
            </ul>

            <div class="nav-actions">
                <a href="register.php" class="btn btn-outline">
                    <i class="fas fa-user-plus"></i> Register
                </a>
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="login-page">
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <div class="login-logo">
                        <?php if ($logoExists): ?>
                            <img src="<?php echo $logoPath; ?>" alt="CampusBloom Logo" class="logo-image">
                        <?php else: ?>
                            <div class="logo-fallback">CB</div>
                        <?php endif; ?>
                    </div>
                    
                    <h1 class="login-title">Welcome Back</h1>
                    <p class="login-subtitle">Sign in to your CampusBloom account</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <label class="form-label" for="emailInput">
                            <i class="fas fa-envelope"></i>
                            Email Address
                        </label>
                        <div class="input-group">
                            <input type="email" 
                                   name="email" 
                                   id="emailInput" 
                                   class="form-control" 
                                   required
                                   value="<?php echo htmlspecialchars($email); ?>"
                                   placeholder="Enter your email address"
                                   autocomplete="email">
                            <span class="input-icon">
                                <i class="fas fa-user"></i>
                            </span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="passwordInput">
                            <i class="fas fa-lock"></i>
                            Password
                        </label>
                        <div class="input-group">
                            <input type="password" 
                                   name="password" 
                                   id="passwordInput" 
                                   class="form-control" 
                                   required
                                   placeholder="Enter your password"
                                   autocomplete="current-password">
                            <span class="input-icon">
                                <i class="fas fa-key"></i>
                            </span>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-login" id="loginButton">
                            <i class="fas fa-sign-in-alt"></i>
                            Sign In to Account
                        </button>
                    </div>
                </form>

                <!-- Demo Accounts Section -->
                <div class="demo-section">
                    <div class="demo-title">Try Demo Accounts</div>
                    
                    <div class="demo-grid">
                        <button class="demo-btn" id="fillStudentBtn">
                            <i class="fas fa-graduation-cap"></i>
                            <span>Student</span>
                        </button>
                        <button class="demo-btn" id="fillStaffBtn">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span>Staff</span>
                        </button>
                        <button class="demo-btn" id="fillPublicBtn">
                            <i class="fas fa-user-friends"></i>
                            <span>Public</span>
                        </button>
                    </div>

                    <div class="demo-credentials" id="demoCredentials">
                        <div class="credential-row">
                            <span class="credential-label">Email:</span>
                            <span class="credential-value" id="demoEmail">-</span>
                        </div>
                        <div class="credential-row">
                            <span class="credential-label">Password:</span>
                            <span class="credential-value" id="demoPassword">-</span>
                        </div>
                    </div>

                    <div class="demo-note">
                        <i class="fas fa-info-circle"></i>
                        <span>Click any demo button to auto-fill credentials</span>
                    </div>
                </div>

                <!-- Loading State -->
                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <div style="color: var(--gray-600); font-size: 0.9rem; margin-top: var(--space-xs);">
                        Signing in...
                    </div>
                </div>

                <!-- Links -->
                <div class="login-links">
                    <div class="register-link">
                        <p>Don't have an account? <a href="register.php">Create new account</a></p>
                    </div>
                    <a href="index.php" class="home-link">
                        <i class="fas fa-home"></i> Back to Homepage
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize components
            initNavbar();
            initLogo();
            initForm();
            initDemoAccounts();
            initKeyboardShortcuts();
        });

        // Logo handling
        function initLogo() {
            const logoImages = document.querySelectorAll('.logo-image');
            logoImages.forEach(img => {
                // Check if image loaded
                if (img.complete && img.naturalHeight === 0) {
                    img.style.display = 'none';
                    const fallback = img.nextElementSibling;
                    if (fallback && fallback.classList.contains('logo-fallback')) {
                        fallback.style.display = 'flex';
                    }
                }
                
                // Handle errors
                img.addEventListener('error', function() {
                    this.style.display = 'none';
                    const fallback = this.nextElementSibling;
                    if (fallback && fallback.classList.contains('logo-fallback')) {
                        fallback.style.display = 'flex';
                    }
                });
            });
        }

        // Navbar functionality
        function initNavbar() {
            const navbar = document.getElementById('navbar');
            
            // Scroll effect
            window.addEventListener('scroll', () => {
                if (window.scrollY > 50) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            });

            // Mobile menu toggle
            const menuToggle = document.getElementById('menuToggle');
            if (menuToggle) {
                menuToggle.addEventListener('click', () => {
                    alert('Mobile menu would open here. This is a simplified demo.');
                });
            }
        }

        // Form functionality
        function initForm() {
            const loginForm = document.getElementById('loginForm');
            const loginButton = document.getElementById('loginButton');
            const emailInput = document.getElementById('emailInput');
            const passwordInput = document.getElementById('passwordInput');
            const loading = document.getElementById('loading');

            // Form submission
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const email = emailInput.value.trim();
                const password = passwordInput.value.trim();
                
                if (!email || !password) {
                    // Show error on empty fields
                    if (!email) {
                        showFieldError(emailInput, 'Email is required');
                    }
                    if (!password) {
                        showFieldError(passwordInput, 'Password is required');
                    }
                    return;
                }
                
                // Show loading state
                loginButton.style.display = 'none';
                loading.style.display = 'block';
                
                // Simulate network delay for demo
                setTimeout(() => {
                    // Submit the form
                    this.submit();
                }, 1000);
            });

            // Input validation
            const inputs = [emailInput, passwordInput];
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    clearFieldError(this);
                    this.style.borderColor = '';
                });
                
                input.addEventListener('focus', function() {
                    this.style.borderColor = 'var(--primary)';
                    this.style.boxShadow = '0 0 0 3px var(--primary-50)';
                });
                
                input.addEventListener('blur', function() {
                    this.style.boxShadow = '';
                    if (!this.value.trim()) {
                        this.style.borderColor = '';
                    }
                });
            });

            // Auto-focus email on page load
            if (!emailInput.value) {
                setTimeout(() => emailInput.focus(), 300);
            }
        }

        // Demo accounts functionality
        function initDemoAccounts() {
            const demoAccounts = {
                student: {
                    email: 'student@campusbloom.edu',
                    password: 'password123',
                    color: '#4361ee'
                },
                staff: {
                    email: 'staff@campusbloom.edu',
                    password: 'password123',
                    color: '#7209b7'
                },
                public: {
                    email: 'public@campusbloom.edu',
                    password: 'password123',
                    color: '#4cc9f0'
                }
            };

            const demoButtons = {
                student: document.getElementById('fillStudentBtn'),
                staff: document.getElementById('fillStaffBtn'),
                public: document.getElementById('fillPublicBtn')
            };

            const demoEmail = document.getElementById('demoEmail');
            const demoPassword = document.getElementById('demoPassword');
            const emailInput = document.getElementById('emailInput');
            const passwordInput = document.getElementById('passwordInput');

            // Function to fill demo credentials
            function fillDemoCredentials(accountType) {
                const account = demoAccounts[accountType];
                if (!account) return;

                // Fill form fields
                emailInput.value = account.email;
                passwordInput.value = account.password;
                
                // Update demo credentials display
                demoEmail.textContent = account.email;
                demoPassword.textContent = account.password;

                // Highlight active demo button
                Object.values(demoButtons).forEach(btn => {
                    btn.classList.remove('active');
                    btn.style.background = '';
                    btn.style.color = '';
                    btn.style.borderColor = '';
                });

                const activeBtn = demoButtons[accountType];
                activeBtn.classList.add('active');
                activeBtn.style.background = account.color;
                activeBtn.style.color = 'white';
                activeBtn.style.borderColor = account.color;

                // Focus on password field
                passwordInput.focus();

                // Show visual feedback
                showVisualFeedback(emailInput);
                showVisualFeedback(passwordInput);
            }

            // Add click handlers
            Object.entries(demoButtons).forEach(([type, button]) => {
                button.addEventListener('click', () => fillDemoCredentials(type));
            });

            // Initialize with student demo
            fillDemoCredentials('student');
        }

        // Utility functions
        function showFieldError(input, message) {
            input.style.borderColor = 'var(--danger)';
            input.style.boxShadow = '0 0 0 3px rgba(239, 71, 111, 0.1)';
            
            // Create or update error message
            let errorMsg = input.parentNode.querySelector('.error-message');
            if (!errorMsg) {
                errorMsg = document.createElement('div');
                errorMsg.className = 'error-message';
                errorMsg.style.color = 'var(--danger)';
                errorMsg.style.fontSize = '0.85rem';
                errorMsg.style.marginTop = '0.25rem';
                input.parentNode.appendChild(errorMsg);
            }
            errorMsg.textContent = message;
        }

        function clearFieldError(input) {
            input.style.borderColor = '';
            input.style.boxShadow = '';
            
            const errorMsg = input.parentNode.querySelector('.error-message');
            if (errorMsg) {
                errorMsg.remove();
            }
        }

        function showVisualFeedback(input) {
            input.style.borderColor = 'var(--primary)';
            input.style.boxShadow = '0 0 0 3px var(--primary-50)';
            
            // Create ripple effect
            const ripple = document.createElement('div');
            ripple.style.position = 'absolute';
            ripple.style.background = 'var(--primary)';
            ripple.style.borderRadius = '50%';
            ripple.style.transform = 'scale(0)';
            ripple.style.animation = 'ripple 0.6s linear';
            ripple.style.pointerEvents = 'none';
            
            const rect = input.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (rect.width - size) / 2 + 'px';
            ripple.style.top = (rect.height - size) / 2 + 'px';
            
            input.parentNode.style.position = 'relative';
            input.parentNode.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        }

        // Keyboard shortcuts
        function initKeyboardShortcuts() {
            document.addEventListener('keydown', function(e) {
                // Ctrl + Number shortcuts for demo accounts
                if (e.ctrlKey && !e.altKey && !e.shiftKey) {
                    const emailInput = document.getElementById('emailInput');
                    
                    switch(e.key) {
                        case '1':
                            e.preventDefault();
                            document.getElementById('fillStudentBtn').click();
                            break;
                        case '2':
                            e.preventDefault();
                            document.getElementById('fillStaffBtn').click();
                            break;
                        case '3':
                            e.preventDefault();
                            document.getElementById('fillPublicBtn').click();
                            break;
                        case 'Enter':
                            // Ensure form has content before submitting
                            if (emailInput.value.trim()) {
                                document.getElementById('loginForm').requestSubmit();
                            }
                            break;
                    }
                }
            });
        }

        // Add CSS for ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(2.5);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>