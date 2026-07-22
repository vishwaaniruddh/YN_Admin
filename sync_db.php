<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Enable mysqli error reporting to throw exceptions on failure
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Connection details for local and server DB
try {
    $localConn = new mysqli('localhost', 'root', '', 'yosshitaneha_db');
} catch (Exception $e) {
    die("Failed to connect to local database: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
}

try {
    $serverConn = new mysqli('193.203.184.203', 'u464193275_yosshitanehafs', 'AVav@@2026', 'u464193275_yosshitanehafs');
} catch (Exception $e) {
    die("Failed to connect to server database: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
}
//    $con = mysqli_connect("localhost", "u464193275_srishrinjuser", "9b@hMgk!=zI", "u464193275_srishrinjewels");
// Handle Sync Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_table'])) {
    $tableToSync = $_POST['sync_table'];

    // 1. Get Create Statement from Local
    $res = $localConn->query("SHOW CREATE TABLE `$tableToSync`");
    if ($res && $row = $res->fetch_assoc()) {
        $createSQL = $row['Create Table'];

        // 2. Execute on Server
        if ($serverConn->query($createSQL)) {
            echo "<script>alert('Table `$tableToSync` created successfully on server.'); window.location.href='sync_db.php';</script>";
        } else {
            echo "<script>alert('Error creating table: " . $serverConn->error . "');</script>";
        }
    } else {
        echo "<script>alert('Error fetching local table structure.');</script>";
    }
}

// Helper to sync single column
function syncColumnDefinition($table, $col, $localConn, $serverConn)
{
    // Get Create Statement
    $res = $localConn->query("SHOW CREATE TABLE `$table`");
    if ($res && $row = $res->fetch_assoc()) {
        $createSQL = $row['Create Table'];
        $colEsc = preg_quote($col, '/');
        $pattern = "/^\s*`$colEsc`\s+(.*?),?$/m";

        if (preg_match($pattern, $createSQL, $matches)) {
            $fullDef = trim($matches[0]);
            if (substr($fullDef, -1) === ',') {
                $fullDef = substr($fullDef, 0, -1);
            }
            $alterSQL = "ALTER TABLE `$table` ADD $fullDef";
            return $serverConn->query($alterSQL) ? true : $serverConn->error;
        } else {
            return "Definition not found";
        }
    }
    return "Local table not found";
}

// Handle Sync Column Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['sync_column'])) {
        $table = $_POST['table'];
        $col = $_POST['column'];
        $res = syncColumnDefinition($table, $col, $localConn, $serverConn);
        if ($res === true) {
            echo "<script>alert('Column `$col` synced successfully.'); window.location.href='sync_db.php';</script>";
        } else {
            echo "<script>alert('Error syncing `$col`: $res');</script>";
        }
    }

    if (isset($_POST['sync_all_columns'])) {
        $table = $_POST['table'];
        $colDiff = compareColumns($table, $localConn, $serverConn);
        $missing = array_keys($colDiff['onlyLocal']);
        $successCount = 0;
        $errors = [];

        foreach ($missing as $col) {
            $res = syncColumnDefinition($table, $col, $localConn, $serverConn);
            if ($res === true) {
                $successCount++;
            } else {
                $errors[] = "$col: $res";
            }
        }

        if (count($errors) === 0) {
            echo "<script>alert('All $successCount columns synced successfully.'); window.location.href='sync_db.php';</script>";
        } else {
            $errMsg = implode("\\n", $errors);
            echo "<script>alert('Synced $successCount columns. Errors:\\n$errMsg'); window.location.href='sync_db.php';</script>";
        }
    }
}

// Handle Sync Index Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['sync_index'])) {
        $table = $_POST['table'];
        $idxKey = $_POST['index_key'];
        $res = $localConn->query("SHOW INDEX FROM `$table` WHERE Key_name = '" . $localConn->real_escape_string($idxKey) . "'");
        $cols = [];
        $isUnique = false;
        while ($r = $res->fetch_assoc()) {
            $cols[$r['Seq_in_index']] = '`' . $r['Column_name'] . '`';
            if ($r['Non_unique'] == 0 && $r['Key_name'] !== 'PRIMARY')
                $isUnique = true;
        }
        ksort($cols);
        $colList = implode(', ', $cols);
        if ($idxKey === 'PRIMARY') {
            $sql = "ALTER TABLE `$table` ADD PRIMARY KEY ($colList)";
        } elseif ($isUnique) {
            $sql = "ALTER TABLE `$table` ADD UNIQUE KEY `$idxKey` ($colList)";
        } else {
            $sql = "ALTER TABLE `$table` ADD INDEX `$idxKey` ($colList)";
        }
        if ($serverConn->query($sql)) {
            echo "<script>alert('Index `$idxKey` on `$table` created on server.'); window.location.href='sync_db.php';</script>";
        } else {
            echo "<script>alert('Error: " . $serverConn->error . "');</script>";
        }
    }

    if (isset($_POST['sync_all_indexes'])) {
        $table = $_POST['table'];
        $idxDiff = compareIndexes($table, $localConn, $serverConn);
        $ok = 0;
        $errs = [];
        foreach ($idxDiff['onlyLocal'] as $idxKey => $info) {
            $res = $localConn->query("SHOW INDEX FROM `$table` WHERE Key_name = '" . $localConn->real_escape_string($idxKey) . "'");
            $cols = [];
            $isUnique = false;
            while ($r = $res->fetch_assoc()) {
                $cols[$r['Seq_in_index']] = '`' . $r['Column_name'] . '`';
                if ($r['Non_unique'] == 0 && $r['Key_name'] !== 'PRIMARY')
                    $isUnique = true;
            }
            ksort($cols);
            $colList = implode(', ', $cols);
            if ($idxKey === 'PRIMARY') {
                $sql = "ALTER TABLE `$table` ADD PRIMARY KEY ($colList)";
            } elseif ($isUnique) {
                $sql = "ALTER TABLE `$table` ADD UNIQUE KEY `$idxKey` ($colList)";
            } else {
                $sql = "ALTER TABLE `$table` ADD INDEX `$idxKey` ($colList)";
            }
            if ($serverConn->query($sql)) {
                $ok++;
            } else {
                $errs[] = "$idxKey: " . $serverConn->error;
            }
        }
        $msg = "Synced $ok index(es)." . (count($errs) ? ' Errors: ' . implode('; ', $errs) : '');
        echo "<script>alert('$msg'); window.location.href='sync_db.php';</script>";
    }
}

// Handle Sync Data Action (Production -> Local)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['sync_data_table'])) {
        $table = $_POST['sync_data_table'];
        $res = syncTableData($table, $localConn, $serverConn);
        if ($res === true) {
            echo "<script>alert('Data for table `$table` synced successfully from Production to Local.'); window.location.href='sync_db.php';</script>";
        } else {
            echo "<script>alert('Error syncing data for `$table`: " . addslashes($res) . "'); window.location.href='sync_db.php';</script>";
        }
    }

    if (isset($_POST['sync_all_data'])) {
        $errors = [];
        $successCount = 0;
        $localTables = getTables($localConn);
        $serverTables = getTables($serverConn);
        $matching = array_intersect($localTables, $serverTables);
        foreach ($matching as $table) {
            $res = syncTableData($table, $localConn, $serverConn);
            if ($res === true) {
                $successCount++;
            } else {
                $errors[] = "$table: $res";
            }
        }
        if (empty($errors)) {
            echo "<script>alert('Data for all $successCount tables synced successfully from Production to Local.'); window.location.href='sync_db.php';</script>";
        } else {
            $errMsg = implode("\\n", $errors);
            echo "<script>alert('Synced data for $successCount tables. Errors:\\n$errMsg'); window.location.href='sync_db.php';</script>";
        }
    }
}

// Fetch table lists
$localTables = getTables($localConn);
$serverTables = getTables($serverConn);

$matchingTables = array_intersect($localTables, $serverTables);
$uniqueToLocal = array_diff($localTables, $serverTables);
$uniqueToServer = array_diff($serverTables, $localTables);

function getTables($conn)
{
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    return $tables;
}

function getColumns($conn, $table)
{
    if (!$conn)
        return [];
    $columns = [];
    $result = $conn->query("SHOW FULL COLUMNS FROM `$table`");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = [
                'Type' => $row['Type'],
                'Collation' => $row['Collation'] ?? 'NULL'
            ];
        }
    }
    return $columns;
}

function getIndexes($conn, $table)
{
    if (!$conn)
        return [];
    $indexes = [];
    $result = $conn->query("SHOW INDEX FROM `$table`");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $key = $row['Key_name'];
            if (!isset($indexes[$key])) {
                $indexes[$key] = [
                    'unique' => $row['Non_unique'] == 0,
                    'columns' => [],
                ];
            }
            $indexes[$key]['columns'][$row['Seq_in_index']] = $row['Column_name'];
        }
    }
    // sort columns by seq
    foreach ($indexes as &$idx)
        ksort($idx['columns']);
    return $indexes;
}

function compareIndexes($table, $localConn, $serverConn)
{
    $local = getIndexes($localConn, $table);
    $server = getIndexes($serverConn, $table);

    $onlyLocal = [];
    $onlyServer = [];
    $mismatch = [];

    foreach ($local as $key => $info) {
        if (!isset($server[$key])) {
            $onlyLocal[$key] = $info;
        } else {
            // check if column lists differ
            if (array_values($info['columns']) !== array_values($server[$key]['columns'])) {
                $mismatch[$key] = [
                    'local' => implode(', ', $info['columns']),
                    'server' => implode(', ', $server[$key]['columns']),
                ];
            }
        }
    }
    foreach ($server as $key => $info) {
        if (!isset($local[$key]))
            $onlyServer[$key] = $info;
    }

    return compact('onlyLocal', 'onlyServer', 'mismatch');
}

function compareColumns($table, $localConn, $serverConn)
{
    $cols1 = getColumns($localConn, $table);
    $cols2 = getColumns($serverConn, $table);

    $typeMismatch = [];
    $collationMismatch = [];

    foreach ($cols1 as $col => $attr1) {
        if (isset($cols2[$col])) {
            if ($attr1['Type'] !== $cols2[$col]['Type']) {
                $typeMismatch[$col] = ['Local' => $attr1['Type'], 'Server' => $cols2[$col]['Type']];
            }
            if ($attr1['Collation'] !== $cols2[$col]['Collation']) {
                $collationMismatch[$col] = ['Local' => $attr1['Collation'], 'Server' => $cols2[$col]['Collation']];
            }
        }
    }

    return [
        'onlyLocal' => array_diff_key($cols1, $cols2),
        'onlyServer' => array_diff_key($cols2, $cols1),
        'typeMismatch' => $typeMismatch,
        'collationMismatch' => $collationMismatch,
    ];
}

function syncTableData($table, $localConn, $serverConn)
{
    try {
        $localConn->query("SET FOREIGN_KEY_CHECKS = 0");

        // Truncate local table
        if (!$localConn->query("TRUNCATE TABLE `$table`")) {
            $err = $localConn->error;
            $localConn->query("SET FOREIGN_KEY_CHECKS = 1");
            return "Truncate error: " . $err;
        }

        // Fetch data from server
        $res = $serverConn->query("SELECT * FROM `$table`");
        if (!$res) {
            $err = $serverConn->error;
            $localConn->query("SET FOREIGN_KEY_CHECKS = 1");
            return "Fetch error: " . $err;
        }

        $fields = [];
        $fieldInfo = $res->fetch_fields();
        foreach ($fieldInfo as $val) {
            $fields[] = "`" . $val->name . "`";
        }

        if (empty($fields)) {
            $localConn->query("SET FOREIGN_KEY_CHECKS = 1");
            return true;
        }

        $fieldList = implode(', ', $fields);

        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $escapedVals = [];
            foreach ($row as $val) {
                if ($val === null) {
                    $escapedVals[] = "NULL";
                } else {
                    $escapedVals[] = "'" . $localConn->real_escape_string($val) . "'";
                }
            }
            $rows[] = "(" . implode(', ', $escapedVals) . ")";
        }

        if (count($rows) > 0) {
            $chunks = array_chunk($rows, 500);
            foreach ($chunks as $chunk) {
                $insertSQL = "INSERT INTO `$table` ($fieldList) VALUES " . implode(', ', $chunk);
                if (!$localConn->query($insertSQL)) {
                    $err = $localConn->error;
                    $localConn->query("SET FOREIGN_KEY_CHECKS = 1");
                    return "Insert error: " . $err;
                }
            }
        }

        $localConn->query("SET FOREIGN_KEY_CHECKS = 1");
        return true;
    } catch (Exception $e) {
        $localConn->query("SET FOREIGN_KEY_CHECKS = 1");
        return $e->getMessage();
    }
}

function getRowCount($conn, $table)
{
    $res = $conn->query("SELECT COUNT(*) FROM `$table`");
    if ($res) {
        $row = $res->fetch_row();
        return intval($row[0]);
    }
    return 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>DB Difference Viewer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f8f9fa;
        }

        h1,
        h2 {
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        th,
        td {
            padding: 10px 15px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #343a40;
            color: white;
        }

        .highlight {
            background: #ffeeba;
        }

        .section-title {
            background: #007bff;
            color: #fff;
            padding: 8px 10px;
            font-weight: bold;
        }

        .cell-danger {
            background-color: #f8d7da;
        }

        .cell-warning {
            background-color: #fff3cd;
        }

        .cell-success {
            background-color: #d4edda;
        }

        .btn-sync {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 4px;
        }

        .btn-sync:hover {
            background-color: #218838;
        }

        .btn-sm {
            font-size: 10px;
            padding: 2px 5px;
            margin-left: 5px;
        }

        .btn-blue {
            background-color: #17a2b8;
        }

        .btn-blue:hover {
            background-color: #138496;
        }
    </style>
</head>

<body>

    <h1>Database Schema Comparison</h1>

    <div class="section-title">Tables Only in Local DB</div>
    <table>
        <tr>
            <th>Table Name</th>
            <th style="width: 150px; text-align: right;">Action</th>
        </tr>
        <?php foreach ($uniqueToLocal as $table): ?>
            <tr>
                <td><?php echo $table; ?></td>
                <td style="text-align: right;">
                    <form method="POST"
                        onsubmit="return confirm('Are you sure you want to create table `<?php echo $table; ?>` on the server?');">
                        <input type="hidden" name="sync_table" value="<?php echo $table; ?>">
                        <button type="submit" class="btn-sync">Sync to Server</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <div class="section-title">Tables Only in Server DB</div>
    <table>
        <tr>
            <th>Table Name</th>
        </tr>
        <?php foreach ($uniqueToServer as $table): ?>
            <tr>
                <td><?php echo $table; ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <div class="section-title">Differences in Matching Tables</div>
    <table>
        <tr>
            <th>Table Name</th>
            <th>Columns Only in Local</th>
            <th>Columns Only in Server</th>
            <th>Column Type Mismatches</th>
            <th>Collation Mismatches</th>
        </tr>
        <?php foreach ($matchingTables as $table): ?>
            <?php
            $colDiff = compareColumns($table, $localConn, $serverConn);
            if (empty($colDiff['onlyLocal']) && empty($colDiff['onlyServer']) && empty($colDiff['typeMismatch']) && empty($colDiff['collationMismatch'])) {
                continue;
            }
            ?>
            <tr class="highlight">
                <td><?php echo $table; ?></td>
                <td class="cell-warning">
                    <?php if (!empty($colDiff['onlyLocal'])): ?>
                        <?php if (count($colDiff['onlyLocal']) > 1): ?>
                            <form method="POST"
                                onsubmit="return confirm('Sync ALL <?php echo count($colDiff['onlyLocal']); ?> missing columns for `<?php echo $table; ?>`?');"
                                style="margin-bottom: 10px; border-bottom: 1px dashed #ccc; padding-bottom: 5px;">
                                <input type="hidden" name="sync_all_columns" value="1">
                                <input type="hidden" name="table" value="<?php echo $table; ?>">
                                <button type="submit" class="btn-sync btn-blue btn-sm" style="margin:0; width:100%;">Sync All
                                    <?php echo count($colDiff['onlyLocal']); ?> Columns</button>
                            </form>
                        <?php endif; ?>

                        <ul style="margin: 0; padding-left: 20px;">
                            <?php foreach ($colDiff['onlyLocal'] as $col => $details): ?>
                                <li style="margin-bottom: 5px;">
                                    <?php echo $col; ?> (<?php echo $details['Type']; ?>)
                                    <form method="POST" style="display:inline;"
                                        onsubmit="return confirm('Sync column `<?php echo $col; ?>` to server?');">
                                        <input type="hidden" name="sync_column" value="1">
                                        <input type="hidden" name="table" value="<?php echo $table; ?>">
                                        <input type="hidden" name="column" value="<?php echo $col; ?>">
                                        <button type="submit" class="btn-sync btn-sm">Sync</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        None
                    <?php endif; ?>
                </td>
                <td class="cell-danger">
                    <?php echo implode('<br>', array_keys($colDiff['onlyServer'])) ?: 'None'; ?>
                </td>
                <td class="cell-warning">
                    <?php
                    if (!empty($colDiff['typeMismatch'])) {
                        foreach ($colDiff['typeMismatch'] as $col => $types) {
                            echo "$col (Local: {$types['Local']}, Server: {$types['Server']})<br>";
                        }
                    } else {
                        echo "None";
                    }
                    ?>
                </td>
                <td class="cell-warning">
                    <?php
                    if (!empty($colDiff['collationMismatch'])) {
                        foreach ($colDiff['collationMismatch'] as $col => $collations) {
                            echo "$col (Local: {$collations['Local']}, Server: {$collations['Server']})<br>";
                        }
                    } else {
                        echo "None";
                    }
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <div class="section-title" style="background:#6f42c1;">Index Differences (Missing / Mismatched)</div>
    <table>
        <tr>
            <th>Table</th>
            <th>Missing on Server (only local)</th>
            <th>Extra on Server (only server)</th>
            <th>Column Mismatch</th>
        </tr>
        <?php
        $anyIdxDiff = false;
        foreach ($matchingTables as $table):
            $idxDiff = compareIndexes($table, $localConn, $serverConn);
            if (empty($idxDiff['onlyLocal']) && empty($idxDiff['onlyServer']) && empty($idxDiff['mismatch']))
                continue;
            $anyIdxDiff = true;
            ?>
            <tr class="highlight">
                <td><strong><?php echo $table; ?></strong></td>
                <td class="cell-warning">
                    <?php if (!empty($idxDiff['onlyLocal'])): ?>
                        <?php if (count($idxDiff['onlyLocal']) > 1): ?>
                            <form method="POST"
                                onsubmit="return confirm('Sync all <?php echo count($idxDiff['onlyLocal']); ?> missing indexes for `<?php echo $table; ?>`?');"
                                style="margin-bottom:8px">
                                <input type="hidden" name="sync_all_indexes" value="1">
                                <input type="hidden" name="table" value="<?php echo $table; ?>">
                                <button type="submit" class="btn-sync btn-blue btn-sm" style="width:100%">Sync All
                                    <?php echo count($idxDiff['onlyLocal']); ?> Indexes</button>
                            </form>
                        <?php endif; ?>
                        <ul style="margin:0;padding-left:18px">
                            <?php foreach ($idxDiff['onlyLocal'] as $idxKey => $info): ?>
                                <li style="margin-bottom:4px">
                                    <code><?php echo $idxKey; ?></code>
                                    (<?php echo $info['unique'] ? 'UNIQUE ' : ''; ?>on:
                                    <?php echo implode(', ', $info['columns']); ?>)
                                    <form method="POST" style="display:inline"
                                        onsubmit="return confirm('Add index `<?php echo $idxKey; ?>` to server?');">
                                        <input type="hidden" name="sync_index" value="1">
                                        <input type="hidden" name="table" value="<?php echo $table; ?>">
                                        <input type="hidden" name="index_key" value="<?php echo $idxKey; ?>">
                                        <button type="submit" class="btn-sync btn-sm">Add</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>None<?php endif; ?>
                </td>
                <td class="cell-danger">
                    <?php if (!empty($idxDiff['onlyServer'])): ?>
                        <ul style="margin:0;padding-left:18px">
                            <?php foreach ($idxDiff['onlyServer'] as $idxKey => $info): ?>
                                <li><code><?php echo $idxKey; ?></code> (<?php echo implode(', ', $info['columns']); ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>None<?php endif; ?>
                </td>
                <td class="cell-warning">
                    <?php if (!empty($idxDiff['mismatch'])): ?>
                        <?php foreach ($idxDiff['mismatch'] as $idxKey => $diff): ?>
                            <code><?php echo $idxKey; ?></code><br>
                            &nbsp;Local: <?php echo $diff['local']; ?><br>
                            &nbsp;Server: <?php echo $diff['server']; ?><br>
                        <?php endforeach; ?>
                    <?php else: ?>None<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$anyIdxDiff): ?>
            <tr>
                <td colspan="4" style="text-align:center;color:#155724;background:#d4edda;font-weight:bold;padding:14px">
                    ✅ All indexes match between local and server!
                </td>
            </tr>
        <?php endif; ?>
    </table>

    <div class="section-title"
        style="background:#28a745; display: flex; justify-content: space-between; align-items: center; padding: 8px 15px;">
        <span>Data Synchronization (Production &rarr; Local)</span>
        <form method="POST"
            onsubmit="return confirm('WARNING: This will overwrite ALL local data for matching tables with production server data. Proceed?');"
            style="margin: 0;">
            <input type="hidden" name="sync_all_data" value="1">
            <button type="submit" class="btn-sync btn-blue" style="font-size: 12px; padding: 4px 12px;">Sync All Tables
                Data to Local</button>
        </form>
    </div>
    <table>
        <tr>
            <th>Table Name</th>
            <th style="width: 150px; text-align: right;">Production Rows</th>
            <th style="width: 150px; text-align: right;">Local Rows</th>
            <th style="width: 200px; text-align: right;">Action</th>
        </tr>
        <?php foreach ($matchingTables as $table): ?>
            <?php
            $prodRows = getRowCount($serverConn, $table);
            $localRows = getRowCount($localConn, $table);
            $isDifferent = ($prodRows !== $localRows);
            ?>
            <tr <?php if ($isDifferent)
                echo 'class="highlight"'; ?>>
                <td><strong><?php echo $table; ?></strong></td>
                <td style="text-align: right; font-weight: bold; color: #111;"><?php echo number_format($prodRows); ?></td>
                <td style="text-align: right; font-weight: bold; color: #555;"><?php echo number_format($localRows); ?></td>
                <td style="text-align: right;">
                    <form method="POST"
                        onsubmit="return confirm('Are you sure you want to overwrite local data for table `<?php echo $table; ?>` with production server data?');">
                        <input type="hidden" name="sync_data_table" value="<?php echo $table; ?>">
                        <button type="submit" class="btn-sync" style="font-size: 11px; padding: 4px 10px;">Sync to
                            Local</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

</body>

</html>