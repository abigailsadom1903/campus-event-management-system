<?php
// createEvent.php - Event Creation with Organizer ID - FIXED VERSION
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user = getCurrentUser();

if (!canCreateEvents()) {
    header('Location: dashboard.php');
    exit();
}

$isEditing = false;
$event = null;
$errors = [];
$success = '';

// Check if editing existing event
if (isset($_GET['edit'])) {
    $eventId = intval($_GET['edit']);
    
    // Fetch event AND check if current user is the organizer (created_by)
    $sql = "SELECT * FROM events WHERE id = ? AND created_by = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $eventId, $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $event = $result->fetch_assoc();
        $isEditing = true;
    } else {
        // Event not found or user is not the organizer
        $_SESSION['error'] = 'Event not found or you do not have permission to edit it';
        header('Location: dashboard.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $max_participants = !empty($_POST['max_participants']) ? intval($_POST['max_participants']) : null;

    // Validation
    if (empty($title)) $errors[] = 'Event title is required';
    if (empty($date)) $errors[] = 'Event date is required';
    if (empty($time)) $errors[] = 'Event time is required';
    if (empty($location)) $errors[] = 'Location is required';
    
    // Validate date is not in the past
    if (!empty($date) && !empty($time)) {
        $eventDateTime = strtotime($date . ' ' . $time);
        if ($eventDateTime < time()) {
            $errors[] = 'Event date and time must be in the future';
        }
    }

    // If no errors, save to database
    if (empty($errors)) {
        try {
            if ($isEditing && $event) {
                // Update existing event - CHECK created_by AGAIN
                $sql = "UPDATE events SET 
                        event_name = ?, description = ?, 
                        event_date = ?, event_time = ?, venue = ?,
                        max_participants = ?, last_updated = NOW()
                        WHERE id = ? AND created_by = ?";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssiii", 
                    $title, $description,
                    $date, $time, $location,
                    $max_participants, $event['id'], $user['id']
                );
                
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $success = 'Event updated successfully!';
                        // Update event array for display
                        $event['event_name'] = $title;
                        $event['description'] = $description;
                        $event['event_date'] = $date;
                        $event['event_time'] = $time;
                        $event['venue'] = $location;
                        $event['max_participants'] = $max_participants;
                    } else {
                        $errors[] = 'No changes made or permission denied';
                    }
                } else {
                    $errors[] = 'Failed to update event: ' . $stmt->error;
                }
            } else {
                // Create new event - INSERT with created_by
                $sql = "INSERT INTO events (
                    event_name, description, event_date, event_time, venue,
                    max_participants, created_by, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssii",
                    $title, $description,
                    $date, $time, $location,
                    $max_participants, $user['id']
                );
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Event created successfully!';
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $errors[] = 'Failed to create event: ' . $stmt->error;
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
            error_log('Event creation error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEditing ? 'Edit Event' : 'Create Event'; ?> - CampusBloom</title>
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
            --transition-fast: all 0.2s ease;
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

        .header-nav {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-link {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            padding: 0.6rem 1.2rem;
            border-radius: 50px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
        }

        .header-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            transition: var(--transition);
            transform: translateX(-50%);
        }

        .header-link:hover {
            color: var(--primary);
            background: rgba(102, 126, 234, 0.1);
        }

        .header-link:hover::after {
            width: 70%;
        }

        .header-link.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .header-link.active::after {
            display: none;
        }

        /* Main Content */
        .main-content {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Page Header */
        .page-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .page-header h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.75rem;
        }

        .page-header p {
            color: var(--gray);
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }

        /* User Info */
        .user-info {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .user-badge {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow-sm);
        }

        .user-badge.editing {
            background: linear-gradient(135deg, var(--warning) 0%, #ffcc00 100%);
        }

        .user-badge.organizer {
            background: linear-gradient(135deg, var(--success) 0%, #4ade80 100%);
        }

        /* Form Card */
        .form-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        /* Messages */
        .message {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius-xs);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
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

        /* Form Groups */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.75rem;
            color: var(--dark);
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label i {
            color: var(--primary);
            width: 20px;
        }

        .form-label.required::after {
            content: '*';
            color: var(--danger);
            margin-left: 0.25rem;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid var(--gray-light);
            border-radius: var(--border-radius-xs);
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            transition: var(--transition);
            background: white;
            color: var(--dark);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
            line-height: 1.6;
        }

        .form-control::placeholder {
            color: var(--gray);
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1rem;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius-xs);
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--gray-light);
            color: var(--dark);
            box-shadow: var(--shadow-sm);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger) 0%, #ff6b6b 100%);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-light);
        }

        /* Delete Button */
        .delete-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-light);
            text-align: center;
        }

        .delete-note {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        /* Footer */
        .footer {
            background: linear-gradient(135deg, var(--dark) 0%, #1a1a2e 100%);
            color: white;
            padding: 3rem 2rem 2rem;
            margin-top: 4rem;
        }

        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
        }

        .footer-logo {
            width: 150px;
            height: auto;
            margin-bottom: 1.5rem;
            filter: brightness(0) invert(1);
            opacity: 0.9;
        }

        .footer-section h4 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: white;
        }

        .footer-section p {
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 0.75rem;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .footer-links a:hover {
            color: white;
            transform: translateX(5px);
        }

        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: var(--transition);
        }

        .social-links a:hover {
            background: var(--primary);
            transform: translateY(-3px);
        }

        .footer-bottom {
            max-width: 1400px;
            margin: 3rem auto 0;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                padding: 1rem;
            }
            
            .header-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .header-nav {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .main-content {
                padding: 1.5rem;
            }
            
            .form-card {
                padding: 1.5rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 1rem;
            }
            
            .header-title {
                font-size: 1.4rem;
            }
            
            .header-link {
                padding: 0.4rem 0.75rem;
                font-size: 0.9rem;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .btn {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
        }

        /* Helper Text */
        .form-helper {
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.5s ease-out;
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
            <nav class="header-nav">
                <a href="index.php" class="header-link">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="dashboard.php" class="header-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="createEvent.php" class="header-link active">
                    <i class="fas fa-plus-circle"></i> Create Event
                </a>
                <a href="logout.php" class="header-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header fade-in-up">
            <h1><?php echo $isEditing ? 'Edit Event' : 'Create New Event'; ?></h1>
            <p><?php echo $isEditing ? 'Update your event details' : 'Share your amazing event with the campus community!'; ?></p>
            
            <div class="user-info">
                <div class="user-badge">
                    <i class="fas fa-user-circle"></i>
                    <?php echo htmlspecialchars($user['name']); ?>
                </div>
                <div class="user-badge">
                    <i class="fas fa-user-tag"></i>
                    <?php echo ucfirst($user['role']); ?> Account
                </div>
                <div class="user-badge organizer">
                    <i class="fas fa-id-badge"></i>
                    User ID: <?php echo $user['id']; ?>
                </div>
                <?php if ($isEditing && isset($event['id'])): ?>
                <div class="user-badge editing">
                    <i class="fas fa-edit"></i>
                    Event ID: <?php echo $event['id']; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Form Card -->
        <div class="form-card fade-in-up">
            <?php if ($success): ?>
                <div class="message message-success">
                    <i class="fas fa-check-circle"></i> 
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="message message-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Please fix the following errors:</strong>
                        <ul style="margin-top: 10px; margin-left: 20px;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="eventForm">
                <!-- Basic Information -->
                <div class="form-group">
                    <label class="form-label required">
                        <i class="fas fa-calendar-alt"></i>
                        Event Title
                    </label>
                    <input type="text" name="title" class="form-control" required
                           value="<?php echo htmlspecialchars($event['event_name'] ?? ($_POST['title'] ?? '')); ?>"
                           placeholder="e.g., Web Development Workshop">
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-align-left"></i>
                        Description
                    </label>
                    <textarea name="description" class="form-control" rows="4"
                              placeholder="Describe your event in detail..."><?php echo htmlspecialchars($event['description'] ?? ($_POST['description'] ?? '')); ?></textarea>
                </div>

                <!-- Date & Time -->
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="far fa-calendar"></i>
                            Event Date
                        </label>
                        <input type="date" name="date" class="form-control" required
                               value="<?php echo $event['event_date'] ?? ($_POST['date'] ?? ''); ?>"
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">
                            <i class="far fa-clock"></i>
                            Event Time
                        </label>
                        <input type="time" name="time" class="form-control" required
                               value="<?php echo $event['event_time'] ?? ($_POST['time'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-map-marker-alt"></i>
                            Venue
                        </label>
                        <input type="text" name="location" class="form-control" required
                               value="<?php echo htmlspecialchars($event['venue'] ?? ($_POST['location'] ?? '')); ?>"
                               placeholder="e.g., Computer Lab 1, University Hall">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-users"></i>
                            Maximum Participants
                        </label>
                        <input type="number" name="max_participants" class="form-control"
                               value="<?php echo $event['max_participants'] ?? ($_POST['max_participants'] ?? ''); ?>"
                               placeholder="Leave empty for unlimited" min="1">
                        <div class="form-helper">
                            <i class="fas fa-info-circle"></i>
                            Leave empty for unlimited participants
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <?php if ($isEditing): ?>
                            <i class="fas fa-save"></i> Update Event
                        <?php else: ?>
                            <i class="fas fa-plus"></i> Create Event
                        <?php endif; ?>
                    </button>
                </div>
            </form>

            <?php if ($isEditing && $event): ?>
            <!-- Delete Section (only for editing) -->
            <div class="delete-section">
                <p class="delete-note">
                    <i class="fas fa-exclamation-triangle"></i>
                    Warning: Deleting an event is permanent and cannot be undone.
                </p>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i> Delete This Event
                </button>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <p>Empowering students to make the most of their campus life through seamless event management and community engagement.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul class="footer-links">
                    <li><a href="index.php"><i class="fas fa-chevron-right"></i> Home</a></li>
                    <li><a href="dashboard.php"><i class="fas fa-chevron-right"></i> Dashboard</a></li>
                    <li><a href="createEvent.php"><i class="fas fa-chevron-right"></i> Create Event</a></li>
                    <li><a href="logout.php"><i class="fas fa-chevron-right"></i> Logout</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h4>Contact Us</h4>
                <p><i class="fas fa-map-marker-alt"></i> Universiti Malaysia Sabah</p>
                <p><i class="fas fa-envelope"></i> support@campusbloom.edu</p>
                <p><i class="fas fa-phone"></i> +60 3-1234 5678</p>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; 2025 CampusBloom - Campus Event Management System. All rights reserved.</p>
            <p>Universiti Malaysia Sabah</p>
        </div>
    </footer>

    <script>
        // Set today as minimum date for date input
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const dateInput = document.querySelector('input[name="date"]');
            if (dateInput && !dateInput.value) {
                dateInput.min = today;
            }
            
            // Set default time to next hour
            const timeInput = document.querySelector('input[name="time"]');
            if (timeInput && !timeInput.value) {
                const nextHour = new Date();
                nextHour.setHours(nextHour.getHours() + 1);
                nextHour.setMinutes(0);
                timeInput.value = nextHour.toTimeString().slice(0, 5);
            }
        });

        // Form validation
        document.getElementById('eventForm').addEventListener('submit', function(e) {
            const title = document.querySelector('input[name="title"]').value.trim();
            const date = document.querySelector('input[name="date"]').value;
            const time = document.querySelector('input[name="time"]').value;
            const location = document.querySelector('input[name="location"]').value.trim();
            
            if (!title || !date || !time || !location) {
                e.preventDefault();
                alert('Please fill in all required fields');
                return false;
            }
            
            // Check if date is in the past
            const eventDateTime = new Date(date + ' ' + time);
            if (eventDateTime < new Date()) {
                e.preventDefault();
                alert('Event date and time must be in the future');
                return false;
            }
            
            return true;
        });

        // Delete confirmation - Updated to use delete_event.php
        function confirmDelete() {
            if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
                window.location.href = 'delete_event.php?id=<?php echo $event['id']; ?>';
            }
        }
    </script>
</body>
</html>