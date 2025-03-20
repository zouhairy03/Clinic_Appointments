<?php
require_once 'config.php';

// session_start();

// Ensure the user is logged in and has the 'patient' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    // Redirect to login page if the user is not logged in or not a patient
    header('Location: login.php');
    exit;
}

// Retrieve user data from the database based on session 'user_id'
$stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// If no user data is found, redirect to login
if (!$user) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - MedBook</title>
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

        .nav-link i {
            width: 24px;
            text-align: center;
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

        .user-greeting {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
            animation: slideIn 0.6s ease;
        }

        .user-greeting h1 {
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .user-greeting p {
            color: #64748b;
        }

        /* Cards */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            transition: var(--transition);
            animation: fadeIn 0.6s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .card-header i {
            font-size: 1.5rem;
            color: var(--primary);
        }

        .card-title {
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .card-text {
            color: #64748b;
            line-height: 1.6;
        }

        /* Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
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
            <a href="patient_dashboard.php" class="nav-link active">
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
            <a href="profile.php" class="nav-link">
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
        <!-- Header -->
        <div class="header">
            <button class="toggle-btn" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="user-info">
            <span>Welcome, <?= htmlspecialchars($user['first_name']) . ' ' . htmlspecialchars($user['last_name']) ?></span>
            </div>
        </div>

        <!-- Greeting Card -->
        <div class="user-greeting">
    <h1 style="text-align: center;">Good <?= date('H') < 12 ? 'Morning' : (date('H') < 18 ? 'Afternoon' : 'Evening') ?>, <?= htmlspecialchars($user['first_name']) . ' ' . htmlspecialchars($user['last_name']) ?></h1>
    <p style="text-align: center;">Here's your health overview for today</p>
</div>


        <!-- Cards Grid -->
        <div class="card-grid">
            <!-- Upcoming Appointments -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-calendar-alt"></i>
                    <h3 class="card-title">Upcoming Appointments</h3>
                </div>
                <div class="card-text">
                    <?php
                    // Retrieve upcoming appointments for the patient
                    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE patient_id = ? AND appointment_date >= CURDATE() ORDER BY appointment_date ASC LIMIT 3");
                    $stmt->execute([$user['patient_id']]);
                    $appointments = $stmt->fetchAll();

                    if ($appointments):
                        foreach ($appointments as $apt): ?>
                            <div class="appointment-item">
                                <div><?= date('M j, Y', strtotime($apt['appointment_date'])) ?></div>
                                <div>Doctor: <?= $apt['doctor_id'] ?> - Status: <?= $apt['status'] ?></div>
                            </div>
                        <?php endforeach;
                    else: ?>
                        <p>No upcoming appointments</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-plus-circle"></i>
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="card-text">
                    <button class="btn-action">
                        <i class="fas fa-calendar-plus"></i>
                        Book Appointment
                    </button>
                    <button class="btn-action">
                        <i class="fas fa-file-medical"></i>
                        Request Prescription
                    </button>
                </div>
            </div>

            <!-- Health Summary -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-heartbeat"></i>
                    <h3 class="card-title">Health Summary</h3>
                </div>
                <div class="card-text">
                    <div class="health-metric">
                        <div>Last Checkup: 2023-08-15</div>
                        <div>Current Medications: None</div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Sidebar Toggle and Mobile Menu Handling
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const sidebarToggle = document.getElementById('sidebarToggle');

        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            // Save state in localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebar-collapsed', isCollapsed ? 'true' : 'false');
        });

        // Initialize sidebar state from localStorage
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }
    </script>
</body>
</html>
