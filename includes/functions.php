<?php
// admin/includes/functions.php

// Load Composer Autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Define Upload Paths
define('UPLOAD_DIR', __DIR__ . '/../uploads/products/');
define('THUMB_DIR', UPLOAD_DIR . 'thumbs/');

// Ensure directories exist
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
if (!is_dir(THUMB_DIR)) {
    mkdir(THUMB_DIR, 0755, true);
}

// 1. JWT Config and Helper
define('JWT_SECRET', 'YosshitaNehaFashionStudioSecretKey_2026!');
define('JWT_ALGO', 'HS256');

function generate_jwt($userId, $username, $role = 'admin') {
    $issuedAt = time();
    $expirationTime = $issuedAt + (3600 * 24);  // 1 day expiration
    $payload = [
        'iat' => $issuedAt,
        'exp' => $expirationTime,
        'sub' => $userId,
        'data' => [
            'username' => $username,
            'role' => $role
        ]
    ];
    return JWT::encode($payload, JWT_SECRET, JWT_ALGO);
}

function verify_jwt($token) {
    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, JWT_ALGO));
        return (array) $decoded;
    } catch (Exception $e) {
        return false;
    }
}

// 2. Slug Generator Helper
function generate_slug($text) {
    // replace non letter or digits by -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    // transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    // remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);
    // trim
    $text = trim($text, '-');
    // remove duplicate -
    $text = preg_replace('~-+~', '-', $text);
    // lowercase
    $text = strtolower($text);

    if (empty($text)) {
        return 'n-a';
    }
    return $text;
}

// 3. Image Upload and Thumbnail Generation Helper
function upload_image($file_array, $target_dir, $filename_without_ext = null) {
    if (!isset($file_array) || $file_array['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_info = getimagesize($file_array['tmp_name']);
    
    if (!$file_info || !in_array($file_info['mime'], $allowed_types)) {
        return ['error' => 'Invalid image format. Allowed formats: JPG, PNG, GIF, WEBP.'];
    }

    $absolute_target_dir = __DIR__ . '/../' . ltrim($target_dir, '/');
    if (!is_dir($absolute_target_dir)) {
        mkdir($absolute_target_dir, 0755, true);
    }
    
    $thumb_dir = $absolute_target_dir . '/thumbs/';
    if (!is_dir($thumb_dir)) {
        mkdir($thumb_dir, 0755, true);
    }

    $ext = pathinfo($file_array['name'], PATHINFO_EXTENSION);
    
    if ($filename_without_ext) {
        $filename = $filename_without_ext . '.' . $ext;
    } else {
        $filename = uniqid() . '.' . $ext;
    }
    
    $target_file = rtrim($absolute_target_dir, '/') . '/' . $filename;
    $relative_target_dir = rtrim(ltrim($target_dir, '/'), '/');

    if (move_uploaded_file($file_array['tmp_name'], $target_file)) {
        // Generate Thumbnail
        $thumb_filename = 'thumb_' . $filename;
        $thumb_target = rtrim($thumb_dir, '/') . '/' . $thumb_filename;
        
        if (generate_square_thumbnail($target_file, $thumb_target, 150)) {
            return [
                'filename' => $filename,
                'filepath' => $relative_target_dir . '/' . $filename,
                'thumbpath' => $relative_target_dir . '/thumbs/' . $thumb_filename
            ];
        } else {
            return [
                'filename' => $filename,
                'filepath' => $relative_target_dir . '/' . $filename,
                'thumbpath' => $relative_target_dir . '/' . $filename
            ];
        }
    }

    return ['error' => 'Failed to move uploaded file.'];
}

// Generates a cropped square thumbnail using PHP GD
function generate_square_thumbnail($source_path, $dest_path, $thumb_size = 150) {
    list($width, $height, $type) = getimagesize($source_path);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            $src_img = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $src_img = imagecreatefrompng($source_path);
            // preserve transparency for PNG
            imagealphablending($src_img, true);
            break;
        case IMAGETYPE_GIF:
            $src_img = imagecreatefromgif($source_path);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $src_img = imagecreatefromwebp($source_path);
            } else {
                return false;
            }
            break;
        default:
            return false;
    }

    if (!$src_img) {
        return false;
    }

    // Crop to square calculation
    $thumb_img = imagecreatetruecolor($thumb_size, $thumb_size);

    // Keep PNG transparency in thumbnail
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
        imagealphablending($thumb_img, false);
        imagesavealpha($thumb_img, true);
        $transparent = imagecolorallocatealpha($thumb_img, 255, 255, 255, 127);
        imagefilledrectangle($thumb_img, 0, 0, $thumb_size, $thumb_size, $transparent);
    }

    $smallest_side = min($width, $height);
    $x_offset = ($width - $smallest_side) / 2;
    $y_offset = ($height - $smallest_side) / 2;

    // Resample
    imagecopyresampled(
        $thumb_img, 
        $src_img, 
        0, 0, 
        $x_offset, $y_offset, 
        $thumb_size, $thumb_size, 
        $smallest_side, $smallest_side
    );

    // Save
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($thumb_img, $dest_path, 85);
            break;
        case IMAGETYPE_PNG:
            imagepng($thumb_img, $dest_path, 8);
            break;
        case IMAGETYPE_GIF:
            imagegif($thumb_img, $dest_path);
            break;
        case IMAGETYPE_WEBP:
            imagewebp($thumb_img, $dest_path, 80);
            break;
    }

    imagedestroy($src_img);
    imagedestroy($thumb_img);
    return true;
}

// 4. Input Sanitization Helpers
function sanitize_html($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    if (!headers_sent()) {
        header("Location: $url");
    } else {
        echo "<script>window.location.href='" . $url . "';</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=" . $url . "'></noscript>";
    }
    exit();
}

// 5. Category Tree / Hierarchy Generator
function get_category_tree($categories, $parentId = null, $depth = 0) {
    $branch = [];
    foreach ($categories as $category) {
        if ($category['parent_id'] == $parentId) {
            $category['depth'] = $depth;
            $branch[] = $category;
            $children = get_category_tree($categories, $category['id'], $depth + 1);
            if ($children) {
                $branch = array_merge($branch, $children);
            }
        }
    }
    return $branch;
}

function build_nested_category_tree($categories, $parentId = null, $parentPath = '') {
    $branch = [];
    foreach ($categories as $category) {
        if ($category['parent_id'] == $parentId) {
            $currentPath = $parentPath ? $parentPath . '/' . $category['slug'] : $category['slug'];
            $category['path'] = $currentPath;
            $children = build_nested_category_tree($categories, $category['id'], $currentPath);
            if ($children) {
                $category['children'] = $children;
            } else {
                $category['children'] = [];
            }
            $branch[] = $category;
        }
    }
    return $branch;
}

function get_all_child_category_ids($pdo, $categoryId) {
    $ids = [$categoryId];
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ? AND deleted_at IS NULL");
    $stmt->execute([$categoryId]);
    $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($children as $childId) {
        $ids = array_merge($ids, get_all_child_category_ids($pdo, $childId));
    }
    return $ids;
}

// 6. Fetch URL Content via cURL
function fetch_url_content($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // bypass SSL checks locally
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

// 7. Scrape Product Details from URL
function scrape_product_from_url($url) {
    $html = fetch_url_content($url);
    if (empty($html)) {
        return false;
    }

    $product_data = [
        'name' => '',
        'description' => '',
        'price' => 0.0,
        'sku' => '',
        'main_image' => '',
        'gallery_images' => []
    ];

    // Try parsing JSON-LD first
    // Regex to find all <script type="application/ld+json">...</script>
    if (preg_match_all('~<script\b[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>~is', $html, $matches)) {
        foreach ($matches[1] as $json_string) {
            $json = json_decode(trim($json_string), true);
            if ($json) {
                // If it is a list or nested structure
                $schemas = isset($json['@graph']) ? $json['@graph'] : [$json];
                foreach ($schemas as $schema) {
                    if (isset($schema['@type'])) {
                        $types = is_array($schema['@type']) ? $schema['@type'] : [$schema['@type']];
                        $is_product = false;
                        foreach ($types as $type) {
                            if (is_string($type) && (strtolower($type) === 'product' || strpos(strtolower($type), 'product') !== false)) {
                                $is_product = true;
                                break;
                            }
                        }
                        
                        if ($is_product) {
                            // Name
                            if (isset($schema['name'])) {
                                $product_data['name'] = is_string($schema['name']) ? html_entity_decode($schema['name']) : '';
                            }
                            
                            // Description
                            if (isset($schema['description'])) {
                                $product_data['description'] = is_string($schema['description']) ? strip_tags(html_entity_decode($schema['description'])) : '';
                            }
                            
                            // SKU
                            if (isset($schema['sku'])) {
                                $product_data['sku'] = is_string($schema['sku']) ? $schema['sku'] : '';
                            }
                            
                            // Price
                            if (isset($schema['offers'])) {
                                $offers = $schema['offers'];
                                if (isset($offers['price'])) {
                                    $product_data['price'] = (float)$offers['price'];
                                } elseif (isset($offers['lowPrice'])) {
                                    $product_data['price'] = (float)$offers['lowPrice'];
                                } elseif (is_array($offers) && isset($offers[0]['price'])) {
                                    $product_data['price'] = (float)$offers[0]['price'];
                                }
                            }

                            // Images
                            if (isset($schema['image'])) {
                                $images = $schema['image'];
                                if (is_string($images)) {
                                    $product_data['main_image'] = $images;
                                } elseif (is_array($images)) {
                                    if (isset($images[0])) {
                                        $product_data['main_image'] = is_array($images[0]) ? ($images[0]['url'] ?? '') : $images[0];
                                    }
                                    // Put other images in gallery
                                    foreach ($images as $img) {
                                        $img_url = is_array($img) ? ($img['url'] ?? '') : $img;
                                        if ($img_url && !in_array($img_url, $product_data['gallery_images'])) {
                                            $product_data['gallery_images'][] = $img_url;
                                        }
                                    }
                                }
                            }
                            
                            // If we found a product schema and it has a name, break out
                            if (!empty($product_data['name'])) {
                                break 2;
                            }
                        }
                    }
                }
            }
        }
    }

    // Try parsing OpenGraph meta tags if details are missing
    if (empty($product_data['name'])) {
        if (preg_match('~<meta\b[^>]*property=["\']og:title["\'][^>]*content=["\'](.*?)["\']~is', $html, $matches)) {
            $product_data['name'] = html_entity_decode($matches[1]);
        } elseif (preg_match('~<meta\b[^>]*name=["\']twitter:title["\'][^>]*content=["\'](.*?)["\']~is', $html, $matches)) {
            $product_data['name'] = html_entity_decode($matches[1]);
        } elseif (preg_match('~<title>(.*?)</title>~is', $html, $matches)) {
            $product_data['name'] = html_entity_decode(trim($matches[1]));
        }
    }

    if (empty($product_data['description'])) {
        if (preg_match('~<meta\b[^>]*property=["\']og:description["\'][^>]*content=["\'](.*?)["\']~is', $html, $matches)) {
            $product_data['description'] = html_entity_decode($matches[1]);
        } elseif (preg_match('~<meta\b[^>]*name=["\']description["\'][^>]*content=["\'](.*?)["\']~is', $html, $matches)) {
            $product_data['description'] = html_entity_decode($matches[1]);
        }
    }

    if (empty($product_data['main_image'])) {
        if (preg_match('~<meta\b[^>]*property=["\']og:image["\'][^>]*content=["\'](.*?)["\']~is', $html, $matches)) {
            $product_data['main_image'] = $matches[1];
        }
    }

    if (empty($product_data['price'])) {
        if (preg_match('~<meta\b[^>]*property=["\']product:price:amount["\'][^>]*content=["\'](.*?)["\']~is', $html, $matches)) {
            $product_data['price'] = (float)$matches[1];
        }
    }

    // Try to find more image elements as gallery fallbacks
    if (empty($product_data['gallery_images']) && !empty($product_data['main_image'])) {
        $product_data['gallery_images'][] = $product_data['main_image'];
    }

    // Resolve relative image URLs to absolute ones
    $url_info = parse_url($url);
    $base_url = $url_info['scheme'] . '://' . $url_info['host'];
    
    if (!empty($product_data['main_image'])) {
        $product_data['main_image'] = resolve_relative_url($product_data['main_image'], $base_url);
    }
    
    foreach ($product_data['gallery_images'] as $key => $gal_url) {
        $product_data['gallery_images'][$key] = resolve_relative_url($gal_url, $base_url);
    }

    return $product_data;
}

// Helper to convert relative URLs to absolute URLs
function resolve_relative_url($url, $base_url) {
    if (empty($url)) return '';
    if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
        return $url;
    }
    if (strpos($url, '//') === 0) {
        return 'https:' . $url;
    }
    if (strpos($url, '/') === 0) {
        return $base_url . $url;
    }
    return $base_url . '/' . $url;
}

// 8. Download Remote Image and generate local paths + thumbnails
function download_remote_image($image_url, $target_dir, $filename_without_ext = null) {
    $image_url = trim($image_url);
    if (empty($image_url)) return false;

    // Fetch the image file contents via cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $image_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/115.0.0.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($http_code !== 200 || empty($data)) {
        return false;
    }

    $absolute_target_dir = __DIR__ . '/../' . ltrim($target_dir, '/');
    if (!is_dir($absolute_target_dir)) {
        mkdir($absolute_target_dir, 0755, true);
    }
    
    $thumb_dir = $absolute_target_dir . '/thumbs/';
    if (!is_dir($thumb_dir)) {
        mkdir($thumb_dir, 0755, true);
    }

    // Determine extension from Content-Type or path
    $ext = 'jpg'; // default
    if (strpos($content_type, 'image/png') !== false) {
        $ext = 'png';
    } elseif (strpos($content_type, 'image/gif') !== false) {
        $ext = 'gif';
    } elseif (strpos($content_type, 'image/webp') !== false) {
        $ext = 'webp';
    } else {
        $path_ext = strtolower(pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (in_array($path_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $ext = ($path_ext === 'jpeg') ? 'jpg' : $path_ext;
        }
    }

    if ($filename_without_ext) {
        $filename = $filename_without_ext . '.' . $ext;
    } else {
        $filename = uniqid() . '.' . $ext;
    }
    
    $target_path = rtrim($absolute_target_dir, '/') . '/' . $filename;
    $relative_target_dir = rtrim(ltrim($target_dir, '/'), '/');

    // Write file to disk
    if (file_put_contents($target_path, $data) === false) {
        return false;
    }

    // Generate square thumbnail
    $thumb_filename = 'thumb_' . $filename;
    $thumb_target = rtrim($thumb_dir, '/') . '/' . $thumb_filename;

    if (generate_square_thumbnail($target_path, $thumb_target, 150)) {
        return [
            'filename' => $filename,
            'filepath' => $relative_target_dir . '/' . $filename,
            'thumbpath' => $relative_target_dir . '/thumbs/' . $thumb_filename
        ];
    } else {
        return [
            'filename' => $filename,
            'filepath' => $relative_target_dir . '/' . $filename,
            'thumbpath' => $relative_target_dir . '/' . $filename
        ];
    }
}

// 9. Activity Logging Helper
function log_activity($pdo, $action, $entity_type = null, $entity_id = null, $details = null, $user_id = null, $user_type = 'admin') {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    
    // For admin actions, try to get user_id from session if not provided
    if ($user_id === null && $user_type === 'admin' && session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['admin_id'])) {
        $user_id = $_SESSION['admin_id'];
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, user_type, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $user_type, $action, $entity_type, $entity_id, $details, $ip_address]);
        return true;
    } catch (PDOException $e) {
        // Silently fail logging rather than breaking the application
        return false;
    }
}

// 10. Role-Based Access Control (RBAC) Helpers
function get_user_role() {
    if (isset($_SESSION['admin_role'])) {
        return $_SESSION['admin_role'];
    }
    
    // Fallback for existing sessions before role column was added
    if (isset($_SESSION['admin_id'])) {
        require __DIR__ . '/../config/db.php';
        try {
            $stmt = $pdo->prepare("SELECT role FROM admins WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $role = $stmt->fetchColumn();
            if ($role) {
                $_SESSION['admin_role'] = $role;
                return $role;
            }
        } catch (Exception $e) {
            // Fail silently
        }
        return 'administrator'; // Safe fallback if DB check fails for main admin
    }
    
    return 'guest';
}

function current_user_can($capability) {
    $role = get_user_role();
    
    // Define role capabilities
    $capabilities = [
        'administrator' => [
            'manage_users' => true,
            'manage_settings' => true,
            'manage_products' => true,
            'delete_products' => true,
            'view_logs' => true
        ],
        'shop_manager' => [
            'manage_users' => false,
            'manage_settings' => false,
            'manage_products' => true,
            'delete_products' => true,
            'view_logs' => true
        ],
        'editor' => [
            'manage_users' => false,
            'manage_settings' => false,
            'manage_products' => true,
            'delete_products' => false,
            'view_logs' => false
        ]
    ];
    
    if (isset($capabilities[$role]) && isset($capabilities[$role][$capability])) {
        return $capabilities[$role][$capability];
    }
    
    return false;
}

/**
 * Format numeric order ID into standardized luxury order number (e.g., YNFS_1001, YNFS_1008)
 */
function format_order_number($id) {
    if (empty($id)) return 'YNFS_1000';
    if (is_string($id) && strpos($id, 'YNFS_') === 0) {
        return $id;
    }
    return 'YNFS_' . (1000 + (int)$id);
}

/**
 * Get dynamic Frontend URL depending on environment (Production vs Local)
 */
function get_frontend_url($path = '') {
    $httpHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    
    $isProduction = (
        str_contains($httpHost, 'yosshitaneha.com') || 
        str_contains($docRoot, 'u464193275') ||
        (!str_contains($httpHost, 'localhost') && !str_contains($httpHost, '127.0.0.1') && !empty($httpHost))
    );

    $baseUrl = $isProduction ? 'https://yosshitaneha.com' : 'http://localhost:5173';
    
    $path = ltrim($path, '/');
    return $path !== '' ? $baseUrl . '/' . $path : $baseUrl;
}

/**
 * Send System Email using database configured SMTP / PHPMailer settings
 */
/**
 * Send System Email using database configured SMTP / PHPMailer settings
 */
function send_system_email($pdo, $toEmail, $subject, $bodyHtml, $altBody = '', $embeddedImages = []) {
    $settings = [];
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'smtp_%' OR setting_key LIKE 'mail_%'");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {}

    $mailer = $settings['mail_mailer'] ?? 'smtp';
    $host = $settings['smtp_host'] ?? 'smtp.gmail.com';
    $port = (int)($settings['smtp_port'] ?? 587);
    $encryption = strtolower($settings['smtp_encryption'] ?? 'tls');
    $username = $settings['smtp_username'] ?? '';
    $password = $settings['smtp_password'] ?? '';
    $fromEmail = !empty($settings['smtp_from_email']) ? $settings['smtp_from_email'] : 'info@yosshitaneha.com';
    $fromName = !empty($settings['smtp_from_name']) ? $settings['smtp_from_name'] : 'YosshitaNeha Fashion Studio';

    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            if ($mailer === 'smtp') {
                $mail->isSMTP();
                $mail->Host       = $host;
                $mail->SMTPAuth   = !empty($username);
                $mail->Username   = $username;
                $mail->Password   = $password;
                $mail->Port       = $port;
                if ($encryption === 'tls') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($encryption === 'ssl') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $mail->SMTPSecure = false;
                    $mail->SMTPAutoTLS = false;
                }
            }

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail);

            // Attach inline CID images (Logo & Product thumbnails)
            if (!empty($embeddedImages) && is_array($embeddedImages)) {
                foreach ($embeddedImages as $img) {
                    if (!empty($img['path']) && file_exists($img['path']) && !empty($img['cid'])) {
                        $mail->addEmbeddedImage($img['path'], $img['cid']);
                    }
                }
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $bodyHtml;
            $mail->AltBody = $altBody ?: strip_tags($bodyHtml);

            $mail->send();
            return ['success' => true, 'message' => 'Email sent successfully.'];
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            return ['success' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
        }
    } else {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: $fromName <$fromEmail>\r\n";
        $sent = mail($toEmail, $subject, $bodyHtml, $headers);
        return $sent ? ['success' => true, 'message' => 'Sent via PHP mail()'] : ['success' => false, 'error' => 'PHP mail() failed'];
    }
}

/**
 * Send Automated Order Email (Confirmation / Success, Failure / Cancellation, Status Update)
 */
function send_order_email($pdo, $orderId, $statusType = 'success') {
    try {
        // Check setting toggle if order emails are enabled
        $toggleStmt = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'smtp_enable_order_emails'");
        $enableVal = $toggleStmt ? $toggleStmt->fetchColumn() : '1';
        if ($enableVal === '0') {
            return ['success' => false, 'message' => 'Order emails disabled in settings'];
        }

        // Fetch order details
        $stmt = $pdo->prepare("SELECT o.*, c.first_name, c.last_name, c.email, c.phone FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order || empty($order['email'])) {
            return ['success' => false, 'error' => 'Order or customer email not found'];
        }

        // Fetch order items
        $itemStmt = $pdo->prepare("SELECT oi.*, p.name, p.main_image, p.sku FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $itemStmt->execute([$orderId]);
        $items = $itemStmt->fetchAll();

        // Fetch shipping address
        $addrStmt = $pdo->prepare("SELECT * FROM addresses WHERE customer_id = ? ORDER BY is_default DESC, created_at DESC LIMIT 1");
        $addrStmt->execute([$order['customer_id']]);
        $address = $addrStmt->fetch() ?: [];

        $orderNumber = format_order_number($order['id']);
        $custName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')) ?: 'Valued Customer';
        $orderDate = date('F j, Y, g:i a', strtotime($order['created_at']));
        $totalFormatted = '₹' . number_format($order['total_amount'], 2);

        $embeddedImages = [];

        // Logo CID Embedding
        $logoFile = __DIR__ . '/assets/images/logo.png';
        if (file_exists($logoFile)) {
            $embeddedImages[] = ['path' => $logoFile, 'cid' => 'yn_logo'];
            $logoHeader = '<img src="cid:yn_logo" alt="YosshitaNeha Logo" style="max-height: 55px; width: auto; display: block; margin: 0 auto;">';
        } else {
            $logoHeader = '<h1 style="color: #c8a55c; font-size: 22px; letter-spacing: 2px; text-transform: uppercase; margin: 0; font-weight: 700;">YOSSHITANEHA</h1><span style="color: #646970; font-size: 10px; letter-spacing: 3px; text-transform: uppercase;">LUXURY BRIDAL &amp; FASHION STUDIO</span>';
        }

        // Build HTML items rows with CID inline product images
        $itemsHtml = '';
        foreach ($items as $idx => $item) {
            $imgHtml = '';
            if (!empty($item['main_image'])) {
                $cleanPath = ltrim(str_replace('http://localhost/yn/admin/', '', $item['main_image']), '/');
                $localFile = __DIR__ . '/' . $cleanPath;
                if (file_exists($localFile)) {
                    $cid = 'item_img_' . $item['id'] . '_' . $idx;
                    $embeddedImages[] = ['path' => $localFile, 'cid' => $cid];
                    $imgHtml = '<img src="cid:' . $cid . '" width="50" height="65" style="border-radius: 4px; object-fit: cover; border: 1px solid #e2e8f0; vertical-align: middle; margin-right: 12px;">';
                }
            }

            $subtotalFormatted = '₹' . number_format($item['price'] * $item['quantity'], 2);
            $itemsHtml .= '
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 12px 10px; color: #1d2327;">' . $imgHtml . ' <strong style="font-size: 13px;">' . htmlspecialchars($item['name']) . '</strong><br><span style="color: #646970; font-size: 11px;">SKU: ' . htmlspecialchars($item['sku']) . '</span></td>
                <td style="padding: 12px 10px; text-align: center; color: #1d2327; font-weight: 600;">' . (int)$item['quantity'] . '</td>
                <td style="padding: 12px 10px; text-align: right; color: #c8a55c; font-weight: bold; font-size: 13px;">' . $subtotalFormatted . '</td>
            </tr>';
        }

        // Build Address HTML
        $addrHtml = 'Primary address on file';
        if (!empty($address['address_line_1'])) {
            $addrHtml = htmlspecialchars($address['address_line_1']);
            if (!empty($address['address_line_2'])) $addrHtml .= ', ' . htmlspecialchars($address['address_line_2']);
            $addrHtml .= '<br>' . htmlspecialchars($address['city']) . ', ' . htmlspecialchars($address['state']) . ' - ' . htmlspecialchars($address['pincode']);
        }

        $statusKey = strtolower($statusType);

        // Fetch tracking URL template from logistics master if available
        $trackingUrl = '';
        if (!empty($order['courier_name']) && !empty($order['tracking_number'])) {
            try {
                $logStmt = $pdo->prepare("SELECT tracking_url FROM logistics WHERE name = ? LIMIT 1");
                $logStmt->execute([$order['courier_name']]);
                $tmpl = $logStmt->fetchColumn();
                if ($tmpl) {
                    $trackingUrl = str_replace('{TRACKING_NO}', urlencode($order['tracking_number']), $tmpl);
                }
            } catch (Exception $ex) {}
        }

        $shipmentBoxHtml = '';

        if ($statusKey === 'shipped') {
            $subject = "Your Order " . $orderNumber . " Has Been Shipped! | YosshitaNeha";
            $badgeBg = "#e8f0fe";
            $badgeColor = "#1a73e8";
            $badgeBorder = "#aecbfa";
            $badgeText = "ORDER SHIPPED & IN TRANSIT";
            $headline = "Great News! Your Order Is On Its Way";
            $subheading = "Your order <strong>" . $orderNumber . "</strong> has been carefully packaged and dispatched via courier.";

            // Special Shipment Info Banner
            $shipmentBoxHtml = '
            <div style="background-color: #e8f0fe; border: 1px solid #aecbfa; border-radius: 8px; padding: 18px; margin: 20px 0;">
                <h4 style="margin: 0 0 10px 0; color: #1a73e8; font-size: 14px; text-transform: uppercase;">Shipment &amp; Tracking Details</h4>
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <tr>
                        <td style="color: #5f6368; padding-bottom: 6px;">Logistics Partner:</td>
                        <td style="color: #1d2327; font-weight: bold; text-align: right; padding-bottom: 6px;">' . htmlspecialchars($order['courier_name'] ?: 'Express Logistics') . '</td>
                    </tr>
                    <tr>
                        <td style="color: #5f6368;">POD / Waybill No:</td>
                        <td style="color: #1a73e8; font-weight: bold; font-family: monospace; text-align: right;">' . htmlspecialchars($order['tracking_number'] ?: 'In Transit') . '</td>
                    </tr>
                </table>
                ' . ($trackingUrl ? '
                <div style="text-align: center; margin-top: 15px;">
                    <a href="' . $trackingUrl . '" target="_blank" style="display: inline-block; background-color: #1a73e8; color: #ffffff; text-decoration: none; padding: 10px 22px; border-radius: 6px; font-weight: bold; font-size: 13px;">
                        Track Shipment Live
                    </a>
                </div>' : '') . '
            </div>';

        } elseif ($statusKey === 'delivered') {
            $subject = "Order Delivered - " . $orderNumber . " | YosshitaNeha";
            $badgeBg = "#e6f4ea";
            $badgeColor = "#137333";
            $badgeBorder = "#b7e1cd";
            $badgeText = "ORDER DELIVERED";
            $headline = "Your Order Has Been Delivered!";
            $subheading = "Your order <strong>" . $orderNumber . "</strong> has been successfully delivered to your shipping address. We hope you adore your luxury creation!";
        } elseif ($statusKey === 'cancelled' || $statusKey === 'failure') {
            $subject = "Order Cancellation Notice - " . $orderNumber . " | YosshitaNeha";
            $badgeBg = "#fce8e6";
            $badgeColor = "#c5221f";
            $badgeBorder = "#f5c2c0";
            $badgeText = "ORDER CANCELLED";
            $headline = "Order #" . $orderNumber . " Has Been Cancelled";
            $subheading = "Your order <strong>" . $orderNumber . "</strong> has been cancelled. If any payment was captured, a full refund will be processed to your original payment method.";
        } else {
            // Default: Order Confirmation / Processing
            $subject = "Order Confirmation - " . $orderNumber . " | YosshitaNeha Fashion Studio";
            $badgeBg = "#e6f4ea";
            $badgeColor = "#137333";
            $badgeBorder = "#b7e1cd";
            $badgeText = "PAYMENT CONFIRMED & ORDER PROCESSING";
            $headline = "Thank You for Your Order!";
            $subheading = "Your order <strong>" . $orderNumber . "</strong> has been placed successfully and is being prepared with exquisite care.";
        }

        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"></head>
        <body style="background-color: #f4f6f8; color: #1d2327; font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; margin: 0; padding: 30px 10px;">
            <div style="max-width: 640px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.06);">
                
                <div style="background-color: #ffffff; border-bottom: 2px solid #c8a55c; padding: 25px 30px; text-align: center;">
                    ' . $logoHeader . '
                </div>

                <div style="padding: 30px;">
                    <div style="display: inline-block; background-color: ' . $badgeBg . '; color: ' . $badgeColor . '; border: 1px solid ' . $badgeBorder . '; font-size: 11px; font-weight: bold; padding: 6px 14px; border-radius: 20px; text-transform: uppercase; margin-bottom: 15px;">
                        ' . $badgeText . '
                    </div>

                    <h2 style="color: #1d2327; margin-top: 0; font-size: 20px; font-weight: 700;">' . $headline . '</h2>
                    <p style="color: #50575e; font-size: 14px; line-height: 1.6;">Dear ' . htmlspecialchars($custName) . ',</p>
                    <p style="color: #50575e; font-size: 14px; line-height: 1.6;">' . $subheading . '</p>

                    ' . $shipmentBoxHtml . '

                    <div style="background-color: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; margin: 25px 0;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                            <tr>
                                <td style="color: #646970; padding-bottom: 6px;">Order Number:</td>
                                <td style="color: #c8a55c; font-weight: bold; text-align: right; padding-bottom: 6px;">' . $orderNumber . '</td>
                            </tr>
                            <tr>
                                <td style="color: #646970; padding-bottom: 6px;">Placed On:</td>
                                <td style="color: #1d2327; text-align: right; padding-bottom: 6px;">' . $orderDate . '</td>
                            </tr>
                            <tr>
                                <td style="color: #646970;">Payment Method:</td>
                                <td style="color: #1d2327; font-weight: 600; text-align: right;">' . htmlspecialchars($order['payment_method'] ?: 'Online Payment') . '</td>
                            </tr>
                        </table>
                    </div>

                    <h3 style="color: #1d2327; font-size: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-top: 25px;">Items Summary</h3>
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 20px;">
                        <thead>
                            <tr style="background-color: #f1f5f9; color: #475569; text-align: left; font-size: 11px; text-transform: uppercase;">
                                <th style="padding: 10px;">Item</th>
                                <th style="padding: 10px; text-align: center;">Qty</th>
                                <th style="padding: 10px; text-align: right;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            ' . $itemsHtml . '
                        </tbody>
                    </table>

                    ';

                    $subtotalVal = (float)(!empty($order['subtotal_amount']) && $order['subtotal_amount'] > 0 ? $order['subtotal_amount'] : $order['total_amount']);
                    $discountVal = (float)($order['discount_amount'] ?? 0);
                    $shippingVal = (float)($order['shipping_charge'] ?? 0);
                    $couponCodeStr = htmlspecialchars($order['coupon_code'] ?? '');

                    $breakdownHtml = '
                    <table style="width: 100%; font-size: 12px; line-height: 1.6; text-align: right;">
                        <tr>
                            <td style="color: #646970;">Subtotal:</td>
                            <td style="color: #1d2327; font-weight: 600; padding-left: 10px;">₹' . number_format($subtotalVal, 2) . '</td>
                        </tr>';

                    if ($discountVal > 0) {
                        $breakdownHtml .= '
                        <tr>
                            <td style="color: #137333;">Discount (' . ($couponCodeStr ?: 'Coupon') . '):</td>
                            <td style="color: #137333; font-weight: 600; padding-left: 10px;">-₹' . number_format($discountVal, 2) . '</td>
                        </tr>';
                    }

                    $breakdownHtml .= '
                        <tr>
                            <td style="color: #646970;">Shipping Fee:</td>
                            <td style="color: #1d2327; font-weight: 600; padding-left: 10px;">' . ($shippingVal == 0 ? '<strong style="color:#137333;">FREE</strong>' : '₹' . number_format($shippingVal, 2)) . '</td>
                        </tr>
                        <tr style="border-top: 1px dashed #cbd5e1;">
                            <td style="color: #1d2327; font-size: 13px; font-weight: bold; padding-top: 6px;">Total Paid:</td>
                            <td style="color: #c8a55c; font-size: 16px; font-weight: bold; padding-left: 10px; padding-top: 6px;">' . $totalFormatted . '</td>
                        </tr>
                    </table>';

                    $htmlBody .= '
                    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                        <tr>
                            <td style="vertical-align: top; width: 55%; padding-right: 15px;">
                                <div style="background-color: #f8f9fa; padding: 14px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                    <div style="color: #c8a55c; font-size: 11px; font-weight: bold; margin-bottom: 4px; text-transform: uppercase;">SHIPPING ADDRESS</div>
                                    <div style="color: #50575e; font-size: 12px; line-height: 1.5;">' . $addrHtml . '</div>
                                </div>
                            </td>
                            <td style="vertical-align: top; width: 45%;">
                                <div style="background-color: #f8f9fa; padding: 14px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                    ' . $breakdownHtml . '
                                </div>
                            </td>
                        </tr>
                    </table>';

                    $htmlBody .= '
                    <div style="text-align: center; margin-top: 35px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                        <a href="' . get_frontend_url('account') . '" style="display: inline-block; background-color: #c8a55c; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 25px; font-weight: bold; font-size: 13px;">View Order Online</a>
                    </div>
                </div>

                <div style="background-color: #f8f9fa; border-top: 1px solid #e2e8f0; padding: 20px; text-align: center; color: #646970; font-size: 11px;">
                    © ' . date('Y') . ' YosshitaNeha Fashion Studio. All rights reserved.<br>
                    Need assistance? Contact us at info@yosshitaneha.com
                </div>
            </div>
        </body>
        </html>';

        return send_system_email($pdo, $order['email'], $subject, $htmlBody, '', $embeddedImages);
    } catch (Exception $ex) {
        return ['success' => false, 'error' => $ex->getMessage()];
    }
}

/**
 * Send Welcome Email on New Customer Sign Up
 */
function send_welcome_email($pdo, $toEmail, $customerName) {
    try {
        $toggleStmt = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'smtp_enable_welcome_emails'");
        $enableVal = $toggleStmt ? $toggleStmt->fetchColumn() : '1';
        if ($enableVal === '0') {
            return ['success' => false, 'message' => 'Welcome emails disabled in settings'];
        }

        $embeddedImages = [];
        $logoFile = __DIR__ . '/assets/images/logo.png';
        if (file_exists($logoFile)) {
            $embeddedImages[] = ['path' => $logoFile, 'cid' => 'yn_logo'];
            $logoHeader = '<img src="cid:yn_logo" alt="YosshitaNeha Logo" style="max-height: 55px; width: auto; display: block; margin: 0 auto;">';
        } else {
            $logoHeader = '<h1 style="color: #c8a55c; font-size: 22px; letter-spacing: 2px; text-transform: uppercase; margin: 0; font-weight: 700;">YOSSHITANEHA</h1><span style="color: #646970; font-size: 10px; letter-spacing: 3px; text-transform: uppercase;">LUXURY BRIDAL &amp; FASHION STUDIO</span>';
        }

        $subject = "Welcome to YosshitaNeha Luxury Fashion Studio";
        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"></head>
        <body style="background-color: #f4f6f8; color: #1d2327; font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; margin: 0; padding: 30px 10px;">
            <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.06);">
                <div style="background-color: #ffffff; border-bottom: 2px solid #c8a55c; padding: 25px 30px; text-align: center;">
                    ' . $logoHeader . '
                </div>
                <div style="padding: 30px;">
                    <h2 style="color: #1d2327; margin-top: 0; font-weight: 700;">Welcome, ' . htmlspecialchars($customerName) . '!</h2>
                    <p style="color: #50575e; font-size: 14px; line-height: 1.6;">Thank you for registering with YosshitaNeha. We are delighted to welcome you to our world of handcrafted luxury bridal couture, fine jewelry, and bespoke fashion.</p>
                    <p style="color: #50575e; font-size: 14px; line-height: 1.6;">Your account is ready. You can now save your addresses, track orders in real-time, and curate your personalized wishlist.</p>
                    <div style="text-align: center; margin-top: 30px;">
                        <a href="' . get_frontend_url('account') . '" style="display: inline-block; background-color: #c8a55c; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 25px; font-weight: bold; font-size: 13px;">Explore My Account</a>
                    </div>
                </div>
                <div style="background-color: #f8f9fa; border-top: 1px solid #e2e8f0; padding: 20px; text-align: center; color: #646970; font-size: 11px;">
                    © ' . date('Y') . ' YosshitaNeha Fashion Studio. All rights reserved.
                </div>
            </div>
        </body>
        </html>';

        return send_system_email($pdo, $toEmail, $subject, $htmlBody, '', $embeddedImages);
    } catch (Exception $ex) {
        return ['success' => false, 'error' => $ex->getMessage()];
    }
}

/**
 * Calculate dynamic shipping charge based on cart subtotal and active shipping rules
 */
function get_shipping_charge($pdo, $cartTotal) {
    try {
        $cartTotal = (float)$cartTotal;
        $stmt = $pdo->prepare("
            SELECT charge FROM shipping_rules 
            WHERE status = 'active' 
              AND min_amount <= ? 
              AND (max_amount >= ? OR max_amount IS NULL)
            ORDER BY min_amount DESC 
            LIMIT 1
        ");
        $stmt->execute([$cartTotal, $cartTotal]);
        $charge = $stmt->fetchColumn();
        if ($charge !== false) {
            return (float)$charge;
        }
    } catch (Exception $e) {}
    return 0.00;
}

/**
 * Calculate dynamic discount for a product using active discount_rules (Weight based priority)
 */
function get_product_discount_info($pdo, $productId, $regularPrice, $categoryIds = []) {
    try {
        $regularPrice = (float)$regularPrice;
        if ($regularPrice <= 0) return false;

        // Fetch active discount rules ordered by weight DESC (highest priority first)
        $stmt = $pdo->query("SELECT * FROM discount_rules WHERE status = 'active' ORDER BY weight DESC, id DESC");
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rules)) return false;

        if (empty($categoryIds) && $productId > 0) {
            $catStmt = $pdo->prepare("SELECT category_id FROM product_categories WHERE product_id = ?");
            $catStmt->execute([$productId]);
            $categoryIds = $catStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        foreach ($rules as $rule) {
            $scope = $rule['scope'];
            $applied = false;
            $targets = !empty($rule['target']) ? array_filter(array_map('trim', explode(',', $rule['target']))) : [];

            if ($scope === 'global') {
                $applied = true;
            } elseif ($scope === 'product') {
                if (in_array((string)$productId, $targets)) {
                    $applied = true;
                }
            } elseif ($scope === 'category') {
                if (!empty(array_intersect($categoryIds, $targets))) {
                    $applied = true;
                }
            } elseif ($scope === 'price_gt') {
                if ($regularPrice > (float)$rule['threshold']) {
                    $applied = true;
                }
            } elseif ($scope === 'price_lt') {
                if ($regularPrice < (float)$rule['threshold']) {
                    $applied = true;
                }
            } elseif ($scope === 'price_between') {
                if ($regularPrice >= (float)$rule['threshold'] && $regularPrice <= (float)$rule['threshold_max']) {
                    $applied = true;
                }
            } elseif (strpos($scope, 'cat_price') !== false) {
                if (!empty(array_intersect($categoryIds, $targets))) {
                    if ($scope === 'cat_price_gt' && $regularPrice > (float)$rule['threshold']) {
                        $applied = true;
                    } elseif ($scope === 'cat_price_lt' && $regularPrice < (float)$rule['threshold']) {
                        $applied = true;
                    } elseif ($scope === 'cat_price_between' && $regularPrice >= (float)$rule['threshold'] && $regularPrice <= (float)$rule['threshold_max']) {
                        $applied = true;
                    }
                }
            }

            if ($applied) {
                $val = (float)$rule['value'];
                $finalPrice = $regularPrice;
                if ($rule['type'] === 'percentage') {
                    $finalPrice = $regularPrice - ($regularPrice * ($val / 100));
                    $label = $val . '% OFF';
                } else {
                    $finalPrice = $regularPrice - $val;
                    $label = '₹' . number_format($val, 0) . ' OFF';
                }
                $finalPrice = max(0, $finalPrice);
                return [
                    'rule_id' => $rule['id'],
                    'rule_name' => $rule['name'],
                    'type' => $rule['type'],
                    'value' => $val,
                    'label' => $label,
                    'original_price' => $regularPrice,
                    'discounted_price' => $finalPrice,
                    'savings' => $regularPrice - $finalPrice
                ];
            }
        }
    } catch (Exception $e) {}

    return false;
}

/**
 * Validate and calculate coupon discount for cart
 */
function validate_and_apply_coupon($pdo, $code, $cartSubtotal) {
    try {
        $code = strtoupper(trim($code));
        if (empty($code)) {
            return ['valid' => false, 'message' => 'Please enter a coupon code.'];
        }

        $stmt = $pdo->prepare("SELECT * FROM coupons WHERE UPPER(code) = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$code]);
        $coupon = $stmt->fetch();

        if (!$coupon) {
            return ['valid' => false, 'message' => 'Invalid or expired coupon code.'];
        }

        // Expiry check
        if (!empty($coupon['expiry_date']) && strtotime($coupon['expiry_date']) < strtotime(date('Y-m-d'))) {
            return ['valid' => false, 'message' => 'This coupon has expired.'];
        }

        // Usage limit check
        if ($coupon['usage_limit'] !== null && $coupon['usage_count'] >= $coupon['usage_limit']) {
            return ['valid' => false, 'message' => 'This coupon has reached its maximum usage limit.'];
        }

        // Minimum cart spend check
        if ($coupon['minimum_amount'] !== null && $cartSubtotal < (float)$coupon['minimum_amount']) {
            return ['valid' => false, 'message' => 'Minimum cart subtotal of ₹' . number_format($coupon['minimum_amount'], 2) . ' required to use this coupon.'];
        }

        // Maximum cart spend check
        if ($coupon['maximum_amount'] !== null && $cartSubtotal > (float)$coupon['maximum_amount']) {
            return ['valid' => false, 'message' => 'Maximum cart limit exceeded for this coupon.'];
        }

        // Calculate discount
        $discountAmount = 0;
        $type = $coupon['discount_type'];
        $val = (float)$coupon['coupon_amount'];

        if ($type === 'percent') {
            $discountAmount = $cartSubtotal * ($val / 100);
        } else {
            $discountAmount = $val;
        }

        $discountAmount = min($cartSubtotal, max(0, $discountAmount));
        $newSubtotal = max(0, $cartSubtotal - $discountAmount);

        return [
            'valid' => true,
            'coupon_id' => $coupon['id'],
            'code' => $coupon['code'],
            'description' => $coupon['description'],
            'discount_type' => $type,
            'coupon_amount' => $val,
            'discount_calculated' => $discountAmount,
            'new_subtotal' => $newSubtotal,
            'message' => 'Coupon code applied successfully!'
        ];
    } catch (Exception $e) {
        return ['valid' => false, 'message' => $e->getMessage()];
    }
}
?>