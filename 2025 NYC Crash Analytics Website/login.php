<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {

        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {

            $_SESSION['fullname'] = $user['fullname'];
            header("Location: dashboard.php");
            exit();

        } else {
            $_SESSION['error'] = "Incorrect password";
            header("Location: login.php");
            exit();
        }

    } else {
        $_SESSION['error'] = "User not found";
        header("Location: login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NYC Crash Analytics | Secure Login</title>
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

        /* animated gradient background (same as index) */
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

        /* Glassmorphic navbar */
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

        /* login card - glass morphism */
        .glass-card {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(16px);
            border-radius: 2rem;
            border: 1px solid rgba(59,130,246,0.4);
            box-shadow: 0 25px 45px -12px rgba(0,0,0,0.5);
            transition: all 0.3s ease;
            width: 100%;
            max-width: 460px;
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

        .register-link {
            color: #7dd3fc;
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
        }

        .register-link:hover {
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

<!-- Glass Navbar -->
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand" href="index.html">
            <i class="bi bi-ev-station-fill"></i> NYC Crash<span style="color:#38bdf8;" >Analytics</span>
        </a>
        <div class="ms-auto">
            <a href="index.html" class="btn btn-outline-cyber"><i class="bi bi-house-door"></i> Home</a>
        </div>
    </div>
</nav>

<!-- Login Container -->
<div class="container d-flex justify-content-center align-items-center" style="min-height: 100vh; padding-top: 80px;">
    <div class="glass-card p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="hero-badge mb-2">
                <i class="bi bi-shield-lock-fill"></i> SECURE ACCESS
            </div>
            <h2 class="fw-bold" style="background: linear-gradient(145deg, #ffffff, #60a5fa); -webkit-background-clip: text; background-clip: text; color: transparent;">
                Welcome back
            </h2>
            <p class="text-secondary-emphasis mt-2 small">Sign in to your crash analytics dashboard</p>
        </div>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-cyber d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <div><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label text-light small fw-semibold"><i class="bi bi-envelope"></i> Email address</label>
                <input type="email" name="email" class="form-control input-cyber" placeholder="analyst@nycdata.com" required>
            </div>

            <div class="mb-4">
                <label class="form-label text-light small fw-semibold"><i class="bi bi-key"></i> Password</label>
                <input type="password" name="password" class="form-control input-cyber" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary-cyber w-100 mb-3">
                <i class="bi bi-box-arrow-in-right"></i> Login to Dashboard
            </button>

            <div class="text-center mt-3">
                <span class="text-muted">No account?</span> 
                <a href="register.php" class="register-link ms-1">Create account →</a>
            </div>
            <div class="text-center mt-4">
                <small class="text-muted">
                    <i class="bi bi-database"></i> NYC Crash Data Analytics · v3.0
                </small>
            </div>
        </form>
    </div>
</div>

<!-- footer note -->
<footer class="footer-note text-center py-3 mt-4 position-relative">
    <div class="container">
        <span class="text-secondary small">🔐 Secure session with PHP · <span style="color:#2dd4bf;">Queenie Marie O. Magcawas</span> · Crash Intelligence Platform</span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // tech console greeting
    console.log("%c NYC Crash AnalytiX | Secure Login Interface | Queenie Marie O. Magcawas", "color: #0ff; font-size: 14px; font-weight: bold;");
</script>
</body>
</html>