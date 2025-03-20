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
    error_log("Reports Error: " . $e->getMessage());
    header('Location: logout.php');
    exit;
}

// Report Generation Logic
$reportData = [];
$reportError = '';
$validTypes = ['appointments', 'prescriptions', 'demographics'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            die("CSRF token validation failed");
        }

        $reportType = $_POST['report_type'];
        $startDate = DateTime::createFromFormat('Y-m-d', $_POST['start_date']);
        $endDate = DateTime::createFromFormat('Y-m-d', $_POST['end_date']);

        if (!in_array($reportType, $validTypes)) {
            throw new Exception("Invalid report type");
        }

        if (!$startDate || !$endDate || $startDate > $endDate) {
            throw new Exception("Invalid date range");
        }

        // Modified queries to include sample data
        switch ($reportType) {
            case 'appointments':
                $stmt = $pdo->prepare("
                    SELECT 
                        DATE(appointment_date) AS date,
                        COUNT(*) AS total,
                        SUM(status = 'completed') AS completed
                    FROM appointments
                    WHERE doctor_id = ?
                    AND appointment_date BETWEEN ? AND ?
                    GROUP BY DATE(appointment_date)
                    ORDER BY DATE(appointment_date) DESC
                ");
                break;

            case 'prescriptions':
                $stmt = $pdo->prepare("
                    SELECT 
                        medication,
                        COUNT(*) AS total_prescribed
                    FROM prescriptions
                    WHERE doctor_id = ?
                    AND created_at BETWEEN ? AND ?
                    GROUP BY medication
                    ORDER BY total_prescribed DESC
                    LIMIT 5
                ");
                break;

                case 'demographics':
                    $stmt = $pdo->prepare("
                        SELECT 
                            FLOOR(TIMESTAMPDIFF(YEAR, p.date_of_birth, NOW()) / 10) * 10 AS age_group,
                            COUNT(DISTINCT p.patient_id) AS patients
                        FROM patients p
                        JOIN appointments a ON p.patient_id = a.patient_id
                        WHERE a.doctor_id = ?
                        AND a.appointment_date BETWEEN ? AND ?
                        GROUP BY age_group
                        ORDER BY age_group
                    ");
                    break;
                
        }

        $stmt->execute([
            $doctor['doctor_id'],
            $startDate->format('Y-m-d'),
            $endDate->modify('+1 day')->format('Y-m-d')
        ]);
        
        $reportData = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $reportError = "Error generating report: " . $e->getMessage();
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
    <title>Medical Analytics - MedSuite Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #2A5C82;
            --accent: #5FB4C9;
            --radius: 16px;
            --transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            --glass: rgba(255, 255, 255, 0.9);
            --text-gradient: linear-gradient(135deg, #2A5C82 0%, #5FB4C9 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', system-ui, sans-serif;
        }

        body {
            background: #f0f4f8;
            color: #2D3748;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            text-align: center;
            margin-bottom: 3rem;
            opacity: 0;
            animation: slideUp 1s ease-out forwards;
        }

        .title {
            font-size: 2.5rem;
            background-image: var(--text-gradient);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 1rem;
        }

        .report-form {
            background: var(--glass);
            backdrop-filter: blur(12px);
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 3rem;
            transform: translateY(20px);
            opacity: 0;
            animation: formEntrance 0.8s ease-out 0.2s forwards;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .form-group {
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: var(--primary);
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            border: 2px solid rgba(42, 92, 130, 0.1);
            border-radius: var(--radius);
            background: rgba(255, 255, 255, 0.8);
            transition: var(--transition);
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(95, 180, 201, 0.2);
        }

        .generate-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            padding: 1rem 2rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            font-size: 1.1rem;
            margin: 0 auto;
            display: block;
        }

        .generate-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(42, 92, 130, 0.2);
        }

        .chart-container {
            margin: 3rem 0;
            padding: 2rem;
            background: var(--glass);
            border-radius: var(--radius);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.05);
            opacity: 0;
            transform: translateY(20px);
            animation: chartEntrance 0.8s ease-out forwards;
        }

        .chart-title {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
        }

        .chart-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: var(--accent);
            border-radius: 2px;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 1.5rem;
            border-radius: var(--radius);
            margin: 2rem 0;
            text-align: center;
            border: 1px solid rgba(239, 68, 68, 0.2);
            opacity: 0;
            animation: fadeIn 0.6s ease-out forwards;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes formEntrance {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes chartEntrance {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }
            
            .title {
                font-size: 2rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1 class="title">Clinical Analytics Dashboard</h1>
        </header>

        <form method="POST" class="report-form">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="report_type">
                        <i class="fas fa-chart-pie"></i> Report Type
                    </label>
                    <select class="form-control" name="report_type" id="report_type" required>
                        <option value="appointments" <?= ($_POST['report_type'] ?? '') === 'appointments' ? 'selected' : '' ?>>Appointment Trends</option>
                        <option value="prescriptions" <?= ($_POST['report_type'] ?? '') === 'prescriptions' ? 'selected' : '' ?>>Medication Analysis</option>
                        <option value="demographics" <?= ($_POST['report_type'] ?? '') === 'demographics' ? 'selected' : '' ?>>Patient Demographics</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="start_date">
                        <i class="fas fa-calendar-alt"></i> Start Date
                    </label>
                    <input type="date" class="form-control" name="start_date" 
                           value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="end_date">
                        <i class="fas fa-calendar-alt"></i> End Date
                    </label>
                    <input type="date" class="form-control" name="end_date" 
                           value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>" required>
                </div>
            </div>

            <button type="submit" name="generate_report" class="generate-btn">
                <i class="fas fa-rocket"></i> Generate Insights
            </button>
        </form>

        <?php if (!empty($reportError)): ?>
            <div class="error-message">
                <?= htmlspecialchars($reportError) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($reportData)): ?>
            <div class="chart-container">
                <h2 class="chart-title">
                    <?php switch($_POST['report_type']) {
                        case 'appointments': echo 'Appointment Trends'; break;
                        case 'prescriptions': echo 'Medication Analysis'; break;
                        case 'demographics': echo 'Patient Demographics'; break;
                    } ?>
                </h2>
                <canvas id="analyticsChart"></canvas>
            </div>

            <script>
                const ctx = document.getElementById('analyticsChart').getContext('2d');
                const reportType = '<?= $_POST['report_type'] ?>';
                const chartData = <?= json_encode($reportData) ?>;

                const chartConfigs = {
                    appointments: {
                        type: 'line',
                        data: {
                            labels: chartData.map(item => item.date),
                            datasets: [{
                                label: 'Completed Appointments',
                                data: chartData.map(item => item.completed),
                                borderColor: '#2A5C82',
                                tension: 0.4,
                                fill: true,
                                backgroundColor: 'rgba(42, 92, 130, 0.05)'
                            }]
                        }
                    },
                    prescriptions: {
                        type: 'bar',
                        data: {
                            labels: chartData.map(item => item.medication),
                            datasets: [{
                                label: 'Prescriptions Issued',
                                data: chartData.map(item => item.total_prescribed),
                                backgroundColor: '#5FB4C9',
                                borderRadius: 8
                            }]
                        }
                    },
                    demographics: {
                        type: 'pie',
                        data: {
                            labels: chartData.map(item => `${item.age_group}-${Number(item.age_group)+10} yrs`),
                            datasets: [{
                                label: 'Patients by Age Group',
                                data: chartData.map(item => item.patients),
                                backgroundColor: [
                                    '#2A5C82', '#5FB4C9', '#8FC1D4', 
                                    '#B3D4E0', '#D6E8EC'
                                ]
                            }]
                        }
                    }
                };

                new Chart(ctx, {
                    type: chartConfigs[reportType].type,
                    data: chartConfigs[reportType].data,
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { font: { size: 14 } }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(42, 92, 130, 0.9)',
                                titleFont: { size: 16 },
                                bodyFont: { size: 14 }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(0, 0, 0, 0.05)' }
                            },
                            x: {
                                grid: { display: false }
                            }
                        },
                        animation: {
                            duration: 2000,
                            easing: 'easeOutQuart'
                        }
                    }
                });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>