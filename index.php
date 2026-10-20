<?php
// index.php - Public Event Listing
require_once 'config.php';

// Get all active events for public viewing
$sql = "SELECT e.*, u.name as organizer_name 
        FROM events e 
        JOIN users u ON e.created_by = u.id 
        WHERE e.status = 'active' 
        AND e.event_date >= CURDATE() 
        ORDER BY e.event_date ASC, e.event_time ASC";

$result = $conn->query($sql);
$events = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}

// Get upcoming events (next 7 days)
$upcomingSql = "SELECT e.*, u.name as organizer_name 
                FROM events e 
                JOIN users u ON e.created_by = u.id 
                WHERE e.status = 'active' 
                AND e.event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                ORDER BY e.event_date ASC, e.event_time ASC 
                LIMIT 6";
$upcomingResult = $conn->query($upcomingSql);
$upcomingEvents = [];
if ($upcomingResult && $upcomingResult->num_rows > 0) {
    while ($row = $upcomingResult->fetch_assoc()) {
        $upcomingEvents[] = $row;
    }
}

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$user = $isLoggedIn ? getCurrentUser() : null;

// Get registration counts
$registrationCounts = [];
foreach ($events as $event) {
    $registrationCounts[$event['id']] = getEventRegistrationCount($event['id']);
}

// Calculate totals
$totalRegistrations = 0;
foreach ($events as $event) {
    $totalRegistrations += $registrationCounts[$event['id']] ?? 0;
}

$todayEvents = array_filter($events, function($event) {
    return $event['event_date'] == date('Y-m-d');
});

// Logo handling
$logoPath = 'img/CemsLogo.png';
$logoExists = file_exists($logoPath);
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusBloom - University Event Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ================= CSS RESET & BASE ================= */
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
            --secondary-50: rgba(114, 9, 183, 0.05);
            
            --accent: #4cc9f0;
            --accent-dark: #3ab7d9;
            --accent-light: #6bd7f4;
            
            --success: #38b000;
            --success-dark: #2d9500;
            --success-light: #4cd93d;
            
            --warning: #ff9e00;
            --warning-dark: #e68a00;
            --warning-light: #ffb133;
            
            --danger: #ef476f;
            --danger-dark: #d43f63;
            --danger-light: #f26c8a;
            
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
            --shadow-secondary: 0 6px 25px rgba(114, 9, 183, 0.2);
            
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
            --container-max: 1400px;
            --header-height: 80px;
            --section-spacing: 6rem;
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
            scroll-padding-top: var(--header-height);
            font-size: 16px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            line-height: 1.6;
            color: var(--dark);
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%);
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

        .stagger-delay-1 { animation-delay: 0.1s; }
        .stagger-delay-2 { animation-delay: 0.2s; }
        .stagger-delay-3 { animation-delay: 0.3s; }
        .stagger-delay-4 { animation-delay: 0.3s; }
        .stagger-delay-5 { animation-delay: 0.4s; }
        .stagger-delay-6 { animation-delay: 0.5s; }

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

        /* ================= HERO SECTION ================= */
        .hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding-top: var(--header-height);
            overflow: hidden;
            background: linear-gradient(
                135deg,
                rgba(67, 97, 238, 0.95) 0%,
                rgba(114, 9, 183, 0.85) 50%,
                rgba(0, 0, 0, 0.8) 100%
            ),
            url('https://images.unsplash.com/photo-1541336032412-2048a678540d?auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: white;
            clip-path: polygon(0 0, 100% 0, 100% 92%, 0 100%);
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(76, 201, 240, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(114, 9, 183, 0.3) 0%, transparent 50%);
            animation: gradientFloat 20s ease infinite;
        }

        @keyframes gradientFloat {
            0%, 100% { transform: translate(0, 0); }
            33% { transform: translate(20px, 20px); }
            66% { transform: translate(-20px, 10px); }
        }

        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }

        .hero-logo {
            width: 200px;
            height: 200px;
            margin: 0 auto var(--space-2xl);
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius-2xl);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        .hero-logo .logo-image {
            height: 120px;
            filter: drop-shadow(0 8px 32px rgba(0,0,0,0.3));
        }

        .hero-logo .logo-fallback {
            font-family: 'Poppins', sans-serif;
            font-size: 4rem;
            font-weight: 800;
            color: white;
        }

        .hero-tag {
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm) var(--space-lg);
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius-full);
            margin-bottom: var(--space-xl);
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-weight: 500;
            animation: fadeInUp 0.8s 0.2s both;
        }

        .hero-title {
            font-size: 3.75rem;
            font-weight: 800;
            margin-bottom: var(--space);
            line-height: 1.1;
            animation: fadeInUp 0.8s 0.3s both;
        }

        .hero-title .highlight {
            background: linear-gradient(90deg, #fff, var(--accent), #fff);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: textShine 3s linear infinite;
        }

        @keyframes textShine {
            to { background-position: 200% center; }
        }

        .hero-description {
            font-size: 1.25rem;
            opacity: 0.9;
            margin-bottom: var(--space-2xl);
            line-height: 1.6;
            animation: fadeInUp 0.8s 0.4s both;
        }

        .hero-actions {
            display: flex;
            gap: var(--space);
            justify-content: center;
            margin-bottom: var(--space-2xl);
            animation: fadeInUp 0.8s 0.5s both;
        }

        .hero-stats {
            display: flex;
            justify-content: center;
            gap: var(--space-xl);
            flex-wrap: wrap;
            animation: fadeInUp 0.8s 0.6s both;
        }

        .stat-box {
            text-align: center;
            padding: var(--space-lg);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
            min-width: 160px;
            transition: transform var(--transition);
        }

        .stat-box:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15);
        }

        .stat-number {
            font-family: 'Poppins', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            display: block;
            margin-bottom: var(--space-xs);
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* ================= MAIN CONTENT ================= */
        .main {
            position: relative;
            z-index: 1;
            margin-top: calc(var(--section-spacing) * -0.5);
        }

        /* ================= WELCOME SECTION ================= */
        .welcome {
            padding-bottom: var(--section-spacing);
        }

        .welcome-card {
            background: white;
            border-radius: var(--border-radius-xl);
            padding: var(--space-2xl);
            box-shadow: var(--shadow-xl);
            border: var(--border);
            position: relative;
            overflow: hidden;
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 6px;
            height: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-xl);
            flex-wrap: wrap;
            gap: var(--space);
        }

        .welcome-title {
            font-size: 2.5rem;
            color: var(--gray-900);
        }

        .welcome-title .gradient {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .welcome-content {
            max-width: 800px;
        }

        .welcome-text {
            font-size: 1.125rem;
            color: var(--gray-600);
            line-height: 1.7;
            margin-bottom: var(--space-xl);
        }

        /* ================= CATEGORIES SECTION ================= */
        .categories {
            padding-bottom: var(--section-spacing);
        }

        .section-header {
            text-align: center;
            margin-bottom: var(--space-2xl);
        }

        .section-subtitle {
            display: inline-block;
            padding: var(--space-sm) var(--space-lg);
            background: var(--primary-50);
            color: var(--primary);
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: var(--border-radius-full);
            margin-bottom: var(--space);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .section-title {
            font-size: 2.5rem;
            color: var(--gray-900);
            margin-bottom: var(--space);
        }

        .section-description {
            color: var(--gray-600);
            max-width: 600px;
            margin: 0 auto;
            font-size: 1.125rem;
            line-height: 1.6;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: var(--space);
        }

        .category-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: var(--space-xl) var(--space);
            text-align: center;
            cursor: pointer;
            border: var(--border);
            transition: all var(--transition);
        }

        .category-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        .category-card.active {
            background: linear-gradient(135deg, var(--primary-50) 0%, var(--secondary-50) 100%);
            border-color: var(--primary);
        }

        .category-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto var(--space);
            background: var(--primary-50);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.5rem;
            transition: all var(--transition);
        }

        .category-card:hover .category-icon {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            transform: scale(1.1);
        }

        .category-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: var(--space-sm);
            color: var(--gray-800);
        }

        .category-count {
            font-size: 0.85rem;
            color: var(--gray-500);
            background: var(--gray-100);
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--border-radius-full);
        }

        /* ================= EVENTS SECTION ================= */
        .events {
            padding-bottom: var(--section-spacing);
        }

        .events-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-2xl);
            flex-wrap: wrap;
            gap: var(--space);
        }

        .events-title {
            font-size: 2rem;
            color: var(--gray-900);
        }

        .events-controls {
            display: flex;
            gap: var(--space);
            align-items: center;
        }

        .filter-select {
            padding: var(--space) var(--space-lg);
            border: var(--border);
            border-radius: var(--border-radius-full);
            background: white;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            color: var(--gray-700);
            cursor: pointer;
            transition: border-color var(--transition-fast);
            outline: none;
            min-width: 160px;
        }

        .filter-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-50);
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: var(--space-xl);
            margin-bottom: var(--section-spacing);
        }

        .event-card {
            background: white;
            border-radius: var(--border-radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: var(--border);
            transition: all var(--transition);
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .event-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        }

        .event-header {
            padding: var(--space-xl);
            border-bottom: var(--border-light);
            position: relative;
        }

        .event-badge {
            position: absolute;
            top: var(--space);
            right: var(--space);
            padding: var(--space-xs) var(--space);
            border-radius: var(--border-radius-full);
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: white;
        }

        .badge-academic { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); }
        .badge-workshop { background: linear-gradient(135deg, var(--secondary) 0%, var(--secondary-dark) 100%); }
        .badge-seminar { background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%); }
        .badge-social { background: linear-gradient(135deg, var(--success) 0%, var(--success-dark) 100%); }
        .badge-sports { background: linear-gradient(135deg, var(--warning) 0%, var(--warning-dark) 100%); }

        .event-date {
            display: flex;
            align-items: center;
            gap: var(--space);
            margin-bottom: var(--space);
        }

        .date-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: var(--border-radius);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            flex-shrink: 0;
        }

        .date-day {
            font-size: 1.5rem;
            line-height: 1;
        }

        .date-month {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .event-info {
            flex: 1;
        }

        .event-title {
            font-size: 1.375rem;
            font-weight: 700;
            margin-bottom: var(--space-sm);
            line-height: 1.3;
            color: var(--gray-900);
        }

        .event-organizer {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        .organizer-avatar {
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

        .event-body {
            padding: var(--space-xl);
            flex: 1;
        }

        .event-description {
            color: var(--gray-600);
            line-height: 1.6;
            margin-bottom: var(--space-xl);
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .event-meta {
            display: flex;
            flex-direction: column;
            gap: var(--space);
            margin-bottom: var(--space-xl);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: var(--space);
            color: var(--gray-600);
            font-size: 0.95rem;
        }

        .meta-icon {
            color: var(--primary);
            width: 20px;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .meta-content {
            flex: 1;
            color: var(--gray-800);
            font-weight: 500;
        }

        .progress-container {
            margin-top: var(--space);
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: var(--space-sm);
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
            background: linear-gradient(90deg, var(--success) 0%, var(--success-light) 100%);
            border-radius: var(--border-radius-full);
            transition: width 1s ease;
        }

        .event-actions {
            padding: var(--space-xl);
            border-top: var(--border-light);
            background: var(--gray-50);
            display: flex;
            gap: var(--space);
        }

        /* ================= STATS SECTION ================= */
        .stats {
            background: linear-gradient(135deg, var(--gray-900) 0%, #1a1d20 100%);
            color: white;
            border-radius: var(--border-radius-xl);
            margin: var(--section-spacing) 0;
            position: relative;
            overflow: hidden;
        }

        .stats::before {
            content: '';
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(circle at 20% 80%, var(--primary-200) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, var(--secondary-50) 0%, transparent 50%);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: var(--space-xl);
            position: relative;
            z-index: 1;
        }

        .stat-card {
            text-align: center;
            padding: var(--space-2xl);
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--border-radius-lg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            transition: all var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-8px);
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .stat-card-icon {
            font-size: 3rem;
            margin-bottom: var(--space);
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card-number {
            font-family: 'Poppins', sans-serif;
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: var(--space-sm);
            background: linear-gradient(135deg, #fff 0%, #a5b4fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card-label {
            font-size: 1.1rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* ================= CTA SECTION ================= */
        .cta {
            padding: var(--section-spacing) 0;
        }

        .cta-card {
            background: white;
            border-radius: var(--border-radius-xl);
            padding: var(--space-2xl);
            box-shadow: var(--shadow-xl);
            border: var(--border);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .cta-title {
            font-size: 2.5rem;
            margin-bottom: var(--space);
            color: var(--gray-900);
        }

        .cta-description {
            color: var(--gray-600);
            max-width: 600px;
            margin: 0 auto var(--space-2xl);
            font-size: 1.125rem;
            line-height: 1.6;
        }

        /* ================= FOOTER ================= */
        .footer {
            background: linear-gradient(135deg, var(--gray-900) 0%, #1a1d20 100%);
            color: white;
            padding: var(--section-spacing) 0 var(--space-xl);
            position: relative;
        }

        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--space-2xl);
            margin-bottom: var(--space-2xl);
        }

        .footer-brand {
            display: flex;
            flex-direction: column;
            gap: var(--space);
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: var(--space);
        }

        .footer-logo .logo-image {
            height: 48px;
            filter: brightness(0) invert(1);
        }

        .footer-logo .logo-text {
            font-size: 2rem;
            color: white;
            background: none;
            -webkit-text-fill-color: white;
        }

        .footer-description {
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.7;
            max-width: 300px;
        }

        .footer-section h4 {
            font-size: 1.25rem;
            margin-bottom: var(--space-lg);
            color: white;
            position: relative;
            padding-bottom: var(--space);
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

        .social-links {
            display: flex;
            gap: var(--space);
            margin-top: var(--space);
        }

        .social-link {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all var(--transition);
        }

        .social-link:hover {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            transform: translateY(-3px);
        }

        .footer-bottom {
            text-align: center;
            padding-top: var(--space-xl);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.9rem;
            line-height: 1.6;
        }

        /* ================= EMPTY STATE ================= */
        .empty-state {
            text-align: center;
            padding: var(--space-2xl);
            background: white;
            border-radius: var(--border-radius-xl);
            box-shadow: var(--shadow-md);
            border: 2px dashed var(--gray-200);
        }

        .empty-state-icon {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: var(--space);
            opacity: 0.5;
        }

        .empty-state-title {
            font-size: 1.75rem;
            color: var(--gray-800);
            margin-bottom: var(--space);
        }

        .empty-state-description {
            color: var(--gray-600);
            max-width: 400px;
            margin: 0 auto var(--space-xl);
        }

        /* ================= RESPONSIVE DESIGN ================= */
        @media (max-width: 1200px) {
            :root {
                --section-spacing: 5rem;
            }
            
            .events-grid {
                grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
                gap: var(--space-lg);
            }
            
            .hero-title {
                font-size: 3.25rem;
            }
        }

        @media (max-width: 992px) {
            :root {
                --section-spacing: 4rem;
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
            
            .hero {
                min-height: 90vh;
            }
            
            .hero-title {
                font-size: 2.75rem;
            }
            
            .hero-stats {
                gap: var(--space);
            }
            
            .stat-box {
                min-width: 140px;
                padding: var(--space);
            }
            
            .categories-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            :root {
                --section-spacing: 3rem;
                --header-height: 70px;
            }
            
            .container {
                padding: 0 var(--space);
            }
            
            .hero {
                min-height: 80vh;
            }
            
            .hero-title {
                font-size: 2.25rem;
            }
            
            .hero-description {
                font-size: 1.125rem;
            }
            
            .hero-actions {
                flex-direction: column;
                align-items: stretch;
                max-width: 300px;
                margin-left: auto;
                margin-right: auto;
            }
            
            .welcome-header {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--space);
            }
            
            .events-grid {
                grid-template-columns: 1fr;
            }
            
            .events-header {
                flex-direction: column;
                align-items: stretch;
                gap: var(--space);
            }
            
            .events-controls {
                flex-direction: column;
            }
            
            .filter-select {
                width: 100%;
            }
            
            .event-actions {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space);
            }
            
            .stat-card {
                padding: var(--space-xl);
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                gap: var(--space-2xl);
            }
        }

        @media (max-width: 480px) {
            :root {
                --section-spacing: 2.5rem;
            }
            
            .hero {
                min-height: 70vh;
                padding-top: var(--header-height);
            }
            
            .hero-title {
                font-size: 1.875rem;
            }
            
            .hero-description {
                font-size: 1rem;
            }
            
            .hero-stats {
                flex-direction: column;
                gap: var(--space);
            }
            
            .stat-box {
                width: 100%;
                min-width: auto;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .categories-grid {
                grid-template-columns: 1fr;
            }
            
            .logo-text {
                font-size: 1.5rem;
            }
            
            .cta-title {
                font-size: 2rem;
            }
        }

        /* ================= PRINT STYLES ================= */
        @media print {
            .navbar,
            .hero,
            .footer,
            .btn,
            .menu-toggle,
            .hero-stats,
            .categories-grid,
            .event-actions {
                display: none !important;
            }
            
            body {
                background: white;
                color: black;
            }
            
            .main {
                margin-top: 0;
            }
            
            .section {
                padding: 2rem 0;
                page-break-inside: avoid;
            }
            
            .event-card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }
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
                        <div class="logo-fallback">CB</div>
                    <?php endif; ?>
                    <span class="logo-text">CampusBloom</span>
                </div>
            </a>

            <button class="menu-toggle" id="menuToggle" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>

            <div class="navbar-menu" id="navbarMenu">
                <ul class="nav-links">
                    <li><a href="index.php" class="nav-link active"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="#events" class="nav-link"><i class="fas fa-calendar-alt"></i> Events</a></li>
                    <li><a href="#categories" class="nav-link"><i class="fas fa-th-large"></i> Categories</a></li>
                    <?php if ($isLoggedIn && canCreateEvents()): ?>
                        <li><a href="createEvent.php" class="nav-link"><i class="fas fa-plus-circle"></i> Create</a></li>
                    <?php endif; ?>
                </ul>

                <div class="user-menu">
                    <?php if ($isLoggedIn && $user): ?>
                        <div class="user-badge">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                            </div>
                            <span><?php echo htmlspecialchars(explode(' ', $user['name'])[0]); ?></span>
                        </div>
                        <a href="dashboard.php" class="btn btn-sm btn-outline">Dashboard</a>
                        <a href="logout.php" class="btn btn-sm btn-primary">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-sm btn-outline">Login</a>
                        <a href="register.php" class="btn btn-sm btn-primary">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <div class="hero-logo">
                    <?php if ($logoExists): ?>
                        <img src="<?php echo $logoPath; ?>" alt="CampusBloom Logo" class="logo-image">
                    <?php else: ?>
                        <div class="logo-fallback">CB</div>
                    <?php endif; ?>
                </div>

                <div class="hero-tag">
                    <i class="fas fa-university"></i> UNIVERSITI MALAYSIA SABAH
                </div>

                <h1 class="hero-title">
                    Campus Event Management
                    <span class="highlight">System</span>
                </h1>

                <p class="hero-description">
                    Where campus events grow, connect, and transform university life through 
                    professional event management and meaningful connections.
                </p>

                <div class="hero-actions">
                    <?php if ($isLoggedIn): ?>
                        <a href="dashboard.php" class="btn btn-lg btn-primary">
                            <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                        </a>
                        <?php if (canCreateEvents()): ?>
                            <a href="createEvent.php" class="btn btn-lg btn-outline">
                                <i class="fas fa-plus-circle"></i> Create Event
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="register.php" class="btn btn-lg btn-primary">
                            <i class="fas fa-rocket"></i> Get Started Free
                        </a>
                        <a href="#events" class="btn btn-lg btn-outline">
                            <i class="fas fa-calendar-alt"></i> Browse Events
                        </a>
                    <?php endif; ?>
                </div>

                <div class="hero-stats">
                    <div class="stat-box">
                        <span class="stat-number" data-count="<?php echo count($events); ?>">0</span>
                        <span class="stat-label">Active Events</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-number" data-count="<?php echo count(array_unique(array_column($events, 'created_by'))); ?>">0</span>
                        <span class="stat-label">Organizers</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-number" data-count="<?php echo $totalRegistrations; ?>">0</span>
                        <span class="stat-label">Registrations</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="main">
        <div class="container">
            <!-- Welcome Section -->
            <section class="section welcome">
                <div class="welcome-card fade-in">
                    <div class="welcome-header">
                        <h2 class="welcome-title">
                            Welcome to <span class="gradient">CampusBloom</span>
                        </h2>
                        
                        <?php if ($isLoggedIn && $user): ?>
                            <div class="user-badge">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                </div>
                                Welcome back, <?php echo htmlspecialchars(explode(' ', $user['name'])[0]); ?>!
                            </div>
                        <?php else: ?>
                            <div class="user-badge">
                                <i class="fas fa-user-graduate"></i>
                                Join our community of students and organizers
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="welcome-content">
                        <p class="welcome-text">
                            CampusBloom is the premier event management platform designed exclusively for university 
                            communities. Whether you're looking to attend academic workshops, social gatherings, or 
                            professional development sessions, we provide the tools and community to make every event 
                            successful.
                        </p>

                        <div class="hero-actions">
                            <?php if (!$isLoggedIn): ?>
                                <a href="register.php" class="btn btn-primary">
                                    <i class="fas fa-user-plus"></i> Create Account
                                </a>
                                <a href="#events" class="btn btn-outline">
                                    <i class="fas fa-search"></i> Explore Events
                                </a>
                            <?php else: ?>
                                <a href="#events" class="btn btn-primary">
                                    <i class="fas fa-calendar-check"></i> Browse Events
                                </a>
                                <?php if (canCreateEvents()): ?>
                                    <a href="createEvent.php" class="btn btn-success">
                                        <i class="fas fa-plus-circle"></i> Create New Event
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Categories Section -->
            <section id="categories" class="section categories">
                <div class="section-header fade-in">
                    <span class="section-subtitle">Browse by Category</span>
                    <h2 class="section-title">Popular Event Types</h2>
                    <p class="section-description">
                        Discover events that match your interests and academic goals
                    </p>
                </div>

                <div class="categories-grid">
                    <?php 
                    $categories = [
                        'academic' => ['icon' => 'fas fa-graduation-cap', 'name' => 'Academic', 'count' => 0],
                        'workshop' => ['icon' => 'fas fa-laptop-code', 'name' => 'Workshop', 'count' => 0],
                        'seminar' => ['icon' => 'fas fa-chalkboard-teacher', 'name' => 'Seminar', 'count' => 0],
                        'social' => ['icon' => 'fas fa-users', 'name' => 'Social', 'count' => 0],
                        'sports' => ['icon' => 'fas fa-running', 'name' => 'Sports', 'count' => 0],
                        'career' => ['icon' => 'fas fa-briefcase', 'name' => 'Career', 'count' => 0],
                        'cultural' => ['icon' => 'fas fa-globe-asia', 'name' => 'Cultural', 'count' => 0],
                        'volunteer' => ['icon' => 'fas fa-hands-helping', 'name' => 'Volunteer', 'count' => 0]
                    ];

                    // Count events per category
                    foreach ($events as $event) {
                        $type = strtolower($event['event_type'] ?? 'academic');
                        if (isset($categories[$type])) {
                            $categories[$type]['count']++;
                        } else {
                            $categories['academic']['count']++;
                        }
                    }

                    $index = 0;
                    foreach ($categories as $key => $category):
                    ?>
                        <div class="category-card fade-in stagger-delay-<?php echo ($index % 4) + 1; ?>">
                            <div class="category-icon">
                                <i class="<?php echo $category['icon']; ?>"></i>
                            </div>
                            <div class="category-name"><?php echo $category['name']; ?></div>
                            <div class="category-count"><?php echo $category['count']; ?> events</div>
                        </div>
                    <?php 
                        $index++;
                    endforeach; 
                    ?>
                </div>
            </section>

            <!-- Events Section -->
            <section id="events" class="section events">
                <div class="section-header fade-in">
                    <span class="section-subtitle">Featured Events</span>
                    <h2 class="section-title">Upcoming Campus Activities</h2>
                    <p class="section-description">
                        Discover professionally organized events designed to enhance your university experience
                    </p>
                </div>

                <div class="events-header fade-in stagger-delay-1">
                    <h3 class="events-title text-gradient">All Events (<?php echo count($events); ?>)</h3>
                    <div class="events-controls">
                        <select class="filter-select">
                            <option>All Categories</option>
                            <option>Academic</option>
                            <option>Workshop</option>
                            <option>Seminar</option>
                            <option>Social</option>
                            <option>Sports</option>
                        </select>
                        <select class="filter-select">
                            <option>Sort by Date</option>
                            <option>Most Popular</option>
                            <option>Recently Added</option>
                        </select>
                    </div>
                </div>

                <?php if (empty($events)): ?>
                    <div class="empty-state fade-in">
                        <div class="empty-state-icon">
                            <i class="fas fa-calendar-star"></i>
                        </div>
                        <h3 class="empty-state-title">No Events Scheduled</h3>
                        <p class="empty-state-description">
                            Be the first to organize an event on campus. Start creating memorable experiences today.
                        </p>
                        <?php if ($isLoggedIn && canCreateEvents()): ?>
                            <a href="createEvent.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> Launch First Event
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="events-grid">
                        <?php foreach ($events as $index => $event): 
                            $title = htmlspecialchars($event['event_name']);
                            $description = htmlspecialchars($event['description'] ?? 'Join us for an exciting campus event!');
                            $date = $event['event_date'];
                            $time = $event['event_time'];
                            $location = htmlspecialchars($event['venue']);
                            $organizerName = htmlspecialchars($event['organizer_name']);
                            $maxParticipants = $event['max_participants'];
                            $registrationCount = $registrationCounts[$event['id']] ?? 0;
                            $spotsLeft = $maxParticipants ? ($maxParticipants - $registrationCount) : null;
                            $registrationPercentage = $maxParticipants ? ($registrationCount / $maxParticipants * 100) : 0;
                            
                            // Format date
                            $day = date('d', strtotime($date));
                            $month = date('M', strtotime($date));
                            $formattedDate = date('F j, Y', strtotime($date));
                            $formattedTime = date('g:i A', strtotime($time));
                            
                            // Determine badge class
                            $eventType = strtolower($event['event_type'] ?? 'workshop');
                            $badgeClass = 'badge-' . $eventType;
                            $badgeText = ucfirst($eventType);
                        ?>
                            <div class="event-card fade-in stagger-delay-<?php echo ($index % 4) + 1; ?>">
                                <div class="event-header">
                                    <div class="event-badge <?php echo $badgeClass; ?>">
                                        <?php echo $badgeText; ?>
                                    </div>
                                    
                                    <div class="event-date">
                                        <div class="date-icon">
                                            <span class="date-day"><?php echo $day; ?></span>
                                            <span class="date-month"><?php echo $month; ?></span>
                                        </div>
                                        <div class="event-info">
                                            <h3 class="event-title"><?php echo $title; ?></h3>
                                            <div class="event-organizer">
                                                <div class="organizer-avatar">
                                                    <?php echo strtoupper(substr($organizerName, 0, 1)); ?>
                                                </div>
                                                Organized by <?php echo $organizerName; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="event-body">
                                    <p class="event-description"><?php echo $description; ?></p>
                                    
                                    <div class="event-meta">
                                        <div class="meta-item">
                                            <i class="meta-icon far fa-calendar"></i>
                                            <div class="meta-content">
                                                <?php echo $formattedDate; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="meta-item">
                                            <i class="meta-icon far fa-clock"></i>
                                            <div class="meta-content">
                                                <?php echo $formattedTime; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="meta-item">
                                            <i class="meta-icon fas fa-map-marker-alt"></i>
                                            <div class="meta-content">
                                                <?php echo $location; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($maxParticipants && $spotsLeft !== null): ?>
                                            <div class="meta-item">
                                                <i class="meta-icon fas fa-users"></i>
                                                <div class="meta-content">
                                                    <?php echo $registrationCount; ?> / <?php echo $maxParticipants; ?> registered
                                                    <?php if ($spotsLeft <= 5 && $spotsLeft > 0): ?>
                                                        <span style="color: var(--warning); font-weight: 600; margin-left: 5px;">
                                                            (<?php echo $spotsLeft; ?> left!)
                                                        </span>
                                                    <?php elseif ($spotsLeft <= 0): ?>
                                                        <span style="color: var(--danger); font-weight: 600; margin-left: 5px;">(Fully Booked)</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="progress-container">
                                                <div class="progress-header">
                                                    <span>Registration Progress</span>
                                                    <span><?php echo round($registrationPercentage); ?>%</span>
                                                </div>
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo min($registrationPercentage, 100); ?>%;"></div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="event-actions">
                                    <a href="view_event.php?id=<?php echo $event['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    
                                    <?php if ($isLoggedIn): ?>
                                        <?php if ($spotsLeft === null || $spotsLeft > 0): ?>
                                            <a href="event_register.php?event_id=<?php echo $event['id']; ?>" class="btn btn-success">
                                                <i class="fas fa-user-plus"></i> Register Now
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-disabled">
                                                <i class="fas fa-users-slash"></i> Fully Booked
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="login.php?redirect=event_register.php?event_id=<?php echo $event['id']; ?>" class="btn btn-outline">
                                            <i class="fas fa-sign-in-alt"></i> Login to Register
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Stats Section -->
                    <section class="stats fade-in">
                        <div class="container">
                            <div class="section-header" style="color: white;">
                                <span class="section-subtitle" style="color: rgba(255,255,255,0.9);">Platform Insights</span>
                                <h2 class="section-title" style="color: white;">CampusBloom Impact</h2>
                                <p class="section-description" style="color: rgba(255,255,255,0.8);">
                                    Measuring our contribution to campus engagement and student success
                                </p>
                            </div>
                            
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <i class="stat-card-icon fas fa-calendar-check"></i>
                                    <div class="stat-card-number" data-count="<?php echo count($events); ?>">0</div>
                                    <div class="stat-card-label">Total Events</div>
                                </div>
                                
                                <div class="stat-card">
                                    <i class="stat-card-icon fas fa-users"></i>
                                    <div class="stat-card-number" data-count="<?php echo count(array_unique(array_column($events, 'created_by'))); ?>">0</div>
                                    <div class="stat-card-label">Active Organizers</div>
                                </div>
                                
                                <div class="stat-card">
                                    <i class="stat-card-icon fas fa-user-graduate"></i>
                                    <div class="stat-card-number" data-count="<?php echo $totalRegistrations; ?>">0</div>
                                    <div class="stat-card-label">Student Registrations</div>
                                </div>
                                
                                <div class="stat-card">
                                    <i class="stat-card-icon fas fa-bolt"></i>
                                    <div class="stat-card-number" data-count="<?php echo count($todayEvents); ?>">0</div>
                                    <div class="stat-card-label">Events Today</div>
                                </div>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
            </section>

            <!-- CTA Section -->
            <section class="section cta">
                <div class="cta-card fade-in">
                    <h2 class="cta-title">Ready to Transform Your Campus Experience?</h2>
                    <p class="cta-description">
                        Join thousands of students and faculty members who are already using CampusBloom to create, 
                        discover, and participate in meaningful campus events. Start your journey today!
                    </p>
                    
                    <div class="hero-actions">
                        <?php if ($isLoggedIn): ?>
                            <?php if (canCreateEvents()): ?>
                                <a href="createEvent.php" class="btn btn-lg btn-primary">
                                    <i class="fas fa-plus-circle"></i> Create New Event
                                </a>
                            <?php endif; ?>
                            <a href="dashboard.php" class="btn btn-lg btn-outline">
                                <i class="fas fa-chart-line"></i> View Dashboard
                            </a>
                        <?php else: ?>
                            <a href="register.php" class="btn btn-lg btn-primary">
                                <i class="fas fa-user-plus"></i> Join Now - It's Free
                            </a>
                            <a href="#events" class="btn btn-lg btn-outline">
                                <i class="fas fa-search"></i> Browse All Events
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <div class="footer-logo">
                        <?php if ($logoExists): ?>
                           
                            <div class="logo-fallback">CB</div>
                        <?php endif; ?>
                        <span class="logo-text">CampusBloom</span>
                    </div>
                    
                    <p class="footer-description">
                        The leading event management platform for academic institutions, 
                        empowering student engagement and campus community building.
                    </p>
                    
                    <div class="social-links">
                        <a href="#" class="social-link" aria-label="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="social-link" aria-label="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="social-link" aria-label="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="social-link" aria-label="LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h4>Platform</h4>
                    <ul class="footer-links">
                        <li><a href="index.php" class="footer-link"><i class="fas fa-chevron-right"></i> Home</a></li>
                        <li><a href="#events" class="footer-link"><i class="fas fa-chevron-right"></i> Events</a></li>
                        <li><a href="#categories" class="footer-link"><i class="fas fa-chevron-right"></i> Categories</a></li>
                        <?php if ($isLoggedIn && canCreateEvents()): ?>
                            <li><a href="createEvent.php" class="footer-link"><i class="fas fa-chevron-right"></i> Create Event</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Resources</h4>
                    <ul class="footer-links">
                        <?php if (!$isLoggedIn): ?>
                            <li><a href="login.php" class="footer-link"><i class="fas fa-chevron-right"></i> Login</a></li>
                            <li><a href="register.php" class="footer-link"><i class="fas fa-chevron-right"></i> Register</a></li>
                        <?php else: ?>
                            <li><a href="dashboard.php" class="footer-link"><i class="fas fa-chevron-right"></i> Dashboard</a></li>
                            <li><a href="profile.php" class="footer-link"><i class="fas fa-chevron-right"></i> Profile</a></li>
                            <li><a href="logout.php" class="footer-link"><i class="fas fa-chevron-right"></i> Logout</a></li>
                        <?php endif; ?>
                        <li><a href="#" class="footer-link"><i class="fas fa-chevron-right"></i> Help Center</a></li>
                        <li><a href="#" class="footer-link"><i class="fas fa-chevron-right"></i> Contact Us</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Contact Info</h4>
                    <ul class="footer-links">
                        <li>
                            <a href="#" class="footer-link">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>Universiti Malaysia Sabah<br>Jalan UMS, 88400 Kota Kinabalu, Sabah</span>
                            </a>
                        </li>
                        <li>
                            <a href="mailto:support@campusbloom.edu" class="footer-link">
                                <i class="fas fa-envelope"></i>
                                support@campusbloom.edu
                            </a>
                        </li>
                        <li>
                            <a href="tel:+6088320000" class="footer-link">
                                <i class="fas fa-phone"></i>
                                +60 88-320 000
                            </a>
                        </li>
                    </ul>
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
            // Logo handling
            initLogo();
            
            // Navbar
            initNavbar();
            
            // Animations
            initAnimations();
            
            // Stats counters
            initCounters();
            
            // Event interactions
            initEventCards();
            
            // Category interactions
            initCategories();
            
            // Parallax effect
            initParallax();
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
            
            // Smooth scroll for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    if (href === '#' || href === '#!') return;
                    
                    const target = document.querySelector(href);
                    if (target) {
                        e.preventDefault();
                        const navbarHeight = navbar.offsetHeight;
                        const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - navbarHeight - 20;
                        
                        window.scrollTo({
                            top: targetPosition,
                            behavior: 'smooth'
                        });
                        
                        // Update active nav link
                        if (this.classList.contains('nav-link')) {
                            document.querySelectorAll('.nav-link').forEach(link => {
                                link.classList.remove('active');
                            });
                            this.classList.add('active');
                        }
                    }
                });
            });
        }

        // Animations
        function initAnimations() {
            // Intersection Observer for fade-in animations
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationPlayState = 'running';
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
            
            // Observe all fade-in elements
            document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));
            
            // Stagger animations for grid items
            document.querySelectorAll('.stagger-delay-1, .stagger-delay-2, .stagger-delay-3, .stagger-delay-4').forEach(el => {
                observer.observe(el);
            });
        }

        // Animated counters
        function initCounters() {
            const counters = document.querySelectorAll('[data-count]');
            
            const counterObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const element = entry.target;
                        const target = parseInt(element.getAttribute('data-count'));
                        if (!isNaN(target)) {
                            animateCounter(element, target);
                        }
                        counterObserver.unobserve(element);
                    }
                });
            }, { threshold: 0.5 });
            
            counters.forEach(counter => counterObserver.observe(counter));
            
            function animateCounter(element, target, duration = 2000) {
                let start = 0;
                const increment = target / (duration / 16);
                const timer = setInterval(() => {
                    start += increment;
                    if (start >= target) {
                        element.textContent = target.toLocaleString();
                        clearInterval(timer);
                    } else {
                        element.textContent = Math.floor(start).toLocaleString();
                    }
                }, 16);
            }
        }

        // Event card interactions
        function initEventCards() {
            const eventCards = document.querySelectorAll('.event-card');
            
            eventCards.forEach(card => {
                // Hover effect for progress bar
                card.addEventListener('mouseenter', () => {
                    const progressFill = card.querySelector('.progress-fill');
                    if (progressFill) {
                        const currentWidth = progressFill.style.width;
                        progressFill.style.transition = 'width 0.3s ease';
                        setTimeout(() => {
                            progressFill.style.width = currentWidth;
                        }, 10);
                    }
                });
                
                // Click tracking for analytics (example)
                card.addEventListener('click', (e) => {
                    if (e.target.tagName === 'A' || e.target.closest('a')) return;
                    
                    const eventTitle = card.querySelector('.event-title').textContent;
                    console.log(`Event viewed: ${eventTitle}`);
                });
            });
            
            // Register button tracking
            document.querySelectorAll('.btn-success').forEach(btn => {
                btn.addEventListener('click', function() {
                    const eventCard = this.closest('.event-card');
                    const eventTitle = eventCard.querySelector('.event-title').textContent;
                    console.log(`Registration started for: ${eventTitle}`);
                });
            });
        }

        // Category interactions
        function initCategories() {
            const categoryCards = document.querySelectorAll('.category-card');
            
            categoryCards.forEach(card => {
                card.addEventListener('click', function() {
                    const categoryName = this.querySelector('.category-name').textContent;
                    
                    // Visual feedback
                    categoryCards.forEach(c => c.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Filter events by category (example - would need server-side implementation)
                    console.log(`Filtering by category: ${categoryName}`);
                    
                    // Scroll to events section
                    const eventsSection = document.getElementById('events');
                    if (eventsSection) {
                        const navbarHeight = document.getElementById('navbar').offsetHeight;
                        const targetPosition = eventsSection.offsetTop - navbarHeight - 20;
                        
                        window.scrollTo({
                            top: targetPosition,
                            behavior: 'smooth'
                        });
                    }
                });
            });
            
            // Filter select functionality
            document.querySelectorAll('.filter-select').forEach(select => {
                select.addEventListener('change', function() {
                    console.log(`Filter changed: ${this.value}`);
                    // Here you would implement actual filtering logic
                });
            });
        }

        // Parallax effect
        function initParallax() {
            const hero = document.querySelector('.hero');
            if (!hero) return;
            
            let ticking = false;
            
            window.addEventListener('scroll', () => {
                if (!ticking) {
                    window.requestAnimationFrame(() => {
                        const scrolled = window.pageYOffset;
                        const speed = 0.3;
                        hero.style.backgroundPositionY = `${scrolled * speed}px`;
                        ticking = false;
                    });
                    ticking = true;
                }
            });
        }

        // Performance optimizations
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Handle window resize with debounce
        window.addEventListener('resize', debounce(() => {
            // Recalculate anything that depends on window size
        }, 250));

        // Add loading state to buttons
        document.querySelectorAll('.btn:not(.btn-disabled)').forEach(btn => {
            if (btn.href && !btn.href.includes('javascript')) {
                btn.addEventListener('click', function(e) {
                    if (this.classList.contains('no-loading')) return;
                    
                    const originalHTML = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                    this.classList.add('btn-disabled');
                    
                    // Reset after 3 seconds (for demo)
                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                        this.classList.remove('btn-disabled');
                    }, 3000);
                });
            }
        });

        // Add touch support for mobile
        document.addEventListener('touchstart', function() {}, { passive: true });
    </script>
</body>
</html>
<?php
// Close connection
if (isset($conn)) {
    $conn->close();
}