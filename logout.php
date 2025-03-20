<?php
require_once 'config.php';


// Verify user is logged in
$logged_in = isset($_SESSION['user_id']);
$username = $_SESSION['first_name'] ?? '';

// Handle logout confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {
    // Destroy session
    session_unset();
    session_destroy();
    session_write_close();
    
    // Redirect to login with success message
    $_SESSION['logout_success'] = true;
    header('Location: index.php');
    exit;
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - MedBook</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        body {
            background: #f8fafc;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            position: relative;
            overflow: hidden;
        }

        .logout-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 3rem;
            border-radius: 1.5rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            text-align: center;
            position: relative;
            z-index: 1;
            transform: scale(0.95);
            opacity: 0;
            animation: scaleUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        .logout-icon {
            font-size: 4rem;
            color: #6366f1;
            margin-bottom: 1.5rem;
            display: inline-block;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .logout-icon:hover {
            transform: rotate(360deg) scale(1.1);
        }

        h1 {
            color: #1e293b;
            margin-bottom: 1rem;
            font-size: 2rem;
        }

        p {
            color: #64748b;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 0.75rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-logout {
            background: #6366f1;
            color: white;
        }

        .btn-logout:hover {
            background: #4f46e5;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .btn-cancel {
            background: #e2e8f0;
            color: #64748b;
        }

        .btn-cancel:hover {
            background: #cbd5e1;
            transform: translateY(-2px);
        }

        .background-pattern {
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: radial-gradient(rgba(255, 255, 255, 0.1) 10%, transparent 10%);
            background-size: 20px 20px;
            opacity: 0.3;
            animation: movePattern 20s linear infinite;
        }

        @keyframes scaleUp {
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes movePattern {
            0% { background-position: 0 0; }
            100% { background-position: 100% 100%; }
        }
    </style>
</head>
<body>
    <div class="background-pattern"></div>
    
    <div class="logout-container">
        <i class="fas fa-sign-out-alt logout-icon"></i>
        <h1>Ready to Leave<?= $logged_in && $username ? ', ' . htmlspecialchars($username) : '' ?>?</h1>
        <p>Are you sure you want to log out of your account?</p>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="button-group">
                <button type="submit" name="confirm_logout" class="btn btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Yes, Log Out
                </button>
                <a href="patient_dashboard.php" class="btn btn-cancel">
                    <i class="fas fa-times"></i>
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        // Add hover effect to logout button
        const logoutBtn = document.querySelector('.btn-logout');
        logoutBtn.addEventListener('mouseenter', () => {
            logoutBtn.style.transform = 'translateY(-2px) scale(1.05)';
        });
        logoutBtn.addEventListener('mouseleave', () => {
            logoutBtn.style.transform = 'translateY(0) scale(1)';
        });

        // Add slight animation to cancel button
        document.querySelector('.btn-cancel').addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        document.querySelector('.btn-cancel').addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    </script>
</body>
</html>