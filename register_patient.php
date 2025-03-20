<?php
require_once 'config.php'; // Ensure this file correctly sets up $pdo

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Sanitize and validate inputs
        $firstName = htmlspecialchars(trim($_POST['first_name']));
        $lastName = htmlspecialchars(trim($_POST['last_name']));
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];

        // Validation
        $errors = [];
        if (empty($firstName)) $errors[] = "First name is required";
        if (empty($lastName)) $errors[] = "Last name is required";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
        if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
        if ($password !== $confirmPassword) $errors[] = "Passwords do not match";

        if (empty($errors)) {
            // Check if the email already exists
            $stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->rowCount() > 0) {
                $error = "Email already registered";
            } else {
                // Insert patient into the database with correct column names
                $stmt = $pdo->prepare("INSERT INTO patients 
                    (first_name, last_name, email, phone, password_hash)
                    VALUES (?, ?, ?, ?, ?)");

                // Store plain text password (not recommended)
                $stmt->execute([$firstName, $lastName, $email, $phone, $password]);

                $success = "Registration successful! Redirecting to login...";
                header("Refresh: 3; URL=index.php");
                exit;
            }
        } else {
            $error = implode("<br>", $errors);
        }
    } catch (PDOException $e) {
        error_log("Registration Error: " . $e->getMessage());
        $error = "A database error occurred. Please try again.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registration - MedBook</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #6366f1 0%, #3b82f6 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .registration-container {
            background: rgba(255, 255, 255, 0.98);
            padding: 2.5rem;
            border-radius: 1.5rem;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 500px;
            transform: translateY(20px);
            opacity: 0;
            animation: slideUp 0.6s cubic-bezier(0.23, 1, 0.32, 1) forwards;
        }

        @keyframes slideUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h1 {
            color: #1e293b;
            font-size: 2rem;
            margin-bottom: 0.75rem;
            font-weight: 700;
        }

        .login-header p {
            color: #64748b;
            font-size: 0.95rem;
        }

        .input-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .input-group input {
            width: 100%;
            padding: 1rem 3rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .input-group input:focus {
            outline: none;
            border-color: #818cf8;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.2);
        }

        .input-group i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .input-group input:focus + i {
            color: #6366f1;
        }

        .btn-login {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 0.75rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
        }

        .password-strength {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin: 0.5rem 0;
            overflow: hidden;
            position: relative;
        }

        .password-strength::after {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: var(--strength, 0%);
            background: #6366f1;
            transition: width 0.4s ease;
        }

        .password-criteria {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .alert {
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.4s ease;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }

        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #86efac;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .register-links {
            text-align: center;
            margin-top: 1.5rem;
            color: #64748b;
        }

        .register-links a {
            color: #6366f1;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .register-links a:hover {
            color: #4f46e5;
        }

        @media (max-width: 480px) {
            .registration-container {
                padding: 1.5rem;
                border-radius: 1rem;
            }
            
            .login-header h1 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <div class="login-header">
            <h1>Patient Registration</h1>
            <p>Create your medical account in seconds</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <div><?= $error ?></div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?= $success ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" name="first_name" placeholder="First Name" required
                       value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
            </div>

            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" name="last_name" placeholder="Last Name" required
                       value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
            </div>

            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="Email Address" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="input-group">
                <i class="fas fa-phone"></i>
                <input type="tel" name="phone" placeholder="Phone Number (optional)"
                       pattern="[0-9]{10}" title="10-digit phone number"
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>

            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" id="password" 
                       placeholder="Password" required
                       oninput="updatePasswordStrength()">
            </div>
            <div class="password-strength"></div>
            <div class="password-criteria">
                • At least 8 characters<br>
                • One uppercase letter<br>
                • One number<br>
                • One special character
            </div>

            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="confirm_password" 
                       placeholder="Confirm Password" required>
            </div>

            <button type="submit" class="btn-login">
                Create Account
            </button>
        </form>

        <div class="register-links">
            <p>Already have an account? <a href="index.php">Sign In</a></p>
            <p>Are you a doctor? <a href="register_doctor.php">Register here</a></p>
        </div>
    </div>

    <script>
        function updatePasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.querySelector('.password-strength');
            let strength = 0;

            // Strength criteria
            if (password.length >= 8) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            if (/[^A-Za-z0-9]/.test(password)) strength += 25;

            strengthBar.style.setProperty('--strength', `${strength}%`);
        }

        // Real-time password validation
        document.getElementById('password').addEventListener('input', function() {
            const criteria = {
                length: this.value.length >= 8,
                upper: /[A-Z]/.test(this.value),
                number: /[0-9]/.test(this.value),
                special: /[^A-Za-z0-9]/.test(this.value)
            };

            document.querySelectorAll('.password-criteria').forEach(item => {
                item.innerHTML = `
                    • ${criteria.length ? '✓' : '✕'} At least 8 characters<br>
                    • ${criteria.upper ? '✓' : '✕'} One uppercase letter<br>
                    • ${criteria.number ? '✓' : '✕'} One number<br>
                    • ${criteria.special ? '✓' : '✕'} One special character
                `;
            });
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password');
            const confirm = document.querySelector('input[name="confirm_password"]');
            
            if (password.value !== confirm.value) {
                e.preventDefault();
                confirm.setCustomValidity("Passwords do not match");
                confirm.reportValidity();
            }
        });
    </script>
</body>
</html>