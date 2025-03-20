<?php
require_once 'config.php';
// session_start();

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

// Fetch Available Doctors
try {
    $doctorsStmt = $pdo->query("SELECT * FROM doctors ORDER BY last_name, first_name");
    $doctors = $doctorsStmt->fetchAll();
} catch (PDOException $e) {
    die("Error retrieving doctors list");
}

$errors = [];
$formData = [
    'doctor_id' => '',
    'appointment_date' => '',
    'appointment_time' => '',
    'notes' => ''
];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }

    // Validate Inputs
    $formData['doctor_id'] = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
    $formData['appointment_date'] = filter_input(INPUT_POST, 'appointment_date', FILTER_SANITIZE_STRING);
    $formData['appointment_time'] = filter_input(INPUT_POST, 'appointment_time', FILTER_SANITIZE_STRING);
    $formData['notes'] = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);

    // Validation
    if (!$formData['doctor_id']) {
        $errors[] = "Please select a doctor";
    }
    
    if (!strtotime($formData['appointment_date'] . ' ' . $formData['appointment_time'])) {
        $errors[] = "Invalid date/time selection";
    }

    // Save if no errors
    if (empty($errors)) {
        try {
            $insertStmt = $pdo->prepare("
                INSERT INTO appointments 
                (patient_id, doctor_id, appointment_date, appointment_time, notes, status)
                VALUES (?, ?, ?, ?, ?, 'scheduled')
            ");
            
            $insertStmt->execute([
                $user['patient_id'],
                $formData['doctor_id'],
                $formData['appointment_date'],
                $formData['appointment_time'],
                $formData['notes']
            ]);

            $_SESSION['success'] = "Appointment booked successfully!";
            header('Location: appointments.php');
            exit;
        } catch (PDOException $e) {
            error_log("Booking Error: " . $e->getMessage());
            $errors[] = "Error saving appointment. Please try again.";
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
    <title>Book Appointment - MedBook</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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

        /* Form Styles */
        .booking-form {
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

        input, select, textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            transition: var(--transition);
        }

        input:focus, select:focus, textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .datetime-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

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

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1rem 0;
        }

        .alert-error { 
            background: #fee2e2; 
            color: #991b1b;
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
                    <a href="appointments.php" class="nav-link">
                        <i class="fas fa-calendar-check"></i>
                        Appointments
                    </a>
                </li>
                <li class="nav-item">
                    <a href="book_appointment.php" class="nav-link active">
                        <i class="fas fa-plus"></i>
                        New Appointment
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
                <a href="appointments.php" class="btn-primary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Appointments
                </a>
            </div>
        </div>

        <div class="booking-form">
            <h1 style="margin-bottom: 2rem;">Book New Appointment</h1>
            
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
                    <label for="doctor_id">Select Doctor</label>
                    <select name="doctor_id" id="doctor_id" required>
                        <option value="">Choose a doctor</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?= $doctor['doctor_id'] ?>"
                                <?= $formData['doctor_id'] == $doctor['doctor_id'] ? 'selected' : '' ?>>
                                Dr. <?= htmlspecialchars($doctor['last_name']) ?>, 
                                <?= htmlspecialchars($doctor['first_name']) ?> 
                                (<?= $doctor['specialty'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Date & Time</label>
                    <div class="datetime-wrapper">
                        <div>
                            <input type="date" 
                                   name="appointment_date" 
                                   value="<?= $formData['appointment_date'] ?>"
                                   min="<?= date('Y-m-d') ?>" 
                                   required>
                        </div>
                        <div>
                            <input type="time" 
                                   name="appointment_time" 
                                   value="<?= $formData['appointment_time'] ?>"
                                   min="09:00" 
                                   max="17:00" 
                                   required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Notes (Optional)</label>
                    <textarea name="notes" id="notes" rows="4"><?= $formData['notes'] ?></textarea>
                </div>

                <div class="form-group" style="margin-top: 2rem; text-align: right;">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-calendar-check"></i>
                        Book Appointment
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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

        // Initialize date/time pickers
        flatpickr('input[type="date"]', {
            minDate: "today",
            disableMobile: true
        });

        flatpickr('input[type="time"]', {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            time_24hr: false,
            minTime: "09:00",
            maxTime: "17:00"
        });
    </script>
</body>
</html>