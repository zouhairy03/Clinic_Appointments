<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Validation
    if (empty($email) || empty($password)) {
        header('Location: index.php?error=1'); // Redirect back with error
        exit;
    }

    try {
        // Check if the email exists for a patient or doctor (you can query both tables)
        $stmt = $pdo->prepare("SELECT patient_id, password_hash, role FROM patients WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // If not found in the patients table, check the doctors table
        if (!$user) {
            $stmt = $pdo->prepare("SELECT doctor_id AS patient_id, password_hash, 'doctor' AS role FROM doctors WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
        }

        // Check if user exists and if passwords match
        if ($user && $user['password_hash'] === $password) {  // Plain text password comparison
            // Start session and store user info
            session_start();
            $_SESSION['user_id'] = $user['patient_id'];  // This could be patient_id or doctor_id
            $_SESSION['role'] = $user['role'];           // Store the role (doctor or patient)

            // Redirect based on role
            if ($user['role'] === 'doctor') {
                header('Location: doctor_dashboard.php');
            } else {
                header('Location: patient_dashboard.php');
            }
            exit;
        } else {
            header('Location: index.php?error=1'); // Invalid credentials
            exit;
        }
    } catch (PDOException $e) {
        error_log("Authentication Error: " . $e->getMessage());
        header('Location: index.php?error=1'); // Redirect on error
        exit;
    }
}
