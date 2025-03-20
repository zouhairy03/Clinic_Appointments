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

// Handle Appointment Cancellation
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancel_appointment'])) {
        $appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
        
        if ($appointmentId) {
            try {
                $cancelStmt = $pdo->prepare("
                    UPDATE appointments 
                    SET status = 'cancelled' 
                    WHERE appointment_id = ? 
                    AND patient_id = ?
                ");
                $cancelStmt->execute([$appointmentId, $user['patient_id']]);
                
                if ($cancelStmt->rowCount() > 0) {
                    $success = "Appointment cancelled successfully";
                }
            } catch (PDOException $e) {
                error_log("Cancellation Error: " . $e->getMessage());
                $error = "Error cancelling appointment";
            }
        }
    }
}

// Fetch Appointments with Doctor Details
try {
    $appointmentStmt = $pdo->prepare("
        SELECT a.*, 
               d.first_name AS doctor_fname, 
               d.last_name AS doctor_lname,
               d.specialty
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC
    ");
    $appointmentStmt->execute([$user['patient_id']]);
    $appointments = $appointmentStmt->fetchAll();
} catch (PDOException $e) {
    die("Error retrieving appointments");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - MedBook</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Inherited Dashboard Styles */
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

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: var(--transition);
            padding: 2rem;
        }

        .main-content.expanded {
            margin-left: 0;
        }

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

        .user-greeting {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
        }

        /* Appointments Specific Styles */
        .appointment-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: start;
            transition: var(--transition);
        }

        .appointment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.08);
        }

        .status-tag {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .status-scheduled { background: #e0f2fe; color: #0369a1; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }

        .btn-cancel {
            background: #fee2e2;
            color: #991b1b;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-cancel:hover {
            background: #fecaca;
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1rem 0;
        }

        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
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
                    <a href="patient_dashboard.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="appointments.php" class="nav-link active">
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
        <div class="header">
            <button class="toggle-btn" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="header-actions">
                <a href="book_appointment.php" class="btn-primary">
                    <i class="fas fa-plus"></i>
                    New Appointment
                </a>
            </div>
        </div>

        <div class="user-greeting">
            <h1>Your Appointments</h1>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>
        </div>

        <div class="appointments-list">
            <?php if (empty($appointments)): ?>
                <div class="appointment-card">
                    <p>No appointments found. Start by booking a new appointment!</p>
                </div>
            <?php else: ?>
                <?php foreach ($appointments as $apt): ?>
                    <div class="appointment-card">
                        <div class="appointment-info">
                            <h3>
                                <?= date('M j, Y', strtotime($apt['appointment_date'])) ?>
                                at <?= date('g:i A', strtotime($apt['appointment_time'])) ?>
                            </h3>
                            <p class="doctor-info">
                                Dr. <?= htmlspecialchars($apt['doctor_fname']) ?> 
                                <?= htmlspecialchars($apt['doctor_lname']) ?> 
                                (<?= htmlspecialchars($apt['specialty']) ?>)
                            </p>
                            <div class="status-tag status-<?= $apt['status'] ?>">
                                <?= ucfirst($apt['status']) ?>
                            </div>
                            <?php if (!empty($apt['notes'])): ?>
                                <p class="notes">Notes: <?= htmlspecialchars($apt['notes']) ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($apt['status'] === 'scheduled'): ?>
                            <form 
                                method="POST" 
                                onsubmit="return confirm('Are you sure you want to cancel this appointment?')"
                            >
                                <input type="hidden" name="appointment_id" value="<?= $apt['appointment_id'] ?>">
                                <button type="submit" name="cancel_appointment" class="btn-cancel">
                                    <i class="fas fa-times-circle"></i> Cancel
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
        handleMobileMenu(); // Initial check
    </script>
</body>
</html>