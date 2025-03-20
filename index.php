<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedBook - Patient & Doctor Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #6366f1, #3b82f6);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 400px;
            transform: translateY(20px);
            opacity: 0;
            animation: slideUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
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
            color: #1f2937;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: #6b7280;
        }

        .input-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .input-group input {
            width: 100%;
            padding: 0.75rem 2.5rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .input-group input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .input-group i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            transition: color 0.3s ease;
        }

        .input-group input:focus + i {
            color: #6366f1;
        }

        .btn-login {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            width: 100%;
            padding: 0.75rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3);
        }

        .register-links {
            margin-top: 1.5rem;
            text-align: center;
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

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
            animation: shake 0.4s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        @media (max-width: 480px) {
            .login-container {
                width: 90%;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Welcome to MedBook</h1>
            <p>Please sign in to continue</p>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert">
                <i class="fas fa-exclamation-circle"></i> Invalid email or password
            </div>
        <?php endif; ?>

        <form action="authenticate.php" method="POST">
            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="Enter your email" required>
            </div>
            
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn-login">Sign In</button>
        </form>

        <div class="register-links">
            <p>Don't have an account? <br>
                <a href="register_patient.php"><i class="fas fa-user-plus"></i> Register as Patient</a> or 
                <a href="register_doctor.php"><i class="fas fa-stethoscope"></i> Register as Doctor</a>
            </p>
        </div>
    </div>

    <script>
        // Add ripple effect to login button
        document.querySelector('.btn-login').addEventListener('click', function(e) {
            let ripple = document.createElement('div');
            ripple.style.position = 'absolute';
            ripple.style.width = '20px';
            ripple.style.height = '20px';
            ripple.style.background = 'rgba(255, 255, 255, 0.4)';
            ripple.style.borderRadius = '50%';
            ripple.style.transform = 'translate(-50%, -50%)';
            ripple.style.pointerEvents = 'none';
            ripple.style.animation = 'ripple 0.6s linear';
            
            this.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        });

        // Add input validation
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', () => {
                input.style.borderColor = input.checkValidity() ? '#6366f1' : '#ef4444';
            });
        });
    </script>
</body>
</html>