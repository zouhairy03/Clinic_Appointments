<?php
require_once 'config.php';


// Authentication and Authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: index.php');
    exit;
}

$appointmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$appointmentId) {
    $_SESSION['error'] = "Invalid appointment ID";
    header('Location: doctor_dashboard.php');
    exit;
}

try {
    $doctorStmt = $pdo->prepare("SELECT * FROM doctors WHERE doctor_id = ?");
    $doctorStmt->execute([$_SESSION['user_id']]);
    $doctor = $doctorStmt->fetch();
    
    if (!$doctor) throw new Exception("Doctor not found");

    $appointmentStmt = $pdo->prepare("
        SELECT a.*, p.first_name, p.last_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        WHERE a.appointment_id = ? AND a.doctor_id = ?
    ");
    $appointmentStmt->execute([$appointmentId, $doctor['doctor_id']]);
    $appointment = $appointmentStmt->fetch();

    if (!$appointment) throw new Exception("Appointment not found or unauthorized access");

    $patientsStmt = $pdo->query("SELECT patient_id, first_name, last_name FROM patients ORDER BY last_name");
    $patients = $patientsStmt->fetchAll();

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: doctor_dashboard.php');
    exit;
}

// Initialize form data
$formData = [
    'patient_id' => $appointment['patient_id'],
    'appointment_date' => $appointment['appointment_date'],
    'appointment_time' => $appointment['appointment_time'],
    'status' => $appointment['status'],
    'notes' => $appointment['notes']
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }

    try {
        $formData['patient_id'] = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $formData['appointment_date'] = $_POST['appointment_date'] ?? '';
        $formData['appointment_time'] = $_POST['appointment_time'] ?? '';
        $formData['status'] = $_POST['status'] ?? 'scheduled';
        $formData['notes'] = strip_tags($_POST['notes'] ?? '');

        // Validation
        if (!$formData['patient_id']) throw new Exception("Please select a patient");
        
        $appointmentDateTime = DateTime::createFromFormat('Y-m-d H:i', 
            $formData['appointment_date'] . ' ' . $formData['appointment_time']);
        
        if (!$appointmentDateTime || $appointmentDateTime < new DateTime()) {
            throw new Exception("Invalid date/time selection");
        }

        if (!in_array($formData['status'], ['scheduled', 'completed', 'cancelled'])) {
            throw new Exception("Invalid status selection");
        }

        // Update appointment
        $updateStmt = $pdo->prepare("
            UPDATE appointments 
            SET patient_id = ?,
                appointment_date = ?,
                appointment_time = ?,
                status = ?,
                notes = ?,
                created_at = NOW()
            WHERE appointment_id = ?
            AND doctor_id = ?
        ");

        $success = $updateStmt->execute([
            $formData['patient_id'],
            $formData['appointment_date'],
            $formData['appointment_time'],
            $formData['status'],
            $formData['notes'],
            $appointmentId,
            $doctor['doctor_id']
        ]);

        if (!$success) throw new Exception("Failed to update appointment");

        $_SESSION['success'] = "Appointment updated successfully!";
        header("Location: appointment_details.php?id=$appointmentId");
        exit;

    } catch (Exception $e) {
        error_log("Update Error: " . $e->getMessage());
        $errors[] = $e->getMessage();
    }
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Appointment - MedSuite Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        :root {
            --primary: #2A5C82;
            --primary-dark: #1E425B;
            --accent: #5FB4C9;
            --success: #22C55E;
            --error: #EF4444;
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

        .main-content {
            margin-top: 80px;
            padding: 2rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

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

        select, input, textarea {
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

        .status-group {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .status-option {
            padding: 1rem;
            border-radius: var(--radius);
            cursor: pointer;
            text-align: center;
            transition: var(--transition);
            border: 2px solid transparent;
        }

        .status-option input[type="radio"] {
            display: none;
        }

        .status-option.active {
            border-color: var(--accent);
        }

        .status-scheduled { background: rgba(95, 180, 201, 0.1); color: var(--accent); }
        .status-completed { background: rgba(34, 197, 94, 0.1); color: var(--success); }
        .status-cancelled { background: rgba(239, 68, 68, 0.1); color: var(--error); }

        @media (max-width: 768px) {
            .datetime-group,
            .status-group {
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
    <nav class="top-nav">
        <div class="nav-brand">
            <i class="fas fa-edit"></i>
            MedSuite Pro
        </div>
        <div class="nav-links">
            <a href="appointment_details.php?id=<?= $appointmentId ?>" class="nav-link">
                <i class="fas fa-arrow-left"></i>
                Back to Appointment
            </a>
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </nav>

    <main class="main-content">
        <div class="form-card">
            <h1 class="form-title">Edit Appointment</h1>
            
            <?php if (!empty($errors)): ?>
                <ul class="error-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="current-patient">
                <strong>Original Patient:</strong> 
                <?= htmlspecialchars($appointment['first_name']) ?> <?= htmlspecialchars($appointment['last_name']) ?>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="form-group">
                    <label>Patient Selection</label>
                    <select name="patient_id" required>
                        <option value="">Select Patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= $patient['patient_id'] ?>"
                                <?= $formData['patient_id'] == $patient['patient_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($patient['last_name']) ?>, 
                                <?= htmlspecialchars($patient['first_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

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
                    <label>Appointment Status</label>
                    <div class="status-group">
                        <label class="status-option status-scheduled <?= $formData['status'] === 'scheduled' ? 'active' : '' ?>">
                            <input type="radio" name="status" value="scheduled" 
                                   <?= $formData['status'] === 'scheduled' ? 'checked' : '' ?>>
                            Scheduled
                        </label>
                        
                        <label class="status-option status-completed <?= $formData['status'] === 'completed' ? 'active' : '' ?>">
                            <input type="radio" name="status" value="completed" 
                                   <?= $formData['status'] === 'completed' ? 'checked' : '' ?>>
                            Completed
                        </label>
                        
                        <label class="status-option status-cancelled <?= $formData['status'] === 'cancelled' ? 'active' : '' ?>">
                            <input type="radio" name="status" value="cancelled" 
                                   <?= $formData['status'] === 'cancelled' ? 'checked' : '' ?>>
                            Cancelled
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="4"><?= htmlspecialchars($formData['notes']) ?></textarea>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i>
                    Save Changes
                </button>
            </form>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Date/Time Pickers
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

        // Status Selection
        document.querySelectorAll('.status-option').forEach(option => {
            option.addEventListener('click', () => {
                document.querySelectorAll('.status-option').forEach(o => o.classList.remove('active'));
                option.classList.add('active');
                option.querySelector('input').checked = true;
            });
        });

        // Form Validation
        const validateForm = () => {
            const dateInput = document.querySelector("input[name='appointment_date']");
            const timeInput = document.querySelector("input[name='appointment_time']");
            const selectedDate = new Date(dateInput.value);
            const now = new Date();
            
            if (selectedDate < now.setHours(0,0,0,0)) {
                dateInput.setCustomValidity("Date cannot be in the past");
                return false;
            }
            return true;
        };

        document.querySelector('form').addEventListener('submit', e => {
            if (!validateForm()) {
                e.preventDefault();
                alert('Please fix validation errors before submitting');
            }
        });
    </script>
</body>
</html>