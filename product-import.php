<?php
// admin/product-import.php
$page_title = "Bulk Import Products";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Ensure user has permission
if (!current_user_can('manage_products')) {
    die("You do not have permission to import products.");
}

$message = '';
$message_type = 'success';

// Handle DB Wipe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_db') {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $tables = ['product_images', 'products', 'categories', 'blog_images', 'blogs', 'order_items', 'orders', 'cart_items', 'wishlists', 'activity_logs', 'search_logs', 'newsletters'];
    foreach ($tables as $t) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$t'");
        if ($stmt->rowCount() > 0) {
            $pdo->exec("TRUNCATE TABLE `$t`");
        }
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    $message = "Database cleared successfully. Ready for fresh import.";
    $message_type = "success";
}

// Handle File Upload for AJAX processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'File upload error']);
        exit;
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        echo json_encode(['success' => false, 'error' => 'Only CSV files allowed']);
        exit;
    }
    
    $temp_name = 'temp_import_' . time() . '.csv';
    $target_file = __DIR__ . '/uploads/' . $temp_name;
    
    if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0755, true);
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Count rows
        $lines = 0;
        $handle = fopen($target_file, "r");
        if ($handle) {
            while (!feof($handle)) {
                if (fgets($handle) !== false) {
                    $lines++;
                }
            }
            fclose($handle);
        }
        $total_rows = max(0, $lines - 1); // subtract header
        
        echo json_encode([
            'success' => true,
            'file_name' => $temp_name,
            'total_rows' => $total_rows
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save file']);
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Bulk Import Products</h1>
    <a href="products.php" class="page-title-action">Back to Products</a>
    <hr class="wp-header-end">

    <?php if ($message): ?>
        <div class="notice notice-<?php echo $message_type; ?> is-dismissible">
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>

    <div id="poststuff">
        <div id="post-body" class="metabox-holder columns-2">
            <div id="post-body-content">
                <!-- Import UI -->
                <div class="postbox">
                    <h2 class="hndle"><span>WooCommerce CSV Import</span></h2>
                    <div class="inside">
                        <p>Upload your exported WooCommerce CSV. Images will be downloaded locally into <code>uploads/products/</code>.</p>
                        
                        <form id="importForm" enctype="multipart/form-data">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="csvFile">CSV File</label></th>
                                    <td>
                                        <input type="file" id="csvFile" name="csv_file" accept=".csv" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="importLimit">Import Limit</label></th>
                                    <td>
                                        <input type="number" id="importLimit" name="importLimit" value="5" min="1" class="small-text">
                                        <p class="description">Number of rows to process. Set to a high number (e.g., 9999) to import all.</p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="submit" class="button button-primary button-large" id="startImportBtn">
                                    <i class="fa-solid fa-upload"></i> Start Import
                                </button>
                            </p>
                        </form>

                        <!-- Progress UI -->
                        <div id="progressContainer" style="display: none; margin-top: 25px; border-top: 1px solid #ccd0d4; padding-top: 20px;">
                            <h3 id="progressText" style="margin-top:0;">Preparing...</h3>
                            <div style="width: 100%; background: #e0e0e0; border-radius: 4px; overflow: hidden; height: 24px; margin-bottom: 20px;">
                                <div id="progressBar" style="width: 0%; height: 100%; background: #2271b1; transition: width 0.3s; text-align: center; color: white; line-height: 24px; font-size: 12px; font-weight: bold;">0%</div>
                            </div>
                            
                            <!-- Live Stats -->
                            <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                                <div style="flex: 1; background: #f8f9fa; border: 1px solid #ccd0d4; padding: 15px; text-align: center; border-radius: 4px;">
                                    <h4 style="margin: 0 0 5px 0; color: #50575e;">Products Done</h4>
                                    <span id="statDone" style="font-size: 24px; font-weight: bold; color: #2271b1;">0</span> / <span id="statTotal">0</span>
                                </div>
                                <div style="flex: 1; background: #f8f9fa; border: 1px solid #ccd0d4; padding: 15px; text-align: center; border-radius: 4px;">
                                    <h4 style="margin: 0 0 5px 0; color: #50575e;">Images Downloaded</h4>
                                    <span id="statImages" style="font-size: 24px; font-weight: bold; color: #00a32a;">0</span>
                                </div>
                                <div style="flex: 1; background: #f8f9fa; border: 1px solid #ccd0d4; padding: 15px; text-align: center; border-radius: 4px;">
                                    <h4 style="margin: 0 0 5px 0; color: #50575e;">Data Downloaded</h4>
                                    <span id="statSize" style="font-size: 24px; font-weight: bold; color: #d63638;">0 MB</span>
                                </div>
                            </div>

                            <div id="logWindow" style="background: #1e1e1e; color: #00ff00; font-family: monospace; padding: 15px; border-radius: 4px; height: 300px; overflow-y: auto; font-size: 13px;">
                                > Waiting for file upload...
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="postbox-container-1" class="postbox-container">
                <!-- Wipe DB -->
                <div class="postbox" style="border-left: 4px solid #d63638;">
                    <h2 class="hndle"><span>Clear Database</span></h2>
                    <div class="inside">
                        <p>Safely TRUNCATE the catalog database before a fresh import.</p>
                        <form method="POST" onsubmit="return confirm('WARNING: This will permanently delete all products, categories, logs, and orders! Proceed?');">
                            <input type="hidden" name="action" value="clear_db">
                            <button type="submit" class="button" style="color: #d63638; border-color: #d63638;">
                                <i class="fa-solid fa-trash"></i> Wipe Data
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 MB';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

document.getElementById('importForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const fileInput = document.getElementById('csvFile');
    const limitInput = document.getElementById('importLimit');
    if (!fileInput.files[0]) return;
    
    const btn = document.getElementById('startImportBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading...';
    
    const formData = new FormData();
    formData.append('csv_file', fileInput.files[0]);
    
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const logWindow = document.getElementById('logWindow');
    
    // Stats elements
    const statDone = document.getElementById('statDone');
    const statTotal = document.getElementById('statTotal');
    const statImages = document.getElementById('statImages');
    const statSize = document.getElementById('statSize');
    
    progressContainer.style.display = 'block';
    
    let totalImages = 0;
    let totalBytes = 0;
    
    function logMessage(msg, isError = false) {
        const div = document.createElement('div');
        div.innerHTML = '> ' + msg;
        if (isError) div.style.color = '#ff6b6b';
        logWindow.appendChild(div);
        logWindow.scrollTop = logWindow.scrollHeight;
    }
    
    try {
        logMessage("Uploading CSV file to server...");
        const response = await fetch('product-import.php', {
            method: 'POST',
            body: formData
        });
        
        const initData = await response.json();
        if (!initData.success) {
            logMessage(initData.error, true);
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-upload"></i> Start Import';
            return;
        }
        
        const fileName = initData.file_name;
        const requestedLimit = parseInt(limitInput.value, 10) || 5;
        const totalRowsToProcess = Math.min(initData.total_rows, requestedLimit);
        
        statTotal.innerText = totalRowsToProcess;
        logMessage(`File uploaded. Beginning row-by-row import of ${totalRowsToProcess} products...`);
        
        for (let i = 1; i <= totalRowsToProcess; i++) {
            progressText.innerText = `Importing Row ${i} of ${totalRowsToProcess}...`;
            const percentage = ((i) / totalRowsToProcess) * 100;
            progressBar.style.width = percentage + '%';
            progressBar.innerText = Math.round(percentage) + '%';
            
            const rowData = new FormData();
            rowData.append('file_name', fileName);
            rowData.append('row_index', i);
            
            try {
                const rowRes = await fetch('api/import_row.php', {
                    method: 'POST',
                    body: rowData
                });
                
                const rowResult = await rowRes.json();
                if (rowResult.success) {
                    logMessage(rowResult.log);
                    
                    if (rowResult.images_downloaded) {
                        totalImages += rowResult.images_downloaded;
                        statImages.innerText = totalImages;
                    }
                    if (rowResult.image_size) {
                        totalBytes += rowResult.image_size;
                        statSize.innerText = formatBytes(totalBytes);
                    }
                } else {
                    logMessage(`Row ${i} Error: ` + rowResult.error, true);
                }
            } catch (err) {
                logMessage(`Row ${i} Network Error: ` + err.message, true);
            }
            
            statDone.innerText = i;
        }
        
        progressBar.style.width = '100%';
        progressBar.innerText = '100%';
        progressText.innerText = "Import Complete!";
        logMessage("Finished processing! You can now check the Products page.", false);
        btn.innerHTML = 'Done!';
        
    } catch (error) {
        logMessage("Server error during upload: " + error.message, true);
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-upload"></i> Start Import';
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
