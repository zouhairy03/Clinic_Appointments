<?php
require_once 'config.php';


// Authentication and Authorization
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$currentRole = $_SESSION['role'];
$userId = $_SESSION['user_id'];

try {
    // Fetch user data based on role
    if ($currentRole === 'doctor') {
        $stmt = $pdo->prepare("SELECT * FROM doctors WHERE doctor_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        $userType = 'doctor';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        $userType = 'patient';
    }

    if (!$user) throw new Exception("User not found");

    // Fetch relevant data based on user type
    if ($userType === 'doctor') {
        $patientsStmt = $pdo->query("SELECT patient_id, first_name, last_name FROM patients ORDER BY last_name");
        $patients = $patientsStmt->fetchAll();
    } else {
        $doctorsStmt = $pdo->query("SELECT doctor_id, first_name, last_name, specialty FROM doctors ORDER BY last_name");
        $doctors = $doctorsStmt->fetchAll();
    }

} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    header('Location: logout.php');
    exit;
}

// Handle form submission
$errors = [];
$formData = [
    'doctor_id' => '',
    'patient_id' => '',
    'appointment_date' => '',
    'appointment_time' => '',
    'notes' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }

    // Validate inputs
    $formData['appointment_date'] = $_POST['appointment_date'] ?? '';
    $formData['appointment_time'] = $_POST['appointment_time'] ?? '';
    $formData['notes'] = strip_tags($_POST['notes'] ?? '');

    // Role-based validation
    if ($userType === 'doctor') {
        $formData['patient_id'] = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        if (!$formData['patient_id']) $errors[] = "Please select a patient";
    } else {
        $formData['doctor_id'] = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
        if (!$formData['doctor_id']) $errors[] = "Please select a doctor";
    }

    // Date/time validation
    $appointmentDateTime = DateTime::createFromFormat('Y-m-d H:i', 
        $formData['appointment_date'] . ' ' . $formData['appointment_time']);
    
    if (!$appointmentDateTime || $appointmentDateTime < new DateTime()) {
        $errors[] = "Invalid date/time selection";
    }

    // Save if no errors
    if (empty($errors)) {
        try {
            // Set automatic values based on user type
            if ($userType === 'doctor') {
                $formData['doctor_id'] = $user['doctor_id'];
            } else {
                $formData['patient_id'] = $user['patient_id'];
            }

            $stmt = $pdo->prepare("INSERT INTO appointments 
                (doctor_id, patient_id, appointment_date, appointment_time, notes, status)
                VALUES (?, ?, ?, ?, ?, 'scheduled')");

            $success = $stmt->execute([
                $formData['doctor_id'],
                $formData['patient_id'],
                $formData['appointment_date'],
                $formData['appointment_time'],
                $formData['notes']
            ]);

            if ($success) {
                $_SESSION['success'] = "Appointment created successfully!";
                header('Location: ' . ($userType === 'doctor' ? 'doctor_dashboard.php' : 'patient_dashboard.php'));
                exit;
            }

        } catch (PDOException $e) {
            error_log("DB Error: " . $e->getMessage());
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
    <title>Create Appointment - MedSuite Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Form Styles */
        .form-card {
            background: var(--glass);
            backdrop-filter: blur(8px);
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
        }

        .form-title {
            font-size: 1.75rem;
            color: var(--primary);
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--primary-dark);
        }

        select, 
        input, 
        textarea {
            width: 100%;
            padding: 0.875rem 1.25rem;
            border: 1px solid rgba(42, 92, 130, 0.1);
            border-radius: var(--radius);
            background: var(--glass);
            backdrop-filter: blur(8px);
            transition: var(--transition);
        }

        select:focus,
        input:focus,
        textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(95, 180, 201, 0.15);
        }

        .btn-primary {
            background: var(--accent);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            font-size: 1rem;
        }

        .btn-primary:hover {
            background: var(--primary);
            transform: translateY(-2px);
        }

        .error-list {
            background: #FEF2F2;
            color: #EF4444;
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
            list-style: none;
        }

        .datetime-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .datetime-group {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                padding: 1rem;
                margin-top: 100px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="nav-brand">
            <i class="fas fa-calendar-plus"></i>
            MedSuite Pro
        </div>
        <div class="nav-links">
            <a href="<?= $userType === 'doctor' ? 'doctor_dashboard.php' : 'patient_dashboard.php' ?>" class="nav-link">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="form-card">
            <h1 class="form-title">Schedule New Appointment</h1>
            
            <?php if (!empty($errors)): ?>
                <ul class="error-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <?php if ($userType === 'doctor'): ?>
                    <div class="form-group">
                        <label for="patient_id">Select Patient</label>
                        <select name="patient_id" id="patient_id" required>
                            <option value="">Choose a patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?= $patient['patient_id'] ?>"
                                    <?= $formData['patient_id'] == $patient['patient_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($patient['last_name']) ?>, 
                                    <?= htmlspecialchars($patient['first_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label for="doctor_id">Select Doctor</label>
                        <select name="doctor_id" id="doctor_id" required>
                            <option value="">Choose a doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?= $doctor['doctor_id'] ?>"
                                    <?= $formData['doctor_id'] == $doctor['doctor_id'] ? 'selected' : '' ?>>
                                    Dr. <?= htmlspecialchars($doctor['last_name']) ?> 
                                    (<?= $doctor['specialty'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-group datetime-group">
                    <div>
                        <label>Date</label>
                        <input type="date" 
                               name="appointment_date" 
                               value="<?= $formData['appointment_date'] ?>"
                               min="<?= date('Y-m-d') ?>"
                               required>
                    </div>
                    <div>
                        <label>Time</label>
                        <input type="time" 
                               name="appointment_time" 
                               value="<?= $formData['appointment_time'] ?>"
                               min="08:00" 
                               max="18:00"
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea name="notes" 
                              id="notes" 
                              rows="4"
                              placeholder="Enter any additional information..."><?= $formData['notes'] ?></textarea>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-calendar-check"></i>
                    Schedule Appointment
                </button>
            </form>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Date/Time Picker Configuration
        flatpickr("input[type='date']", {
            minDate: "today",
            disableMobile: true
        });

        flatpickr("input[type='time']", {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            time_24hr: false,
            minTime: "08:00",
            maxTime: "18:00"
        });

        // Real-time Validation
        const dateInput = document.querySelector("input[name='appointment_date']");
        const timeInput = document.querySelector("input[name='appointment_time']");

        function validateDateTime() {
            const selectedDate = new Date(dateInput.value);
            const today = new Date();
            today.setHours(0,0,0,0);
            
            if (selectedDate < today) {
                dateInput.setCustomValidity("Date cannot be in the past");
            } else {
                dateInput.setCustomValidity("");
            }
        }

        dateInput.addEventListener('change', validateDateTime);
        timeInput.addEventListener('change', validateDateTime);
    </script>
</body>
</html>