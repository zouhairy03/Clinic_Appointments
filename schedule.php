<?php
require_once 'config.php';


// Authentication and Authorization
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch user data based on role
try {
    if ($_SESSION['role'] === 'doctor') {
        $stmt = $pdo->prepare("SELECT * FROM doctors WHERE doctor_id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = ?");
    }
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) throw new Exception("User not found");
    
} catch (Exception $e) {
    error_log("Access Error: " . $e->getMessage());
    header('Location: logout.php');
    exit;
}

// Date handling
$currentDate = new DateTime();
$year = isset($_GET['year']) ? intval($_GET['year']) : $currentDate->format('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : $currentDate->format('n');
$calendarDate = new DateTime("$year-$month-01");

// Fetch appointments
try {
    if ($_SESSION['role'] === 'doctor') {
        $appointmentsQuery = "SELECT a.*, p.first_name, p.last_name 
                            FROM appointments a
                            JOIN patients p ON a.patient_id = p.patient_id
                            WHERE a.doctor_id = ?
                            AND YEAR(appointment_date) = ?
                            AND MONTH(appointment_date) = ?";
    } else {
        $appointmentsQuery = "SELECT a.*, d.first_name, d.last_name 
                            FROM appointments a
                            JOIN doctors d ON a.doctor_id = d.doctor_id
                            WHERE a.patient_id = ?
                            AND YEAR(appointment_date) = ?
                            AND MONTH(appointment_date) = ?";
    }
    
    $stmt = $pdo->prepare($appointmentsQuery);
    $stmt->execute([$_SESSION['user_id'], $year, $month]);
    $appointments = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $appointments = [];
}

// Generate calendar data
$calendar = [];
$daysInMonth = $calendarDate->format('t');
$firstDayOfWeek = (int)$calendarDate->format('N');

// Create calendar grid
for ($i = 1; $i < $firstDayOfWeek; $i++) {
    $calendar[] = ['type' => 'empty'];
}

for ($day = 1; $day <= $daysInMonth; $day++) {
    $date = new DateTime("$year-$month-$day");
    $dayAppointments = array_filter($appointments, function($apt) use ($date) {
        return (new DateTime($apt['appointment_date']))->format('Y-m-d') === $date->format('Y-m-d');
    });
    $calendar[] = [
        'type' => 'day',
        'date' => $date,
        'appointments' => $dayAppointments
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule - MedSuite Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2A5C82;
            --primary-dark: #1E425B;
            --accent: #5FB4C9;
            --transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
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
            color: #2D3748;
        }

        /* Top Navigation */
        .top-nav {
            background: var(--glass);
            backdrop-filter: blur(12px);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.05);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .nav-brand {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-link {
            color: #4A5568;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            transition: var(--transition);
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(95, 180, 201, 0.1);
            color: var(--primary);
        }

        /* Main Content */
        .main-content {
            margin-top: 80px;
            padding: 2rem;
        }

        /* Calendar Controls */
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .calendar-nav {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .calendar-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--primary);
        }

        /* Calendar Grid */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: rgba(42, 92, 130, 0.1);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .calendar-day-header {
            padding: 1rem;
            background: var(--primary);
            color: white;
            text-align: center;
            font-weight: 500;
        }

        .calendar-day {
            min-height: 120px;
            background: var(--glass);
            padding: 0.75rem;
            position: relative;
            transition: var(--transition);
        }

        .calendar-day:hover {
            background: rgba(95, 180, 201, 0.03);
        }

        .calendar-date {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        /* Appointment Cards */
        .appointment-card {
            background: white;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            cursor: pointer;
            transition: var(--transition);
        }

        .appointment-card:hover {
            transform: translateX(4px);
        }

        .appointment-time {
            font-size: 0.875rem;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .appointment-patient {
            font-size: 0.875rem;
            color: #4A5568;
        }

        /* Status Indicators */
        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }

        .status-scheduled { background: #5FB4C9; }
        .status-completed { background: #22C55E; }
        .status-cancelled { background: #EF4444; }

        @media (max-width: 768px) {
            .calendar-day-header {
                display: none;
            }
            
            .calendar-grid {
                grid-template-columns: 1fr;
            }
            
            .calendar-day {
                min-height: auto;
                border-bottom: 1px solid rgba(42, 92, 130, 0.1);
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="nav-brand">
            <i class="fas fa-calendar-alt"></i>
            MedSuite Pro
        </div>
        <div class="nav-links">
            <a href="doctor_dashboard.php" class="nav-link">
                <i class="fas fa-chart-line"></i>
                Dashboard
            </a>
            <a href="schedule.php" class="nav-link active">
                <i class="fas fa-calendar-day"></i>
                Schedule
            </a>
            <a href="patients.php" class="nav-link">
                <i class="fas fa-user-injured"></i>
                Patients
            </a>
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="calendar-header">
            <div class="calendar-nav">
                <button class="btn-icon" onclick="navigateMonth(-1)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <h2 class="calendar-title">
                    <?= $calendarDate->format('F Y') ?>
                </h2>
                <button class="btn-icon" onclick="navigateMonth(1)">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            

            <button class="btn-primary" onclick="showNewAppointmentModal()">
                <i class="fas fa-plus"></i>
                New Appointment
            </button>
        </div>

        <div class="calendar-grid">
            <!-- Day Headers -->
            <div class="calendar-day-header">Mon</div>
            <div class="calendar-day-header">Tue</div>
            <div class="calendar-day-header">Wed</div>
            <div class="calendar-day-header">Thu</div>
            <div class="calendar-day-header">Fri</div>
            <div class="calendar-day-header">Sat</div>
            <div class="calendar-day-header">Sun</div>

            <!-- Calendar Days -->
            <?php foreach ($calendar as $day): ?>
                <div class="calendar-day">
                    <?php if ($day['type'] === 'day'): ?>
                        <div class="calendar-date">
                            <?= $day['date']->format('j') ?>
                        </div>
                        <div class="appointments-list">
                            <?php foreach ($day['appointments'] as $apt): ?>
                                <div class="appointment-card" 
                                     data-appointment-id="<?= $apt['appointment_id'] ?>">
                                    <div class="appointment-time">
                                        <span class="status-indicator status-<?= $apt['status'] ?>"></span>
                                        <?= date('g:i A', strtotime($apt['appointment_time'])) ?>
                                    </div>
                                    <div class="appointment-patient">
                                        <?php if ($_SESSION['role'] === 'doctor'): ?>
                                            <?= htmlspecialchars($apt['first_name']) ?> <?= htmlspecialchars($apt['last_name']) ?>
                                        <?php else: ?>
                                            Dr. <?= htmlspecialchars($apt['first_name']) ?> <?= htmlspecialchars($apt['last_name']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <script>
        // Calendar Navigation
        function navigateMonth(offset) {
            const currentDate = new Date(<?= $year ?>, <?= $month - 1 ?>);
            currentDate.setMonth(currentDate.getMonth() + offset);
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth() + 1;
            window.location.href = `schedule.php?year=${year}&month=${month}`;
        }

        // Appointment Click Handler
        document.querySelectorAll('.appointment-card').forEach(card => {
            card.addEventListener('click', () => {
                const appointmentId = card.dataset.appointmentId;
                window.location.href = `appointment_details.php?id=${appointmentId}`;
            });
        });

        // New Appointment Modal
        function showNewAppointmentModal() {
            // Implement modal display logic
            window.location.href = 'create_appointment.php';
        }
    </script>
</body>
</html>