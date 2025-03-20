<?php
require_once 'config.php';
// session_start();

// Authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Get appointment ID
$appointmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$appointmentId) {
    $_SESSION['error'] = "Invalid appointment ID";
    header('Location: ' . ($_SESSION['role'] === 'doctor' ? 'doctor_dashboard.php' : 'patient_dashboard.php'));
    exit;
}

try {
    // Fetch appointment details
    $stmt = $pdo->prepare("
        SELECT a.*, 
               d.first_name as doctor_fname, d.last_name as doctor_lname, d.specialty,
               p.first_name as patient_fname, p.last_name as patient_lname
        FROM appointments a
        LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
        LEFT JOIN patients p ON a.patient_id = p.patient_id
        WHERE a.appointment_id = ?
    ");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        throw new Exception("Appointment not found");
    }

    // Verify authorization
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['role'];
    if ($userRole === 'doctor' && $appointment['doctor_id'] !== $userId) {
        throw new Exception("Unauthorized access");
    }
    if ($userRole === 'patient' && $appointment['patient_id'] !== $userId) {
        throw new Exception("Unauthorized access");
    }

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: ' . ($_SESSION['role'] === 'doctor' ? 'doctor_dashboard.php' : 'patient_dashboard.php'));
    exit;
}

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }

    if (isset($_POST['cancel_appointment'])) {
        try {
            $updateStmt = $pdo->prepare("
                UPDATE appointments 
                SET status = 'cancelled' 
                WHERE appointment_id = ?
                AND status = 'scheduled'
            ");
            $updateStmt->execute([$appointmentId]);
            
            if ($updateStmt->rowCount() > 0) {
                $_SESSION['success'] = "Appointment cancelled successfully";
                header("Location: appointment_details.php?id=$appointmentId");
                exit;
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error cancelling appointment: " . $e->getMessage();
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
    <title>Appointment Details - MedSuite Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        /* Appointment Card */
        .appointment-card {
            background: var(--glass);
            backdrop-filter: blur(8px);
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            font-weight: 500;
            margin-bottom: 1.5rem;
        }

        .status-scheduled { background: rgba(95, 180, 201, 0.1); color: var(--accent); }
        .status-completed { background: rgba(34, 197, 94, 0.1); color: var(--success); }
        .status-cancelled { background: rgba(239, 68, 68, 0.1); color: var(--error); }

        .detail-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            padding: 1rem;
            background: rgba(42, 92, 130, 0.03);
            border-radius: var(--radius);
        }

        .detail-label {
            font-size: 0.875rem;
            color: #4A5568;
            margin-bottom: 0.5rem;
        }

        .detail-value {
            font-weight: 500;
            color: var(--primary-dark);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius);
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary);
        }

        .btn-danger {
            background: var(--error);
            color: white;
        }

        .btn-danger:hover {
            background: #DC2626;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
                margin-top: 100px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="nav-brand">
            <i class="fas fa-calendar-check"></i>
            MedSuite Pro
        </div>
        <div class="nav-links">
            <a href="<?= $_SESSION['role'] === 'doctor' ? 'doctor_dashboard.php' : 'patient_dashboard.php' ?>" class="nav-link">
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
        <div class="appointment-card">
            <?php if(isset($_SESSION['error'])): ?>
                <div class="error-message"><?= $_SESSION['error'] ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if(isset($_SESSION['success'])): ?>
                <div class="success-message"><?= $_SESSION['success'] ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <div class="status-badge status-<?= $appointment['status'] ?>">
                <?= ucfirst($appointment['status']) ?>
            </div>

            <div class="detail-group">
                <div class="detail-item">
                    <div class="detail-label">Date & Time</div>
                    <div class="detail-value">
                        <?= date('M j, Y \a\t g:i A', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'])) ?>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-label"><?= $_SESSION['role'] === 'doctor' ? 'Patient' : 'Doctor' ?></div>
                    <div class="detail-value">
                        <?php if($_SESSION['role'] === 'doctor'): ?>
                            <?= $appointment['patient_fname'] ?> <?= $appointment['patient_lname'] ?>
                        <?php else: ?>
                            Dr. <?= $appointment['doctor_fname'] ?> <?= $appointment['doctor_lname'] ?>
                            <div class="text-muted"><?= $appointment['specialty'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if($appointment['status'] === 'cancelled'): ?>
    <div class="detail-item">
        <div class="detail-label">Cancelled On</div>
        <div class="detail-value">
            <?php 
                // Check if 'updated_at' exists and is not null
                $updated_at = isset($appointment['updated_at']) && $appointment['updated_at'] !== null 
                    ? $appointment['updated_at'] 
                    : '1970-01-01 00:00:00'; // Default to Jan 1, 1970 if not set
                
                // Format the date
                echo date('M j, Y \a\t g:i A', strtotime($updated_at));
            ?>
        </div>
    </div>
<?php endif; ?>

            </div>

            <?php if(!empty($appointment['notes'])): ?>
                <div class="detail-item">
                    <div class="detail-label">Notes</div>
                    <div class="detail-value"><?= nl2br(htmlspecialchars($appointment['notes'])) ?></div>
                </div>
            <?php endif; ?>

            <?php if($appointment['status'] === 'scheduled'): ?>
                <form method="POST" class="action-buttons">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <?php if($_SESSION['role'] === 'doctor'): ?>
                        <a href="edit_appointment.php?id=<?= $appointmentId ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i>
                            Edit Appointment
                        </a>
                    <?php endif; ?>
                    
                    <button type="submit" name="cancel_appointment" class="btn btn-danger">
                        <i class="fas fa-times-circle"></i>
                        Cancel Appointment
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>