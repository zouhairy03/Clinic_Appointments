<?php
require_once 'config.php';

// Authentication and Authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: index.php');
    exit;
}

// Fetch doctor data
try {
    $stmt = $pdo->prepare("SELECT * FROM doctors WHERE doctor_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $doctor = $stmt->fetch();
    
    if (!$doctor) throw new Exception("Doctor not found");
} catch (Exception $e) {
    error_log("Notifications Error: " . $e->getMessage());
    header('Location: logout.php');
    exit;
}

// Handle notification actions
try {
    // Mark single notification as read
    if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
        $updateStmt = $pdo->prepare("UPDATE notifications SET is_read = 1 
                                   WHERE id = ? AND user_id = ?");
        $updateStmt->execute([$_GET['mark_read'], $doctor['doctor_id']]);
    }

    // Mark all as read
    if (isset($_POST['mark_all_read'])) {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            die("CSRF token validation failed");
        }
        $updateStmt = $pdo->prepare("UPDATE notifications SET is_read = 1 
                                   WHERE user_id = ?");
        $updateStmt->execute([$doctor['doctor_id']]);
        header('Location: notifications.php');
        exit;
    }

    // Delete all read notifications
    if (isset($_POST['delete_read'])) {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            die("CSRF token validation failed");
        }
        $deleteStmt = $pdo->prepare("DELETE FROM notifications 
                                   WHERE user_id = ? AND is_read = 1");
        $deleteStmt->execute([$doctor['doctor_id']]);
        header('Location: notifications.php');
        exit;
    }

    // Fetch notifications
    $stmt = $pdo->prepare("SELECT * FROM notifications 
                         WHERE user_id = ? 
                         ORDER BY created_at DESC");
    $stmt->execute([$doctor['doctor_id']]);
    $notifications = $stmt->fetchAll();

    // Get unread count
    $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications 
                               WHERE user_id = ? AND is_read = 0");
    $unreadStmt->execute([$doctor['doctor_id']]);
    $unreadCount = $unreadStmt->fetchColumn();

} catch (PDOException $e) {
    error_log("Notification Error: " . $e->getMessage());
    $notifications = [];
    $unreadCount = 0;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - MedSuite Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2A5C82;
            --accent: #5FB4C9;
            --radius: 12px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', system-ui, sans-serif;
        }

        body {
            background: #F9FBFD;
            color: #2D3748;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(42, 92, 130, 0.1);
        }

        .card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .notification-item {
            padding: 1.5rem;
            margin: 1rem 0;
            background: white;
            border-radius: var(--radius);
            border-left: 4px solid transparent;
            transition: var(--transition);
        }

        .notification-item.unread {
            border-left-color: var(--accent);
            background: rgba(95, 180, 201, 0.05);
        }

        .notification-time {
            font-size: 0.9rem;
            color: #718096;
            margin-top: 0.5rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            border: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 4rem;
            opacity: 0.6;
        }

        .text-danger {
            color: #ef4444;
        }

        .notification-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Notifications</h1>
            <div class="profile-menu">
                <div class="avatar">DR</div>
            </div>
        </div>

        <div class="card">
            <div class="notification-actions">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button type="submit" name="mark_all_read" class="btn btn-primary">
                        <i class="fas fa-check-double"></i> Mark all as read
                    </button>
                </form>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button type="submit" name="delete_read" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete read
                    </button>
                </form>
            </div>

            <?php if (!empty($notifications)): ?>
                <div class="notification-list">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>">
                            <div class="notification-content">
                                <?= htmlspecialchars($notification['content']) ?>
                            </div>
                            <div class="notification-meta">
                                <span class="notification-time">
                                    <?= date('M j, Y \a\t g:i A', strtotime($notification['created_at'])) ?>
                                </span>
                                <?php if (!$notification['is_read']): ?>
                                    <a href="?mark_read=<?= $notification['id'] ?>" 
                                       class="text-danger"
                                       style="margin-left: 1rem; text-decoration: none;">
                                        Mark as read
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash fa-3x"></i>
                    <h4>No notifications found</h4>
                    <p>You're all caught up!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>