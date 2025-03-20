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
    error_log("Telemedicine Error: " . $e->getMessage());
    header('Location: logout.php');
    exit;
}

// Fetch upcoming appointments
try {
    $stmt = $pdo->prepare("SELECT a.*, p.first_name, p.last_name 
                         FROM appointments a
                         JOIN patients p ON a.patient_id = p.patient_id
                         WHERE a.doctor_id = ? 
                         AND a.status = 'scheduled'
                         AND a.appointment_date >= CURDATE()
                         ORDER BY a.appointment_date ASC");
    $stmt->execute([$doctor['doctor_id']]);
    $appointments = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Appointment Error: " . $e->getMessage());
    $appointments = [];
}

// Handle call session
$activeCall = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF token validation failed");
    }
    
    if (isset($_POST['start_call'])) {
        // Validate appointment belongs to doctor
        $appointmentId = (int)$_POST['appointment_id'];
        $checkStmt = $pdo->prepare("SELECT * FROM appointments 
                                   WHERE appointment_id = ? AND doctor_id = ?");
        $checkStmt->execute([$appointmentId, $doctor['doctor_id']]);
        if ($checkStmt->fetch()) {
            $activeCall = true;
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telemedicine - MedSuite Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2A5C82;
            --accent: #5FB4C9;
            --radius: 12px;
            --transition: all 0.3s ease;
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

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(42, 92, 130, 0.1);
        }

        .video-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        .call-interface {
            background: #fff;
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            position: relative;
        }

        .video-feed {
            background: #1a1a1a;
            border-radius: var(--radius);
            aspect-ratio: 16/9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-bottom: 1rem;
        }

        .call-controls {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-success {
            background: #22c55e;
            color: white;
        }

        .appointment-list {
            background: #fff;
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }

        .appointment-item {
            padding: 1rem;
            margin: 0.5rem 0;
            border-radius: var(--radius);
            border: 1px solid rgba(42, 92, 130, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            opacity: 0.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Telemedicine Portal</h1>
            <div class="profile-menu">
                <div class="avatar">DR</div>
            </div>
        </div>

        <?php if ($activeCall): ?>
        <div class="call-interface">
            <div class="video-feed">
                <i class="fas fa-video fa-2x"></i>
                <span>Patient Video Feed</span>
            </div>
            <div class="video-feed">
                <i class="fas fa-user-md fa-2x"></i>
                <span>Your Camera</span>
            </div>
            <div class="call-controls">
                <button class="btn btn-danger">
                    <i class="fas fa-phone-slash"></i>
                    End Call
                </button>
                <button class="btn btn-primary">
                    <i class="fas fa-microphone"></i>
                    Mute
                </button>
                <button class="btn btn-primary">
                    <i class="fas fa-video-slash"></i>
                    Stop Video
                </button>
            </div>
        </div>

        <?php else: ?>
        <div class="video-container">
            <div class="appointment-list">
                <h2>Upcoming Appointments</h2>
                <?php if (!empty($appointments)): ?>
                    <?php foreach ($appointments as $apt): ?>
                        <div class="appointment-item">
                            <div>
                                <h3><?= htmlspecialchars($apt['first_name']) ?> <?= htmlspecialchars($apt['last_name']) ?></h3>
                                <p>
                                    <?= date('M j, Y \a\t g:i A', 
                                           strtotime($apt['appointment_date'] . ' ' . $apt['appointment_time'])) ?>
                                </p>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="appointment_id" value="<?= $apt['appointment_id'] ?>">
                                <button type="submit" name="start_call" class="btn btn-success">
                                    <i class="fas fa-video"></i>
                                    Start Call
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times fa-2x"></i>
                        <p>No upcoming appointments</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="call-interface">
                <div class="video-feed" style="height: 400px">
                    <i class="fas fa-video-slash fa-3x"></i>
                    <p>No active call</p>
                </div>
                <div class="call-controls">
                    <p>Select an appointment to start a video call</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>