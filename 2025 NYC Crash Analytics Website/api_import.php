<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 600);
ini_set('memory_limit', '512M');

include 'nyc_db.php';
include 'db.php';

if (!isset($_SESSION['fullname'])) {
    header("Location: login.php");
    exit();
}

$fullname = $_SESSION['fullname'];
$table = "`TABLE 2`";

// Create table if not exists - ALIGNED WITH DASHBOARD COLUMNS (SPACES)
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
    `VEHICLE TYPE CODE 1` VARCHAR(100),
    `VEHICLE TYPE CODE 2` VARCHAR(100),
    `collision_id` VARCHAR(50) UNIQUE,
    `ON STREET NAME` VARCHAR(100),
    `CROSS STREET NAME` VARCHAR(100),
    `OFF STREET NAME` VARCHAR(100),
    INDEX idx_borough (BOROUGH),
    INDEX idx_crash_date (`CRASH DATE`),
    INDEX idx_collision_id (collision_id),
    INDEX idx_vehicle_1 (`VEHICLE TYPE CODE 1`),
    INDEX idx_vehicle_2 (`VEHICLE TYPE CODE 2`)
)";
$nyc_conn->query($createSQL);

// Handle clear data - SAFEGUARDED WITH POST REQUEST (Prevents Crawler Truncation)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'clear_data') {
    $nyc_conn->query("TRUNCATE TABLE $table");
    header("Location: dashboard.php?cleared=1");
    exit();
}

// Get current record count
$result = $nyc_conn->query("SELECT COUNT(*) as count FROM $table");
$current_records = $result ? $result->fetch_assoc()['count'] : 0;
$api_total = 85545;

// Handle the actual import via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'start_import') {
    // Start output buffering to prevent any stray warnings from corrupting the JSON payload
    ob_start();
    header('Content-Type: application/json');
    
    $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
    $batch_size = 1000;
    
    // OPTIMIZED SOQL QUERY: Using date_extract_y is substantially faster on Socrata's engine
    $where_clause = urlencode("date_extract_y(crash_date) = 2025");
    $url = "https://data.cityofnewyork.us/resource/h9gi-nx95.json?\$where=" . $where_clause . "&\$limit=$batch_size&\$offset=$offset";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    // OPTIONAL TIP: If you register a free Socrata App Token, add it here to completely avoid rate limits:
    // curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'User-Agent: Projects-App', 'X-App-Token: YOUR_TOKEN_HERE']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'User-Agent: Mozilla/5.0']);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => "API Error: HTTP $http_code. Socrata might be rate-limiting requests."]);
        exit();
    }
    
    $data = json_decode($response, true);
    
    // CRITICAL FIX: Detect if Socrata returned an error object instead of a data array
    if (isset($data['code']) || isset($data['message'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Socrata API Error: ' . ($data['message'] ?? 'Unknown error')]);
        exit();
    }
    
    if (!is_array($data) || empty($data)) {
        ob_clean();
        echo json_encode(['success' => true, 'complete' => true, 'message' => 'No more records found']);
        exit();
    }
    
    $importedCount = 0;
    $bulk_values = [];
    
    foreach ($data as $row) {
        // Double-check row structural format to protect against malformed lines
        if (!is_array($row) || empty($row['collision_id'])) continue;
        
        $collision_id = $nyc_conn->real_escape_string($row['collision_id']);
        
        $crash_date = $nyc_conn->real_escape_string($row['crash_date'] ?? '');
        $crash_time = $nyc_conn->real_escape_string($row['crash_time'] ?? '');
        $borough = $nyc_conn->real_escape_string($row['borough'] ?? '');
        $location = isset($row['location']) ? $nyc_conn->real_escape_string(json_encode($row['location'])) : '';
        if(strlen($location) > 200) $location = substr($location, 0, 197) . '...'; // Protect VARCHAR(200) limits
        
        $latitude = $nyc_conn->real_escape_string($row['latitude'] ?? '');
        $longitude = $nyc_conn->real_escape_string($row['longitude'] ?? '');
        $injured = (int)($row['number_of_persons_injured'] ?? 0);
        $killed = (int)($row['number_of_persons_killed'] ?? 0);
        $ped_injured = (int)($row['number_of_pedestrians_injured'] ?? 0);
        $ped_killed = (int)($row['number_of_pedestrians_killed'] ?? 0);
        $cyclist_injured = (int)($row['number_of_cyclist_injured'] ?? 0);
        $cyclist_killed = (int)($row['number_of_cyclist_killed'] ?? 0);
        $motorist_injured = (int)($row['number_of_motorist_injured'] ?? 0);
        $motorist_killed = (int)($row['number_of_motorist_killed'] ?? 0);
        $factor1 = $nyc_conn->real_escape_string($row['contributing_factor_vehicle_1'] ?? '');
        $factor2 = $nyc_conn->real_escape_string($row['contributing_factor_vehicle_2'] ?? '');
        $vehicle1 = $nyc_conn->real_escape_string($row['vehicle_type_code1'] ?? '');
        $vehicle2 = $nyc_conn->real_escape_string($row['vehicle_type_code2'] ?? '');
        $on_street = $nyc_conn->real_escape_string($row['on_street_name'] ?? '');
        $cross_street = $nyc_conn->real_escape_string($row['cross_street_name'] ?? '');
        $off_street = $nyc_conn->real_escape_string($row['off_street_name'] ?? '');
        
        $bulk_values[] = "('$crash_date', '$crash_time', '$borough', '$location', '$latitude', '$longitude',
            $injured, $killed, $ped_injured, $ped_killed,
            $cyclist_injured, $cyclist_killed, $motorist_injured, $motorist_killed,
            '$factor1', '$factor2', '$vehicle1', '$vehicle2', '$collision_id',
            '$on_street', '$cross_street', '$off_street')";
    }
    
    if (count($bulk_values) > 0) {
        $bulk_sql = "INSERT IGNORE INTO $table (
            `CRASH DATE`, `CRASH TIME`, BOROUGH, LOCATION, LATITUDE, LONGITUDE,
            `NUMBER OF PERSONS INJURED`, `NUMBER OF PERSONS KILLED`,
            `NUMBER OF PEDESTRIANS INJURED`, `NUMBER OF PEDESTRIANS KILLED`,
            `NUMBER OF CYCLIST INJURED`, `NUMBER OF CYCLIST KILLED`,
            `NUMBER OF MOTORIST INJURED`, `NUMBER OF MOTORIST KILLED`,
            `CONTRIBUTING FACTOR VEHICLE 1`, `CONTRIBUTING FACTOR VEHICLE 2`,
            `VEHICLE TYPE CODE 1`, `VEHICLE TYPE CODE 2`, collision_id,
            `ON STREET NAME`, `CROSS STREET NAME`, `OFF STREET NAME`
        ) VALUES " . implode(", ", $bulk_values);
        
        try {
            if ($nyc_conn->query($bulk_sql)) {
                $importedCount = $nyc_conn->affected_rows > 0 ? $nyc_conn->affected_rows : count($bulk_values);
            }
        } catch (Exception $e) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Database SQL Error: ' . $e->getMessage()]);
            exit();
        }
    }
    
    ob_clean(); // Discard any unintended output data
    echo json_encode(['success' => true, 'imported' => $importedCount, 'offset' => $offset]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NYC Crash Data Import - 2025 Only</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #0a0c15; color: #eef2ff; }
        .gradient-bg {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -2;
            background: radial-gradient(circle at 20% 30%, rgba(2,15,35,1) 0%, rgba(0,5,18,1) 100%);
        }
        .import-card {
            background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(12px); border-radius: 1.5rem;
            border: 1px solid rgba(59, 130, 246, 0.3); padding: 30px; max-width: 750px; margin: 40px auto;
        }
        .stats-box { background: rgba(0, 0, 0, 0.3); border-radius: 1rem; padding: 15px; margin-bottom: 20px; }
        .btn-import {
            background: linear-gradient(95deg, #10b981, #059669); border: none; padding: 16px;
            font-weight: 700; font-size: 1.2rem; width: 100%; color: white; border-radius: 0.8rem;
        }
        .btn-import:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16,185,129,0.4); }
        .btn-import:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-danger { background: linear-gradient(95deg, #dc2626, #ef4444); border: none; padding: 12px; }
        .btn-secondary { background: rgba(100, 100, 100, 0.3); border: 1px solid rgba(255,255,255,0.2); color: white; }
        .filter-badge { background: rgba(0, 255, 255, 0.15); border-radius: 2rem; padding: 6px 15px; font-size: 0.9rem; color: #0ff; }
        .progress-container { background: rgba(0,0,0,0.5); border-radius: 1rem; padding: 15px; margin-top: 20px; }
        .progress { height: 10px; background: rgba(255,255,255,0.1); border-radius: 5px; }
        .progress-bar { background: linear-gradient(95deg, #8b5cf6, #10b981); border-radius: 5px; transition: width 0.3s ease; }
        .loading-spinner {
            display: inline-block; width: 20px; height: 20px; border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%; border-top-color: white; animation: spin 0.8s linear infinite; margin-right: 8px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .log-container { max-height: 200px; overflow-y: auto; font-size: 0.8rem; margin-top: 15px; padding: 10px; background: rgba(0,0,0,0.3); border-radius: 0.8rem; display: none; }
        .log-entry { padding: 3px 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-family: monospace; }
        .alert-success { background: rgba(16,185,129,0.2); border: 1px solid rgba(16,185,129,0.5); color: #6ee7b7; border-radius: 1rem; padding: 12px; }
    </style>
</head>
<body>
<div class="gradient-bg"></div>
<div class="container">
    <div class="import-card">
        <div class="text-center mb-4">
            <i class="bi bi-cloud-download fs-1" style="color: #38bdf8;"></i>
            <h2 class="mt-3">NYC Crash Data Import</h2>
            <p class="text-muted">Imports ONLY 2025 data from NYC Open Data API</p>
        </div>
        
        <div class="stats-box">
            <div class="row text-center">
                <div class="col-4">
                    <small class="text-muted">Current</small>
                    <h3 class="text-info mb-0" id="currentRecords"><?php echo number_format($current_records); ?></h3>
                </div>
                <div class="col-4">
                    <small class="text-muted">Target</small>
                    <h3 class="text-info mb-0">85,545</h3>
                </div>
                <div class="col-4">
                    <small class="text-muted">Remaining</small>
                    <h3 class="text-info mb-0" id="remainingRecords"><?php echo number_format(max(0, 85545 - $current_records)); ?></h3>
                </div>
            </div>
            <div class="text-center mt-3">
                <span class="filter-badge" id="progressBadge">📊 Progress: <?php echo round(($current_records / 85545) * 100, 1); ?>%</span>
            </div>
        </div>
        
        <div id="messageArea" class="mb-3"></div>
        
        <div class="d-grid gap-3">
            <button id="importBtn" class="btn-import">
                <i class="bi bi-play-fill"></i> START IMPORT 2025 DATA
            </button>
            
            <form action="" method="POST" onsubmit="return confirm('⚠️ WARNING: This will completely delete ALL imported crash data!\n\nAre you sure?')">
                <input type="hidden" name="action" value="clear_data">
                <button type="submit" class="btn btn-danger w-100">
                    <i class="bi bi-trash3"></i> CLEAR ALL DATA
                </button>
            </form>
            
            <a href="dashboard.php" class="btn btn-secondary w-100">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <div id="progressContainer" class="progress-container" style="display: none;">
            <div class="d-flex justify-content-between small mb-2">
                <span id="progressStatus">Preparing import...</span>
                <span id="progressPercent">0%</span>
            </div>
            <div class="progress">
                <div id="progressBar" class="progress-bar" style="width: 0%;"></div>
            </div>
        </div>
        
        <div id="logContainer" class="log-container">
            <div id="logEntries"></div>
        </div>
        
        <div class="alert-info mt-4 p-3 rounded" style="background: rgba(59,130,246,0.1);">
            <i class="bi bi-info-circle"></i> <strong>Surveillance Settings:</strong><br>
            • System target payload: <strong>85,545 records</strong><br>
            • Current local rows: <strong><?php echo number_format($current_records); ?></strong>
        </div>
    </div>
</div>

<script>
const TARGET_RECORDS = 85545;
let currentRecords = <?php echo $current_records; ?>;
let isImporting = false;

function updateStats(imported) {
    currentRecords += imported;
    document.getElementById('currentRecords').innerText = currentRecords.toLocaleString();
    const remaining = Math.max(0, TARGET_RECORDS - currentRecords);
    document.getElementById('remainingRecords').innerText = remaining.toLocaleString();
    const percent = Math.min(100, ((currentRecords / TARGET_RECORDS) * 100)).toFixed(1);
    document.getElementById('progressBadge').innerHTML = `📊 Progress: ${percent}%`;
    return remaining;
}

function addLog(message, isError = false) {
    const logDiv = document.getElementById('logEntries');
    const entry = document.createElement('div');
    entry.className = 'log-entry';
    entry.style.color = isError ? '#fca5a5' : '#6ee7b7';
    entry.innerHTML = `<small>${new Date().toLocaleTimeString()}</small> - ${message}`;
    logDiv.appendChild(entry);
    logDiv.scrollTop = logDiv.scrollHeight;
}

document.getElementById('importBtn').addEventListener('click', async function() {
    if (isImporting) return;
    
    const btn = this;
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressStatus = document.getElementById('progressStatus');
    const progressPercent = document.getElementById('progressPercent');
    const messageArea = document.getElementById('messageArea');
    const logContainer = document.getElementById('logContainer');
    
    if (currentRecords >= TARGET_RECORDS) {
        messageArea.innerHTML = '<div class="alert-success">✅ Database is already complete! ' + currentRecords.toLocaleString() + ' / 85,545 records.</div>';
        return;
    }
    
    if (confirm(`🚀 START DATA ENGINE\n\nCurrent: ${currentRecords.toLocaleString()}\nTarget: ${TARGET_RECORDS.toLocaleString()}\n\nClick OK to execute pagination requests.`)) {
        
        isImporting = true;
        btn.disabled = true;
        btn.innerHTML = '<span class="loading-spinner"></span> IMPORTING...';
        progressContainer.style.display = 'block';
        logContainer.style.display = 'block';
        document.getElementById('logEntries').innerHTML = '';
        addLog('Connecting to NYC Open Data Endpoint...');
        
        let offset = Math.floor(currentRecords / 1000) * 1000;
        let totalImported = 0;
        let batchNum = Math.floor(offset / 1000) + 1;
        let hasMoreData = true;
        let consecutiveErrors = 0; // Prevent infinite loop lockups
        
        while (hasMoreData && offset < 105000) {
            const percent = Math.min(Math.round((currentRecords / TARGET_RECORDS) * 100), 99);
            progressBar.style.width = percent + '%';
            progressPercent.innerText = percent + '%';
            progressStatus.innerText = `Batch ${batchNum}: Fetching offset ${offset.toLocaleString()}...`;
            btn.innerHTML = `<span class="loading-spinner"></span> Batch ${batchNum} - ${percent}%`;
            
            try {
                const formData = new FormData();
                formData.append('action', 'start_import');
                formData.append('offset', offset);
                
                const response = await fetch(window.location.href, { method: 'POST', body: formData });
                
                // Get raw text output first to troubleshoot potential syntax issues
                const rawText = await response.text();
                let result;
                try {
                    result = JSON.parse(rawText);
                } catch(e) {
                    throw new Error(`Invalid JSON server output. Server returned: ${rawText.substring(0, 150)}`);
                }
                
                if (result.success) {
                    consecutiveErrors = 0; // Reset error tracker
                    if (result.complete || result.imported === 0) {
                        addLog(`🎉 Process verified complete! Total records: ${currentRecords.toLocaleString()}`);
                        hasMoreData = false;
                        break;
                    }
                    
                    totalImported += result.imported;
                    const remaining = updateStats(result.imported);
                    addLog(`✅ Batch ${batchNum}: Safe compiled ${result.imported.toLocaleString()} elements.`);
                    offset += 1000;
                    batchNum++;
                    await new Promise(r => setTimeout(r, 400)); // Throttling protection delay
                } else {
                    consecutiveErrors++;
                    addLog(`❌ Batch ${batchNum} Error: ${result.message}`, true);
                    if(consecutiveErrors >= 3) {
                        addLog('🚨 Critical: 3 consecutive batches failed. Halting process to avoid script lockup.', true);
                        break;
                    }
                    await new Promise(r => setTimeout(r, 4000));
                }
            } catch (error) {
                consecutiveErrors++;
                addLog(`❌ Client Connection Error: ${error.message}`, true);
                if(consecutiveErrors >= 3) {
                    addLog('🚨 Halting process due to persistent runtime issues.', true);
                    break;
                }
                await new Promise(r => setTimeout(r, 5000));
            }
        }
        
        progressBar.style.width = '100%';
        progressPercent.innerText = '100%';
        progressStatus.innerText = 'Process Terminated';
        btn.innerHTML = '<i class="bi bi-play-fill"></i> START IMPORT 2025 DATA';
        btn.disabled = false;
        isImporting = false;
        
        if (currentRecords >= TARGET_RECORDS) {
            messageArea.innerHTML = `<div class="alert-success">🎉 WORK COMPLETE! Local database matches targets. Redirecting...</div>`;
            setTimeout(() => { window.location.href = 'dashboard.php'; }, 2000);
        } else {
            messageArea.innerHTML = `<div class="alert-success">✅ Engine cycle complete. Saved ${totalImported.toLocaleString()} records this run. Click START to pick up where you left off.</div>`;
        }
    }
});
</script>
</body>
</html>