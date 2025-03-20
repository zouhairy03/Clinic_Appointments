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
    error_log("Dashboard Error: " . $e->getMessage());
    header('Location: logout.php');
    exit;
}

// Fetch dashboard data
try {
    // Today's Appointments
    $todayStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments 
                              WHERE doctor_id = ? AND appointment_date = CURDATE()");
    $todayStmt->execute([$doctor['doctor_id']]);
    $todayCount = $todayStmt->fetchColumn();

    // New Patients (last 30 days)
    $newPatientsStmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) FROM appointments 
                                    WHERE doctor_id = ? AND created_at >= NOW() - INTERVAL 30 DAY");
    $newPatientsStmt->execute([$doctor['doctor_id']]);
    $newPatients = $newPatientsStmt->fetchColumn();

    // Notifications
    $notificationsStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications 
                                      WHERE user_id = ? AND is_read = 0");
    $notificationsStmt->execute([$doctor['doctor_id']]);
    $unreadNotifications = $notificationsStmt->fetchColumn();

    // Upcoming Appointments
    $appointmentsStmt = $pdo->prepare("SELECT a.*, p.first_name, p.last_name 
                                     FROM appointments a
                                     JOIN patients p ON a.patient_id = p.patient_id
                                     WHERE a.doctor_id = ? AND a.status = 'scheduled'
                                     ORDER BY a.appointment_date ASC LIMIT 5");
    $appointmentsStmt->execute([$doctor['doctor_id']]);
    $appointments = $appointmentsStmt->fetchAll();

} catch (PDOException $e) {
    error_log("Data Fetch Error: " . $e->getMessage());
    $todayCount = $newPatients = $unreadNotifications = 0;
    $appointments = [];
}
$stmt = $pdo->prepare("SELECT first_name, last_name, specialty FROM doctors WHERE doctor_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

// Debugging: Check if doctor data is fetched
if (!$doctor) {
    die("Error: Doctor not found.");
}

// Sanitize output
$doctor_name = htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']);
$doctor_specialty = htmlspecialchars($doctor['specialty']);
$current_hour = date('G');

// Set the greeting based on the time of day
if ($current_hour >= 5 && $current_hour < 12) {
    $greeting = "Good Morning";
} elseif ($current_hour >= 12 && $current_hour < 17) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - MedSuite Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="styles.css"> <!-- Link to external CSS -->

    <style>
        :root {
            --primary: #2A5C82;
            --primary-dark: #1E425B;
            --accent: #5FB4C9;
            --sidebar-width: 280px;
            --transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            --elevation-1: 0 8px 24px rgba(0, 0, 0, 0.08);
            --elevation-2: 0 12px 32px rgba(0, 0, 0, 0.1);
            --radius: 12px;
            --glass: rgba(255, 255, 255, 0.88);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', system-ui, sans-serif;
        }

        body {
            background: #F9FBFD;
            min-height: 100vh;
            display: flex;
            color: #2D3748;
        }

        /* Modern Sidebar */
        .sidebar {
            background: var(--glass);
            width: var(--sidebar-width);
            height: 100vh;
            padding: 1.5rem;
            position: fixed;
            backdrop-filter: blur(12px);
            border-right: 1px solid rgba(255, 255, 255, 0.9);
            transition: var(--transition);
            z-index: 100;
            box-shadow: var(--elevation-1);
        }

        .sidebar.collapsed {
            transform: translateX(calc(-1 * var(--sidebar-width)));
            opacity: 0;
        }

        .logo {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            background: rgba(42, 92, 130, 0.05);
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.875rem 1.25rem;
            border-radius: var(--radius);
            color: #4A5568;
            text-decoration: none;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .nav-link:before {
            content: '';
            position: absolute;
            left: -100%;
            width: 4px;
            height: 100%;
            background: var(--accent);
            transition: var(--transition);
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(95, 180, 201, 0.08);
            color: var(--primary);
        }

        .nav-link.active:before {
            left: 0;
        }

        .badge {
            background: var(--accent);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            margin-left: auto;
            font-weight: 500;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: var(--transition);
            padding: 2rem;
            background: #F9FBFD;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        /* Modern Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem;
            animation: slideDown 0.6s ease;
        }

        .toggle-btn {
            background: var(--glass);
            border: 1px solid rgba(42, 92, 130, 0.1);
            width: 44px;
            height: 44px;
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            backdrop-filter: blur(8px);
        }

        .toggle-btn:hover {
            background: rgba(95, 180, 201, 0.1);
            transform: translateY(-1px);
        }

        .search-bar {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .search-input {
            padding: 0.875rem 1.5rem;
            border: 1px solid rgba(42, 92, 130, 0.1);
            border-radius: var(--radius);
            width: 320px;
            background: var(--glass);
            backdrop-filter: blur(8px);
            transition: var(--transition);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(95, 180, 201, 0.15);
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        /* Modern Cards */
        .card {
            background: var(--glass);
            padding: 1.75rem;
            border-radius: var(--radius);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.9);
            transition: var(--transition);
            animation: cardEntrance 0.6s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--elevation-2);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(95, 180, 201, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
        }

        .stat-number {
            font-size: 2.75rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.75rem;
            line-height: 1;
        }

        /* Emergency Alert */
        .emergency-alert {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.15);
            padding: 1.25rem;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            gap: 1.25rem;
            margin-bottom: 2rem;
            animation: pulse 2s infinite;
        }

        /* Patient List */
        .patient-list {
            display: grid;
            gap: 1rem;
        }

        .patient-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem;
            border-radius: var(--radius);
            background: var(--glass);
            border: 1px solid rgba(42, 92, 130, 0.05);
            transition: var(--transition);
        }

        .patient-item:hover {
            transform: translateX(8px);
        }

        /* Animations */
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes cardEntrance {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.1); }
            70% { box-shadow: 0 0 0 12px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
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
                padding: 1.5rem;
            }
            .search-input {
                width: 100%;
            }
        }
        .welcome-section {
        margin-bottom: 2.5rem;
        position: relative;
        padding: 1.5rem 0;
    }

    .welcome-message {
        font-size: 2.25rem;
        color: var(--primary-dark);
        margin-bottom: 0.5rem;
        animation: slideIn 0.6s ease-out;
    }

    .specialty-container {
        position: relative;
        display: inline-block;
    }

    .specialty-text {
        font-size: 1.5rem;
        font-weight: 500;
        color: transparent;
        background: linear-gradient(
            120deg,
            rgba(0, 0, 0, 0.8) 20%,
            rgba(121, 224, 255, 0.9) 40%,
            rgba(0, 0, 0, 0.8) 60%
        );
        background-size: 200% auto;
        background-clip: text;
        -webkit-background-clip: text;
        animation: waveShine 4s linear infinite;
        position: relative;
        z-index: 1;
    }

    .specialty-container::after {
        content: '';
        position: absolute;
        bottom: -4px;
        left: 0;
        width: 100%;
        height: 2px;
        background: linear-gradient(
            90deg,
            transparent,
            rgba(0, 0, 0, 0.4),
            transparent
        );
        animation: waveLine 3s ease-in-out infinite;
    }

    @keyframes waveShine {
        to {
            background-position: 200% center;
        }
    }

    @keyframes waveLine {
        0% { transform: translateX(-100%); }
        50% { transform: translateX(100%); }
        100% { transform: translateX(100%); }
    }

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
    </style>
</head>
<body>
    <!-- Modern Sidebar -->
    <aside class="sidebar">
        <div class="logo">
            <i class="fas fa-stethoscope"></i>
            MedSuite Pro
        </div>
        <nav>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="doctor_dashboard.php" class="nav-link active">
                        <i class="fas fa-chart-line"></i>
                        Dashboard
                        <span class="badge">New</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="patients.php" class="nav-link">
                        <i class="fas fa-user-injured"></i>
                        Patients
                        <?php if($newPatients > 0): ?>
                            <span class="badge"><?= $newPatients ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="schedule.php" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        Schedule
                    </a>
                </li>
                <li class="nav-item">
                    <a href="telemedicine.php" class="nav-link">
                        <i class="fas fa-video"></i>
                        Telemedicine
                    </a>
                </li>
                <li class="nav-item">
                    <a href="notifications.php" class="nav-link">
                        <i class="fas fa-bell"></i>
                        Notifications
                        <?php if($unreadNotifications > 0): ?>
                            <span class="badge"><?= $unreadNotifications ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <!-- i wanna add an li for reports  -->
                 <li class="nav-item">
                    <a href="reports.php" class="nav-link">
                        <i class="fas fa-file-alt"></i>
                        Reports
                        </a>
                        </li>
                        <!-- i wanna add an li for support  -->
                         <!-- <li class="nav-item">
                            <a href="support.php" class="nav-link">
                                <i class="fas fa-question-circle"></i>
                                Support
                                </a>
                                </li> -->
                   

            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="header">
            <button class="toggle-btn" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
         
        </div>
        <div class="welcome-section" style="text-align: center;">
    <h1 class="welcome-message"><?= $greeting ?>, Dr. <?= htmlspecialchars($doctor['last_name']) ?></h1>
    <div class="specialty-container">
        <span class="specialty-text"><?= htmlspecialchars($doctor['specialty']) ?></span>
    </div>
</div>
</div>
    <br>
        <!-- Emergency Alert -->
        <div class="emergency-alert">
            <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
            <div class="alert-content">
                <h4>Emergency Alert - Room 204</h4>
                <p>Patient: Sarah Johnson • Cardiac Arrest • Priority: Critical</p>
            </div>
            <button class="btn-icon">
                <i class="fas fa-arrow-right"></i>
            </button>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Stats Cards -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3>Today's Schedule</h3>
                </div>
                <div class="stat-number"><?= $todayCount ?></div>
                <p class="text-muted">Appointments remaining</p>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3>New Patients</h3>
                </div>
                <div class="stat-number"><?= $newPatients ?></div>
                <p class="text-muted">Last 30 days</p>
            </div>

            <!-- Schedule Calendar -->
            <div class="card" style="grid-column: span 2">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3>Medical Calendar</h3>
                </div>
                <div id="calendar"></div>
            </div>

            <!-- Patient List -->
            <div class="card" style="grid-column: span 2">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Upcoming Appointments</h3>
                </div>
                <div class="patient-list">
                    <?php foreach($appointments as $apt): ?>
                        <div class="patient-item">
                            <div>
                                <h4><?= htmlspecialchars($apt['first_name']) ?> <?= htmlspecialchars($apt['last_name']) ?></h4>
                                <p class="text-muted"><?= date('M j, Y \a\t g:i A', strtotime($apt['appointment_date'] . ' ' . $apt['appointment_time'])) ?></p>
                            </div>
                            <div class="patient-actions">
                                <a href="patient_profile.php?id=<?= $apt['patient_id'] ?>" class="btn-icon">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Modern Sidebar Toggle
        const sidebarToggle = () => {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            // Animate with Web Animations API
            sidebar.animate([
                { opacity: 1, transform: 'translateX(0)' },
                { opacity: 0, transform: 'translateX(-100%)' }
            ], {
                duration: 300,
                easing: 'ease-in-out'
            });
            
            localStorage.setItem('sidebarState', sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded');
        }

        // Initialize Calendar
        const calendar = flatpickr("#calendar", {
            inline: true,
            mode: "multiple",
            dateFormat: "Y-m-d",
            disableMobile: true,
            theme: "light"
        });

        // Restore UI State
        window.addEventListener('DOMContentLoaded', () => {
            const sidebarState = localStorage.getItem('sidebarState');
            if (sidebarState === 'collapsed') {
                document.querySelector('.sidebar').classList.add('collapsed');
                document.querySelector('.main-content').classList.add('expanded');
            }
            
            // Animate elements on load
            document.querySelectorAll('.card').forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });

        // Modern Mobile Handling
        const handleResponsiveMenu = () => {
            const sidebar = document.querySelector('.sidebar');
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                document.querySelector('.main-content').classList.add('expanded');
            } else {
                sidebar.classList.remove('collapsed');
                document.querySelector('.main-content').classList.remove('expanded');
            }
        }

        window.addEventListener('resize', handleResponsiveMenu);
        handleResponsiveMenu();
    </script>
</body>
</html>