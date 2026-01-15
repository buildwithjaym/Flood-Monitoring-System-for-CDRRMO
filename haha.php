<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "finalstep_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if ($username === "" || $password === "") {
        $error = "Please enter both username and password.";
    } else {
        $sql = "SELECT user_id, username, password FROM admin_users WHERE username = ? LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($dbId, $dbUsername, $dbPassword);
            $stmt->fetch();

            if ($password === $dbPassword) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username']  = $dbUsername;
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "Account not found.";
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
<title>FinalStep | Admin Login</title>

<style>
    * { box-sizing: border-box; }

    body, html {
        margin: 0;
        height: 100%;
        font-family: "Segoe UI", sans-serif;
        display: flex;
        justify-content: center;
        align-items: center;
        background: linear-gradient(145deg, #003A70, #0072BC, #00A99D);
        color: #ffffff;
        background-size: 200%;
        animation: bgFlow 10s infinite alternate ease-in-out;
    }

    @keyframes bgFlow {
        from { background-position: left; }
        to { background-position: right; }
    }

    .login-wrapper {
        width: 100%;
        max-width: 420px;
        padding: 20px;
        animation: fadeIn 0.9s ease forwards;
    }

    .login-card {
        background: rgba(255, 255, 255, 0.12);
        backdrop-filter: blur(16px);
        border-radius: 28px;
        padding: 40px 30px;
        border: 1px solid rgba(255,255,255,0.3);
        box-shadow: 0 18px 50px rgba(0,0,0,0.55);
    }

    .login-logo {
        display: block;
        margin: 0 auto 16px;
        max-width: 115px;
        border-radius: 50%;
        background: #ffffff;
        padding: 6px;
        box-shadow: 0 0 0 4px rgba(255,255,255,0.6),
                    0 10px 25px rgba(0,0,0,0.4);
    }

    h1 {
        margin: 6px 0 4px;
        text-align: center;
        font-size: 24px;
        text-transform: uppercase;
        font-weight: 800;
        letter-spacing: 0.1em;
    }

    .subtitle {
        text-align: center;
        font-size: 13px;
        color: #d8faff;
        margin-bottom: 20px;
    }

    .badge {
        display: inline-flex;
        padding: 5px 12px;
        border-radius: 999px;
        background: rgba(0,0,0,0.3);
        border: 1px solid rgba(255,255,255,0.4);
        font-size: 11px;
        margin: 0 auto;
        text-transform: uppercase;
        gap: 6px;
        color: #FFD700;
        align-items: center;
        display: flex;
        justify-content: center;
    }

    .badge-dot {
        width: 8px;
        height: 8px;
        background: #4fffdf;
        border-radius: 50%;
        box-shadow: 0 0 6px #4fffdf;
    }

    .error {
        background: rgba(255, 60, 60, 0.25);
        border: 1px solid rgba(255, 120, 120, 0.7);
        padding: 10px 12px;
        border-radius: 10px;
        font-size: 13px;
        margin-bottom: 18px;
        color: #ffecec;
    }

    .input-group {
        position: relative;
        margin-bottom: 22px;
    }

    .input-group input {
        width: 100%;
        padding: 16px 14px;
        border-radius: 14px;
        background: rgba(0,0,0,0.25);
        border: 1px solid rgba(255,255,255,0.6);
        color: white;
        outline: none;
        font-size: 14px;
        transition: all 0.25s ease;
    }

    .input-group input:focus {
        border-color: #FFD700;
        box-shadow: 0 0 0 2px rgba(255,215,0,0.8);
    }

    .input-group label {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
        color: #d9faff;
        font-size: 13px;
        transition: all 0.25s ease;
    }

    .input-group input:focus + label,
    .input-group input:not(:placeholder-shown) + label {
        top: -8px;
        background: #003A70;
        padding: 0 6px;
        border-radius: 999px;
        font-size: 11px;
        color: #FFD700;
    }

    button {
        width: 100%;
        padding: 14px;
        margin-top: 6px;
        border-radius: 999px;
        border: none;
        font-size: 15px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: white;
        cursor: pointer;
        background: linear-gradient(135deg, #00C29A, #0072BC);
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.55);
        transition: all 0.25s ease;
    }

    button:hover {
        transform: translateY(-1px) scale(1.02);
        box-shadow: 0 18px 45px rgba(0,0,0,0.65);
    }

    .footer-text {
        margin-top: 16px;
        text-align: center;
        font-size: 11px;
        color: #ccf7ff;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: translateY(0); }
    }
</style>

</head>
<body>

<div class="login-wrapper">
    <div class="login-card">

        <img src="assets/cictt_logo.jpg" class="login-logo">

        <div class="badge">
            <span class="badge-dot"></span>
            FinalStep e-Clearance
        </div>

        <h1>Admin Login</h1>
        <p class="subtitle">Access the academic clearance management system</p>

        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">

            <div class="input-group">
                <input type="text" name="username" placeholder=" " required>
                <label>Username</label>
            </div>

            <div class="input-group">
                <input type="password" name="password" placeholder=" " required>
                <label>Password</label>
            </div>

            <button type="submit" name="login">Sign In</button>

        </form>

        <div class="footer-text">
            FinalStep Student Clearance System © <?= date('Y'); ?>
        </div>
    </div>
</div>

</body>
</html>
