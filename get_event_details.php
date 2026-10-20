<?php
// event_details.php - View event details
session_start();
require_once 'config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$event_id = intval($_GET['id']);
$user = isLoggedIn() ? getCurrentUser() : null;

// Get event details with registration count
$sql = "SELECT e.*, 
        u.name as organizer_name,
        (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'confirmed') as registered_count
        FROM events e 
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = ? AND e.status = 'active'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php?error=Event not found');
    exit();
}

$event = $result->fetch_assoc();

// Check if user is already registered
$is_registered = false;
if ($user) {
    $check_sql = "SELECT id FROM registrations WHERE event_id = ? AND user_email = ? AND status = 'confirmed'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $event_id, $user['email']);
    $check_stmt->execute();
    $is_registered = $check_stmt->get_result()->num_rows > 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['event_name']); ?> - CampusBloom</title>
    <style>
        /* Add your styles from dashboard.php */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; color: #333; }
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .event-card { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .btn { padding: 10px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 6px; display: inline-block; }
        .btn-success { background: #28a745; }
        .btn-secondary { background: #6c757d; }
    </style>
</head>
<body>
    <div class="container">
        <div class="event-card">
            <h1><?php echo htmlspecialchars($event['event_name']); ?></h1>
            <p><strong>Organizer:</strong> <?php echo htmlspecialchars($event['organizer_name']); ?></p>
            <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($event['event_date'])); ?></p>
            <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($event['event_time'])); ?></p>
            <p><strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?></p>
            <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
            
            <!-- Registration Status -->
            <div style="margin-top: 30px;">
                <?php if ($user): ?>
                    <?php if ($is_registered): ?>
                        <button class="btn btn-success" disabled>
                            <i class="fas fa-check"></i> Already Registered
                        </button>
                    <?php elseif (date('Y-m-d') <= $event['reg_close_date']): ?>
                        <a href="event_register.php?event_id=<?php echo $event['id']; ?>" class="btn">
                            <i class="fas fa-user-plus"></i> Register Now
                        </a>
                    <?php else: ?>
                        <button class="btn btn-secondary" disabled>
                            <i class="fas fa-calendar-times"></i> Registration Closed
                        </button>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="login.php" class="btn">
                        <i class="fas fa-sign-in-alt"></i> Login to Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>