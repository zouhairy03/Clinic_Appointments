<?php
require_once 'config.php';


// Authentication and Authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: login.php');
    exit;
}

// Fetch doctor data
try {
    $stmt = $pdo->prepare("SELECT * FROM doctors WHERE doctor_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $doctor = $stmt->fetch();
    
    if (!$doctor) throw new Exception("Doctor not found");
    
} catch (Exception $e) {
    error_log("Access Error: " . $e->getMessage());
    header('Location: logout.php');
    exit;
}

// Fetch patients with pagination and search
$currentPage = max(1, $_GET['page'] ?? 1);
$searchTerm = $_GET['search'] ?? '';
$perPage = 10;
$offset = ($currentPage - 1) * $perPage;

try {
    $baseQuery = "FROM patients p
                INNER JOIN appointments a ON p.patient_id = a.patient_id
                WHERE a.doctor_id = ?";
    $params = [$doctor['doctor_id']];
    
    if (!empty($searchTerm)) {
        $baseQuery .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.email LIKE ?)";
        $searchParam = "%$searchTerm%";
        array_push($params, $searchParam, $searchParam, $searchParam);
    }

    // Count total patients
    $countStmt = $pdo->prepare("SELECT COUNT(DISTINCT p.patient_id) $baseQuery");
    $countStmt->execute($params);
    $totalPatients = $countStmt->fetchColumn();

    // Fetch patient data
    $patientStmt = $pdo->prepare("
        SELECT DISTINCT 
            p.patient_id,
            p.first_name,
            p.last_name,
            p.email,
            p.phone,
            MAX(a.appointment_date) as last_visit
        $baseQuery
        GROUP BY p.patient_id
        ORDER BY last_visit DESC
        LIMIT $perPage OFFSET $offset
    ");
    $patientStmt->execute($params);
    $patients = $patientStmt->fetchAll();

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $patients = [];
    $totalPatients = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Management - MedSuite Pro</title>
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

        /* Patient Table */
        .patient-table-container {
            background: var(--glass);
            backdrop-filter: blur(8px);
            border-radius: var(--radius);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            padding: 1.5rem;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .patient-table {
            width: 100%;
            border-collapse: collapse;
        }

        .patient-table th,
        .patient-table td {
            padding: 1.25rem;
            text-align: left;
            border-bottom: 1px solid rgba(42, 92, 130, 0.05);
        }

        .patient-table tr:last-child td {
            border-bottom: none;
        }

        .patient-table tr {
            transition: var(--transition);
        }

        .patient-table tr:hover {
            background: rgba(95, 180, 201, 0.03);
            transform: translateX(8px);
        }

        .patient-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 500;
        }

        /* Search and Pagination */
        .search-box {
            width: 300px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 0.875rem 1.5rem;
            border: 1px solid rgba(42, 92, 130, 0.1);
            border-radius: var(--radius);
            background: var(--glass);
            backdrop-filter: blur(8px);
            transition: var(--transition);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(95, 180, 201, 0.15);
        }

        .pagination {
            display: flex;
            gap: 0.5rem;
            margin-top: 2rem;
            justify-content: center;
        }

        .page-link {
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            background: var(--glass);
            border: 1px solid rgba(42, 92, 130, 0.1);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
        }

        .page-link:hover {
            background: rgba(95, 180, 201, 0.1);
        }

        .page-link.active {
            background: var(--accent);
            color: white;
        }

        @media (max-width: 768px) {
            .top-nav {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }

            .nav-links {
                flex-wrap: wrap;
                gap: 0.5rem;
                justify-content: center;
            }

            .main-content {
                margin-top: 120px;
                padding: 1rem;
            }

            .patient-table td:nth-child(4),
            .patient-table th:nth-child(4) {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="nav-brand">
            <i class="fas fa-stethoscope"></i>
            MedSuite Pro
        </div>
        <div class="nav-links">
            <a href="doctor_dashboard.php" class="nav-link">
                <i class="fas fa-chart-line"></i>
                Dashboard
            </a>
            <a href="patients.php" class="nav-link active">
                <i class="fas fa-user-injured"></i>
                Patients
            </a>
            <a href="schedule.php" class="nav-link">
                <i class="fas fa-calendar-alt"></i>
                Schedule
            </a>
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="patient-table-container">
            <div class="table-header">
                <h2>Patient Directory <span class="badge"><?= $totalPatients ?> Patients</span></h2>
                <form method="GET" class="search-box">
                    <input type="text" 
                           class="search-input" 
                           name="search" 
                           placeholder="Search patients..."
                           value="<?= htmlspecialchars($searchTerm) ?>">
                </form>
            </div>

            <table class="patient-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Contact</th>
                        <th>Last Visit</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <div class="patient-avatar">
                                        <?= strtoupper(substr($patient['first_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <h4><?= htmlspecialchars($patient['first_name']) ?> <?= htmlspecialchars($patient['last_name']) ?></h4>
                                        <p class="text-muted">ID: <?= $patient['patient_id'] ?></p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="contact-info">
                                    <p><?= htmlspecialchars($patient['email']) ?></p>
                                    <p class="text-muted"><?= htmlspecialchars($patient['phone']) ?></p>
                                </div>
                            </td>
                            <td>
                                <?= $patient['last_visit'] 
                                    ? date('M j, Y', strtotime($patient['last_visit'])) 
                                    : 'N/A' ?>
                            </td>
                            <td>
                                <a href="patient_profile.php?id=<?= $patient['patient_id'] ?>" 
                                   class="action-btn">
                                   <i class="fas fa-eye"></i>
                                   View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination">
                <?php 
                $totalPages = ceil($totalPatients / $perPage);
                for ($i = 1; $i <= $totalPages; $i++): 
                ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($searchTerm) ?>" 
                       class="page-link <?= $i == $currentPage ? 'active' : '' ?>">
                       <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    </main>

    <script>
        // Search Debounce
        let searchTimeout;
        document.querySelector('.search-input').addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });

        // Table Row Animation
        document.querySelectorAll('.patient-table tr').forEach((row, index) => {
            row.style.opacity = '0';
            row.style.transform = 'translateY(20px)';
            row.style.animation = `fadeInUp 0.4s ease ${index * 0.05}s forwards`;
        });

        // Add keyframe animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>