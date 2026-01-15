<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin_dashboard.php");
    exit;
}

// Database connection
$conn = new mysqli("localhost", "root", "", "babala_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";

if (isset($_POST['login'])) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if ($username === "" || $password === "") {
        $error = "Please enter both username and password.";
    } else {
        // Make sure you really have a table `users` with columns `id`, `username`, `password`
        $sql = "SELECT user_id, username, password FROM users WHERE username = ? LIMIT 1";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            // While developing, show the real DB error so you know what's wrong
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("s", $username);
        $stmt->execute();

        // Compatible way without get_result()
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($dbId, $dbUsername, $dbPassword);
            $stmt->fetch();

          
            if ($password === $dbPassword) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id']        = $dbId;
                $_SESSION['admin_username']  = $dbUsername;

                header("Location: admin_dashboard.php");
                exit;
            } else {
                $error = "Incorrect password.";
            }

          
        } else {
            $error = "Username not found.";
        }

        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CDRRMO | BabalaBaha Admin Login</title>
<style>
    * {
        box-sizing: border-box;
    }

    body, html {
        margin: 0;
        padding: 0;
        height: 100%;
        width: 100%;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        display: flex;
        justify-content: center;
        align-items: center;
        background: radial-gradient(circle at top left, #ff4b2b 0%, #c2001a 35%, #840000 70%, #4a0000 100%);
        color: #ffffff;
    }

    .login-wrapper {
        width: 100%;
        max-width: 420px;
        padding: 20px;
        position: relative;
        z-index: 1;
        animation: fadeIn 0.8s ease forwards;
    }

    .login-card {
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(18px);
        border-radius: 28px;
        padding: 40px 30px 32px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
        border: 1px solid rgba(255, 255, 255, 0.35);
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .login-card::before {
        content: "";
        position: absolute;
        inset: -40%;
        background:
            radial-gradient(circle at top, rgba(255, 255, 255, 0.16), transparent 60%),
            radial-gradient(circle at bottom, rgba(255, 215, 0, 0.18), transparent 60%);
        opacity: 0.85;
        pointer-events: none;
    }

    .login-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 26px 70px rgba(0, 0, 0, 0.7);
        transition: all 0.25s ease;
    }

    .login-logo {
        display: block;
        margin: 0 auto 16px;
        max-width: 110px;
        height: auto;
        border-radius: 50%;
        background: #ffffff;
        padding: 6px;
        box-shadow: 0 0 0 4px rgba(255,255,255,0.6),
                    0 10px 25px rgba(0,0,0,0.5);
        position: relative;
        z-index: 1;
    }

    h1 {
        color: #ffffff;
        font-size: 24px;
        margin-bottom: 4px;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        position: relative;
        z-index: 1;
    }

    .subtitle {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.8);
        margin-bottom: 26px;
        position: relative;
        z-index: 1;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(0, 0, 0, 0.35);
        color: #ffeaa7;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        position: relative;
        z-index: 1;
        border: 1px solid rgba(255, 255, 255, 0.4);
    }

    .badge-dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: #22c55e;
        box-shadow: 0 0 10px #22c55e;
    }

    .error {
        color: #ffe4e6;
        background: rgba(220, 38, 38, 0.28);
        border: 1px solid rgba(248, 113, 113, 0.75);
        text-align: left;
        margin-bottom: 18px;
        font-size: 13px;
        padding: 10px 12px;
        border-radius: 10px;
        position: relative;
        z-index: 1;
    }

    form {
        margin-top: 10px;
        text-align: left;
        position: relative;
        z-index: 1;
    }

    .input-group {
        position: relative;
        margin-bottom: 22px;
        width: 100%;
    }

    .input-group input {
        width: 100%;
        padding: 16px 14px 16px 14px;
        border-radius: 14px;
        border: 1px solid rgba(255, 255, 255, 0.6);
        background: rgba(0, 0, 0, 0.35);
        color: #f9fafb;
        outline: none;
        font-size: 14px;
        transition: all 0.22s ease;
    }

    .input-group input::placeholder {
        color: transparent;
    }

    .input-group input:focus {
        border-color: #ffe066;
        box-shadow: 0 0 0 1px rgba(255, 224, 102, 0.9);
        background: rgba(0, 0, 0, 0.55);
    }

    .input-group label {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(255, 255, 255, 0.85);
        pointer-events: none;
        transition: all 0.22s ease;
        font-size: 13px;
        background: transparent;
        padding: 0 4px;
    }

    .input-group input:focus + label,
    .input-group input:not(:placeholder-shown) + label {
        top: -8px;
        transform: translateY(0);
        font-size: 11px;
        color: #ffe066;
        background: #840000;
        border-radius: 999px;
        padding: 0 6px;
    }

    .helper-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 12px;
        margin-top: -6px;
        margin-bottom: 14px;
        color: rgba(255, 255, 255, 0.8);
    }

    .helper-row span {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .status-dot {
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: #22c55e;
        box-shadow: 0 0 6px #22c55e;
    }

    button {
        width: 100%;
        padding: 14px 16px;
        border: none;
        border-radius: 999px;
        background: linear-gradient(135deg, #ff9f1c, #ff3b3f);
        color: #ffffff;
        font-size: 15px;
        cursor: pointer;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        transition: all 0.22s ease;
        box-shadow: 0 12px 32px rgba(92, 0, 0, 0.9);
    }

    button:hover {
        transform: translateY(-1px) scale(1.01);
        box-shadow: 0 18px 40px rgba(92, 0, 0, 0.95);
        background: linear-gradient(135deg, #ffb347, #ff1f3d);
    }

    button:active {
        transform: translateY(0);
        box-shadow: 0 8px 20px rgba(92, 0, 0, 0.9);
    }

    .footer-text {
        margin-top: 18px;
        font-size: 11px;
        color: rgba(255, 255, 255, 0.8);
        text-align: center;
        position: relative;
        z-index: 1;
    }

    @media (max-width: 480px) {
        .login-card {
            padding: 32px 22px 26px;
            border-radius: 22px;
        }
        h1 {
            font-size: 20px;
        }
        .subtitle {
            font-size: 12px;
        }
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(18px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <img src="assets/cddrmo_logo.jpg" class="login-logo" alt="CDRRMO LOGO">

        <div class="badge">
            <span class="badge-dot"></span>
            BabalaBaha • CDRRMO Portal
        </div>

        <h1>Admin Login</h1>
        <p class="subtitle">Secure access to flood risk monitoring and alerts.</p>

        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" autocomplete="off">
            <div class="input-group">
                <input type="text" name="username" id="username" placeholder=" " required autocomplete="off">
                <label for="username">Username</label>
            </div>

            <div class="input-group">
                <input type="password" name="password" id="password" placeholder=" " required autocomplete="off">
                <label for="password">Password</label>
            </div>

            <div class="helper-row">
                <span><span class="status-dot"></span> System Online</span>
                <span>CDRRMO • Internal Use Only</span>
            </div>

            <button type="submit" name="login">Sign In</button>
        </form>

        <div class="footer-text">
            BabalaBaha Flood Early Warning Prototype • <?php echo date('Y'); ?>
        </div>
    </div>
</div>
</body>
</html>
