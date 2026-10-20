<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['isLoggedIn']) || $_SESSION['isLoggedIn'] !== true) {
    header('Location: login.php');
    exit();
}

// Check if event ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$event_id = (int)$_GET['id'];
$current_user = $_SESSION['currentUser'] ?? [];
$user_id = $current_user['id'] ?? null;
$user_role = $current_user['role'] ?? 'public';

// Check if user is the creator or admin - FIXED: using 'id' instead of 'event_id'
$check_stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$check_stmt->bind_param("i", $event_id);
$check_stmt->execute();
$event_result = $check_stmt->get_result();

if ($event_result->num_rows === 0) {
    $check_stmt->close();
    header("Location: dashboard.php");
    exit();
}

$event = $event_result->fetch_assoc();
$check_stmt->close();

// Check permissions: User must be creator OR admin
$is_creator = ($event['created_by'] == $user_id);
$is_admin = ($user_role == 'admin');

if (!$is_creator && !$is_admin) {
    header("Location: view_event.php?id=$event_id");
    exit();
}

// Get event details with counts - FIXED: using 'id' instead of 'event_id'
$details_stmt = $conn->prepare("
    SELECT e.*, 
           (SELECT COUNT(*) FROM registrations WHERE event_id = e.id) as total_registrations,
           (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status = 'approved') as approved_count,
           (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status = 'pending') as pending_count,
           (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status = 'rejected') as rejected_count
    FROM events e
    WHERE e.id = ?
");
$details_stmt->bind_param("i", $event_id);
$details_stmt->execute();
$event_details = $details_stmt->get_result()->fetch_assoc();
$details_stmt->close();

// Handle actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $participant_id = isset($_POST['participant_id']) ? (int)$_POST['participant_id'] : 0;
        
        switch ($action) {
            case 'approve':
                $update_stmt = $conn->prepare("UPDATE registrations SET status = 'approved' WHERE id = ? AND event_id = ?");
                $update_stmt->bind_param("ii", $participant_id, $event_id);
                if ($update_stmt->execute()) {
                    $success = "Registration approved successfully.";
                } else {
                    $error = "Failed to approve registration.";
                }
                $update_stmt->close();
                break;
                
            case 'reject':
                $update_stmt = $conn->prepare("UPDATE registrations SET status = 'rejected' WHERE id = ? AND event_id = ?");
                $update_stmt->bind_param("ii", $participant_id, $event_id);
                if ($update_stmt->execute()) {
                    $success = "Registration rejected successfully.";
                } else {
                    $error = "Failed to reject registration.";
                }
                $update_stmt->close();
                break;
                
            case 'mark_attended':
                // Check if 'attended' column exists
                $checkColumn = columnExists('registrations', 'attended');
                if ($checkColumn) {
                    $update_stmt = $conn->prepare("UPDATE registrations SET attended = 1 WHERE id = ? AND event_id = ?");
                    $update_stmt->bind_param("ii", $participant_id, $event_id);
                    if ($update_stmt->execute()) {
                        $success = "Attendance marked successfully.";
                    } else {
                        $error = "Failed to mark attendance.";
                    }
                    $update_stmt->close();
                } else {
                    $error = "Attendance feature not available.";
                }
                break;
                
            case 'remove_attendance':
                // Check if 'attended' column exists
                $checkColumn = columnExists('registrations', 'attended');
                if ($checkColumn) {
                    $update_stmt = $conn->prepare("UPDATE registrations SET attended = 0 WHERE id = ? AND event_id = ?");
                    $update_stmt->bind_param("ii", $participant_id, $event_id);
                    if ($update_stmt->execute()) {
                        $success = "Attendance removed successfully.";
                    } else {
                        $error = "Failed to remove attendance.";
                    }
                    $update_stmt->close();
                } else {
                    $error = "Attendance feature not available.";
                }
                break;
                
            case 'bulk_approve':
                if (isset($_POST['selected_participants']) && is_array($_POST['selected_participants'])) {
                    $placeholders = implode(',', array_fill(0, count($_POST['selected_participants']), '?'));
                    $types = str_repeat('i', count($_POST['selected_participants']));
                    $bulk_stmt = $conn->prepare("UPDATE registrations SET status = 'approved' WHERE id IN ($placeholders) AND event_id = ?");
                    $params = array_merge($_POST['selected_participants'], [$event_id]);
                    $bulk_stmt->bind_param($types . 'i', ...$params);
                    if ($bulk_stmt->execute()) {
                        $success = count($_POST['selected_participants']) . " registrations approved successfully.";
                    } else {
                        $error = "Failed to approve registrations.";
                    }
                    $bulk_stmt->close();
                }
                break;
                
            case 'export_csv':
                exportParticipantsCSV($conn, $event_id);
                exit();
                break;
        }
        
        // Refresh event details after update - FIXED: using 'id' instead of 'event_id'
        $details_stmt = $conn->prepare("
            SELECT e.*, 
                   (SELECT COUNT(*) FROM registrations WHERE event_id = e.id) as total_registrations,
                   (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status = 'approved') as approved_count,
                   (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status = 'pending') as pending_count,
                   (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status = 'rejected') as rejected_count
            FROM events e
            WHERE e.id = ?
        ");
        $details_stmt->bind_param("i", $event_id);
        $details_stmt->execute();
        $event_details = $details_stmt->get_result()->fetch_assoc();
        $details_stmt->close();
    }
}

// Get participants based on filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$participants_sql = "
    SELECT r.*, u.name, u.email, u.phone, u.organization, u.role as user_role
    FROM registrations r
    JOIN users u ON r.user_id = u.id
    WHERE r.event_id = ?
";

$params = [$event_id];
$types = 'i';

if ($filter !== 'all') {
    // Check if status column exists
    if (columnExists('registrations', 'status')) {
        $participants_sql .= " AND r.status = ?";
        $params[] = $filter;
        $types .= 's';
    }
}

if (!empty($search)) {
    $participants_sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.organization LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$participants_sql .= " ORDER BY r.created_at DESC";

$participants_stmt = $conn->prepare($participants_sql);
if (!empty($params)) {
    $participants_stmt->bind_param($types, ...$params);
}
$participants_stmt->execute();
$participants = $participants_stmt->get_result();
$total_participants = $participants->num_rows;
$participants_stmt->close();

// Function to export CSV
function exportParticipantsCSV($conn, $event_id) {
    // Check which columns exist
    $has_attended = columnExists('registrations', 'attended');
    $has_registration_type = columnExists('registrations', 'registration_type');
    $has_payment_status = columnExists('registrations', 'payment_status');
    $has_amount = columnExists('registrations', 'amount');
    
    $select_fields = "u.name, u.email, u.phone, u.organization, u.role, 
               r.status, r.created_at as registration_date";
    
    if ($has_attended) {
        $select_fields .= ", r.attended, CASE WHEN r.attended = 1 THEN 'Yes' ELSE 'No' END as attendance_status";
    }
    
    if ($has_registration_type) {
        $select_fields .= ", r.registration_type";
    }
    
    if ($has_payment_status) {
        $select_fields .= ", r.payment_status";
    }
    
    if ($has_amount) {
        $select_fields .= ", r.amount";
    }
    
    $export_stmt = $conn->prepare("
        SELECT $select_fields
        FROM registrations r
        JOIN users u ON r.user_id = u.id
        WHERE r.event_id = ?
        ORDER BY r.created_at DESC
    ");
    $export_stmt->bind_param("i", $event_id);
    $export_stmt->execute();
    $export_result = $export_stmt->get_result();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=participants_' . $event_id . '_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Dynamic headers based on available columns
    $headers = ['Name', 'Email', 'Phone', 'Organization', 'Role', 'Status', 'Registration Date'];
    if ($has_attended) $headers[] = 'Attendance';
    if ($has_registration_type) $headers[] = 'Registration Type';
    if ($has_payment_status) $headers[] = 'Payment Status';
    if ($has_amount) $headers[] = 'Amount';
    
    fputcsv($output, $headers);
    
    while ($row = $export_result->fetch_assoc()) {
        $csv_row = [
            $row['name'],
            $row['email'],
            $row['phone'],
            $row['organization'],
            $row['role'],
            $row['status'],
            $row['registration_date']
        ];
        
        if ($has_attended) {
            $csv_row[] = $row['attendance_status'] ?? 'No';
        }
        
        if ($has_registration_type) {
            $csv_row[] = $row['registration_type'] ?? 'N/A';
        }
        
        if ($has_payment_status) {
            $csv_row[] = $row['payment_status'] ?? 'N/A';
        }
        
        if ($has_amount) {
            $csv_row[] = $row['amount'] ?? '0';
        }
        
        fputcsv($output, $csv_row);
    }
    fclose($output);
    $export_stmt->close();
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Participants - <?php echo htmlspecialchars($event['event_name']); ?> - CampusBloom</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .navbar {
            background: white;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            padding: 15px 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }

        .logo-text {
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-links {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #667eea;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 20px;
        }

        .user-info i {
            color: #667eea;
        }

        .manage-container {
            max-width: 1200px;
            margin: 100px auto 40px;
            padding: 0 20px;
        }

        .manage-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .header-title {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-title h1 {
            color: #333;
            font-size: 28px;
            margin: 0;
        }

        .event-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .stat-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: #667eea;
            line-height: 1;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            font-weight: 600;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .manage-actions {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
        }

        .filter-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-right: auto;
        }

        .filter-tab {
            padding: 8px 20px;
            background: #f8f9fa;
            border: none;
            border-radius: 50px;
            color: #666;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-tab:hover,
        .filter-tab.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .search-box {
            position: relative;
            min-width: 250px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 2px solid #e1e5eb;
            border-radius: 10px;
            font-size: 14px;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
        }

        .bulk-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #333;
            border: 2px solid #e1e5eb;
        }

        .btn-secondary:hover {
            background: #e9ecef;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .participants-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .table-header {
            display: grid;
            grid-template-columns: 50px 2fr 1fr 1fr 1fr 100px 100px 120px;
            gap: 15px;
            padding: 15px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-weight: 600;
        }

        .table-row {
            display: grid;
            grid-template-columns: 50px 2fr 1fr 1fr 1fr 100px 100px 120px;
            gap: 15px;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            align-items: center;
        }

        .table-row:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-pending {
            background: #fff3e0;
            color: #f57c00;
        }

        .status-approved {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-rejected {
            background: #ffebee;
            color: #c62828;
        }

        .attendance-badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }

        .attended {
            background: #e3f2fd;
            color: #1565c0;
        }

        .not-attended {
            background: #f5f5f5;
            color: #616161;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            min-width: 40px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 64px;
            color: #c3cfe2;
            margin-bottom: 20px;
        }

        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 14px;
            margin-top: 40px;
        }

        @media (max-width: 992px) {
            .table-header,
            .table-row {
                grid-template-columns: 50px 2fr 1fr 1fr 100px 100px;
            }
            
            .table-header div:nth-child(4),
            .table-row div:nth-child(4),
            .table-header div:nth-child(7),
            .table-row div:nth-child(7),
            .table-header div:nth-child(8),
            .table-row div:nth-child(8) {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .manage-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-tabs {
                justify-content: center;
            }
            
            .search-box {
                width: 100%;
            }
            
            .table-header {
                display: none;
            }
            
            .table-row {
                display: flex;
                flex-direction: column;
                gap: 10px;
                padding: 20px;
                border: 1px solid #eee;
                margin-bottom: 10px;
                border-radius: 10px;
            }
            
            .action-buttons {
                margin-top: 10px;
            }
            
            .header-title {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="logo">
                <div class="logo-icon">CB</div>
                <div class="logo-text">CampusBloom</div>
            </a>
            <div class="nav-links">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="events.php"><i class="fas fa-calendar"></i> Events</a>
                <div class="user-info">
                    <i class="fas fa-user"></i>
                    <span><?php echo htmlspecialchars($current_user['name'] ?? 'User'); ?> (<?php echo htmlspecialchars($user_role); ?>)</span>
                </div>
                <a href="logout.php" class="btn btn-secondary btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="manage-container">
        <!-- Manage Header -->
        <div class="manage-header">
            <div class="header-title">
                <h1>Manage Participants: <?php echo htmlspecialchars($event['event_name']); ?></h1>
                <div>
                    <a href="view_event.php?id=<?php echo $event_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-eye"></i> View Event
                    </a>
                    <a href="edit_event.php?id=<?php echo $event_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-edit"></i> Edit Event
                    </a>
                </div>
            </div>
            
            <p style="color: #666; margin-bottom: 20px;">
                <i class="fas fa-calendar-alt"></i> <?php echo formatDate($event['event_date']); ?> •  
                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['venue']); ?>
            </p>
            
            <div class="event-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $event_details['total_registrations']; ?></div>
                    <div class="stat-label">Total Registrations</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $event_details['approved_count']; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $event_details['pending_count']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $event_details['rejected_count']; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Manage Actions -->
        <div class="manage-actions">
            <div class="filter-tabs">
                <button class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>" onclick="window.location.href='?id=<?php echo $event_id; ?>&filter=all'">
                    All (<?php echo $event_details['total_registrations']; ?>)
                </button>
                <button class="filter-tab <?php echo $filter == 'approved' ? 'active' : ''; ?>" onclick="window.location.href='?id=<?php echo $event_id; ?>&filter=approved'">
                    Approved (<?php echo $event_details['approved_count']; ?>)
                </button>
                <button class="filter-tab <?php echo $filter == 'pending' ? 'active' : ''; ?>" onclick="window.location.href='?id=<?php echo $event_id; ?>&filter=pending'">
                    Pending (<?php echo $event_details['pending_count']; ?>)
                </button>
                <button class="filter-tab <?php echo $filter == 'rejected' ? 'active' : ''; ?>" onclick="window.location.href='?id=<?php echo $event_id; ?>&filter=rejected'">
                    Rejected (<?php echo $event_details['rejected_count']; ?>)
                </button>
            </div>
            
            <form method="GET" action="" class="search-box">
                <input type="hidden" name="id" value="<?php echo $event_id; ?>">
                <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search participants..." value="<?php echo htmlspecialchars($search); ?>">
            </form>
            
            <form method="POST" action="" id="bulkForm">
                <input type="hidden" name="id" value="<?php echo $event_id; ?>">
                <div class="bulk-actions">
                    <button type="submit" name="action" value="export_csv" class="btn btn-secondary">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                    <?php if ($filter == 'pending'): ?>
                        <button type="submit" name="action" value="bulk_approve" class="btn btn-primary">
                            <i class="fas fa-check"></i> Approve Selected
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Participants Table -->
        <div class="participants-table">
            <?php if ($total_participants > 0): ?>
                <div class="table-header">
                    <div><input type="checkbox" id="selectAll"></div>
                    <div>Participant</div>
                    <div>Contact</div>
                    <div>Organization</div>
                    <div>Role</div>
                    <div>Status</div>
                    <div>Attendance</div>
                    <div>Actions</div>
                </div>
                
                <form method="POST" action="" id="participantsForm">
                    <input type="hidden" name="id" value="<?php echo $event_id; ?>">
                    
                    <?php while($participant = $participants->fetch_assoc()): 
                        // Check if attended column exists
                        $has_attended = columnExists('registrations', 'attended');
                        $has_status = columnExists('registrations', 'status');
                        $participant_status = $has_status ? $participant['status'] : 'pending';
                        $participant_attended = $has_attended ? $participant['attended'] : 0;
                    ?>
                        <div class="table-row">
                            <div>
                                <input type="checkbox" name="selected_participants[]" value="<?php echo $participant['id']; ?>" class="participant-checkbox">
                            </div>
                            <div>
                                <strong><?php echo htmlspecialchars($participant['name']); ?></strong>
                                <div style="font-size: 12px; color: #666; margin-top: 3px;">
                                    Reg: <?php echo formatDate($participant['created_at']); ?>
                                </div>
                            </div>
                            <div>
                                <div><?php echo htmlspecialchars($participant['email']); ?></div>
                                <div style="font-size: 12px; color: #666; margin-top: 3px;">
                                    <?php echo !empty($participant['phone']) ? htmlspecialchars($participant['phone']) : 'No phone'; ?>
                                </div>
                            </div>
                            <div>
                                <?php echo !empty($participant['organization']) ? htmlspecialchars($participant['organization']) : '—'; ?>
                            </div>
                            <div>
                                <span style="padding: 4px 10px; background: #f0f0f0; border-radius: 3px; font-size: 12px; text-transform: capitalize;">
                                    <?php echo htmlspecialchars($participant['user_role']); ?>
                                </span>
                            </div>
                            <div>
                                <?php if ($has_status): ?>
                                    <span class="status-badge status-<?php echo $participant_status; ?>">
                                        <?php echo $participant_status; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-pending">
                                        Pending
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php if ($has_status && $participant_status == 'approved' && $has_attended): ?>
                                    <span class="attendance-badge <?php echo $participant_attended ? 'attended' : 'not-attended'; ?>">
                                        <?php echo $participant_attended ? 'Attended' : 'Not Attended'; ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 12px;">N/A</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="action-buttons">
                                    <?php if ($has_status && $participant_status == 'pending'): ?>
                                        <button type="submit" name="action" value="approve" class="btn btn-primary btn-sm">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-secondary btn-sm" style="background: #ff6b6b; color: white;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php elseif ($has_status && $participant_status == 'approved'): ?>
                                        <?php if ($has_attended && $participant_attended): ?>
                                            <button type="submit" name="action" value="remove_attendance" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-user-times"></i>
                                            </button>
                                        <?php elseif ($has_attended): ?>
                                            <button type="submit" name="action" value="mark_attended" class="btn btn-primary btn-sm">
                                                <i class="fas fa-user-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php elseif ($has_status && $participant_status == 'rejected'): ?>
                                        <button type="submit" name="action" value="approve" class="btn btn-primary btn-sm">
                                            <i class="fas fa-redo"></i>
                                        </button>
                                    <?php elseif (!$has_status): ?>
                                        <button type="submit" name="action" value="approve" class="btn btn-primary btn-sm">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    <?php endif; ?>
                                    <input type="hidden" name="participant_id" value="<?php echo $participant['id']; ?>">
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </form>
                
                <div style="padding: 15px 20px; background: #f8f9fa; text-align: center; color: #666; font-size: 14px;">
                    Showing <?php echo $total_participants; ?> participant<?php echo $total_participants != 1 ? 's' : ''; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h3>No Participants Found</h3>
                    <p><?php echo !empty($search) ? 'No participants match your search criteria.' : 'No participants have registered for this event yet.'; ?></p>
                    <?php if (!empty($search)): ?>
                        <a href="?id=<?php echo $event_id; ?>&filter=<?php echo $filter; ?>" class="btn btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-times"></i> Clear Search
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Navigation -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px;">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            
            <?php if ($total_participants > 0): ?>
                <div style="color: #666; font-size: 14px;">
                    <i class="fas fa-info-circle"></i> Use checkboxes to select multiple participants for bulk actions.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> CampusBloom. All rights reserved.</p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Select all checkboxes
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.participant-checkbox');
            
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = selectAll.checked;
                    });
                });
            }
            
            // Update select all when individual checkboxes change
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (!this.checked) {
                        selectAll.checked = false;
                    } else {
                        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                        selectAll.checked = allChecked;
                    }
                });
            });
            
            // Form submission for actions
            const participantsForm = document.getElementById('participantsForm');
            const bulkForm = document.getElementById('bulkForm');
            
            if (participantsForm) {
                participantsForm.addEventListener('submit', function(e) {
                    const action = e.submitter ? e.submitter.value : '';
                    if (action === 'approve' || action === 'reject') {
                        if (!confirm(`Are you sure you want to ${action} this registration?`)) {
                            e.preventDefault();
                        }
                    }
                });
            }
            
            if (bulkForm) {
                bulkForm.addEventListener('submit', function(e) {
                    const action = e.submitter ? e.submitter.value : '';
                    if (action === 'bulk_approve') {
                        const selected = document.querySelectorAll('.participant-checkbox:checked');
                        if (selected.length === 0) {
                            e.preventDefault();
                            alert('Please select at least one participant to approve.');
                            return false;
                        }
                        if (!confirm(`Are you sure you want to approve ${selected.length} selected registration(s)?`)) {
                            e.preventDefault();
                        }
                    }
                });
            }
            
            // Auto-submit search on enter
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        this.form.submit();
                    }
                });
            }
        });
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>