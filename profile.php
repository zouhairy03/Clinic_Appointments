<?php
require_once 'config.php';


// Authentication Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: login.php');
    exit;
}

// Fetch Patient Data
$stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }

    // Sanitize Inputs
    $firstName = filter_input(INPUT_POST, 'first_name', FILTER_UNSAFE_RAW);
    $lastName = filter_input(INPUT_POST, 'last_name', FILTER_UNSAFE_RAW);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_UNSAFE_RAW);
    $password = $_POST['password'];  
    
    // Additional Sanitization (if needed)
    $firstName = trim($firstName);
    $lastName = trim($lastName);
    $phone = trim($phone);
    
    // If you want to prevent XSS:
    $firstName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $lastName = htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8');
    $phone = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
    

    // Validation
    if (empty($firstName) || empty($lastName) || empty($email)) {
        $errors[] = "First name, last name, and email are required";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    // Check email uniqueness
    $emailCheck = $pdo->prepare("SELECT patient_id FROM patients WHERE email = ? AND patient_id != ?");
    $emailCheck->execute([$email, $user['patient_id']]);
    if ($emailCheck->fetch()) {
        $errors[] = "Email already exists";
    }

    if (empty($errors)) {
        try {
            $updateData = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'patient_id' => $user['patient_id']
            ];

            $sql = "UPDATE patients SET 
                    first_name = :first_name,
                    last_name = :last_name,
                    email = :email,
                    phone = :phone";

            // Handle password update
            if (!empty($password)) {
                if (strlen($password) < 8) {
                    $errors[] = "Password must be at least 8 characters";
                } else {
                    $updateData['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                    $sql .= ", password_hash = :password_hash";
                }
            }

            $sql .= " WHERE patient_id = :patient_id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($updateData);

            $_SESSION['success'] = "Profile updated successfully";
            header('Location: profile.php');
            exit;
        } catch (PDOException $e) {
            error_log("Profile Update Error: " . $e->getMessage());
            $errors[] = "Error updating profile";
        }
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - MedBook</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --sidebar-width: 260px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background: #f8fafc;
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar Styles */
        .sidebar {
            background: white;
            width: var(--sidebar-width);
            height: 100vh;
            padding: 1.5rem;
            position: fixed;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.05);
            transition: var(--transition);
            z-index: 100;
        }

        .sidebar.collapsed {
            transform: translateX(calc(-1 * var(--sidebar-width)));
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 0.75rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            color: #64748b;
            text-decoration: none;
            transition: var(--transition);
        }

        .nav-link:hover,
        .nav-link.active {
            background: #eef2ff;
            color: var(--primary);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: var(--transition);
            padding: 2rem;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .toggle-btn {
            background: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: var(--transition);
            font-size: 1.25rem;
            color: #64748b;
        }

        .toggle-btn:hover {
            background: #eef2ff;
            color: var(--primary);
        }

        /* Profile Styles */
        .profile-form {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            max-width: 600px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #1e293b;
            font-weight: 500;
        }

        input, button {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            transition: var(--transition);
        }

        input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1rem 0;
        }

        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo">
            <i class="fas fa-heartbeat"></i>
            MedBook
        </div>
        <nav>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="patient_dashboard.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="appointments.php" class="nav-link">
                        <i class="fas fa-calendar-check"></i>
                        Appointments
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link active">
                        <i class="fas fa-user"></i>
                        Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="header">
            <button class="toggle-btn" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="user-info">
                <span>Welcome, <?= htmlspecialchars($user['first_name']) ?></span>
            </div>
        </div>

        <div class="profile-form">
            <h1 style="margin-bottom: 1.5rem;">My Profile</h1>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= $error ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" 
                           value="<?= htmlspecialchars($user['first_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" 
                           value="<?= htmlspecialchars($user['last_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" 
                           value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" 
                           value="<?= htmlspecialchars($user['phone']) ?>">
                </div>

                <div class="form-group">
                    <label>New Password (leave blank to keep current)</label>
                    <input type="password" name="password">
                </div>

                <div class="form-group">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Sidebar Toggle Logic
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const sidebarToggle = document.getElementById('sidebarToggle');

        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('collapsed'));
        });

        // Initialize sidebar state
        const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }

        // Mobile menu handling
        function handleMobileMenu() {
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }
        }
        
        window.addEventListener('resize', handleMobileMenu);
        handleMobileMenu();
    </script>
</body>
</html>