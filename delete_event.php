<?php
// delete_event.php - Delete Event with Confirmation Page
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user = getCurrentUser();

// Get event ID from URL
$eventId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($eventId <= 0) {
    $_SESSION['error'] = 'Invalid event ID';
    header('Location: dashboard.php');
    exit();
}

// Check if event exists
$sql = "SELECT e.* FROM events e WHERE e.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $eventId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Event not found';
    header('Location: dashboard.php');
    exit();
}

$eventDetails = $result->fetch_assoc();

// Check if user has permission to delete this event
// Admin can delete any event, users can only delete their own events
$canDelete = ($user['role'] == 'admin' || $eventDetails['created_by'] == $user['id']);

if (!$canDelete) {
    $_SESSION['error'] = 'You do not have permission to delete this event';
    header('Location: dashboard.php');
    exit();
}

// Handle form submission (actual deletion)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    // Delete the event with appropriate checks
    if ($user['role'] == 'admin') {
        // Admin can delete any event
        $deleteSql = "DELETE FROM events WHERE id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param("i", $eventId);
    } else {
        // Regular users can only delete their own events
        $deleteSql = "DELETE FROM events WHERE id = ? AND created_by = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param("ii", $eventId, $user['id']);
    }
    
    if ($deleteStmt->execute()) {
        $_SESSION['success'] = "Event '{$eventDetails['event_name']}' has been deleted successfully.";
        header('Location: dashboard.php');
        exit();
    } else {
        $message = "Error deleting event: " . $conn->error;
        error_log("Delete event error: " . $conn->error);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Event - CampusBloom</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .confirmation-container {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 600px;
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .confirmation-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #ef476f, #ff6b6b);
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
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
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }

        .logo-text {
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .user-info {
            font-size: 14px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .icon-container {
            width: 100px;
            height: 100px;
            margin: 0 auto 25px;
            background: linear-gradient(135deg, #ef476f, #ff6b6b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(239, 71, 111, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .icon-container i {
            font-size: 48px;
            color: white;
        }

        h1 {
            color: #333;
            margin-bottom: 15px;
            font-weight: 700;
            font-size: 28px;
        }

        .message {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .event-details {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin: 25px 0;
            border-left: 4px solid #ef476f;
            text-align: left;
        }

        .event-details h3 {
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
        }

        .event-details h3 i {
            color: #ef476f;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #666;
            font-weight: 500;
            min-width: 150px;
        }

        .detail-value {
            color: #333;
            font-weight: 600;
            text-align: right;
            flex: 1;
        }

        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            text-align: left;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .warning-box i {
            color: #f39c12;
            font-size: 20px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .warning-text {
            color: #856404;
            font-size: 14px;
            line-height: 1.5;
        }

        .warning-text strong {
            color: #856404;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 16px 24px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-delete {
            background: linear-gradient(135deg, #ef476f, #ff6b6b);
            color: white;
            box-shadow: 0 5px 20px rgba(239, 71, 111, 0.3);
        }

        .btn-delete:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(239, 71, 111, 0.4);
            background: linear-gradient(135deg, #e63946, #ef476f);
        }

        .btn-cancel {
            background: white;
            color: #666;
            border: 2px solid #ddd;
        }

        .btn-cancel:hover {
            background: #f8f9fa;
            border-color: #bbb;
            transform: translateY(-3px);
        }

        .loading {
            display: none;
            margin-top: 20px;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #ef476f;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
        }

        .error-message i {
            font-size: 20px;
        }

        .form-container {
            margin-top: 20px;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .footer-note {
            margin-top: 25px;
            color: #888;
            font-size: 12px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        @media (max-width: 576px) {
            .confirmation-container {
                padding: 25px 20px;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .detail-row {
                flex-direction: column;
                gap: 5px;
            }
            
            .detail-label, .detail-value {
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="header">
            <a href="dashboard.php" class="logo">
                <div class="logo-icon">CB</div>
                <div class="logo-text">CampusBloom</div>
            </a>
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <?php echo htmlspecialchars($user['name']); ?> (<?php echo ucfirst($user['role']); ?>)
            </div>
        </div>
        
        <div class="icon-container">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        
        <h1>Delete Event</h1>
        <p class="message">Are you sure you want to delete this event? This action cannot be undone.</p>
        
        <?php if ($eventDetails): ?>
        <div class="event-details">
            <h3><i class="fas fa-calendar-alt"></i> Event Details</h3>
            <div class="detail-row">
                <span class="detail-label">Event Title:</span>
                <span class="detail-value"><?php echo htmlspecialchars($eventDetails['event_name']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date:</span>
                <span class="detail-value">
                    <?php echo date('F j, Y', strtotime($eventDetails['event_date'])); ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Time:</span>
                <span class="detail-value">
                    <?php echo date('g:i A', strtotime($eventDetails['event_time'])); ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Location:</span>
                <span class="detail-value"><?php echo htmlspecialchars($eventDetails['venue']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Organizer:</span>
                <span class="detail-value"><?php echo htmlspecialchars($eventDetails['organizer']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Event Type:</span>
                <span class="detail-value"><?php echo htmlspecialchars($eventDetails['event_type']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Mode:</span>
                <span class="detail-value"><?php echo htmlspecialchars($eventDetails['mode']); ?></span>
            </div>
            <?php if (!empty($eventDetails['fee']) && $eventDetails['fee'] != 'Free'): ?>
            <div class="detail-row">
                <span class="detail-label">Fee:</span>
                <span class="detail-value">$<?php echo htmlspecialchars($eventDetails['fee_amount']); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="warning-box">
            <i class="fas fa-exclamation-circle"></i>
            <div class="warning-text">
                <strong>Warning:</strong> This action will permanently delete the event and all associated data. This cannot be undone.
            </div>
        </div>
        
        <?php if (!empty($message)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="" id="deleteForm">
                <input type="hidden" name="confirm_delete" value="1">
                
                <div class="action-buttons">
                    <a href="dashboard.php" class="btn btn-cancel">
                        <i class="fas fa-times"></i> Cancel & Return
                    </a>
                    <button type="submit" class="btn btn-delete" id="deleteBtn">
                        <i class="fas fa-trash-alt"></i> Delete Event Permanently
                    </button>
                </div>
            </form>
        </div>
        
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p style="margin-top: 10px; color: #666;">Deleting event...</p>
        </div>
        
        <div class="footer-note">
            <i class="fas fa-info-circle"></i> 
            Deleting this event will remove all associated data from the system.
        </div>
    </div>

    <script>
        // Form submission with confirmation
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const confirmation = confirm('Are you absolutely sure you want to delete this event? This action cannot be undone.');
            
            if (confirmation) {
                // Show loading animation
                document.getElementById('deleteBtn').style.display = 'none';
                document.querySelector('.btn-cancel').style.display = 'none';
                document.getElementById('loading').style.display = 'block';
                
                // Submit the form after a short delay to show loading state
                setTimeout(() => {
                    this.submit();
                }, 500);
            }
        });

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.location.href = 'dashboard.php';
            }
        });
        
        // Add visual feedback for buttons
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach(button => {
            button.addEventListener('mousedown', () => {
                button.style.transform = 'translateY(1px)';
            });
            button.addEventListener('mouseup', () => {
                button.style.transform = '';
            });
            button.addEventListener('mouseleave', () => {
                button.style.transform = '';
            });
        });
        
        // Prevent accidental form submission on Enter
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.target.matches('button, input[type="submit"], input[type="button"]')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>