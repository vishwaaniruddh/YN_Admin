<?php
// admin/desc-corrector.php
$page_title = "Description Corrector";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Fetch products (supports search by SKU, Name, or ID, or default formatting issues)
$search = trim($_GET['search'] ?? '');

// Helper: check if a description has formatting issues that need correction
function has_format_issues($desc) {
    if (empty($desc)) return false;
    if (strpos($desc, '•') !== false) return true;
    if (strpos($desc, '??') !== false) return true;
    if (strpos($desc, '\n') !== false) return true;
    if (strpos($desc, '\r') !== false) return true;
    if (str_starts_with(trim($desc), '?')) return true;
    return false;
}

if (!empty($search)) {
    $search_param = '%' . $search . '%';
    $search_id = is_numeric($search) ? (int)$search : 0;
    $stmt = $pdo->prepare("SELECT id, name, sku, main_image, description FROM products WHERE deleted_at IS NULL AND (sku LIKE ? OR name LIKE ? OR id = ?) ORDER BY id DESC LIMIT 200");
    $stmt->execute([$search_param, $search_param, $search_id]);
} else {
    $stmt = $pdo->query("SELECT id, name, sku, main_image, description FROM products WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 500");
}
$allProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter only products with actual formatting issues
$products = array_filter($allProducts, function($p) {
    return has_format_issues($p['description'] ?? '');
});
// Limit to 100
$products = array_slice($products, 0, 100);

$cleanedProducts = [];
foreach ($products as $p) {
    $desc = $p['description'] ?? '';
    
    // 1. Replace literal '\r\n', '\n?', '\n' strings with real newlines
    $cleaned = str_replace(['\r\n', '\n?', '\n', '\r'], "\n", $desc);
    
    // 2. Process line by line
    $rawLines = explode("\n", $cleaned);
    $formattedLines = [];

    foreach ($rawLines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        // Strip leading bullets •, ?, or spaces
        $line = preg_replace('/^[\?\•\s]+/', '', $line);
        $line = trim($line);

        // Strip surrounding double quotes
        if (str_starts_with($line, '"') && str_ends_with($line, '"')) {
            $line = trim(substr($line, 1, -1));
        }

        // If line contains '??', split it into sub-items
        if (str_contains($line, '??')) {
            $parts = explode('??', $line);
            foreach ($parts as $part) {
                $part = trim(preg_replace('/^[\?\•\s]+/', '', $part));
                if (!empty($part)) {
                    $formattedLines[] = $part;
                }
            }
        } else if (!empty($line)) {
            $formattedLines[] = $line;
        }
    }

    $corrected = implode("\n\n", $formattedLines);
    
    // Skip products where the corrected description is identical to the original
    // (i.e., no actual formatting issues to fix)
    if (trim($corrected) === trim($desc)) {
        continue;
    }
    
    $p['corrected_description'] = $corrected;
    $cleanedProducts[] = $p;
}
?>

<style>
.desc-corrector-container {
    padding: 20px;
    color: #09090b;
}
.page-header-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
    padding: 16px 20px;
    border-radius: 8px;
    border: 1px solid #e4e4e7;
    margin-bottom: 20px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}
.page-header-card h1 {
    font-size: 18px;
    font-weight: 600;
    color: #09090b;
    margin: 0 0 4px 0;
    letter-spacing: -0.02em;
}
.page-header-card p {
    font-size: 12.5px;
    color: #71717a;
    margin: 0;
}
.info-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-left: 3px solid #3b82f6;
    border-radius: 6px;
    padding: 12px 16px;
    font-size: 12.5px;
    color: #334155;
    margin-bottom: 20px;
    line-height: 1.5;
}
.info-card strong {
    color: #1e293b;
    font-size: 13px;
    display: inline-block;
    margin-bottom: 2px;
}
.info-card code {
    background: #f1f5f9;
    color: #0284c7;
    border: 1px solid #e2e8f0;
    padding: 1px 5px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 11.5px;
}
.search-form {
    display: flex;
    gap: 8px;
    align-items: center;
}
.search-input {
    background: #ffffff;
    border: 1px solid #e4e4e7;
    border-radius: 6px;
    color: #09090b;
    padding: 0 12px;
    height: 32px;
    font-size: 12.5px;
    outline: none;
    width: 240px;
    transition: all 0.15s ease;
}
.search-input:focus {
    border-color: #09090b;
    box-shadow: 0 0 0 1px #09090b;
}
.btn-search {
    background: #09090b;
    color: #fff;
    border: none;
    height: 32px;
    padding: 0 14px;
    border-radius: 6px;
    font-weight: 500;
    font-size: 12.5px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background 0.15s;
}
.btn-search:hover {
    background: #27272a;
}
.btn-clear-search {
    background: #f4f4f5;
    color: #71717a;
    text-decoration: none;
    height: 32px;
    padding: 0 10px;
    border-radius: 6px;
    border: 1px solid #e4e4e7;
    font-size: 12.5px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-clear-search:hover {
    color: #09090b;
    background: #e4e4e7;
}
.btn-bulk {
    background: #f4f4f5;
    color: #09090b;
    border: 1px solid #e4e4e7;
    height: 32px;
    padding: 0 14px;
    border-radius: 6px;
    font-weight: 500;
    font-size: 12.5px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s;
}
.btn-bulk:hover {
    background: #e4e4e7;
}
.product-correct-card {
    background: #ffffff;
    border: 1px solid #e4e4e7;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 16px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 12px;
    margin-bottom: 14px;
    border-bottom: 1px solid #f4f4f5;
}
.product-meta {
    display: flex;
    align-items: center;
    gap: 12px;
}
.product-img-wrap {
    width: 44px;
    height: 56px;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid #e4e4e7;
    background: #f4f4f5;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.product-img-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.product-title {
    font-size: 13.5px;
    font-weight: 500;
    color: #09090b;
    margin: 0 0 3px 0;
}
.product-sku {
    font-size: 11.5px;
    color: #71717a;
    font-family: monospace;
}
.btn-action {
    height: 32px;
    padding: 0 14px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s;
}
.btn-apply {
    background: #10b981;
    color: #fff;
}
.btn-apply:hover {
    background: #059669;
}
.btn-skip {
    background: #f4f4f5;
    color: #71717a;
    border: 1px solid #e4e4e7;
}
.btn-skip:hover {
    background: #e4e4e7;
    color: #09090b;
}
.grid-compare {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}
.desc-box {
    background: #fafafa;
    border: 1px solid #e4e4e7;
    border-radius: 6px;
    padding: 12px;
}
.box-label {
    font-size: 10.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #71717a;
    margin-bottom: 6px;
    display: block;
}
.desc-preview {
    font-size: 12.5px;
    color: #52525b;
    white-space: pre-wrap;
    line-height: 1.5;
}
.desc-textarea {
    width: 100%;
    background: #ffffff;
    border: 1px solid #e4e4e7;
    border-radius: 6px;
    color: #09090b;
    padding: 8px 10px;
    font-size: 12.5px;
    line-height: 1.5;
    resize: vertical;
    outline: none;
    font-family: inherit;
}
.desc-textarea:focus {
    border-color: #09090b;
    box-shadow: 0 0 0 1px #09090b;
}
.empty-state {
    text-align: center;
    padding: 50px 20px;
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #e4e4e7;
}
.empty-state i {
    font-size: 36px;
    color: #10b981;
    margin-bottom: 10px;
}
.empty-state h3 {
    font-size: 16px;
    font-weight: 600;
    color: #09090b;
    margin-bottom: 4px;
}
</style>

<div class="desc-corrector-container">
    
    <!-- Top Header Card -->
    <div class="page-header-card">
        <div>
            <h1>Format Description Tool</h1>
            <p>Detects and corrects leading bullets (&bull;) and double question marks (??) into clean numbered list formatting.</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <form method="GET" action="desc-corrector.php" class="search-form">
                <input type="text" name="search" class="search-input" placeholder="Search SKU, Name or ID..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-search"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                <?php if (!empty($search)): ?>
                    <a href="desc-corrector.php" class="btn-clear-search" title="Clear search"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </form>

            <?php if (!empty($cleanedProducts)): ?>
                <button onclick="bulkCorrectAll()" id="btn-bulk-correct" class="btn-bulk">
                    <i class="fa-solid fa-wand-magic-sparkles" style="color: #6366f1;"></i> Bulk Correct (Max <?php echo count($cleanedProducts); ?>)
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Informational Card -->
    <div class="info-card">
        <strong>💡 Formatting Information:</strong><br>
        This utility detects double question marks (<code>??</code>), leading bullets (<code>&bull;</code>), or unformatted text wrappers often caused by legacy encoding. It automatically converts separators into clean numbered lines (<code>1)</code>, <code>2)</code>, etc.) separated by newlines.
    </div>

    <?php if (empty($cleanedProducts)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-circle-check"></i>
            <h3>All Clear!</h3>
            <p style="color: #71717a; font-size: 13px;">No products were found with poorly formatted descriptions (bullets or double question marks).</p>
        </div>
    <?php else: ?>
        <div id="product-list">
            <?php foreach ($cleanedProducts as $p): ?>
                <?php 
                $imgUrl = $p['main_image'] ?: '';
                ?>
                <div id="row-product-<?php echo $p['id']; ?>" class="product-correct-card">
                    <div class="card-header">
                        <div class="product-meta">
                            <div class="product-img-wrap">
                                <?php if ($imgUrl): ?>
                                    <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; color: #a1a1aa;">
                                        <i class="fa-solid fa-gem" style="font-size: 13px;"></i>
                                    </div>
                                <?php else: ?>
                                    <i class="fa-solid fa-gem" style="font-size: 13px; color: #a1a1aa;"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h3 class="product-title"><?php echo htmlspecialchars($p['name']); ?></h3>
                                <div class="product-sku">SKU: <?php echo htmlspecialchars($p['sku'] ?: 'N/A'); ?> &bull; ID: #<?php echo $p['id']; ?></div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button onclick="saveCorrection(this, <?php echo $p['id']; ?>)" class="btn-action btn-apply">
                                <i class="fa-solid fa-check"></i> Apply
                            </button>
                            <button onclick="skipRow(<?php echo $p['id']; ?>)" class="btn-action btn-skip">
                                Skip
                            </button>
                        </div>
                    </div>

                    <div class="grid-compare">
                        <div class="desc-box">
                            <span class="box-label">Original Description</span>
                            <div class="desc-preview"><?php echo htmlspecialchars($p['description']); ?></div>
                        </div>
                        <div class="desc-box">
                            <span class="box-label" style="color: #6366f1;">Corrected Preview (Editable)</span>
                            <textarea id="correct-product-<?php echo $p['id']; ?>" rows="5" class="desc-textarea"><?php echo htmlspecialchars($p['corrected_description']); ?></textarea>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<script>
function skipRow(id) {
    const row = document.getElementById(`row-product-${id}`);
    if (row) {
        row.style.opacity = '0';
        row.style.transform = 'translateY(-10px)';
        setTimeout(() => {
            row.remove();
            checkEmpty();
        }, 300);
    }
}

function checkEmpty() {
    const remaining = document.querySelectorAll('.product-correct-card').length;
    if (remaining === 0) {
        window.location.reload();
    }
}

async function saveCorrection(btn, id) {
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

    const textarea = document.getElementById(`correct-product-${id}`);
    const correctedValue = textarea ? textarea.value.trim() : '';

    if (!correctedValue) {
        alert('Description cannot be empty');
        btn.disabled = false;
        btn.innerHTML = originalText;
        return;
    }

    try {
        const response = await fetch('api/desc_corrector.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, description: correctedValue })
        });

        const data = await response.json();
        if (data.success) {
            skipRow(id);
        } else {
            alert('Error: ' + (data.error || data.message));
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (err) {
        console.error(err);
        alert('A network error occurred.');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

async function bulkCorrectAll() {
    const bulkBtn = document.getElementById('bulkBtn');
    if (!confirm('Are you sure you want to bulk apply all corrected descriptions?')) {
        return;
    }

    bulkBtn.disabled = true;
    bulkBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Correcting...';

    const rows = Array.from(document.querySelectorAll('.product-correct-card')).slice(0, 50);
    for (let row of rows) {
        const id = parseInt(row.id.replace('row-product-', ''));
        const textarea = document.getElementById(`correct-product-${id}`);
        const correctedValue = textarea ? textarea.value.trim() : '';

        if (correctedValue) {
            try {
                const response = await fetch('api/desc_corrector.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, description: correctedValue })
                });
                const data = await response.json();
                if (data.success) {
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 300);
                }
            } catch (err) {
                console.error('Failed to correct product ID ' + id, err);
            }
        }
    }

    alert('Bulk description corrections complete!');
    window.location.reload();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
