<?php
session_start();

// Require login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// DB connection
$conn = new mysqli("localhost", "root", "", "babala_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ----------------- FETCH INITIAL DATA (for first render only) -------------------

// Latest reading
$current = null;
$sqlCurrent = "SELECT id, sensor_value, status, created_at 
               FROM water_readings 
               ORDER BY id DESC 
               LIMIT 1";
$resCurrent = $conn->query($sqlCurrent);
if ($resCurrent && $resCurrent->num_rows > 0) {
    $current = $resCurrent->fetch_assoc();
}

// Recent history (static until refresh)
$history = [];
$sqlHistory = "SELECT sensor_value, status, created_at 
               FROM water_readings 
               ORDER BY id DESC 
               LIMIT 10";
$resHistory = $conn->query($sqlHistory);
if ($resHistory && $resHistory->num_rows > 0) {
    while ($row = $resHistory->fetch_assoc()) {
        $history[] = $row;
    }
}

// Simple report stats (all-time counts)
$normalCount   = 0;
$alertCount    = 0;
$criticalCount = 0;

$sqlStats = "SELECT status, COUNT(*) AS c FROM water_readings GROUP BY status";
$resStats = $conn->query($sqlStats);
if ($resStats && $resStats->num_rows > 0) {
    while ($row = $resStats->fetch_assoc()) {
        $s = strtoupper($row['status']);
        if ($s === 'NORMAL') {
            $normalCount = (int)$row['c'];
        } elseif ($s === 'ALERT') {
            $alertCount = (int)$row['c'];
        } elseif ($s === 'CRITICAL') {
            $criticalCount = (int)$row['c'];
        }
    }
}

$conn->close();

// ----------------- HELPERS -------------------

function statusBadgeClass($status) {
    $status = strtoupper($status);
    if ($status === 'CRITICAL') return 'badge-critical';
    if ($status === 'ALERT')    return 'badge-alert';
    if ($status === 'NORMAL')   return 'badge-normal';
    return 'badge-unknown';
}

function ledState($status) {
    $s = strtoupper($status);
    $states = [
        'green'  => false,
        'yellow' => false,
        'red'    => false,
        'buzzer' => false
    ];

    if ($s === 'NORMAL') {
        $states['green'] = true;
    } elseif ($s === 'ALERT') {
        $states['yellow'] = true;
    } elseif ($s === 'CRITICAL') {
        $states['red']    = true;
        $states['buzzer'] = true;
    }

    return $states;
}

// Decide current status text
if ($current) {
    $currentStatus = strtoupper($current['status']);
    $sensorValue   = (int)$current['sensor_value'];
    $lastUpdated   = $current['created_at'];
} else {
    $currentStatus = 'NO DATA';
    $sensorValue   = 0;
    $lastUpdated   = 'No data received yet';
}

// Map sensor value (100–700) into 0–100% for water animation
$waterPercent = 0;
if ($current) {
    $minLevel = 100;
    $maxLevel = 600;
    $raw = $sensorValue;

    if ($raw < $minLevel) $raw = $minLevel;
    if ($raw > $maxLevel) $raw = $maxLevel;

    $waterPercent = (int)round((($raw - $minLevel) / ($maxLevel - $minLevel)) * 100);
    if ($waterPercent < 0)   $waterPercent = 0;
    if ($waterPercent > 100) $waterPercent = 100;
}

// LED / buzzer virtual states for first render
$leds = ledState($currentStatus);

// Username
$adminUsername = 'admin';
if (isset($_SESSION['admin_username']) && $_SESSION['admin_username'] !== '') {
    $adminUsername = $_SESSION['admin_username'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CDRRMO | BabalaBaha Dashboard</title>
<style>
    * { box-sizing: border-box; }

    body, html {
        margin: 0;
        padding: 0;
        height: 100%;
        width: 100%;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        background: radial-gradient(circle at top left, #ff4b2b 0%, #c2001a 35%, #840000 70%, #4a0000 100%);
        color: #ffffff;
    }

    .layout {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    /* Top bar */
    .topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 24px;
        background: rgba(0, 0, 0, 0.33);
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        backdrop-filter: blur(16px);
    }

    .topbar-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .topbar-logo {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #fff;
        padding: 4px;
        box-shadow: 0 0 0 2px rgba(255,255,255,0.8),
                    0 8px 18px rgba(0,0,0,0.6);
    }

    .topbar-title {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .topbar-title h1 {
        font-size: 18px;
        margin: 0;
        letter-spacing: 0.16em;
        text-transform: uppercase;
    }

    .topbar-title span {
        font-size: 11px;
        opacity: 0.9;
    }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 12px;
    }

    .topbar-user-text {
        text-align: right;
        line-height: 1.3;
        opacity: 0.9;
    }

    .logout-btn {
        padding: 7px 14px;
        border-radius: 999px;
        border: 1px solid rgba(255,255,255,0.8);
        background: rgba(0, 0, 0, 0.35);
        color: #ffffff;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .logout-btn:hover {
        background: #ffffff;
        color: #840000;
        box-shadow: 0 0 0 1px rgba(255,255,255,0.85),
                    0 8px 22px rgba(0,0,0,0.7);
    }

    /* Content layout */
    .content {
        flex: 1;
        padding: 20px;
        max-width: 1150px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .card {
        background: rgba(255, 255, 255, 0.08);
        border-radius: 20px;
        padding: 18px 18px 16px;
        border: 1px solid rgba(255, 255, 255, 0.22);
        backdrop-filter: blur(18px);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.55);
    }

    .hero-card {
        display: grid;
        grid-template-columns: minmax(0, 2.2fr) minmax(0, 1.2fr);
        gap: 18px;
        align-items: center;
    }

    .hero-text-title {
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.18em;
        opacity: 0.95;
        margin-bottom: 4px;
    }

    .hero-status {
        font-size: 32px;
        font-weight: 800;
        margin-bottom: 4px;
    }

    .hero-status-desc {
        font-size: 13px;
        opacity: 0.9;
    }

    .hero-meta {
        font-size: 11px;
        margin-top: 8px;
        opacity: 0.9;
    }

    .hero-status-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 6px;
    }

    .status-badge {
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        white-space: nowrap;
    }

    .badge-normal {
        background: rgba(22, 163, 74, 0.2);
        border: 1px solid #22c55e;
        color: #bbf7d0;
    }

    .badge-alert {
        background: rgba(234, 179, 8, 0.2);
        border: 1px solid #eab308;
        color: #fef9c3;
    }

    .badge-critical {
        background: rgba(220, 38, 38, 0.25);
        border: 1px solid #f97373;
        color: #fee2e2;
    }

    .badge-unknown {
        background: rgba(148, 163, 184, 0.3);
        border: 1px solid rgba(148, 163, 184, 0.85);
        color: #e5e7eb;
    }

    .hero-reading {
        font-size: 36px;
        font-weight: 800;
        line-height: 1;
        margin-top: 10px;
    }

    .hero-reading-label {
        font-size: 12px;
        opacity: 0.9;
    }

    /* ===== CRITICAL FULL-SCREEN ALERT ===== */
    .critical-overlay {
        position: fixed;
        inset: 0;
        background: radial-gradient(circle at top, rgba(248, 113, 113, 0.3), transparent 60%),
                    rgba(15, 23, 42, 0.96);
        display: none; /* JS will set to flex when needed */
        align-items: center;
        justify-content: center;
        z-index: 9999;
        pointer-events: auto;
    }

    .critical-box {
        position: relative;
        max-width: 520px;
        width: 90%;
        background: rgba(127, 29, 29, 0.9);
        border-radius: 24px;
        padding: 24px 20px 20px;
        border: 2px solid rgba(254, 226, 226, 0.95);
        box-shadow: 0 0 40px rgba(248, 113, 113, 0.95);
        text-align: center;
        animation: pulseAlert 1.5s ease-in-out infinite;
    }

    .critical-title {
        font-size: 22px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.22em;
        margin-bottom: 10px;
    }

    .critical-subtitle {
        font-size: 14px;
        margin-bottom: 14px;
        opacity: 0.95;
    }

    .critical-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 999px;
        background: rgba(15, 23, 42, 0.85);
        border: 1px solid rgba(254, 226, 226, 0.9);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.16em;
        margin-bottom: 14px;
    }

    .critical-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: #fee2e2;
        box-shadow: 0 0 10px #fee2e2;
    }

    .critical-reading {
        font-size: 30px;
        font-weight: 800;
        margin-bottom: 4px;
    }

    .critical-reading-label {
        font-size: 12px;
        opacity: 0.9;
        margin-bottom: 10px;
    }

    .critical-footer {
        font-size: 11px;
        opacity: 0.9;
    }

    .critical-sound-btn {
        position: absolute;
        top: 14px;
        right: 18px;
        width: 34px;
        height: 34px;
        border-radius: 999px;
        border: 2px solid rgba(254, 226, 226, 0.9);
        background: rgba(15, 23, 42, 0.9);
        color: #fee2e2;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        cursor: pointer;
        box-shadow: 0 0 12px rgba(248, 113, 113, 0.9);
        transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }

    .critical-sound-btn:hover {
        transform: scale(1.05);
        background: rgba(185, 28, 28, 0.95);
        box-shadow: 0 0 18px rgba(248, 113, 113, 1);
    }

    @keyframes pulseAlert {
        0%   { transform: scale(1);   box-shadow: 0 0 30px rgba(248, 113, 113, 0.7); }
        50%  { transform: scale(1.03); box-shadow: 0 0 55px rgba(248, 113, 113, 1); }
        100% { transform: scale(1);   box-shadow: 0 0 30px rgba(248, 113, 113, 0.7); }
    }

    /* Water tank animation */
    .water-card {
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .water-tank {
        position: relative;
        width: 170px;
        height: 170px;
        border-radius: 50%;
        border: 3px solid rgba(255,255,255,0.9);
        overflow: hidden;
        background: radial-gradient(circle at 30% 20%, #ffffff33 0, #020617aa 60%, #020617ff 100%);
        box-shadow: 0 10px 30px rgba(0,0,0,0.7);
    }

    .water-inner {
        position: absolute;
        inset: 10px;
        border-radius: 50%;
        overflow: hidden;
        background: #020617;
    }

    .water-fill {
        position: absolute;
        left: 0;
        bottom: 0;
        width: 100%;
        background: linear-gradient(180deg, #38bdf8, #0ea5e9);
        transform-origin: bottom;
        overflow: hidden;
    }

    .water-wave {
        position: absolute;
        top: -12px;
        left: -50%;
        width: 200%;
        height: 30px;
        background: rgba(255,255,255,0.35);
        opacity: 0.8;
        border-radius: 50%;
        animation: waveMove 3s linear infinite;
    }

    .water-percent-text {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        text-shadow: 0 2px 6px rgba(0,0,0,0.9);
    }

    .water-percent-main {
        font-size: 26px;
    }

    .water-percent-sub {
        font-size: 11px;
        opacity: 0.9;
    }

    @keyframes waveMove {
        0%   { transform: translateX(0); }
        100% { transform: translateX(50%); }
    }

    /* Grid below hero */
    .grid {
        display: grid;
        grid-template-columns: minmax(0, 1.5fr) minmax(0, 1.3fr);
        gap: 18px;
        align-items: stretch;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }

    .card-title {
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.16em;
        opacity: 0.95;
    }

    .card-sub {
        font-size: 11px;
        opacity: 0.85;
        margin-top: 3px;
    }

    /* LED panel */
    .led-panel {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 6px;
    }

    .led-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .led-label {
        font-size: 12px;
    }

    .led-dot {
        width: 16px;
        height: 16px;
        border-radius: 999px;
        border: 2px solid rgba(255, 255, 255, 0.35);
        background: rgba(0, 0, 0, 0.4);
        box-shadow: none;
    }

    .led-on-green {
        background: #22c55e;
        box-shadow: 0 0 10px #22c55e;
        border-color: #4ade80;
    }

    .led-on-yellow {
        background: #eab308;
        box-shadow: 0 0 10px #eab308;
        border-color: #facc15;
    }

    .led-on-red {
        background: #ef4444;
        box-shadow: 0 0 10px #ef4444;
        border-color: #f97373;
    }

    .buzzer-chip {
        font-size: 11px;
        padding: 4px 9px;
        border-radius: 999px;
        border: 1px solid rgba(148, 163, 184, 0.7);
        background: rgba(15, 23, 42, 0.4);
    }

    .buzzer-on {
        border-color: #f97373;
        background: rgba(220, 38, 38, 0.3);
        color: #fee2e2;
        box-shadow: 0 0 10px rgba(248, 113, 113, 0.7);
    }

    /* Reports */
    .report-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-top: 10px;
    }

    .report-item {
        background: rgba(0,0,0,0.35);
        border-radius: 14px;
        padding: 10px;
        border: 1px solid rgba(255,255,255,0.12);
        font-size: 11px;
    }

    .report-label {
        text-transform: uppercase;
        letter-spacing: 0.12em;
        opacity: 0.85;
        margin-bottom: 4px;
    }

    .report-value {
        font-size: 16px;
        font-weight: 700;
    }

    .report-tag {
        font-size: 10px;
        opacity: 0.85;
    }

    /* History table */
    .history-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        margin-top: 6px;
    }

    .history-table th,
    .history-table td {
        padding: 6px 8px;
        text-align: left;
    }

    .history-table th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        opacity: 0.9;
        border-bottom: 1px solid rgba(248, 250, 252, 0.22);
    }

    .history-table tr:nth-child(even) {
        background: rgba(15, 23, 42, 0.25);
    }

    .download-btn {
        display: inline-block;
        padding: 7px 14px;
        border-radius: 999px;
        border: 1px solid rgba(255,255,255,0.85);
        background: rgba(0, 0, 0, 0.35);
        color: #ffffff;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s ease;
        margin-top: 6px;
    }

    .download-btn:hover {
        background: #ffffff;
        color: #840000;
        box-shadow: 0 0 0 1px rgba(255,255,255,0.9),
                    0 8px 22px rgba(0,0,0,0.7);
    }

    .pill {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 11px;
    }

    .pill-normal   { background: rgba(22,163,74,0.22); }
    .pill-alert    { background: rgba(234,179,8,0.22); }
    .pill-critical { background: rgba(220,38,38,0.26); }

    .footer {
        padding: 8px 24px 14px;
        font-size: 11px;
        opacity: 0.85;
        text-align: center;
    }

    /* =================== RESPONSIVE =================== */

    @media (max-width: 1200px) {
        .content {
            max-width: 100%;
            padding: 18px;
        }
    }

    @media (max-width: 950px) {
        .hero-card {
            grid-template-columns: minmax(0, 1fr);
        }

        .water-card {
            justify-content: flex-start;
        }

        .hero-status {
            font-size: 28px;
        }

        .hero-reading {
            font-size: 30px;
        }
    }

    @media (max-width: 900px) {
        .grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .content {
            padding: 14px;
        }

        .topbar {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }

        .topbar-right {
            align-self: stretch;
            justify-content: space-between;
        }

        .topbar-user-text {
            text-align: left;
        }
    }

    @media (max-width: 600px) {
        .topbar {
            padding: 10px 12px;
        }

        .topbar-left {
            width: 100%;
        }

        .topbar-title h1 {
            font-size: 16px;
        }

        .card {
            padding: 14px 12px 12px;
        }

        .hero-status {
            font-size: 24px;
        }

        .hero-reading {
            font-size: 26px;
        }

        .water-tank {
            width: 140px;
            height: 140px;
        }

        .water-inner {
            inset: 8px;
        }

        .report-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .card-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .download-btn {
            margin-top: 10px;
        }
    }
</style>
</head>
<body>

<!-- Audio for critical alarm -->
<audio id="criticalSound" src="assets/warning.mp3" preload="auto" loop></audio>

<div class="layout">

    <header class="topbar">
        <div class="topbar-left">
            <img src="assets/cddrmo_logo.jpg" class="topbar-logo" alt="CDRRMO Logo">
            <div class="topbar-title">
                <h1>BabalaBaha</h1>
                <span>Isabela City Disaster Risk Reduction and Management Office</span>
            </div>
        </div>
        <div class="topbar-right">
            <div class="topbar-user-text">
                Logged in as<br>
                <strong><?php echo htmlspecialchars($adminUsername); ?></strong>
            </div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <main class="content">
        <!-- HERO: Flood status front and center -->
        <section class="card hero-card">
            <div>
                <div class="hero-text-title">Current Flood Status</div>
                <div class="hero-status-row">
                    <div class="hero-status" id="currentStatusText">
                        <?php echo htmlspecialchars($currentStatus); ?>
                    </div>
                    <div class="status-badge <?php echo statusBadgeClass($currentStatus); ?>" id="statusBadge">
                        <?php echo htmlspecialchars($currentStatus); ?>
                    </div>
                </div>
                <div class="hero-status-desc">
                    Live status from BabalaBaha water level sensor deployed at the monitoring point.
                </div>

                <div class="hero-reading" id="sensorValueText">
                    <?php echo $current ? $sensorValue : '--'; ?>
                </div>
                <div class="hero-reading-label">Analog sensor reading (0–1023)</div>

                <div class="hero-meta">
                    Last update:
                    <span id="lastUpdateText"><?php echo htmlspecialchars($lastUpdated); ?></span>
                </div>
            </div>

            <!-- Water visualization -->
            <div class="water-card">
                <div class="water-tank">
                    <div class="water-inner">
                        <div class="water-fill" id="waterFill" style="height: <?php echo $waterPercent; ?>%;">
                            <div class="water-wave"></div>
                        </div>
                        <div class="water-percent-text">
                            <div class="water-percent-main" id="waterPercentText">
                                <?php echo $waterPercent; ?>%
                            </div>
                            <div class="water-percent-sub">Relative water level</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECOND ROW: Indicators + Reports -->
        <div class="grid">
            <!-- Device indicators -->
            <section class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Device Indicators</div>
                        <div class="card-sub">
                            Virtual mirror of LEDs and buzzer on BabalaBaha unit
                        </div>
                    </div>
                </div>

                <div class="led-panel">
                    <div class="led-row">
                        <span class="led-label">Green LED • Normal Level</span>
                        <span class="led-dot <?php if ($leds['green']) echo 'led-on-green'; ?>" id="ledGreen"></span>
                    </div>
                    <div class="led-row">
                        <span class="led-label">Yellow LED • Alert Level</span>
                        <span class="led-dot <?php if ($leds['yellow']) echo 'led-on-yellow'; ?>" id="ledYellow"></span>
                    </div>
                    <div class="led-row">
                        <span class="led-label">Red LED • Critical Level</span>
                        <span class="led-dot <?php if ($leds['red']) echo 'led-on-red'; ?>" id="ledRed"></span>
                    </div>
                    <div class="led-row">
                        <span class="led-label">Buzzer</span>
                        <span class="buzzer-chip <?php if ($leds['buzzer']) echo 'buzzer-on'; ?>" id="buzzerChip">
                            <?php echo $leds['buzzer'] ? 'ACTIVE (Warning sounding)' : 'Silent'; ?>
                        </span>
                    </div>
                </div>
            </section>

            <!-- Reports -->
            <section class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Flood Status Report</div>
                        <div class="card-sub">
                            Summary of all recorded status changes from the device
                        </div>
                    </div>
                    <div>
                        <a href="download_report.php" class="download-btn">
                            Download CSV Report
                        </a>
                    </div>
                </div>

                <div class="report-grid">
                    <div class="report-item">
                        <div class="report-label">Normal</div>
                        <div class="report-value"><?php echo $normalCount; ?></div>
                        <div class="report-tag">Times water level stayed within safe range.</div>
                    </div>
                    <div class="report-item">
                        <div class="report-label">Alert</div>
                        <div class="report-value"><?php echo $alertCount; ?></div>
                        <div class="report-tag">Moderate elevation—prepare for possible action.</div>
                    </div>
                    <div class="report-item">
                        <div class="report-label">Critical</div>
                        <div class="report-value"><?php echo $criticalCount; ?></div>
                        <div class="report-tag">High water level events needing immediate attention.</div>
                    </div>
                </div>
            </section>
        </div>

        <!-- History -->
        <section class="card">
            <div class="card-header">
                <div class="card-title">Recent Readings</div>
                <div class="card-sub">Last 10 status changes received from the water level sensor</div>
            </div>

            <table class="history-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Sensor Value</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($history) === 0): ?>
                    <tr>
                        <td colspan="3">No readings recorded yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($history as $row): ?>
                        <?php
                        $s = strtoupper($row['status']);
                        $pillClass = 'pill-normal';
                        if ($s === 'ALERT') {
                            $pillClass = 'pill-alert';
                        } elseif ($s === 'CRITICAL') {
                            $pillClass = 'pill-critical';
                        }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                            <td><span class="pill <?php echo $pillClass; ?>"><?php echo htmlspecialchars($s); ?></span></td>
                            <td><?php echo (int)$row['sensor_value']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>

    <footer class="footer">
        BabalaBaha Flood Early Warning Prototype • <?php echo date('Y'); ?>
    </footer>
</div>

<!-- Critical overlay (always in DOM; JS shows/hides) -->
<div class="critical-overlay" id="criticalOverlay" style="<?php echo ($currentStatus === 'CRITICAL' ? 'display:flex;' : 'display:none;'); ?>">
    <div class="critical-box">
        <!-- Click once to arm continuous warning while CRITICAL -->
        <button type="button"
                class="critical-sound-btn"
                onclick="armCriticalSound()"
                title="Play warning sound continuously">
            🔊
        </button>

        <div class="critical-title">Critical Flood Level</div>
        <div class="critical-subtitle">
            Water level has reached a <strong>CRITICAL</strong> threshold.<br>
            Immediate attention and field verification are required.
        </div>
        <div class="critical-pill">
            <span class="critical-dot"></span>
            BABALABAHA • LIVE ALERT
        </div>
        <div class="critical-reading" id="criticalReading">
            <?php echo (int)$sensorValue; ?>
        </div>
        <div class="critical-reading-label">
            Current sensor reading (0–1023)
        </div>
        <div class="critical-footer">
            <span id="criticalLastUpdate">Last update: <?php echo htmlspecialchars($lastUpdated); ?></span><br>
            This alert will clear automatically once status returns below critical.
        </div>
    </div>
</div>

<script>
const ALERT_KEY = 'babala_critical_sound_enabled';
const sound    = document.getElementById('criticalSound');

// Elements to update
const statusEl        = document.getElementById('currentStatusText');
const badgeEl         = document.getElementById('statusBadge');
const sensorEl        = document.getElementById('sensorValueText');
const lastUpdateSpan  = document.getElementById('lastUpdateText');
const waterFillEl     = document.getElementById('waterFill');
const waterPercentEl  = document.getElementById('waterPercentText');
const ledGreen        = document.getElementById('ledGreen');
const ledYellow       = document.getElementById('ledYellow');
const ledRed          = document.getElementById('ledRed');
const buzzerChip      = document.getElementById('buzzerChip');
const overlayEl       = document.getElementById('criticalOverlay');
const criticalReading = document.getElementById('criticalReading');
const criticalLastUpdate = document.getElementById('criticalLastUpdate');

function armCriticalSound() {
    if (!sound) return;
    // Mark as armed so auto-refresh JS keeps it playing while critical
    localStorage.setItem(ALERT_KEY, '1');
    if (sound.paused) {
        sound.currentTime = 0;
        sound.play().catch(() => {});
    }
}

function stopCriticalSound() {
    if (!sound) return;
    sound.pause();
    sound.currentTime = 0;
    localStorage.removeItem(ALERT_KEY);
}

function ensureSoundForStatus(isCritical) {
    const armed = (localStorage.getItem(ALERT_KEY) === '1');
    if (isCritical && armed && sound) {
        if (sound.paused) {
            sound.play().catch(()=>{});
        }
    } else {
        stopCriticalSound();
    }
}

function computePercent(sensor_value) {
    const min = 100, max = 700;
    let raw = sensor_value;
    if (raw < min) raw = min;
    if (raw > max) raw = max;
    let p = Math.round(((raw - min) / (max - min)) * 100);
    if (p < 0) p = 0;
    if (p > 100) p = 100;
    return p;
}

function updateUI(data) {
    if (!data) return;
    const sensor_value = parseInt(data.sensor_value ?? 0, 10);
    const statusRaw    = (data.status || 'NO DATA').toString();
    const created_at   = data.created_at || '';

    const statusUpper = statusRaw.toUpperCase();

    // Badge class
    let badgeClass = 'badge-unknown';
    if (statusUpper === 'NORMAL')  badgeClass = 'badge-normal';
    else if (statusUpper === 'ALERT')    badgeClass = 'badge-alert';
    else if (statusUpper === 'CRITICAL') badgeClass = 'badge-critical';

    // Percent
    const percent = computePercent(sensor_value);

    // Update text
    statusEl.textContent = statusUpper;
    badgeEl.className = 'status-badge ' + badgeClass;
    badgeEl.textContent = statusUpper;
    sensorEl.textContent = isNaN(sensor_value) ? '--' : sensor_value;
    lastUpdateSpan.textContent = created_at ? created_at : 'No data';

    waterFillEl.style.height = percent + '%';
    waterPercentEl.textContent = percent + '%';

    // LEDs
    ledGreen.classList.toggle('led-on-green', statusUpper === 'NORMAL');
    ledYellow.classList.toggle('led-on-yellow', statusUpper === 'ALERT');
    ledRed.classList.toggle('led-on-red', statusUpper === 'CRITICAL');

    // Buzzer / overlay / sound
    if (statusUpper === 'CRITICAL') {
        buzzerChip.classList.add('buzzer-on');
        buzzerChip.textContent = 'ACTIVE (Warning sounding)';

        overlayEl.style.display = 'flex';
        criticalReading.textContent = sensor_value;
        criticalLastUpdate.textContent = 'Last update: ' + (created_at || 'No data');

        ensureSoundForStatus(true);
    } else {
        buzzerChip.classList.remove('buzzer-on');
        buzzerChip.textContent = 'Silent';

        overlayEl.style.display = 'none';

        ensureSoundForStatus(false);
    }
}

// Poll every 1.5 seconds
function pollStatus() {
    fetch('fetch_status.php')
        .then(res => res.json())
        .then(data => {
            // console.log(data);
            updateUI(data);
        })
        .catch(err => {
            console.error('Failed to fetch status', err);
        });
}

window.addEventListener('load', () => {
    
    const initialIsCritical = ('<?php echo $currentStatus; ?>'.toUpperCase() === 'CRITICAL');
    ensureSoundForStatus(initialIsCritical);

    
    pollStatus();
    setInterval(pollStatus, 1000);
});
</script>

</body>
</html>
