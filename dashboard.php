<?php
// dashboard.php - FIXED VERSION WITH CONSISTENT UI
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get user from session
$user = getCurrentUser();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Safely assign variables
$userId = isset($user['id']) ? $user['id'] : 0;
$userRole = isset($user['role']) ? $user['role'] : 'public';
$userName = isset($user['name']) ? $user['name'] : 'User';
$userEmail = isset($user['email']) ? $user['email'] : '';
$userOrganization = isset($user['organization']) ? $user['organization'] : '';
$userPhone = isset($user['phone']) ? $user['phone'] : '';
$userCreatedAt = isset($user['created_at']) ? $user['created_at'] : date('Y-m-d');

// Initialize arrays
$allEvents = [];
$myEvents = [];
$myEventsCount = 0;
$myRegistrations = [];
$registeredCount = 0;
$availableEvents = [];
$pendingParticipants = 0;

try {
    if ($userId > 0) {
        // Fetch ALL events for viewing
        $sql = "SELECT e.*, u.name as organizer_name 
                FROM events e 
                JOIN users u ON e.created_by = u.id 
                ORDER BY e.event_date DESC, e.event_time DESC";
        $allEventsResult = $conn->query($sql);
        if ($allEventsResult) {
            while ($row = $allEventsResult->fetch_assoc()) {
                $allEvents[] = $row;
            }
        }

        // Get user's events
        $sql = "SELECT e.*, u.name as organizer_name 
                FROM events e 
                JOIN users u ON e.created_by = u.id 
                WHERE e.created_by = ? 
                ORDER BY e.event_date DESC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $myEvents[] = $row;
                }
                $myEventsCount = count($myEvents);
            }
            $stmt->close();
        }

        // Get pending participants count
        $pendingParticipants = 0;
        if (tableExists('registrations')) {
            $checkColumnSql = "SHOW COLUMNS FROM registrations LIKE 'status'";
            $checkResult = $conn->query($checkColumnSql);
            if ($checkResult && $checkResult->num_rows > 0) {
                $sql = "SELECT COUNT(*) as pending_count FROM registrations r 
                        JOIN events e ON r.event_id = e.id 
                        WHERE e.created_by = ? AND r.status = 'pending'";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("i", $userId);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result()->fetch_assoc();
                        $pendingParticipants = $result['pending_count'] ?? 0;
                    }
                    $stmt->close();
                }
            }
        }

        // Get user's registrations
        if (tableExists('registrations')) {
            $sql = "SELECT r.*, e.event_name, e.event_date, e.event_time, 
                           e.venue, e.max_participants, e.id as event_id
                    FROM registrations r 
                    JOIN events e ON r.event_id = e.id 
                    WHERE r.user_id = ? 
                    ORDER BY e.event_date DESC";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $myRegistrations[] = $row;
                    }
                    $registeredCount = count($myRegistrations);
                }
                $stmt->close();
            }
        }
        
        // Get available events
        if (tableExists('registrations')) {
            $sql = "SELECT e.*, u.name as organizer_name,
                           (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.user_id = ?) as is_registered
                    FROM events e 
                    JOIN users u ON e.created_by = u.id
                    WHERE e.created_by != ? 
                    AND e.event_date >= CURDATE()
                    ORDER BY e.event_date ASC
                    LIMIT 10";
            
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ii", $userId, $userId);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $availableEvents[] = $row;
                    }
                }
                $stmt->close();
            }
        } else {
            $sql = "SELECT e.*, u.name as organizer_name
                    FROM events e 
                    JOIN users u ON e.created_by = u.id
                    WHERE e.created_by != ? 
                    AND e.event_date >= CURDATE()
                    ORDER BY e.event_date ASC
                    LIMIT 10";
            
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $row['is_registered'] = 0;
                        $availableEvents[] = $row;
                    }
                }
                $stmt->close();
            }
        }
    }
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading dashboard data.');
}

// Helper functions
function isEventUpcoming($eventDate) {
    if (empty($eventDate)) return false;
    $eventTimestamp = strtotime($eventDate);
    return $eventTimestamp > time();
}

function getEventStatus($event) {
    if (empty($event['event_date'])) return 'unknown';
    $eventTimestamp = strtotime($event['event_date']);
    $now = time();
    return $eventTimestamp > $now ? 'upcoming' : 'completed';
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
    <title>Dashboard - CampusBloom Event Management System</title>
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
            --secondary-50: rgba(114, 9, 183, 0.05);
            
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
            gap: var(--space-xs);
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

        /* ================= BUTTONS ================= */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-sm);
            padding: var(--space-sm) var(--space-lg);
            border-radius: var(--border-radius-full);
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 0.95rem;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all var(--transition);
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

        .btn-sm {
            padding: var(--space-xs) var(--space);
            font-size: 0.85rem;
        }

        .btn-lg {
            padding: var(--space) var(--space-xl);
            font-size: 1rem;
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

        .btn-warning {
            background: linear-gradient(135deg, var(--warning) 0%, var(--warning-dark) 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger) 0%, var(--danger-dark) 100%);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            color: white;
        }

        .btn-manage {
            background: linear-gradient(135deg, #9d4edd 0%, #c77dff 100%);
            color: white;
        }

        .btn-disabled {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* ================= MAIN CONTENT ================= */
        .dashboard {
            padding-top: var(--header-height);
            padding-bottom: var(--space-2xl);
        }

        .dashboard-container {
            max-width: var(--container-max);
            margin: 0 auto;
            padding: 0 var(--space-md);
        }

        /* ================= WELCOME CARD ================= */
        .welcome-card {
            background: white;
            border-radius: var(--border-radius-xl);
            padding: var(--space-2xl);
            box-shadow: var(--shadow-xl);
            border: var(--border);
            margin-bottom: var(--space-xl);
            position: relative;
            overflow: hidden;
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
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
            margin-bottom: var(--space-xs);
        }

        .welcome-subtitle {
            color: var(--gray-600);
            font-size: 1.125rem;
        }

        .user-badge-lg {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: var(--space-sm) var(--space-lg);
            border-radius: var(--border-radius-full);
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            box-shadow: var(--shadow-primary);
        }

        .user-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--space);
            margin-top: var(--space-xl);
        }

        .info-card {
            background: var(--primary-50);
            border-radius: var(--border-radius-lg);
            padding: var(--space-lg);
            border: 1px solid var(--primary-100);
            transition: transform var(--transition);
        }

        .info-card:hover {
            transform: translateY(-3px);
        }

        .info-header {
            display: flex;
            align-items: center;
            gap: var(--space);
            margin-bottom: var(--space);
        }

        .info-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--border-radius);
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }

        .info-label {
            font-size: 0.9rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .info-value {
            font-size: 1.125rem;
            color: var(--gray-900);
            font-weight: 600;
        }

        /* ================= STATS GRID ================= */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--space);
            margin-bottom: var(--space-xl);
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: var(--space-xl);
            box-shadow: var(--shadow-lg);
            border: var(--border);
            transition: all var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--border-radius);
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }

        .stat-number {
            font-family: 'Poppins', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            line-height: 1;
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 0.95rem;
            margin-top: var(--space-sm);
            font-weight: 500;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            font-size: 0.9rem;
            margin-top: var(--space);
            color: var(--success);
            font-weight: 500;
        }

        .stat-trend.negative {
            color: var(--danger);
        }

        /* ================= SECTION CARDS ================= */
        .section-card {
            background: white;
            border-radius: var(--border-radius-xl);
            padding: var(--space-xl);
            margin-bottom: var(--space-xl);
            box-shadow: var(--shadow-lg);
            border: var(--border);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-xl);
            padding-bottom: var(--space);
            border-bottom: 2px solid var(--gray-100);
        }

        .section-title {
            font-size: 1.75rem;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: var(--space);
        }

        .section-title i {
            color: var(--primary);
        }

        /* ================= EVENTS GRID ================= */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: var(--space);
            margin-bottom: var(--space-xl);
        }

        .event-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: var(--space-lg);
            border: var(--border);
            box-shadow: var(--shadow-sm);
            transition: all var(--transition);
        }

        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        .event-header {
            margin-bottom: var(--space);
        }

        .event-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: var(--space-sm);
            line-height: 1.3;
        }

        .event-meta {
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
            margin-bottom: var(--space);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        .meta-item i {
            color: var(--primary);
            width: 16px;
        }

        .event-actions {
            display: flex;
            gap: var(--space-xs);
            margin-top: var(--space);
            flex-wrap: wrap;
        }

        /* ================= QUICK MANAGE ================= */
        .quick-manage {
            background: linear-gradient(135deg, var(--primary-50) 0%, var(--secondary-50) 100%);
            border: 1px solid var(--primary-100);
            border-radius: var(--border-radius-xl);
            padding: var(--space-xl);
            margin-bottom: var(--space-xl);
        }

        .quick-manage-header {
            display: flex;
            align-items: center;
            gap: var(--space);
            margin-bottom: var(--space);
            color: var(--primary);
        }

        .quick-manage-header i {
            font-size: 1.5rem;
        }

        .quick-manage-header h3 {
            font-size: 1.5rem;
            color: var(--gray-900);
        }

        .quick-manage-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--space);
            margin-top: var(--space);
        }

        .quick-manage-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: var(--space);
            border: 1px solid var(--primary-100);
            transition: all var(--transition);
        }

        .quick-manage-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .quick-manage-card h4 {
            font-size: 1.125rem;
            color: var(--gray-900);
            margin-bottom: var(--space-sm);
        }

        .quick-manage-meta {
            display: flex;
            gap: var(--space);
            margin-bottom: var(--space);
            flex-wrap: wrap;
        }

        .quick-manage-meta span {
            font-size: 0.85rem;
            color: var(--gray-600);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .quick-manage-meta i {
            color: var(--primary);
        }

        /* ================= EMPTY STATES ================= */
        .empty-state {
            text-align: center;
            padding: var(--space-2xl) var(--space);
            color: var(--gray-600);
        }

        .empty-state-icon {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: var(--space);
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            color: var(--gray-800);
            margin-bottom: var(--space-sm);
        }

        .empty-state p {
            margin-bottom: var(--space-lg);
        }

        /* ================= BADGES ================= */
        .badge {
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--border-radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .badge-primary {
            background: var(--primary-100);
            color: var(--primary);
            border: 1px solid var(--primary-200);
        }

        .badge-success {
            background: rgba(56, 176, 0, 0.1);
            color: var(--success);
            border: 1px solid rgba(56, 176, 0, 0.2);
        }

        .badge-warning {
            background: rgba(255, 158, 0, 0.1);
            color: var(--warning);
            border: 1px solid rgba(255, 158, 0, 0.2);
        }

        .badge-danger {
            background: rgba(239, 71, 111, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 71, 111, 0.2);
        }

        .badge-info {
            background: rgba(76, 201, 240, 0.1);
            color: var(--accent);
            border: 1px solid rgba(76, 201, 240, 0.2);
        }

        .badge-upcoming {
            background: rgba(76, 201, 240, 0.1);
            color: var(--accent);
            border: 1px solid rgba(76, 201, 240, 0.2);
        }

        .badge-completed {
            background: var(--gray-100);
            color: var(--gray-600);
            border: 1px solid var(--gray-200);
        }

        /* ================= MESSAGES ================= */
        .message {
            padding: var(--space);
            border-radius: var(--border-radius);
            margin-bottom: var(--space);
            display: flex;
            align-items: center;
            gap: var(--space);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message-success {
            background: rgba(56, 176, 0, 0.1);
            border: 1px solid rgba(56, 176, 0, 0.2);
            color: var(--success);
        }

        .message-error {
            background: rgba(239, 71, 111, 0.1);
            border: 1px solid rgba(239, 71, 111, 0.2);
            color: var(--danger);
        }

        .message-warning {
            background: rgba(255, 158, 0, 0.1);
            border: 1px solid rgba(255, 158, 0, 0.2);
            color: var(--warning);
        }

        .message-info {
            background: var(--primary-50);
            border: 1px solid var(--primary-100);
            color: var(--primary);
        }

        /* ================= FOOTER ================= */
        .footer {
            background: linear-gradient(135deg, var(--gray-900) 0%, #1a1d20 100%);
            color: white;
            padding: var(--space-2xl) 0 var(--space-xl);
            margin-top: var(--space-2xl);
            position: relative;
        }

        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .footer-container {
            max-width: var(--container-max);
            margin: 0 auto;
            padding: 0 var(--space-md);
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
            gap: var(--space-sm);
        }

        .footer-link {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            transition: var(--transition-fast);
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
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: var(--transition);
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

        /* ================= RESPONSIVE ================= */
        @media (max-width: 1200px) {
            .events-grid {
                grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            }
            
            .welcome-title {
                font-size: 2rem;
            }
        }

        @media (max-width: 992px) {
            .nav-links {
                display: none;
            }
            
            .user-menu {
                display: none;
            }
            
            .events-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
            
            .welcome-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--space);
            }
            
            .quick-manage-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            :root {
                --header-height: 70px;
            }
            
            .dashboard-container {
                padding: 0 var(--space);
            }
            
            .welcome-card {
                padding: var(--space-xl);
            }
            
            .section-card {
                padding: var(--space-lg);
            }
            
            .events-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .user-info-grid {
                grid-template-columns: 1fr;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                gap: var(--space-xl);
            }
        }

        @media (max-width: 480px) {
            .welcome-title {
                font-size: 1.75rem;
            }
            
            .section-title {
                font-size: 1.5rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .event-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .event-actions .btn {
                width: 100%;
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
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="navbar-brand">
                <div class="logo-wrapper">
                    <?php if ($logoExists): ?>
                        <img src="<?php echo $logoPath; ?>" alt="CampusBloom Logo" class="logo-image">
                    <?php else: ?>
                        <div class="logo-fallback">CB</div>
                    <?php endif; ?>
                    <span class="logo-text">CampusBloom</span>
                </div>
            </a>

            <ul class="nav-links">
                <li><a href="dashboard.php" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="index.php" class="nav-link"><i class="fas fa-home"></i> Home</a></li>
                <?php if (canCreateEvents()): ?>
                    <li><a href="createEvent.php" class="nav-link"><i class="fas fa-plus-circle"></i> Create Event</a></li>
                <?php endif; ?>
                <li><a href="events.php" class="nav-link"><i class="fas fa-calendar"></i> All Events</a></li>
            </ul>

            <div class="user-menu">
                <div class="user-badge">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($userName, 0, 1)); ?>
                    </div>
                    <span><?php echo htmlspecialchars($userName); ?></span>
                </div>
                <a href="logout.php" class="btn btn-sm btn-outline">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="dashboard">
        <div class="dashboard-container">
            <?php displayFlashMessages(); ?>

            <!-- Welcome Card -->
            <div class="welcome-card fade-in">
                <div class="welcome-header">
                    <div>
                        <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($userName); ?>! 👋</h1>
                        <p class="welcome-subtitle">Manage your events and participants in one place</p>
                    </div>
                    <div class="user-badge-lg">
                        <i class="fas fa-user-tag"></i>
                        <?php echo ucfirst($userRole); ?> Account
                    </div>
                </div>
                
                <div class="user-info-grid">
                    <div class="info-card">
                        <div class="info-header">
                            <div class="info-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <div class="info-label">Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($userEmail); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($userOrganization)): ?>
                    <div class="info-card">
                        <div class="info-header">
                            <div class="info-icon">
                                <i class="fas fa-university"></i>
                            </div>
                            <div>
                                <div class="info-label">Organization</div>
                                <div class="info-value"><?php echo htmlspecialchars($userOrganization); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($userPhone)): ?>
                    <div class="info-card">
                        <div class="info-header">
                            <div class="info-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div>
                                <div class="info-label">Phone</div>
                                <div class="info-value"><?php echo htmlspecialchars($userPhone); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-card">
                        <div class="info-header">
                            <div class="info-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div>
                                <div class="info-label">Member Since</div>
                                <div class="info-value"><?php echo date('F j, Y', strtotime($userCreatedAt)); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card fade-in">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $myEventsCount; ?></div>
                    <div class="stat-label">Events Created</div>
                    <div class="stat-trend">
                        <i class="fas fa-chart-line"></i>
                        <span>Manage your events</span>
                    </div>
                </div>
                
                <div class="stat-card fade-in">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $registeredCount; ?></div>
                    <div class="stat-label">My Registrations</div>
                    <div class="stat-trend">
                        <i class="fas fa-users"></i>
                        <span>Active participations</span>
                    </div>
                </div>
                
                <div class="stat-card fade-in">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $pendingParticipants; ?></div>
                    <div class="stat-label">Pending Approvals</div>
                    <div class="stat-trend">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Needs attention</span>
                    </div>
                </div>
                
                <div class="stat-card fade-in">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo count($availableEvents); ?></div>
                    <div class="stat-label">Available Events</div>
                    <div class="stat-trend">
                        <i class="fas fa-bolt"></i>
                        <span>Ready to join</span>
                    </div>
                </div>
            </div>

            <!-- Quick Manage Section -->
            <?php if ($pendingParticipants > 0): ?>
            <div class="quick-manage fade-in">
                <div class="quick-manage-header">
                    <i class="fas fa-users-cog"></i>
                    <h3>Quick Participant Management</h3>
                </div>
                <p style="color: var(--gray-700); margin-bottom: var(--space);">
                    You have <strong style="color: var(--warning);"><?php echo $pendingParticipants; ?> pending participant(s)</strong> awaiting your approval.
                </p>
                
                <?php 
                $quickManageItems = [];
                if (tableExists('registrations')) {
                    $checkColumnSql = "SHOW COLUMNS FROM registrations LIKE 'status'";
                    $checkResult = $conn->query($checkColumnSql);
                    if ($checkResult && $checkResult->num_rows > 0) {
                        $pendingEventsSql = "SELECT e.id, e.event_name, e.event_date, 
                                                    COUNT(r.id) as pending_count
                                             FROM events e 
                                             JOIN registrations r ON e.id = r.event_id 
                                             WHERE e.created_by = ? AND r.status = 'pending' 
                                             GROUP BY e.id 
                                             ORDER BY pending_count DESC 
                                             LIMIT 3";
                        $stmt = $conn->prepare($pendingEventsSql);
                        if ($stmt) {
                            $stmt->bind_param("i", $userId);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            while ($row = $result->fetch_assoc()) {
                                $quickManageItems[] = $row;
                            }
                            $stmt->close();
                        }
                    }
                }
                ?>
                
                <?php if (!empty($quickManageItems)): ?>
                <div class="quick-manage-grid">
                    <?php foreach ($quickManageItems as $event): ?>
                    <div class="quick-manage-card">
                        <h4><?php echo htmlspecialchars($event['event_name']); ?></h4>
                        <div class="quick-manage-meta">
                            <span>
                                <i class="far fa-calendar"></i>
                                <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                            </span>
                            <span>
                                <i class="fas fa-users"></i>
                                <strong style="color: var(--warning);"><?php echo $event['pending_count']; ?></strong> pending
                            </span>
                        </div>
                        <div class="event-actions">
                            <a href="manage.php?id=<?php echo $event['id']; ?>&filter=pending" class="btn btn-manage btn-sm">
                                <i class="fas fa-users-cog"></i> Manage
                            </a>
                            <a href="view_event.php?id=<?php echo $event['id']; ?>" class="btn btn-info btn-sm">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- All Events Section -->
            <div class="section-card fade-in">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-calendar-alt"></i>
                        All Events
                    </h2>
                </div>

                <?php if (empty($allEvents)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-calendar-times"></i>
                        </div>
                        <h3>No events available at the moment</h3>
                        <p>Check back later for new events!</p>
                    </div>
                <?php else: ?>
                    <div class="events-grid">
                        <?php foreach ($allEvents as $event): 
                            $title = $event['event_name'] ?? 'Untitled Event';
                            $date = $event['event_date'] ?? '';
                            $time = $event['event_time'] ?? '';
                            $location = $event['venue'] ?? '';
                            $organizer_name = $event['organizer_name'] ?? 'Unknown';
                            $created_by = $event['created_by'] ?? 0;
                            $max_participants = $event['max_participants'] ?? 0;
                            $description = $event['description'] ?? '';
                            $eventStatus = getEventStatus($event);
                        ?>
                            <div class="event-card">
                                <div class="event-header">
                                    <h3 class="event-title"><?php echo htmlspecialchars($title); ?></h3>
                                    <span class="badge badge-<?php echo $eventStatus; ?>">
                                        <i class="fas fa-circle"></i>
                                        <?php echo ucfirst($eventStatus); ?>
                                    </span>
                                </div>
                                
                                <div class="event-meta">
                                    <div class="meta-item">
                                        <i class="far fa-calendar"></i>
                                        <span><?php echo date('F j, Y', strtotime($date)); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="far fa-clock"></i>
                                        <span><?php echo date('g:i A', strtotime($time)); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($location); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-user"></i>
                                        <span><?php echo htmlspecialchars($organizer_name); ?></span>
                                    </div>
                                </div>
                                
                                <div class="event-actions">
                                    <a href="view_event.php?id=<?php echo $event['id']; ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    
                                    <?php if ($created_by == $userId): ?>
                                        <a href="manage.php?id=<?php echo $event['id']; ?>" class="btn btn-manage btn-sm">
                                            <i class="fas fa-users-cog"></i> Manage
                                        </a>
                                        <a href="createEvent.php?edit=<?php echo $event['id']; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- My Events Section -->
            <div class="section-card fade-in">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-calendar-star"></i>
                        My Events (Created by Me)
                    </h2>
                    <?php if (canCreateEvents()): ?>
                        <a href="createEvent.php" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Create New Event
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (empty($myEvents)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <h3>No events created yet</h3>
                        <p>Create your first event to get started!</p>
                        <?php if (canCreateEvents()): ?>
                            <a href="createEvent.php" class="btn btn-primary" style="margin-top: var(--space);">
                                <i class="fas fa-plus-circle"></i> Create Your First Event
                            </a>
                        <?php else: ?>
                            <div class="badge badge-info" style="margin-top: var(--space); display: inline-flex;">
                                <i class="fas fa-info-circle"></i> Only authorized users can create events
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="events-grid">
                        <?php foreach ($myEvents as $event): 
                            $title = $event['event_name'] ?? 'Untitled Event';
                            $date = $event['event_date'] ?? '';
                            $time = $event['event_time'] ?? '';
                            $location = $event['venue'] ?? '';
                            $eventStatus = getEventStatus($event);
                            
                            $pendingCount = 0;
                            if (tableExists('registrations')) {
                                $checkColumnSql = "SHOW COLUMNS FROM registrations LIKE 'status'";
                                $checkResult = $conn->query($checkColumnSql);
                                if ($checkResult && $checkResult->num_rows > 0) {
                                    $eventPendingSql = "SELECT COUNT(*) as pending_count FROM registrations 
                                                       WHERE event_id = ? AND status = 'pending'";
                                    $pendingStmt = $conn->prepare($eventPendingSql);
                                    if ($pendingStmt) {
                                        $pendingStmt->bind_param("i", $event['id']);
                                        $pendingStmt->execute();
                                        $result = $pendingStmt->get_result()->fetch_assoc();
                                        $pendingCount = $result['pending_count'] ?? 0;
                                        $pendingStmt->close();
                                    }
                                }
                            }
                        ?>
                            <div class="event-card">
                                <div class="event-header">
                                    <div style="display: flex; align-items: center; gap: var(--space-sm); flex-wrap: wrap;">
                                        <h3 class="event-title"><?php echo htmlspecialchars($title); ?></h3>
                                        <?php if ($pendingCount > 0): ?>
                                            <span class="badge badge-warning">
                                                <i class="fas fa-clock"></i> <?php echo $pendingCount; ?> pending
                                            </span>
                                        <?php endif; ?>
                                        <span class="badge badge-<?php echo $eventStatus; ?>">
                                            <i class="fas fa-circle"></i>
                                            <?php echo ucfirst($eventStatus); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="event-meta">
                                    <div class="meta-item">
                                        <i class="far fa-calendar"></i>
                                        <span><?php echo date('M d, Y', strtotime($date)); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="far fa-clock"></i>
                                        <span><?php echo date('g:i A', strtotime($time)); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($location); ?></span>
                                    </div>
                                </div>
                                
                                <div class="event-actions">
                                    <a href="manage.php?id=<?php echo $event['id']; ?>" class="btn btn-manage btn-sm">
                                        <i class="fas fa-users-cog"></i> Manage
                                    </a>
                                    <a href="view_event.php?id=<?php echo $event['id']; ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="createEvent.php?edit=<?php echo $event['id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Available Events Section -->
            <div class="section-card fade-in">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-calendar-check"></i>
                        Available Events (To Join)
                    </h2>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> Browse All Events
                    </a>
                </div>

                <?php if (empty($availableEvents)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3>No available events to join</h3>
                        <p>All events are either created by you or have passed their date.</p>
                        <a href="index.php" class="btn btn-primary" style="margin-top: var(--space);">
                            <i class="fas fa-calendar-alt"></i> Browse Events
                        </a>
                    </div>
                <?php else: ?>
                    <div class="events-grid">
                        <?php foreach ($availableEvents as $event): 
                            $title = $event['event_name'] ?? 'Untitled Event';
                            $date = $event['event_date'] ?? '';
                            $time = $event['event_time'] ?? '';
                            $location = $event['venue'] ?? '';
                            $organizer_name = $event['organizer_name'] ?? 'Unknown';
                            $max_participants = $event['max_participants'] ?? 0;
                            $is_registered = $event['is_registered'] ?? 0;
                            $spots_left = $max_participants ? ($max_participants - ($event['registered_count'] ?? 0)) : null;
                        ?>
                            <div class="event-card">
                                <div class="event-header">
                                    <div style="display: flex; align-items: center; gap: var(--space-sm); flex-wrap: wrap;">
                                        <h3 class="event-title"><?php echo htmlspecialchars($title); ?></h3>
                                        <?php if ($is_registered): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check-circle"></i> Registered
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="event-meta">
                                    <div class="meta-item">
                                        <i class="far fa-calendar"></i>
                                        <span><?php echo date('F j, Y', strtotime($date)); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="far fa-clock"></i>
                                        <span><?php echo date('g:i A', strtotime($time)); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($location); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-user"></i>
                                        <span>By: <?php echo htmlspecialchars($organizer_name); ?></span>
                                    </div>
                                </div>
                                
                                <?php if ($max_participants && $spots_left !== null && $spots_left <= 5): ?>
                                    <div style="margin-top: var(--space-sm);">
                                        <span class="badge badge-warning">
                                            <i class="fas fa-users"></i> Only <?php echo $spots_left; ?> spots left!
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="event-actions">
                                    <a href="view_event.php?id=<?php echo $event['id']; ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    
                                    <?php if (!$is_registered): ?>
                                        <?php if ($spots_left !== null && $spots_left > 0): ?>
                                            <a href="event_register.php?event_id=<?php echo $event['id']; ?>" class="btn btn-success btn-sm">
                                                <i class="fas fa-user-plus"></i> Register
                                            </a>
                                        <?php elseif ($spots_left !== null && $spots_left <= 0): ?>
                                            <span class="badge badge-danger">
                                                <i class="fas fa-users-slash"></i> Full
                                            </span>
                                        <?php else: ?>
                                            <a href="event_register.php?event_id=<?php echo $event['id']; ?>" class="btn btn-success btn-sm">
                                                <i class="fas fa-user-plus"></i> Register
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- My Registrations Section -->
            <?php if (!empty($myRegistrations)): ?>
            <div class="section-card fade-in">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-ticket-alt"></i>
                        My Registrations
                    </h2>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> Browse Events
                    </a>
                </div>

                <div class="events-grid">
                    <?php foreach ($myRegistrations as $reg): 
                        $event_name = $reg['event_name'] ?? 'Untitled Event';
                        $event_date = $reg['event_date'] ?? '';
                        $event_time = $reg['event_time'] ?? '';
                        $venue = $reg['venue'] ?? '';
                        $status = $reg['status'] ?? 'pending';
                        $registration_date = $reg['created_at'] ?? $reg['registration_date'] ?? '';
                        
                        $is_upcoming = isEventUpcoming($event_date);
                        $is_today = $event_date ? (date('Y-m-d') == date('Y-m-d', strtotime($event_date))) : false;
                        
                        $status_class = '';
                        $status_text = ucfirst(str_replace('_', ' ', $status));
                        
                        switch($status) {
                            case 'approved':
                                $status_class = $is_upcoming ? 'badge-success' : 'badge-completed';
                                $status_text = $is_upcoming ? 'Approved' : 'Attended';
                                break;
                            case 'pending':
                                $status_class = 'badge-warning';
                                $status_text = 'Pending Approval';
                                break;
                            case 'rejected':
                                $status_class = 'badge-danger';
                                $status_text = 'Rejected';
                                break;
                            case 'cancelled':
                                $status_class = 'badge-danger';
                                $status_text = 'Cancelled';
                                break;
                            default:
                                $status_class = 'badge-completed';
                        }
                    ?>
                        <div class="event-card">
                            <div class="event-header">
                                <div style="display: flex; align-items: center; gap: var(--space-sm); flex-wrap: wrap;">
                                    <h3 class="event-title"><?php echo htmlspecialchars($event_name); ?></h3>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <i class="fas fa-circle"></i> <?php echo $status_text; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="event-meta">
                                <?php if ($event_date && $event_time): ?>
                                <div class="meta-item">
                                    <i class="far fa-calendar"></i>
                                    <span><?php echo date('M d, Y', strtotime($event_date)); ?> at <?php echo date('g:i A', strtotime($event_time)); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($venue): ?>
                                <div class="meta-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($venue); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($registration_date): ?>
                                <div class="meta-item">
                                    <i class="far fa-calendar-check"></i>
                                    <span>Registered: <?php echo date('M d, Y', strtotime($registration_date)); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="event-actions">
                                <a href="view_event.php?id=<?php echo $reg['event_id']; ?>" class="btn btn-info btn-sm">
                                    <i class="fas fa-eye"></i> View Event
                                </a>
                                
                                <?php if ($status === 'approved' && $is_upcoming): ?>
                                    <a href="cancel_registration.php?id=<?php echo $reg['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this registration?')">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-brand">
                    <div class="footer-logo">
                        <?php if ($logoExists): ?>
                            <img src="<?php echo $logoPath; ?>" alt="CampusBloom Logo" class="logo-image">
                        <?php else: ?>
                            <div class="logo-fallback">CB</div>
                        <?php endif; ?>
                        <span class="logo-text">CampusBloom</span>
                    </div>
                    
                    <p class="footer-description">
                        Empowering students to make the most of their campus life through seamless event management and community engagement.
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
                    <h4>Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="index.php" class="footer-link"><i class="fas fa-chevron-right"></i> Home</a></li>
                        <li><a href="dashboard.php" class="footer-link"><i class="fas fa-chevron-right"></i> Dashboard</a></li>
                        <?php if (canCreateEvents()): ?>
                            <li><a href="createEvent.php" class="footer-link"><i class="fas fa-chevron-right"></i> Create Event</a></li>
                        <?php endif; ?>
                        <li><a href="events.php" class="footer-link"><i class="fas fa-chevron-right"></i> All Events</a></li>
                        <li><a href="logout.php" class="footer-link"><i class="fas fa-chevron-right"></i> Logout</a></li>
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
                <p>&copy; <?php echo date('Y'); ?> CampusBloom Event Management System. All rights reserved.</p>
                <p>Universiti Malaysia Sabah | Premium Campus Platform</p>
            </div>
        </div>
    </footer>

    <script>
        // Initialize components
        document.addEventListener('DOMContentLoaded', function() {
            // Navbar scroll effect
            const navbar = document.getElementById('navbar');
            window.addEventListener('scroll', () => {
                if (window.scrollY > 50) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            });

            // Auto-hide messages after 5 seconds
            setTimeout(() => {
                document.querySelectorAll('.message').forEach(msg => {
                    msg.style.opacity = '0';
                    msg.style.transition = 'opacity 0.5s';
                    setTimeout(() => msg.remove(), 500);
                });
            }, 5000);

            // Logo handling
            const logoImages = document.querySelectorAll('.logo-image');
            logoImages.forEach(img => {
                if (img.complete && img.naturalHeight === 0) {
                    img.style.display = 'none';
                    const fallback = img.nextElementSibling;
                    if (fallback && fallback.classList.contains('logo-fallback')) {
                        fallback.style.display = 'flex';
                    }
                }
                
                img.addEventListener('error', function() {
                    this.style.display = 'none';
                    const fallback = this.nextElementSibling;
                    if (fallback && fallback.classList.contains('logo-fallback')) {
                        fallback.style.display = 'flex';
                    }
                });
            });

            // Add animation classes
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));
        });

        // Add CSS for animations
        const style = document.createElement('style');
        style.textContent = `
            .fade-in {
                opacity: 0;
                transform: translateY(20px);
                transition: opacity 0.6s ease, transform 0.6s ease;
            }
            
            .fade-in.visible {
                opacity: 1;
                transform: translateY(0);
            }
            
            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
<?php
// Close connection
if (isset($conn)) {
    $conn->close(); 
}