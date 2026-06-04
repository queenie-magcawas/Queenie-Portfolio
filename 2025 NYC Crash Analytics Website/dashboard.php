<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

include 'nyc_db.php';
include 'db.php';

if (!isset($_SESSION['fullname'])) {
    header("Location: login.php");
    exit();
}

$fullname = $_SESSION['fullname'];
$table = "`TABLE 2`";

function createTableIfNotExists($conn, $table) {
    $createSQL = "CREATE TABLE IF NOT EXISTS $table (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `CRASH DATE` VARCHAR(30),
        `CRASH TIME` VARCHAR(20),
        `BOROUGH` VARCHAR(50),
        `LOCATION` VARCHAR(200),
        `LATITUDE` VARCHAR(30),
        `LONGITUDE` VARCHAR(30),
        `NUMBER OF PERSONS INJURED` INT DEFAULT 0,
        `NUMBER OF PERSONS KILLED` INT DEFAULT 0,
        `NUMBER OF PEDESTRIANS INJURED` INT DEFAULT 0,
        `NUMBER OF PEDESTRIANS KILLED` INT DEFAULT 0,
        `NUMBER OF CYCLIST INJURED` INT DEFAULT 0,
        `NUMBER OF CYCLIST KILLED` INT DEFAULT 0,
        `NUMBER OF MOTORIST INJURED` INT DEFAULT 0,
        `NUMBER OF MOTORIST KILLED` INT DEFAULT 0,
        `CONTRIBUTING FACTOR VEHICLE 1` TEXT,
        `CONTRIBUTING FACTOR VEHICLE 2` TEXT,
        `CONTRIBUTING FACTOR VEHICLE 3` TEXT,
        `CONTRIBUTING FACTOR VEHICLE 4` TEXT,
        `CONTRIBUTING FACTOR VEHICLE 5` TEXT,
        `VEHICLE TYPE CODE 1` VARCHAR(100),
        `VEHICLE TYPE CODE 2` VARCHAR(100),
        `VEHICLE TYPE CODE 3` VARCHAR(100),
        `VEHICLE TYPE CODE 4` VARCHAR(100),
        `VEHICLE TYPE CODE 5` VARCHAR(100),
        `collision_id` VARCHAR(50) UNIQUE,
        `ON STREET NAME` VARCHAR(100),
        `CROSS STREET NAME` VARCHAR(100),
        `OFF STREET NAME` VARCHAR(100),
        INDEX idx_borough (BOROUGH),
        INDEX idx_crash_date (`CRASH DATE`),
        INDEX idx_collision_id (collision_id)
    )";
    return $conn->query($createSQL);
}

createTableIfNotExists($nyc_conn, $table);

// Get filter parameters
$search_location = $_GET['search_location'] ?? '';
$borough_filter = $_GET['borough_filter'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$severity = $_GET['severity'] ?? '';
$injured_min = $_GET['injured_min'] ?? '';
$killed_min = $_GET['killed_min'] ?? '';
$vehicle_type = $_GET['vehicle_type'] ?? '';
$debug = isset($_GET['debug']) ? true : false; // Debug mode

$where = [];

// Debug info array
$debug_info = [];

// FIXED: Handle filtering when "UNKNOWN" is selected
if ($borough_filter != '' && $borough_filter != 'all') {
    if (strtoupper($borough_filter) === 'UNKNOWN') {
        $where[] = "(BOROUGH = '' OR BOROUGH IS NULL)";
        $debug_info['borough'] = "UNKNOWN (empty or null)";
    } else {
        $borough_filter = mysqli_real_escape_string($nyc_conn, $borough_filter);
        $where[] = "BOROUGH = '$borough_filter'";
        $debug_info['borough'] = $borough_filter;
    }
}

if ($date_from != '') {
    $where[] = "DATE(`CRASH DATE`) >= DATE('$date_from')";
    $debug_info['date_from'] = $date_from;
}

if ($date_to != '') {
    $where[] = "DATE(`CRASH DATE`) <= DATE('$date_to')";
    $debug_info['date_to'] = $date_to;
}

if ($search_location != '') {
    $search_location = mysqli_real_escape_string($nyc_conn, $search_location);
    $where[] = "(BOROUGH LIKE '%$search_location%' 
                OR `ON STREET NAME` LIKE '%$search_location%' 
                OR `CROSS STREET NAME` LIKE '%$search_location%'
                OR `OFF STREET NAME` LIKE '%$search_location%')";
    $debug_info['search_location'] = $search_location;
}

if ($severity != '' && $severity != 'all') {
    if ($severity == 'fatal') {
        $where[] = "`NUMBER OF PERSONS KILLED` > 0";
        $debug_info['severity'] = 'Fatal crashes only';
    } elseif ($severity == 'injury') {
        $where[] = "`NUMBER OF PERSONS INJURED` > 0";
        $debug_info['severity'] = 'Injury crashes only';
    } elseif ($severity == 'pdo') {
        $where[] = "`NUMBER OF PERSONS INJURED` = 0 AND `NUMBER OF PERSONS KILLED` = 0";
        $debug_info['severity'] = 'Property damage only';
    }
}

if ($injured_min != '' && is_numeric($injured_min) && $injured_min > 0) {
    $injured_min = (int)$injured_min;
    $where[] = "`NUMBER OF PERSONS INJURED` >= $injured_min";
    $debug_info['injured_min'] = $injured_min;
}

if ($killed_min != '' && is_numeric($killed_min) && $killed_min > 0) {
    $killed_min = (int)$killed_min;
    $where[] = "`NUMBER OF PERSONS KILLED` >= $killed_min";
    $debug_info['killed_min'] = $killed_min;
}

// IMPROVED: Vehicle type filter with partial matching and case-insensitive search
if ($vehicle_type != '' && $vehicle_type != 'all') {
    $vehicle_type = mysqli_real_escape_string($nyc_conn, $vehicle_type);
    // Use LIKE for partial matching and make it case-insensitive
    $where[] = "(LOWER(`VEHICLE TYPE CODE 1`) LIKE LOWER('%$vehicle_type%') 
                OR LOWER(`VEHICLE TYPE CODE 2`) LIKE LOWER('%$vehicle_type%'))";
    $debug_info['vehicle_type'] = $vehicle_type . " (partial, case-insensitive match)";
}

$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// DEBUG: Show the actual SQL query
if ($debug) {
    echo "<div style='background: #000; color: #0f0; padding: 10px; margin: 10px; font-family: monospace; position: fixed; top: 10px; right: 10px; z-index: 9999; max-width: 500px; overflow: auto;'>";
    echo "<strong>DEBUG SQL:</strong><br>";
    echo htmlspecialchars("SELECT COUNT(*) FROM $table $where_sql") . "<br><br>";
    echo "<strong>Active Filters:</strong><br>";
    foreach($debug_info as $key => $value) {
        echo "$key: $value<br>";
    }
    echo "</div>";
}

// Get filtered statistics
$stats_result = $nyc_conn->query("SELECT 
    COUNT(*) as total_records,
    SUM(`NUMBER OF PERSONS INJURED`) as total_injured,
    SUM(`NUMBER OF PERSONS KILLED`) as total_killed,
    SUM(CASE WHEN `NUMBER OF PERSONS INJURED` = 0 AND `NUMBER OF PERSONS KILLED` = 0 THEN 1 ELSE 0 END) as property_damage
FROM $table $where_sql");

$stats = $stats_result ? $stats_result->fetch_assoc() : ['total_records' => 0, 'total_injured' => 0, 'total_killed' => 0, 'property_damage' => 0];

$total_records = (int)($stats['total_records'] ?? 0);
$total_injured = (int)($stats['total_injured'] ?? 0);
$total_killed = (int)($stats['total_killed'] ?? 0);
$property_damage = (int)($stats['property_damage'] ?? 0);

// FIXED: Populating boroughs for dropdown list and explicitly adding "UNKNOWN"
$boroughs_query = $nyc_conn->query("SELECT DISTINCT BOROUGH FROM $table WHERE BOROUGH IS NOT NULL AND BOROUGH != '' ORDER BY BOROUGH");
$boroughs = [];
if ($boroughs_query) {
    while ($row = $boroughs_query->fetch_assoc()) {
        if ($row['BOROUGH']) $boroughs[] = $row['BOROUGH'];
    }
}
// Manually add UNKNOWN so it's always an available option in the dropdown selector
if (!in_array('UNKNOWN', $boroughs)) {
    $boroughs[] = 'UNKNOWN';
}

// IMPROVED: Get distinct vehicle types with more accurate matching
$vehicle_types_query = $nyc_conn->query("SELECT DISTINCT vehicle_type FROM (
    SELECT `VEHICLE TYPE CODE 1` as vehicle_type FROM $table WHERE `VEHICLE TYPE CODE 1` IS NOT NULL AND `VEHICLE TYPE CODE 1` != ''
    UNION
    SELECT `VEHICLE TYPE CODE 2` as vehicle_type FROM $table WHERE `VEHICLE TYPE CODE 2` IS NOT NULL AND `VEHICLE TYPE CODE 2` != ''
) as vehicle_codes ORDER BY vehicle_type");

$vehicle_types = [];
if ($vehicle_types_query) {
    while ($row = $vehicle_types_query->fetch_assoc()) {
        if ($row['vehicle_type']) {
            $vehicle_types[] = $row['vehicle_type'];
        }
    }
}

// Pagination
$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$data = null;
$labels = [];
$values = [];
$total_pages = 1;

if ($total_records > 0) {
    $total_pages = ceil($total_records / $limit);
    
    // Order by CRASH DATE ASCENDING (January first)
    $sql = "SELECT * FROM $table $where_sql ORDER BY STR_TO_DATE(`CRASH DATE`, '%Y-%m-%d') ASC LIMIT $offset, $limit";
    $data = $nyc_conn->query($sql);
    
    // Chart data - Include UNKNOWN as well
    $chartQ = $nyc_conn->query("SELECT BOROUGH, COUNT(*) as total FROM $table GROUP BY BOROUGH ORDER BY total DESC");
    if ($chartQ) {
        while ($r = $chartQ->fetch_assoc()) {
            $labels[] = $r['BOROUGH'] ?: 'UNKNOWN';
            $values[] = $r['total'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NYC Crash Analytics | 2025 Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: #0a0c15; color: #eef2ff; overflow-x: hidden; }
        
        .gradient-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            background: radial-gradient(circle at 20% 30%, rgba(2, 15, 35, 1) 0%, rgba(0, 5, 18, 1) 100%);
        }
        
        .sidebar {
            width: 280px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: rgba(10, 14, 23, 0.95);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(0, 255, 255, 0.2);
            padding: 30px 25px;
            z-index: 100;
        }
        
        .sidebar h3 {
            background: linear-gradient(135deg, #A5F0FF, #3B82F6);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-weight: 800;
            font-size: 1.6rem;
            margin-bottom: 20px;
        }
        
        .sidebar hr { border-color: rgba(0, 255, 255, 0.2); margin: 20px 0; }
        .sidebar p { color: #94a3b8; font-size: 0.85rem; margin-bottom: 5px; }
        .sidebar b { color: #0ff; font-size: 1rem; }
        
        .btn-logout {
            background: linear-gradient(95deg, #dc2626, #ef4444);
            border: none;
            color: white;
            border-radius: 2rem;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        
        .btn-import {
            background: linear-gradient(95deg, #8b5cf6, #7c3aed);
            border: none;
            color: white;
            border-radius: 0.8rem;
            padding: 10px 20px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 10px;
            text-align: center;
            width: 100%;
        }
        
        .btn-import:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139,92,246,0.4);
            color: white;
        }
        
        .btn-clear {
            background: linear-gradient(95deg, #dc2626, #ef4444);
            border: none;
            color: white;
            border-radius: 0.8rem;
            padding: 10px 20px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 10px;
            text-align: center;
            width: 100%;
        }
        
        .btn-clear:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220,38,38,0.4);
            color: white;
        }
        
        .main { margin-left: 280px; padding: 25px 30px; position: relative; z-index: 1; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            border-radius: 1.5rem;
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(0, 255, 255, 0.5);
            box-shadow: 0 0 25px rgba(0, 255, 255, 0.1);
        }
        
        .stat-icon { font-size: 2rem; color: #38bdf8; margin-bottom: 10px; }
        .stat-value { font-size: 2rem; font-weight: 800; color: #38bdf8; }
        .stat-label { font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; }
        
        .filter-section {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            border-radius: 1.5rem;
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .filter-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #0ff;
            margin-bottom: 5px;
            display: block;
        }
        
        .filter-input, .filter-select {
            background: rgba(0, 10, 25, 0.6);
            border: 1px solid rgba(59, 130, 246, 0.4);
            color: #eef2ff;
            border-radius: 0.8rem;
            padding: 10px 14px;
            font-size: 0.85rem;
            width: 100%;
        }
        
        .filter-input:focus, .filter-select:focus {
            border-color: #0ff;
            outline: none;
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.2);
        }
        
        .btn-filter {
            background: linear-gradient(95deg, #2563eb, #06b6d4);
            border: none;
            color: white;
            border-radius: 0.8rem;
            padding: 10px 24px;
            font-weight: 600;
            width: 100%;
            margin-top: 24px;
        }
        
        .btn-filter:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(37,99,235,0.4); }
        
        .btn-reset {
            background: rgba(255, 70, 85, 0.2);
            border: 1px solid rgba(255, 70, 85, 0.4);
            color: #ff6b6b;
            border-radius: 0.8rem;
            padding: 10px 24px;
            font-weight: 600;
            width: 100%;
            margin-top: 12px;
            text-align: center;
            text-decoration: none;
            display: block;
        }
        
        .card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            border-radius: 1.5rem;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        .card h5 { color: #38bdf8; font-weight: 600; margin-bottom: 20px; }
        
        .table-wrapper {
            overflow-x: auto;
            border-radius: 1rem;
            max-height: 550px;
            overflow-y: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.72rem;
        }
        
        .data-table thead th {
            background: #1a2035;
            color: #00ffff;
            font-weight: 600;
            padding: 8px 8px;
            font-size: 0.65rem;
            text-transform: uppercase;
            border-bottom: 2px solid #00ffff;
            position: sticky;
            top: 0;
            white-space: nowrap;
        }
        
        .data-table tbody td {
            padding: 6px 8px;
            color: #f0f3fa;
            border-bottom: 1px solid rgba(59, 130, 246, 0.15);
            background: rgba(10, 14, 23, 0.8);
            white-space: nowrap;
        }
        
        .data-table tbody tr:nth-child(even) td { background: rgba(20, 25, 45, 0.9); }
        .data-table tbody tr:hover td { background: rgba(0, 255, 255, 0.12); }
        
        .borough-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            background: rgba(59, 130, 246, 0.25);
            color: #90cdf4;
        }
        
        .location-link {
            color: #93c5fd;
            text-decoration: none;
            cursor: pointer;
        }
        
        .location-link:hover { color: #0ff; text-decoration: underline; }
        
        .injury-count.positive { color: #f87171; }
        .injury-count.zero { color: #6b7280; }
        .killed-count.positive { color: #ef4444; }
        .killed-count.zero { color: #6b7280; }
        
        .filter-badge {
            background: rgba(0, 255, 255, 0.15);
            border-radius: 2rem;
            padding: 4px 12px;
            font-size: 0.7rem;
            color: #0ff;
        }
        
        .active-filter {
            background: rgba(0, 255, 255, 0.08);
            border-radius: 1rem;
            padding: 12px;
            margin-bottom: 15px;
        }
        
        canvas { max-height: 320px; }
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .pagination-container {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .pagination { margin: 0; gap: 5px; }
        .page-link {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(0, 255, 255, 0.3);
            color: #0ff;
            border-radius: 8px;
            padding: 5px 10px;
            text-decoration: none;
            font-size: 0.75rem;
        }
        .page-item.active .page-link { background: linear-gradient(95deg, #2563eb, #06b6d4); color: white; }
        
        .debug-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            background: #0ff;
            color: #000;
            padding: 5px 10px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main { margin-left: 0; padding: 15px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stat-value { font-size: 1.3rem; }
        }
    </style>
</head>
<body>
<div class="gradient-bg"></div>
<div class="sidebar">
    <h3><i class="bi bi-ev-station-fill"></i> NYC Crash Analytics</h3>
    <hr>
    <p>Logged in as:</p>
    <b><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($fullname); ?></b>
    <hr>
    <div class="small text-muted mb-2"><i class="bi bi-database"></i> Data Source</div>
    <div class="filter-badge mb-3">NYC Open Data API (2025)</div>
    <div class="small text-muted mb-2"><i class="bi bi-bar-chart"></i> Status</div>
    <div class="filter-badge mb-3"><?php echo number_format($total_records); ?> records</div>
    <hr>
    <div class="d-grid gap-2">
        <a href="api_import.php" class="btn-import">
            <i class="bi bi-cloud-download"></i> IMPORT DATA
        </a>
        <a href="api_import.php?action=clear" class="btn-clear" onclick="return confirm('⚠️ WARNING: This will delete ALL imported crash data! This cannot be undone!\n\nAre you sure?')">
            <i class="bi bi-trash3"></i> CLEAR ALL DATA
        </a>
        <a href="logout.php" class="btn-logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>
<div class="main">
    <div class="header-actions">
        <h4 class="mb-0"><i class="bi bi-speedometer2"></i> NYC Crash Analytics Dashboard 2025</h4>
        <?php if($borough_filter != ''): ?>
        <span class="filter-badge"><i class="bi bi-funnel"></i> Filtered by: <?php echo htmlspecialchars($borough_filter); ?></span>
        <?php endif; ?>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-car-front-fill"></i></div>
            <div class="stat-value"><?php echo number_format($total_records); ?></div>
            <div class="stat-label">Total Reported Crashes</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-heart-pulse-fill"></i></div>
            <div class="stat-value"><?php echo number_format($total_injured); ?></div>
            <div class="stat-label">Total Persons Injured</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-emoji-frown-fill"></i></div>
            <div class="stat-value"><?php echo number_format($total_killed); ?></div>
            <div class="stat-label">Total Persons Killed</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-building"></i></div>
            <div class="stat-value"><?php echo number_format($property_damage); ?></div>
            <div class="stat-label">Property Damage Only</div>
        </div>
    </div>
    
    <div class="filter-section">
        <h6 class="mb-3"><i class="bi bi-funnel-fill"></i> Incident Registry • Real-time surveillance • Managed Filtered View</h6>
        <form method="GET" action="" id="filterForm">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="filter-label"><i class="bi bi-search"></i> Search by location/street</label>
                    <input type="text" name="search_location" class="filter-input" placeholder="Street or borough..." value="<?php echo htmlspecialchars($search_location); ?>">
                </div>
                <div class="col-md-2">
                    <label class="filter-label"><i class="bi bi-geo-alt"></i> Borough</label>
                    <select name="borough_filter" class="filter-select">
                        <option value="">All Boroughs</option>
                        <?php foreach($boroughs as $b): ?>
                        <option value="<?php echo htmlspecialchars($b); ?>" <?php echo strcasecmp($borough_filter, $b) == 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars($b); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="filter-label"><i class="bi bi-calendar"></i> Date From</label>
                    <input type="date" name="date_from" class="filter-input" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-2">
                    <label class="filter-label"><i class="bi bi-calendar"></i> Date To</label>
                    <input type="date" name="date_to" class="filter-input" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-md-3">
                    <label class="filter-label"><i class="bi bi-exclamation-triangle"></i> Severity</label>
                    <select name="severity" class="filter-select">
                        <option value="">All Levels</option>
                        <option value="fatal" <?php echo $severity == 'fatal' ? 'selected' : ''; ?>>Fatal Crash</option>
                        <option value="injury" <?php echo $severity == 'injury' ? 'selected' : ''; ?>>Injury Crash</option>
                        <option value="pdo" <?php echo $severity == 'pdo' ? 'selected' : ''; ?>>Property Damage Only</option>
                    </select>
                </div>
            </div>
            <div class="row g-3 mt-2">
                <div class="col-md-2">
                    <label class="filter-label"><i class="bi bi-heart-pulse"></i> Injured ≥</label>
                    <input type="number" name="injured_min" class="filter-input" placeholder="0" min="0" value="<?php echo htmlspecialchars($injured_min); ?>">
                </div>
                <div class="col-md-2">
                    <label class="filter-label"><i class="bi bi-emoji-frown"></i> Killed ≥</label>
                    <input type="number" name="killed_min" class="filter-input" placeholder="0" min="0" value="<?php echo htmlspecialchars($killed_min); ?>">
                </div>
                <!-- IMPROVED: Vehicle Type Filter with note about partial matching -->
                <div class="col-md-3">
                    <label class="filter-label"><i class="bi bi-truck"></i> Vehicle Type <small class="text-muted">(partial match)</small></label>
                    <select name="vehicle_type" class="filter-select">
                        <option value="">All Vehicles</option>
                        <?php foreach($vehicle_types as $vt): ?>
                        <option value="<?php echo htmlspecialchars($vt); ?>" <?php echo $vehicle_type == $vt ? 'selected' : ''; ?>><?php echo htmlspecialchars($vt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="filter-label">&nbsp;</label>
                    <button type="submit" class="btn-filter w-100 mt-0"><i class="bi bi-funnel"></i> Apply Filters</button>
                </div>
                <div class="col-md-2">
                    <label class="filter-label">&nbsp;</label>
                    <a href="dashboard.php" class="btn-reset w-100 mt-0"><i class="bi bi-arrow-repeat"></i> Reset</a>
                </div>
            </div>
        </form>
    </div>
    
    <?php if($search_location != '' || $borough_filter != '' || $date_from != '' || $date_to != '' || $severity != '' || ($injured_min != '' && $injured_min > 0) || ($killed_min != '' && $killed_min > 0) || $vehicle_type != ''): ?>
    <div class="active-filter mb-4">
        <small class="text-muted"><i class="bi bi-funnel"></i> Active Filters:</small>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <?php if($search_location != ''): ?>
            <span class="filter-badge"><i class="bi bi-search"></i> <?php echo htmlspecialchars($search_location); ?></span>
            <?php endif; ?>
            <?php if($borough_filter != ''): ?>
            <span class="filter-badge"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($borough_filter); ?></span>
            <?php endif; ?>
            <?php if($date_from != ''): ?>
            <span class="filter-badge"><i class="bi bi-calendar"></i> From: <?php echo htmlspecialchars($date_from); ?></span>
            <?php endif; ?>
            <?php if($date_to != ''): ?>
            <span class="filter-badge"><i class="bi bi-calendar"></i> To: <?php echo htmlspecialchars($date_to); ?></span>
            <?php endif; ?>
            <?php if($severity != '' && $severity != 'all'): ?>
            <span class="filter-badge"><i class="bi bi-exclamation-triangle"></i> <?php echo ucfirst($severity); ?></span>
            <?php endif; ?>
            <?php if($injured_min != '' && $injured_min > 0): ?>
            <span class="filter-badge"><i class="bi bi-heart-pulse"></i> Injured ≥ <?php echo $injured_min; ?></span>
            <?php endif; ?>
            <?php if($killed_min != '' && $killed_min > 0): ?>
            <span class="filter-badge"><i class="bi bi-emoji-frown"></i> Killed ≥ <?php echo $killed_min; ?></span>
            <?php endif; ?>
            <?php if($vehicle_type != ''): ?>
            <span class="filter-badge"><i class="bi bi-truck"></i> Vehicle containing: <?php echo htmlspecialchars($vehicle_type); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="card p-4 mb-4">
        <h5><i class="bi bi-bar-chart-fill"></i> Crashes per Borough <span class="filter-badge ms-2">Click bar to filter by borough</span></h5>
        <canvas id="boroughChart" style="max-height: 350px;"></canvas>
    </div>
    
    <div class="card p-4">
        <h5><i class="bi bi-table"></i> Crash Records <span class="filter-badge ms-2">Click location to view on Google Maps</span></h5>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th><th>Time</th><th>Borough</th><th>Location</th>
                        <th>Injured</th><th>Killed</th><th>Ped Inj</th><th>Ped Killed</th>
                        <th>Cyc Inj</th><th>Cyc Killed</th><th>Mot Inj</th><th>Mot Killed</th>
                        <th>Factor 1</th><th>Factor 2</th><th>Vehicle 1</th><th>Vehicle 2</th>
                        <th>Collision ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($data && $data->num_rows > 0): while ($row = $data->fetch_assoc()): 
                        $crash_date = preg_replace('/T.*$/', '', $row['CRASH DATE'] ?? 'N/A');
                        $date_obj = DateTime::createFromFormat('Y-m-d', $crash_date);
                        if ($date_obj) {
                            $display_date = $date_obj->format('M d, Y');
                        } else {
                            $display_date = $crash_date;
                        }
                        $location = $row['ON STREET NAME'] ?: ($row['CROSS STREET NAME'] ?: ($row['OFF STREET NAME'] ?: 'N/A'));
                        $latitude = $row['LATITUDE'] ?? '';
                        $longitude = $row['LONGITUDE'] ?? '';
                        $google_maps_url = '';
                        if ($latitude && $longitude && $latitude != '0' && $longitude != '0') {
                            $google_maps_url = "http://maps.google.com/?q={$latitude},{$longitude}";
                        } elseif ($location != 'N/A') {
                            $google_maps_url = "http://maps.google.com/?q=" . urlencode($location . ", New York, NY");
                        }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($display_date); ?></td>
                        <td><?php echo htmlspecialchars($row['CRASH TIME'] ?? 'N/A'); ?></td>
                        <td><span class="borough-badge"><?php echo htmlspecialchars($row['BOROUGH'] ?: 'UNKNOWN'); ?></span></td>
                        <td><?php if ($google_maps_url): ?><a href="<?php echo $google_maps_url; ?>" target="_blank" class="location-link"><i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars(substr($location, 0, 25)); ?></a><?php else: ?><?php echo htmlspecialchars(substr($location, 0, 25)); endif; ?></td>
                        <td class="injury-count <?php echo ($row['NUMBER OF PERSONS INJURED'] ?? 0) > 0 ? 'positive' : 'zero'; ?>"><?php echo number_format($row['NUMBER OF PERSONS INJURED'] ?? 0); ?></td>
                        <td class="killed-count <?php echo ($row['NUMBER OF PERSONS KILLED'] ?? 0) > 0 ? 'positive' : 'zero'; ?>"><?php echo number_format($row['NUMBER OF PERSONS KILLED'] ?? 0); ?></td>
                        <td><?php echo number_format($row['NUMBER OF PEDESTRIANS INJURED'] ?? 0); ?></td>
                        <td><?php echo number_format($row['NUMBER OF PEDESTRIANS KILLED'] ?? 0); ?></td>
                        <td><?php echo number_format($row['NUMBER OF CYCLIST INJURED'] ?? 0); ?></td>
                        <td><?php echo number_format($row['NUMBER OF CYCLIST KILLED'] ?? 0); ?></td>
                        <td><?php echo number_format($row['NUMBER OF MOTORIST INJURED'] ?? 0); ?></td>
                        <td><?php echo number_format($row['NUMBER OF MOTORIST KILLED'] ?? 0); ?></td>
                        <td class="location-text"><?php echo htmlspecialchars(substr($row['CONTRIBUTING FACTOR VEHICLE 1'] ?? 'N/A', 0, 20)); ?></td>
                        <td class="location-text"><?php echo htmlspecialchars(substr($row['CONTRIBUTING FACTOR VEHICLE 2'] ?? 'N/A', 0, 20)); ?></td>
                        <td><?php 
                            $vehicle1 = $row['VEHICLE TYPE CODE 1'] ?? 'N/A';
                            $vehicle2 = $row['VEHICLE TYPE CODE 2'] ?? 'N/A';
                            if ($vehicle_type != '' && (stripos($vehicle1, $vehicle_type) !== false || stripos($vehicle2, $vehicle_type) !== false)) {
                                echo "<span style='background: #00ff0011; color: #0f0;'>" . htmlspecialchars($vehicle1) . "</span>";
                            } else {
                                echo htmlspecialchars($vehicle1);
                            }
                         ?></td>
                        <td><?php 
                            if ($vehicle_type != '' && (stripos($vehicle1, $vehicle_type) !== false || stripos($vehicle2, $vehicle_type) !== false)) {
                                echo "<span style='background: #00ff0011; color: #0f0;'>" . htmlspecialchars($vehicle2) . "</span>";
                            } else {
                                echo htmlspecialchars($vehicle2);
                            }
                         ?></td>
                        <td class="location-text"><?php echo htmlspecialchars($row['collision_id'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="18" class="text-center py-4">No crash records found with the selected filters</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_records > 0): ?>
        <div class="pagination-container">
            <div class="small text-muted">Page <?php echo $page; ?> of <?php echo number_format($total_pages); ?> (<?php echo number_format($total_records); ?> results)</div>
            <nav><ul class="pagination">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>"><a class="page-link" href="?page=<?php echo $page-1; ?>&search_location=<?php echo urlencode($search_location); ?>&borough_filter=<?php echo urlencode($borough_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&severity=<?php echo urlencode($severity); ?>&injured_min=<?php echo urlencode($injured_min); ?>&killed_min=<?php echo urlencode($killed_min); ?>&vehicle_type=<?php echo urlencode($vehicle_type); ?>">Prev</a></li>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1&' . http_build_query(array_filter(['search_location'=>$search_location, 'borough_filter'=>$borough_filter, 'date_from'=>$date_from, 'date_to'=>$date_to, 'severity'=>$severity, 'injured_min'=>$injured_min, 'killed_min'=>$killed_min, 'vehicle_type'=>$vehicle_type])) . '">1</a></li>';
                    if($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
                for($i = $start_page; $i <= $end_page; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&search_location=<?php echo urlencode($search_location); ?>&borough_filter=<?php echo urlencode($borough_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&severity=<?php echo urlencode($severity); ?>&injured_min=<?php echo urlencode($injured_min); ?>&killed_min=<?php echo urlencode($killed_min); ?>&vehicle_type=<?php echo urlencode($vehicle_type); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor;
                if($end_page < $total_pages) {
                    if($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&' . http_build_query(array_filter(['search_location'=>$search_location, 'borough_filter'=>$borough_filter, 'date_from'=>$date_from, 'date_to'=>$date_to, 'severity'=>$severity, 'injured_min'=>$injured_min, 'killed_min'=>$killed_min, 'vehicle_type'=>$vehicle_type])) . '">' . number_format($total_pages) . '</a></li>';
                }
                ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page+1; ?>&search_location=<?php echo urlencode($search_location); ?>&borough_filter=<?php echo urlencode($borough_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&severity=<?php echo urlencode($severity); ?>&injured_min=<?php echo urlencode($injured_min); ?>&killed_min=<?php echo urlencode($killed_min); ?>&vehicle_type=<?php echo urlencode($vehicle_type); ?>">Next</a>
                </li>
            </ul></nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<a href="?<?php echo http_build_query(array_merge($_GET, ['debug' => $debug ? 0 : 1])); ?>" class="debug-toggle">
    <?php echo $debug ? 'Hide Debug' : 'Debug SQL'; ?>
</a>

<script>
    // Chart configurations
    const labels = <?php echo json_encode($labels); ?>;
    const dataValues = <?php echo json_encode($values); ?>;
    
    const ctx = document.getElementById('boroughChart').getContext('2d');
    const boroughChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Number of Crashes',
                data: dataValues,
                backgroundColor: 'rgba(56, 189, 248, 0.2)',
                borderColor: 'rgba(56, 189, 248, 1)',
                borderWidth: 2,
                borderRadius: 8,
                hoverBackgroundColor: 'rgba(0, 255, 255, 0.4)',
                hoverBorderColor: 'rgba(0, 255, 255, 1)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(10, 14, 23, 0.95)',
                    titleColor: '#0ff',
                    bodyColor: '#fff',
                    borderColor: 'rgba(0, 255, 255, 0.2)',
                    borderWidth: 1
                }
            },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } },
                y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } }
            },
            onClick: (e, activeEls) => {
                if (activeEls.length > 0) {
                    const idx = activeEls[0].index;
                    const bName = labels[idx];
                    const form = document.getElementById('filterForm');
                    form.elements['borough_filter'].value = bName === 'UNKNOWN' ? 'UNKNOWN' : bName;
                    form.submit();
                }
            }
        }
    });
</script>
</body>
</html>