<?php
// logout.php
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();

// Destroy all session data
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Also clear localStorage via JavaScript
echo '<script>
    localStorage.removeItem("currentUser");
</script>';

// Display logout page with consistent design
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - CampusBloom</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --secondary: #764ba2;
            --accent: #4cc9f0;
            --success: #38b000;
            --warning: #ff9e00;
            --danger: #ef476f;
            --dark: #1a1a2e;
            --light: #f8f9fa;
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --gray-dark: #343a40;
            --border-radius: 16px;
            --border-radius-sm: 12px;
            --border-radius-xs: 8px;
            --shadow-sm: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-md: 0 8px 24px rgba(0,0,0,0.12);
            --shadow-lg: 0 15px 40px rgba(0,0,0,0.15);
            --shadow-xl: 0 20px 50px rgba(0,0,0,0.2);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            color: var(--dark);
            background: linear-gradient(135deg, #f5f7fa 0%, #f0f4f8 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 1rem 2rem;
            box-shadow: var(--shadow-sm);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            transition: var(--transition);
        }

        .header-brand:hover {
            transform: translateY(-2px);
        }

        .header-logo {
            height: 45px;
            width: auto;
            transition: var(--transition);
        }

        .header-brand:hover .header-logo {
            transform: scale(1.05);
        }

        .header-title {
            font-family: 'Poppins', sans-serif;
            font-size: 1.6rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        /* Logout Card */
        .logout-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            width: 100%;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(0, 0, 0, 0.05);
            animation: fadeInUp 0.6s ease-out;
        }

        .logout-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            animation: pulse 2s infinite;
        }

        .logout-icon i {
            font-size: 3rem;
            color: white;
        }

        .logout-card h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }

        .logout-card p {
            color: var(--gray);
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.8;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: var(--gray-light);
            border-radius: 3px;
            margin: 2rem 0;
            overflow: hidden;
        }

        .progress {
            width: 0%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 3px;
            animation: progress 5s linear forwards;
        }

        /* Buttons */
        .btn {
            padding: 1rem 2rem;
            border-radius: var(--border-radius-xs);
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            font-size: 1rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        /* Footer */
        .footer {
            background: linear-gradient(135deg, var(--dark) 0%, #1a1a2e 100%);
            color: white;
            padding: 2rem;
            margin-top: auto;
        }

        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            text-align: center;
        }

        .footer p {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 0.5rem;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.4);
            }
            70% {
                transform: scale(1.05);
                box-shadow: 0 0 0 20px rgba(102, 126, 234, 0);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(102, 126, 234, 0);
            }
        }

        @keyframes progress {
            from {
                width: 0%;
            }
            to {
                width: 100%;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header {
                padding: 1rem;
            }
            
            .logout-card {
                padding: 2rem;
                margin: 1rem;
            }
            
            .logout-card h1 {
                font-size: 2rem;
            }
            
            .logout-icon {
                width: 80px;
                height: 80px;
            }
            
            .logout-icon i {
                font-size: 2.5rem;
            }
        }

        @media (max-width: 480px) {
            .logout-card {
                padding: 1.5rem;
            }
            
            .logout-card h1 {
                font-size: 1.75rem;
            }
            
            .btn {
                padding: 0.75rem 1.5rem;
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="header-brand">
                <img src="img/CemsLogo.png" alt="CampusBloom Logo" class="header-logo">
                <span class="header-title">CampusBloom</span>
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="logout-card">
            <div class="logout-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            
            <h1>Goodbye! 👋</h1>
            <p>You have been successfully logged out of your account.</p>
            <p>Thank you for using CampusBloom. We hope to see you again soon!</p>
            
            <div class="progress-bar">
                <div class="progress"></div>
            </div>
            
            <p>Redirecting to login page...</p>
            
            <a href="login.php" class="btn">
                <i class="fas fa-sign-in-alt"></i> Return to Login
            </a>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <p>&copy; 2025 CampusBloom - Campus Event Management System. All rights reserved.</p>
            <p>Universiti Malaysia Sabah</p>
        </div>
    </footer>

    <script>
        // Redirect to login page after 5 seconds
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 5000);

        // Update countdown timer
        let countdown = 5;
        const countdownElement = document.querySelector('.progress-bar + p');
        
        const countdownInterval = setInterval(function() {
            countdown--;
            if (countdownElement) {
                countdownElement.textContent = `Redirecting to login page in ${countdown} second${countdown !== 1 ? 's' : ''}...`;
            }
            if (countdown <= 0) {
                clearInterval(countdownInterval);
            }
        }, 1000);
    </script>
</body>
</html>