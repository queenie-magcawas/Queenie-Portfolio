<?php
include 'db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $check = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($check);

    if ($result->num_rows > 0) {
        $error = "Email already exists";
    } else {

        $sql = "INSERT INTO users (fullname, email, password)
                VALUES ('$fullname', '$email', '$password')";

        if ($conn->query($sql)) {
            $_SESSION['success'] = "Registration successful! Please login.";
            header("Location: login.php");
            exit();
        } else {
            $error = "Registration failed: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NYC Crash AnalytiX | Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #0a0c15;
            color: #eef2ff;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .gradient-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            background: radial-gradient(circle at 20% 30%, rgba(2, 15, 35, 1) 0%, rgba(0, 5, 18, 1) 100%);
        }

        .gradient-bg::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                repeating-linear-gradient(45deg, rgba(0, 255, 255, 0.02) 0px, rgba(0, 255, 255, 0.02) 2px, transparent 2px, transparent 8px),
                repeating-linear-gradient(135deg, rgba(0, 100, 255, 0.03) 0px, rgba(0, 100, 255, 0.03) 1px, transparent 1px, transparent 12px);
            pointer-events: none;
        }

        .shape-glow {
            position: absolute;
            width: 450px;
            height: 450px;
            background: radial-gradient(circle, rgba(56,189,248,0.2), transparent 70%);
            border-radius: 50%;
            filter: blur(60px);
            bottom: -150px;
            right: -100px;
            z-index: -1;
            animation: pulseGlow 8s infinite alternate;
        }

        @keyframes pulseGlow {
            0% { opacity: 0.4; transform: scale(0.9);}
            100% { opacity: 0.8; transform: scale(1.2);}
        }

        .navbar {
            background: rgba(10, 14, 23, 0.75);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(0, 255, 255, 0.2);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.8rem;
            background: linear-gradient(135deg, #A5F0FF, #3B82F6);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent !important;
            letter-spacing: -0.5px;
        }

        .navbar-brand i {
            background: none;
            -webkit-background-clip: unset;
            color: #3b82f6;
            margin-right: 6px;
        }

        .btn-outline-cyber {
            border: 1px solid rgba(0, 255, 255, 0.6);
            background: transparent;
            color: #0ff;
            border-radius: 40px;
            padding: 8px 24px;
            font-weight: 600;
            transition: 0.25s ease;
        }

        .btn-outline-cyber:hover {
            background: rgba(0, 255, 255, 0.1);
            box-shadow: 0 0 12px rgba(0,255,255,0.4);
            border-color: #0ff;
            color: #0ff;
        }

        .glass-card {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(16px);
            border-radius: 2rem;
            border: 1px solid rgba(59,130,246,0.4);
            box-shadow: 0 25px 45px -12px rgba(0,0,0,0.5);
            transition: all 0.3s ease;
            width: 100%;
            max-width: 480px;
        }

        .glass-card:hover {
            border-color: rgba(0, 255, 255, 0.5);
            box-shadow: 0 0 25px rgba(0, 255, 255, 0.15);
        }

        .input-cyber {
            background: rgba(0, 10, 25, 0.6);
            border: 1px solid rgba(59,130,246,0.4);
            color: #eef2ff;
            border-radius: 1.2rem;
            padding: 12px 18px;
            font-size: 0.95rem;
            transition: all 0.2s;
        }

        .input-cyber:focus {
            background: rgba(0, 20, 45, 0.8);
            border-color: #0ff;
            box-shadow: 0 0 12px rgba(0, 255, 255, 0.3);
            color: white;
            outline: none;
        }

        .input-cyber::placeholder {
            color: #6c86a3;
        }

        .btn-primary-cyber {
            background: linear-gradient(95deg, #2563eb, #06b6d4);
            border: none;
            color: white;
            border-radius: 2rem;
            padding: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            transition: 0.25s;
        }

        .btn-primary-cyber:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37,99,235,0.5);
            background: linear-gradient(95deg, #3b82f6, #22d3ee);
        }

        .alert-cyber {
            background: rgba(255, 70, 85, 0.15);
            border-left: 4px solid #ff4d6d;
            border-radius: 1rem;
            backdrop-filter: blur(4px);
            color: #ffb3c6;
            font-weight: 500;
        }

        .login-link {
            color: #7dd3fc;
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
        }

        .login-link:hover {
            color: #0ff;
            text-shadow: 0 0 6px #0ff;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(0, 255, 255, 0.12);
            backdrop-filter: blur(4px);
            border-radius: 40px;
            padding: 6px 16px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid rgba(0,255,255,0.3);
            color: #7dd3fc;
        }

        .footer-note {
            background: rgba(2, 8, 20, 0.6);
            backdrop-filter: blur(4px);
            border-top: 1px solid rgba(0, 255, 255, 0.15);
        }

        .password-strength {
            font-size: 0.7rem;
            margin-top: 5px;
            margin-left: 10px;
        }

        @media (max-width: 520px) {
            .glass-card {
                margin: 0 1rem;
            }
        }
    </style>
</head>
<body>

<div class="gradient-bg"></div>
<div class="shape-glow"></div>

<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand" href="index.html">
            <i class="bi bi-ev-station-fill"></i> NYC Crash<span style="color:#38bdf8;">Analytics</span>
        </a>
        <div class="ms-auto">
            <a href="index.html" class="btn btn-outline-cyber"><i class="bi bi-house-door"></i> Home</a>
        </div>
    </div>
</nav>

<div class="container d-flex justify-content-center align-items-center" style="min-height: 100vh; padding-top: 80px;">
    <div class="glass-card p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="hero-badge mb-2">
                <i class="bi bi-person-plus-fill"></i> JOIN THE PLATFORM
            </div>
            <h2 class="fw-bold" style="background: linear-gradient(145deg, #ffffff, #60a5fa); -webkit-background-clip: text; background-clip: text; color: transparent;">
                Create account
            </h2>
            <p class="text-secondary-emphasis mt-2 small">Access NYC crash analytics & insights</p>
        </div>

        <?php if(isset($error)): ?>
            <div class="alert alert-cyber d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label text-light small fw-semibold"><i class="bi bi-person-badge"></i> Username</label>
                <input type="text" name="fullname" class="form-control input-cyber" placeholder="Enter username" required>
            </div>

            <div class="mb-3">
                <label class="form-label text-light small fw-semibold"><i class="bi bi-envelope"></i> Email Address</label>
                <input type="email" name="email" class="form-control input-cyber" placeholder="you@example.com" required>
            </div>

            <div class="mb-4">
                <label class="form-label text-light small fw-semibold"><i class="bi bi-key"></i> Password</label>
                <input type="password" name="password" id="password" class="form-control input-cyber" placeholder="Create a strong password" required>
                <div class="password-strength text-muted" id="passwordHelp">
                    <i class="bi bi-info-circle"></i> Min 6 characters
                </div>
            </div>

            <button type="submit" class="btn btn-primary-cyber w-100 mb-3">
                <i class="bi bi-person-plus-fill"></i> Register & Continue
            </button>

            <div class="text-center mt-3">
                <span class="text-muted">Already have an account?</span> 
                <a href="login.php" class="login-link ms-1">Sign in →</a>
            </div>
            <div class="text-center mt-4">
                <small class="text-muted">
                    <i class="bi bi-shield-check"></i> Your data is secure · SSL Encrypted
                </small>
            </div>
        </form>
    </div>
</div>

<footer class="footer-note text-center py-3 mt-4 position-relative">
    <div class="container">
        <span class="text-secondary small">🔐 Join NYC Crash Analytics · Data-Driven Safety Platform</span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const passwordInput = document.getElementById('password');
    const passwordHelp = document.getElementById('passwordHelp');
    
    if(passwordInput) {
        passwordInput.addEventListener('input', function() {
            const val = this.value;
            if(val.length === 0) {
                passwordHelp.innerHTML = '<i class="bi bi-info-circle"></i> Min 6 characters';
                passwordHelp.className = 'password-strength text-muted';
            } else if(val.length < 6) {
                passwordHelp.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Weak - at least 6 characters';
                passwordHelp.className = 'password-strength text-warning';
            } else if(val.length < 10) {
                passwordHelp.innerHTML = '<i class="bi bi-shield"></i> Medium strength';
                passwordHelp.className = 'password-strength text-info';
            } else {
                passwordHelp.innerHTML = '<i class="bi bi-shield-check"></i> Strong password';
                passwordHelp.className = 'password-strength text-success';
            }
        });
    }
    
    console.log("%c NYC Crash AnalytiX | Registration Portal", "color: #0ff; font-size: 14px; font-weight: bold;");
</script>
</body>
</html>