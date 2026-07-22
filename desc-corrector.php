<?php
// admin/desc-corrector.php
$page_title = "Description Corrector";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Fetch products (supports search by SKU, Name, or ID, or default formatting issues)
$search = trim($_GET['search'] ?? '');

if (!empty($search)) {
    $search_param = '%' . $search . '%';
    $search_id = is_numeric($search) ? (int)$search : 0;
    $stmt = $pdo->prepare("SELECT id, name, sku, main_image, description FROM products WHERE deleted_at IS NULL AND (sku LIKE ? OR name LIKE ? OR id = ?) ORDER BY id DESC LIMIT 100");
    $stmt->execute([$search_param, $search_param, $search_id]);
} else {
    $stmt = $pdo->query("SELECT id, name, sku, main_image, description FROM products WHERE deleted_at IS NULL AND (description LIKE '%•%' OR description LIKE '%??%' OR description LIKE '%\\\\n%' OR description LIKE '?%') ORDER BY id DESC LIMIT 100");
}
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    $p['corrected_description'] = implode("\n\n", $formattedLines);
    $cleanedProducts[] = $p;
}
?>

<style>
.desc-corrector-container {
    padding: 25px 30px;
    color: #e2e8f0;
}
.page-header-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #1e1e1e;
    padding: 20px 25px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    margin-bottom: 25px;
}
.page-header-card h1 {
    font-size: 20px;
    font-weight: 700;
    color: #fff;
    margin: 0 0 4px 0;
}
.page-header-card p {
    font-size: 13px;
    color: #94a3b8;
    margin: 0;
}
.info-card {
    background: #181818;
    border: 1px solid rgba(200, 165, 92, 0.3);
    border-left: 4px solid #c8a55c;
    border-radius: 8px;
    padding: 16px 20px;
    font-size: 13px;
    color: #cbd5e1;
    margin-bottom: 25px;
    line-height: 1.6;
}
.info-card strong {
    color: #c8a55c;
    font-size: 14px;
    display: inline-block;
    margin-bottom: 4px;
}
.info-card code {
    background: #090909;
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.2);
    padding: 2px 6px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
}
.search-form {
    display: flex;
    gap: 8px;
    align-items: center;
}
.search-input {
    background: #090909;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 6px;
    color: #fff;
    padding: 9px 14px;
    font-size: 13px;
    outline: none;
    width: 260px;
    transition: border-color 0.2s;
}
.search-input:focus {
    border-color: #c8a55c;
}
.btn-search {
    background: #334155;
    color: #fff;
    border: none;
    padding: 9px 16px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background 0.2s;
}
.btn-search:hover {
    background: #475569;
}
.btn-clear-search {
    background: rgba(255, 255, 255, 0.08);
    color: #94a3b8;
    text-decoration: none;
    padding: 9px 12px;
    border-radius: 6px;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}
.btn-clear-search:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.15);
}
.btn-bulk {
    background: #c8a55c;
    color: #000;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}
.btn-bulk:hover {
    background: #dfb96c;
}
.product-correct-card {
    background: #181818;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    transition: all 0.3s;
}
.product-correct-card:hover {
    border-color: rgba(255, 255, 255, 0.15);
}
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 14px;
    margin-bottom: 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}
.product-meta {
    display: flex;
    align-items: center;
    gap: 12px;
}
.product-img {
    width: 48px;
    height: 60px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}
.product-title {
    font-size: 14px;
    font-weight: 600;
    color: #fff;
    margin: 0 0 4px 0;
}
.product-sku {
    font-size: 12px;
    color: #94a3b8;
    font-family: monospace;
}
.btn-action {
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}
.btn-apply {
    background: #16a34a;
    color: #fff;
}
.btn-apply:hover {
    background: #15803d;
}
.btn-skip {
    background: #334155;
    color: #cbd5e1;
}
.btn-skip:hover {
    background: #475569;
}
.grid-compare {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.desc-box {
    background: #101010;
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 8px;
    padding: 12px 14px;
}
.box-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    margin-bottom: 8px;
    display: block;
}
.desc-preview {
    font-size: 12px;
    color: #cbd5e1;
    white-space: pre-wrap;
    line-height: 1.5;
    font-weight: 300;
}
.desc-textarea {
    width: 100%;
    background: #090909;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 6px;
    color: #fff;
    padding: 10px;
    font-size: 12px;
    line-height: 1.5;
    resize: vertical;
    outline: none;
}
.desc-textarea:focus {
    border-color: #c8a55c;
}
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #181818;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.08);
}
.empty-state i {
    font-size: 48px;
    color: #2ecc71;
    margin-bottom: 16px;
}
.empty-state h3 {
    font-size: 18px;
    color: #fff;
    margin-bottom: 8px;
}
</style>

<div class="desc-corrector-container">

    <!-- Header Section -->
    <div class="page-header-card">
        <div>
            <h1>Format Description Tool</h1>
            <p>Detects and corrects leading bullets (•) and double question marks (??) into clean numbered list formatting.</p>
        </div>
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <form method="GET" action="desc-corrector.php" class="search-form">
                <input type="text" name="search" placeholder="Search SKU, Name or ID..." value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                <button type="submit" class="btn-search"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                <?php if (!empty($search)): ?>
                    <a href="desc-corrector.php" class="btn-clear-search" title="Clear Search"><i class="fa-solid fa-xmark"></i> Clear</a>
                <?php endif; ?>
            </form>
            <?php if (!empty($cleanedProducts)): ?>
                <button onclick="bulkCorrectAll()" id="bulkBtn" class="btn-bulk">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Bulk Correct (Max 50)
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Informational Card -->
    <div class="info-card">
        <strong>💡 Formatting Information:</strong><br>
        This utility detects double question marks (<code>??</code>), leading bullets (<code>•</code>), or unformatted text wrappers often caused by legacy encoding. It automatically converts separators into clean numbered lines (<code>1)</code>, <code>2)</code>, etc.) separated by newlines.
    </div>

    <?php if (empty($cleanedProducts)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-circle-check"></i>
            <h3>All Clear!</h3>
            <p style="color: #94a3b8; font-size: 13px;">No products were found with poorly formatted descriptions (bullets or double question marks).</p>
        </div>
    <?php else: ?>
        <div id="product-list">
            <?php foreach ($cleanedProducts as $p): ?>
                <?php 
                $imgUrl = $p['main_image'] ? ($p['main_image'] && str_starts_with($p['main_image'], 'http') ? $p['main_image'] : $p['main_image']) : 'https://placehold.co/100x120/1A1A1A/D4AF37?text=No+Image';
                ?>
                <div id="row-product-<?php echo $p['id']; ?>" class="product-correct-card">
                    <div class="card-header">
                        <div class="product-meta">
                            <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="" class="product-img">
                            <div>
                                <h3 class="product-title"><?php echo htmlspecialchars($p['name']); ?></h3>
                                <div class="product-sku">SKU: <?php echo htmlspecialchars($p['sku'] ?: 'N/A'); ?> | ID: #<?php echo $p['id']; ?></div>
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
                            <span class="box-label" style="color: #c8a55c;">Corrected Preview (Editable)</span>
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
