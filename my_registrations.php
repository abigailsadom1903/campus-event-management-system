<?php
// my_registrations.php - PURPLE THEME VERSION
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

// Get user details
$user_id = $_SESSION['user_id'];
$sql = "SELECT name, email FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Handle cancellation request
if (isset($_GET['cancel']) && isset($_GET['registration_id'])) {
    $registration_id = intval($_GET['registration_id']);
    
    // Verify the registration belongs to the current user
    $sql = "SELECT er.*, e.event_name, e.event_date 
            FROM event_registrations er 
            JOIN events e ON er.event_id = e.id 
            WHERE er.id = ? AND er.user_id = ? AND er.status = 'registered'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $registration_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $registration = $result->fetch_assoc();
    $stmt->close();
    
    if ($registration) {
        // Check if event is in the future
        $event_date = strtotime($registration['event_date']);
        $current_date = strtotime(date('Y-m-d'));
        $days_diff = floor(($event_date - $current_date) / (60 * 60 * 24));
        
        if ($days_diff > 1) {
            $sql = "UPDATE event_registrations SET status = 'cancelled' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $registration_id);
            
            if ($stmt->execute()) {
                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'message' => 'Registration cancelled successfully for: ' . $registration['event_name']
                ];
            } else {
                $_SESSION['flash_message'] = [
                    'type' => 'danger',
                    'message' => 'Failed to cancel registration'
                ];
            }
            $stmt->close();
        } else {
            $_SESSION['flash_message'] = [
                'type' => 'warning',
                'message' => 'Cannot cancel registration less than 24 hours before the event'
            ];
        }
    } else {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'message' => 'Registration not found'
        ];
    }
    
    header('Location: my_registrations.php');
    exit();
}

// Get user's registrations
$sql = "SELECT 
            er.id as registration_id,
            er.event_id,
            er.user_email,
            er.user_name,
            er.registration_date,
            er.status,
            er.payment_method,
            er.comments,
            e.event_name,
            e.description,
            e.organizer,
            e.organizer_email,
            e.event_type,
            e.venue,
            e.event_date,
            e.event_time,
            e.mode,
            e.reg_close_date,
            e.max_participants,
            e.fee,
            e.fee_amount,
            e.contact_person,
            e.contact_no,
            e.remarks,
            (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND status = 'registered') as total_registered,
            DATEDIFF(e.event_date, CURDATE()) as days_until_event
        FROM event_registrations er
        JOIN events e ON er.event_id = e.id
        WHERE er.user_id = ? AND er.status != 'cancelled'
        ORDER BY 
            CASE 
                WHEN er.status = 'pending_payment' THEN 1
                WHEN er.status = 'registered' THEN 2
                ELSE 3
            END,
            e.event_date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$registrations = $stmt->get_result();
$total_registrations = $registrations->num_rows;
$stmt->close();

// Get statistics
$sql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'registered' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'pending_payment' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM event_registrations 
        WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats_result = $stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stmt->close();

// Logo handling
$logoPath = 'img/CemsLogo.png';
$logoExists = file_exists($logoPath);
?>

<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Registrations - CampusBloom</title>
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

        /* ================= NAVBAR (Consistent with other pages) ================= */
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

        .btn-danger {
            background: linear-gradient(135deg, var(--danger) 0%, var(--danger-dark) 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning) 0%, var(--warning-dark) 100%);
            color: white;
        }

        .btn-disabled {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* ================= DASHBOARD HEADER ================= */
        .dashboard-header {
            margin-top: var(--header-height);
            padding: var(--space-2xl) 0;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            position: relative;
            overflow: hidden;
            clip-path: polygon(0 0, 100% 0, 100% 92%, 0 100%);
        }

        .dashboard-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(139, 92, 246, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(192, 38, 211, 0.3) 0%, transparent 50%);
        }

        .dashboard-header-content {
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

        .dashboard-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: var(--space);
            line-height: 1.2;
        }

        .dashboard-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            margin-bottom: var(--space-lg);
        }

        /* ================= STATS CARDS ================= */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: var(--space);
            margin: var(--space-2xl) 0;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: var(--space-xl);
            box-shadow: var(--shadow-md);
            border: var(--border);
            text-align: center;
            transition: all var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 6px;
            height: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--space);
            font-size: 1.75rem;
        }

        .stat-icon.total {
            background: var(--primary-50);
            color: var(--primary);
        }

        .stat-icon.active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .stat-icon.pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .stat-icon.cancelled {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .stat-number {
            font-family: 'Poppins', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: var(--space-xs);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        /* ================= FILTER BUTTONS ================= */
        .filter-section {
            margin-bottom: var(--space-2xl);
        }

        .filter-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: var(--space);
            color: var(--gray-800);
        }

        .filter-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-sm);
        }

        .filter-btn {
            padding: var(--space-sm) var(--space-lg);
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius-full);
            background: white;
            font-weight: 500;
            color: var(--gray-700);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-50);
        }

        .filter-btn.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border-color: transparent;
            box-shadow: var(--shadow-primary);
        }

        /* ================= REGISTRATIONS TABLE ================= */
        .registrations-section {
            margin-bottom: var(--space-2xl);
        }

        .registrations-card {
            background: white;
            border-radius: var(--border-radius-xl);
            padding: var(--space-2xl);
            box-shadow: var(--shadow-md);
            border: var(--border);
            position: relative;
            overflow: hidden;
        }

        .registrations-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 6px;
            height: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: var(--space-xl);
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: var(--space);
        }

        .section-title-icon {
            color: var(--primary);
        }

        /* ================= TABLE STYLES ================= */
        .registrations-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .registrations-table thead th {
            background: var(--primary-50);
            color: var(--gray-700);
            font-weight: 600;
            padding: var(--space);
            text-align: left;
            border-bottom: 2px solid var(--primary-200);
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .registrations-table tbody tr {
            transition: all var(--transition-fast);
        }

        .registrations-table tbody tr:hover {
            background: var(--primary-50);
        }

        .registrations-table tbody td {
            padding: var(--space);
            border-bottom: 1px solid var(--gray-100);
            vertical-align: middle;
        }

        .registrations-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* ================= STATUS BADGES ================= */
        .status-badge {
            padding: var(--space-xs) var(--space);
            border-radius: var(--border-radius-full);
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .status-registered {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid var(--success);
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border: 1px solid var(--warning);
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        /* ================= REGISTRATION CARD (Mobile) ================= */
        .registration-card-mobile {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: var(--space);
            margin-bottom: var(--space);
            box-shadow: var(--shadow-sm);
            border: var(--border);
            transition: all var(--transition);
        }

        .registration-card-mobile:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }

        .registration-header {
            display: flex;
            align-items: flex-start;
            gap: var(--space);
            margin-bottom: var(--space);
        }

        .date-badge {
            min-width: 70px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border-radius: var(--border-radius);
            padding: var(--space-sm);
            text-align: center;
            flex-shrink: 0;
        }

        .date-day {
            font-size: 1.5rem;
            font-weight: 800;
            line-height: 1;
        }

        .date-month {
            font-size: 0.8rem;
            opacity: 0.9;
            text-transform: uppercase;
        }

        .event-info h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: var(--space-xs);
            color: var(--gray-900);
        }

        .event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-sm);
            margin-bottom: var(--space);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            color: var(--gray-600);
            font-size: 0.85rem;
        }

        .meta-icon {
            color: var(--primary);
        }

        .registration-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: var(--space);
            border-top: 1px solid var(--gray-100);
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

        .badge-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        /* ================= ACTIONS BUTTONS ================= */
        .actions-group {
            display: flex;
            gap: var(--space-xs);
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            transition: all var(--transition-fast);
        }

        .action-btn.view {
            background: var(--primary-50);
            color: var(--primary);
        }

        .action-btn.view:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        .action-btn.cancel {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .action-btn.cancel:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-2px);
        }

        .action-btn.payment {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .action-btn.payment:hover {
            background: var(--warning);
            color: white;
            transform: translateY(-2px);
        }

        /* ================= EMPTY STATE ================= */
        .empty-state {
            text-align: center;
            padding: var(--space-2xl);
        }

        .empty-state-icon {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: var(--space);
        }

        .empty-state-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: var(--space);
            color: var(--gray-800);
        }

        .empty-state-description {
            color: var(--gray-600);
            max-width: 400px;
            margin: 0 auto var(--space-xl);
        }

        /* ================= MODAL STYLES ================= */
        .modal-content {
            border-radius: var(--border-radius-xl);
            border: none;
            box-shadow: var(--shadow-xl);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border-radius: var(--border-radius-xl) var(--border-radius-xl) 0 0;
            padding: var(--space-xl);
            border: none;
        }

        .modal-title {
            font-weight: 600;
            font-size: 1.25rem;
        }

        .modal-body {
            padding: var(--space-xl);
        }

        .modal-footer {
            padding: var(--space) var(--space-xl) var(--space-xl);
            border: none;
        }

        /* ================= FLASH MESSAGE ================= */
        .flash-message {
            margin: var(--space) auto;
            max-width: var(--container-max);
            padding: 0 var(--space-md);
        }

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

        .alert-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
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
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .dashboard-title {
                font-size: 2rem;
            }
            
            .registrations-table {
                display: none;
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
            
            .dashboard-title {
                font-size: 1.75rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: var(--space);
            }
            
            .stat-card {
                padding: var(--space-lg);
            }
            
            .filter-buttons {
                gap: var(--space-xs);
            }
            
            .filter-btn {
                padding: var(--space-xs) var(--space);
                font-size: 0.9rem;
            }
            
            .modal-header {
                padding: var(--space);
            }
            
            .modal-body {
                padding: var(--space);
            }
        }

        @media (max-width: 480px) {
            :root {
                --section-spacing: 1.5rem;
            }
            
            .dashboard-title {
                font-size: 1.5rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .logo-text {
                font-size: 1.5rem;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .actions-group {
                width: 100%;
                justify-content: center;
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
                    <li><a href="#!" class="nav-link active"><i class="fas fa-ticket-alt"></i> My Registrations</a></li>
                </ul>

                <div class="user-menu">
                    <div class="user-badge">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                        </div>
                        <span><?php echo htmlspecialchars(explode(' ', $user['name'])[0]); ?></span>
                    </div>
                    <a href="dashboard.php" class="btn btn-sm btn-outline">Dashboard</a>
                    <a href="logout.php" class="btn btn-sm btn-primary">Logout</a>
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

    <!-- Dashboard Header -->
    <section class="dashboard-header fade-in">
        <div class="container">
            <div class="dashboard-header-content">
                <!-- Breadcrumb -->
                <nav class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="index.php"><i class="fas fa-home"></i> Home</a>
                        <span class="breadcrumb-separator">/</span>
                    </div>
                    <div class="breadcrumb-item active">
                        <i class="fas fa-ticket-alt"></i> My Registrations
                    </div>
                </nav>

                <!-- Dashboard Title -->
                <h1 class="dashboard-title">My Event Registrations</h1>
                <p class="dashboard-subtitle">Manage and track all your event registrations in one place</p>

                <!-- Action Buttons -->
                <div class="d-flex gap-3">
                    <a href="events.php" class="btn btn-primary">
                        <i class="fas fa-calendar-plus me-2"></i>Browse Events
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="main">
        <div class="container">
            <!-- Statistics Cards -->
            <div class="stats-grid fade-in">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div class="stat-number" style="color: var(--primary);"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="stat-label">Total Registrations</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon active">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number" style="color: var(--success);"><?php echo $stats['active'] ?? 0; ?></div>
                    <div class="stat-label">Active</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number" style="color: var(--warning);"><?php echo $stats['pending'] ?? 0; ?></div>
                    <div class="stat-label">Pending Payment</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon cancelled">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-number" style="color: var(--danger);"><?php echo $stats['cancelled'] ?? 0; ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section fade-in">
                <h3 class="filter-title">Filter Registrations</h3>
                <div class="filter-buttons">
                    <button class="filter-btn active" data-filter="all">All (<?php echo $total_registrations; ?>)</button>
                    <button class="filter-btn" data-filter="registered">Registered (<?php echo $stats['active'] ?? 0; ?>)</button>
                    <button class="filter-btn" data-filter="pending">Pending Payment (<?php echo $stats['pending'] ?? 0; ?>)</button>
                    <button class="filter-btn" data-filter="upcoming">Upcoming Events</button>
                    <button class="filter-btn" data-filter="past">Past Events</button>
                </div>
            </div>

            <!-- Registrations Section -->
            <div class="registrations-section">
                <div class="registrations-card fade-in">
                    <h3 class="section-title">
                        <i class="fas fa-list-alt section-title-icon"></i>
                        My Registrations
                    </h3>

                    <?php if ($total_registrations > 0): ?>
                        <!-- Desktop Table View -->
                        <div class="d-none d-lg-block">
                            <div class="table-responsive">
                                <table class="registrations-table">
                                    <thead>
                                        <tr>
                                            <th>Event Details</th>
                                            <th>Date & Time</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($registration = $registrations->fetch_assoc()): 
                                            $is_upcoming = $registration['days_until_event'] > 0;
                                            $is_past = $registration['days_until_event'] < 0;
                                            $event_date = strtotime($registration['event_date']);
                                            $current_date = strtotime(date('Y-m-d'));
                                            $days_diff = floor(($event_date - $current_date) / (60 * 60 * 24));
                                        ?>
                                            <tr class="registration-row fade-in" 
                                                data-status="<?php echo $registration['status']; ?>"
                                                data-upcoming="<?php echo $is_upcoming ? 'yes' : 'no'; ?>"
                                                data-past="<?php echo $is_past ? 'yes' : 'no'; ?>">
                                                <td>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div class="date-badge">
                                                            <div class="date-day"><?php echo date('d', strtotime($registration['event_date'])); ?></div>
                                                            <div class="date-month"><?php echo date('M', strtotime($registration['event_date'])); ?></div>
                                                        </div>
                                                        <div>
                                                            <h4 class="mb-1"><?php echo htmlspecialchars($registration['event_name']); ?></h4>
                                                            <div class="event-meta">
                                                                <span class="meta-item">
                                                                    <i class="meta-icon fas fa-map-marker-alt"></i>
                                                                    <?php echo htmlspecialchars($registration['venue']); ?>
                                                                </span>
                                                                <span class="meta-item">
                                                                    <i class="meta-icon fas fa-tag"></i>
                                                                    <?php echo $registration['fee'] == 'free' ? 'FREE' : 'RM ' . number_format($registration['fee_amount'], 2); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <div class="fw-medium"><?php echo date('l, M j, Y', strtotime($registration['event_date'])); ?></div>
                                                        <div class="text-muted small">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <?php echo date('h:i A', strtotime($registration['event_time'])); ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($registration['status'] == 'registered'): ?>
                                                        <span class="status-badge status-registered">
                                                            <i class="fas fa-check-circle"></i> Registered
                                                        </span>
                                                        <?php if ($is_upcoming): ?>
                                                            <div class="mt-1">
                                                                <span class="badge badge-success">
                                                                    <i class="fas fa-calendar-day me-1"></i>
                                                                    In <?php echo $registration['days_until_event']; ?> days
                                                                </span>
                                                            </div>
                                                        <?php elseif ($is_past): ?>
                                                            <div class="mt-1">
                                                                <span class="badge badge-secondary">
                                                                    <i class="fas fa-history me-1"></i>
                                                                    <?php echo abs($registration['days_until_event']); ?> days ago
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php elseif ($registration['status'] == 'pending_payment'): ?>
                                                        <span class="status-badge status-pending">
                                                            <i class="fas fa-clock"></i> Pending Payment
                                                        </span>
                                                        <div class="mt-1">
                                                            <span class="badge badge-warning">
                                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                                Action Required
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="actions-group">
                                                        <button type="button" class="action-btn view view-details" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#detailsModal"
                                                                data-registration='<?php echo json_encode($registration); ?>'
                                                                title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <?php if ($registration['status'] == 'registered' && $days_diff > 1): ?>
                                                            <a href="my_registrations.php?cancel=1&registration_id=<?php echo $registration['registration_id']; ?>" 
                                                               class="action-btn cancel"
                                                               onclick="return confirm('Are you sure you want to cancel your registration for <?php echo addslashes($registration['event_name']); ?>?')"
                                                               title="Cancel Registration">
                                                                <i class="fas fa-times"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($registration['status'] == 'pending_payment'): ?>
                                                            <button type="button" class="action-btn payment make-payment"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#paymentModal"
                                                                    data-registration='<?php echo json_encode($registration); ?>'
                                                                    title="Complete Payment">
                                                                <i class="fas fa-credit-card"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Mobile Card View -->
                        <div class="d-lg-none">
                            <?php 
                            // Reset pointer to beginning
                            $registrations->data_seek(0);
                            while ($registration = $registrations->fetch_assoc()): 
                                $is_upcoming = $registration['days_until_event'] > 0;
                                $is_past = $registration['days_until_event'] < 0;
                                $event_date = strtotime($registration['event_date']);
                                $current_date = strtotime(date('Y-m-d'));
                                $days_diff = floor(($event_date - $current_date) / (60 * 60 * 24));
                            ?>
                                <div class="registration-card-mobile fade-in"
                                     data-status="<?php echo $registration['status']; ?>"
                                     data-upcoming="<?php echo $is_upcoming ? 'yes' : 'no'; ?>"
                                     data-past="<?php echo $is_past ? 'yes' : 'no'; ?>">
                                    <div class="registration-header">
                                        <div class="date-badge">
                                            <div class="date-day"><?php echo date('d', strtotime($registration['event_date'])); ?></div>
                                            <div class="date-month"><?php echo date('M', strtotime($registration['event_date'])); ?></div>
                                        </div>
                                        <div class="event-info">
                                            <h4><?php echo htmlspecialchars($registration['event_name']); ?></h4>
                                            <div class="event-meta">
                                                <span class="meta-item">
                                                    <i class="meta-icon fas fa-clock"></i>
                                                    <?php echo date('h:i A', strtotime($registration['event_time'])); ?>
                                                </span>
                                                <span class="meta-item">
                                                    <i class="meta-icon fas fa-map-marker-alt"></i>
                                                    <?php echo htmlspecialchars($registration['venue']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="registration-footer">
                                        <div>
                                            <?php if ($registration['status'] == 'registered'): ?>
                                                <span class="status-badge status-registered">
                                                    <i class="fas fa-check-circle"></i> Registered
                                                </span>
                                                <?php if ($is_upcoming): ?>
                                                    <span class="badge badge-success ms-2">
                                                        In <?php echo $registration['days_until_event']; ?>d
                                                    </span>
                                                <?php elseif ($is_past): ?>
                                                    <span class="badge badge-secondary ms-2">
                                                        <?php echo abs($registration['days_until_event']); ?>d ago
                                                    </span>
                                                <?php endif; ?>
                                            <?php elseif ($registration['status'] == 'pending_payment'): ?>
                                                <span class="status-badge status-pending">
                                                    <i class="fas fa-clock"></i> Pending
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="actions-group">
                                            <button type="button" class="action-btn view view-details" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#detailsModal"
                                                    data-registration='<?php echo json_encode($registration); ?>'
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($registration['status'] == 'registered' && $days_diff > 1): ?>
                                                <a href="my_registrations.php?cancel=1&registration_id=<?php echo $registration['registration_id']; ?>" 
                                                   class="action-btn cancel"
                                                   onclick="return confirm('Are you sure you want to cancel your registration for <?php echo addslashes($registration['event_name']); ?>?')"
                                                   title="Cancel Registration">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($registration['status'] == 'pending_payment'): ?>
                                                <button type="button" class="action-btn payment make-payment"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#paymentModal"
                                                        data-registration='<?php echo json_encode($registration); ?>'
                                                        title="Complete Payment">
                                                    <i class="fas fa-credit-card"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>

                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="empty-state fade-in">
                            <div class="empty-state-icon">
                                <i class="fas fa-calendar-times"></i>
                            </div>
                            <h3 class="empty-state-title">No Registrations Yet</h3>
                            <p class="empty-state-description">
                                You haven't registered for any events yet. Browse available events and join the campus community!
                            </p>
                            <a href="events.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-calendar-plus me-2"></i>Browse Available Events
                            </a>
                        </div>
                    <?php endif; ?>
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

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Registration Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detailsContent">
                    <!-- Details will be loaded here via JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Complete Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="paymentContent">
                    <!-- Payment form will be loaded here via JavaScript -->
                </div>
            </div>
        </div>
    </div>

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
            
            // Filter functionality
            const filterButtons = document.querySelectorAll('.filter-btn');
            const registrationRows = document.querySelectorAll('.registration-row, .registration-card-mobile');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    const filter = this.dataset.filter;
                    
                    registrationRows.forEach(row => {
                        let show = false;
                        
                        switch(filter) {
                            case 'all':
                                show = true;
                                break;
                            case 'registered':
                                show = row.dataset.status === 'registered';
                                break;
                            case 'pending':
                                show = row.dataset.status === 'pending_payment';
                                break;
                            case 'upcoming':
                                show = row.dataset.upcoming === 'yes';
                                break;
                            case 'past':
                                show = row.dataset.past === 'yes';
                                break;
                        }
                        
                        if (show) {
                            row.style.display = '';
                            setTimeout(() => {
                                row.style.opacity = '1';
                                row.style.transform = 'translateY(0)';
                            }, 10);
                        } else {
                            row.style.opacity = '0';
                            row.style.transform = 'translateY(20px)';
                            setTimeout(() => {
                                row.style.display = 'none';
                            }, 300);
                        }
                    });
                });
            });
            
            // Details Modal
            const detailsModal = document.getElementById('detailsModal');
            const detailsContent = document.getElementById('detailsContent');
            
            detailsModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const registration = JSON.parse(button.dataset.registration);
                
                let statusBadge = '';
                if (registration.status === 'registered') {
                    statusBadge = '<span class="badge badge-success">Registered</span>';
                } else if (registration.status === 'pending_payment') {
                    statusBadge = '<span class="badge badge-warning">Pending Payment</span>';
                }
                
                const content = `
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3"><i class="fas fa-calendar-alt me-2"></i>Event Information</h6>
                                    <p class="mb-2"><strong>Event:</strong> ${registration.event_name}</p>
                                    <p class="mb-2"><strong>Date:</strong> ${new Date(registration.event_date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
                                    <p class="mb-2"><strong>Time:</strong> ${new Date('1970-01-01T' + registration.event_time + 'Z').toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</p>
                                    <p class="mb-2"><strong>Venue:</strong> ${registration.venue}</p>
                                    <p class="mb-2"><strong>Type:</strong> ${registration.event_type}</p>
                                    <p class="mb-0"><strong>Mode:</strong> ${registration.mode}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3"><i class="fas fa-ticket-alt me-2"></i>Registration Details</h6>
                                    <p class="mb-2"><strong>Status:</strong> ${statusBadge}</p>
                                    <p class="mb-2"><strong>Registration Date:</strong> ${new Date(registration.registration_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                                    <p class="mb-2"><strong>Registration ID:</strong> REG-${registration.registration_id.toString().padStart(6, '0')}</p>
                                    ${registration.payment_method ? `<p class="mb-2"><strong>Payment Method:</strong> ${registration.payment_method.replace('_', ' ').toUpperCase()}</p>` : ''}
                                    <p class="mb-2"><strong>Fee:</strong> ${registration.fee === 'free' ? 'FREE' : 'RM ' + parseFloat(registration.fee_amount).toFixed(2)}</p>
                                    <p class="mb-0"><strong>Total Registered:</strong> ${registration.total_registered} / ${registration.max_participants || 'Unlimited'}</p>
                                </div>
                            </div>
                        </div>
                        
                        ${registration.description ? `
                        <div class="col-12">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3"><i class="fas fa-align-left me-2"></i>Event Description</h6>
                                    <p>${registration.description.replace(/\n/g, '<br>')}</p>
                                </div>
                            </div>
                        </div>` : ''}
                        
                        ${registration.comments ? `
                        <div class="col-12">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3"><i class="fas fa-comment me-2"></i>Your Comments</h6>
                                    <p>${registration.comments.replace(/\n/g, '<br>')}</p>
                                </div>
                            </div>
                        </div>` : ''}
                        
                        <div class="col-md-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3"><i class="fas fa-building me-2"></i>Organizer Information</h6>
                                    <p class="mb-2"><strong>Organizer:</strong> ${registration.organizer}</p>
                                    <p class="mb-2"><strong>Contact:</strong> ${registration.contact_person || 'N/A'}</p>
                                    <p class="mb-2"><strong>Email:</strong> ${registration.organizer_email}</p>
                                    <p class="mb-0"><strong>Phone:</strong> ${registration.contact_no || 'N/A'}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3"><i class="fas fa-user me-2"></i>Your Information</h6>
                                    <p class="mb-2"><strong>Name:</strong> ${registration.user_name}</p>
                                    <p class="mb-0"><strong>Email:</strong> ${registration.user_email}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                detailsContent.innerHTML = content;
            });
            
            // Payment Modal
            const paymentModal = document.getElementById('paymentModal');
            const paymentContent = document.getElementById('paymentContent');
            
            paymentModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const registration = JSON.parse(button.dataset.registration);
                
                const content = `
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <div class="alert alert-warning mb-4">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-exclamation-triangle fa-lg me-3"></i>
                                    <div>
                                        <p class="mb-1"><strong>Payment Due:</strong> RM ${parseFloat(registration.fee_amount).toFixed(2)}</p>
                                        <p class="mb-0"><strong>Event:</strong> ${registration.event_name}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <form id="paymentForm">
                                <input type="hidden" name="registration_id" value="${registration.registration_id}">
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Payment Method</label>
                                    <div class="d-flex flex-column gap-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="payment_method" id="card" value="credit_card" checked>
                                            <label class="form-check-label d-flex align-items-center" for="card">
                                                <div class="rounded-circle bg-primary-50 p-2 me-3">
                                                    <i class="fas fa-credit-card text-primary"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-medium">Credit/Debit Card</div>
                                                    <div class="text-muted small">Pay securely online</div>
                                                </div>
                                            </label>
                                        </div>
                                        
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="payment_method" id="transfer" value="bank_transfer">
                                            <label class="form-check-label d-flex align-items-center" for="transfer">
                                                <div class="rounded-circle bg-primary-50 p-2 me-3">
                                                    <i class="fas fa-university text-primary"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-medium">Bank Transfer</div>
                                                    <div class="text-muted small">Manual bank transfer</div>
                                                </div>
                                            </label>
                                        </div>
                                        
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="payment_method" id="ewallet" value="e_wallet">
                                            <label class="form-check-label d-flex align-items-center" for="ewallet">
                                                <div class="rounded-circle bg-primary-50 p-2 me-3">
                                                    <i class="fas fa-mobile-alt text-primary"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-medium">E-Wallet</div>
                                                    <div class="text-muted small">Touch 'n Go, GrabPay, etc.</div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="reference" class="form-label fw-bold">Payment Reference (Optional)</label>
                                    <input type="text" class="form-control" id="reference" name="reference" placeholder="Transaction ID or Reference Number">
                                    <div class="form-text">Include this for bank transfers to help us verify your payment</div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-check-circle me-2"></i>Confirm Payment
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                `;
                
                paymentContent.innerHTML = content;
            });
            
            // Add animations to elements
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((el, index) => {
                el.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Add loading state to submit button
            const paymentForm = document.getElementById('paymentForm');
            if (paymentForm) {
                paymentForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalHTML = submitBtn.innerHTML;
                    
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';
                    submitBtn.disabled = true;
                    
                    // Simulate payment processing
                    setTimeout(() => {
                        alert('Payment processed successfully!');
                        location.reload();
                    }, 2000);
                });
            }
        });
    </script>
</body>
</html>