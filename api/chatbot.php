<?php
// admin/api/chatbot.php
// AI Assistant Engine with Trained Knowledge Base & Gemini Vision Integration
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Fetch Chatbot Settings from DB
$settings = [];
try {
    $stmtS = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'chatbot_%'");
    while ($row = $stmtS->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}

$isEnabled = ($settings['chatbot_enabled'] ?? '1') === '1';
$apiKey = trim($settings['chatbot_gemini_api_key'] ?? '');

// Auto-fallback API key check from shared secrets file if empty in DB
if (empty($apiKey)) {
    $secretsFile = 'C:/xampp/htdocs/ss/new_admin/Config/secrets.php';
    if (file_exists($secretsFile)) {
        $secrets = include $secretsFile;
        if (!empty($secrets['GEMINI_API_KEY']) && !str_starts_with($secrets['GEMINI_API_KEY'], 'AQ.')) {
            $apiKey = $secrets['GEMINI_API_KEY'];
        }
    }
}

$welcomeMsg = $settings['chatbot_welcome_message'] ?? "Namaste! ✨ I am your YosshitaNeha Personal Assistant & Stylist. How can I help you today?";

// Trained Store Knowledge System Prompt
$systemPrompt = "You are an expert luxury Indian fashion stylist for YosshitaNeha Fashion Studio. Specialising in handcrafted designer blouses, heritage jewellery (Kundan, Polki, Antique), and bespoke bridal customisation.

STORE KNOWLEDGE DATA:
- Store Name: YosshitaNeha Fashion Studio
- Phone / WhatsApp: +91 9324243011 / +91 7506628663
- Email: yosshita.neha@gmail.com
- Address: 104, Shyamkamal Building B/1, Agarwal Market, Near Deenanath Mangeshkar Natya Mandir, Vile Parle East, Mumbai - 400057
- Studio Hours: Monday to Saturday, 11:00 AM - 7:30 PM (IST)
- Customisation Services: Blouse stitching starts from ₹1,500. Heavy bridal customisation & hand embroidery quotes depend on fabric, zari work, and pattern complexity. Customers can share outfit photos via WhatsApp (+91 9324243011) or upload photo in chat for an instant quote.
- Domestic Shipping: 3-7 business days. Free shipping over ₹5,000.
- International Shipping: Available worldwide (7-12 business days).

Be warm, polite, luxury-oriented, concise, and helpful. Always give exact phone numbers and address when asked.";

// 1. Config Check Endpoint
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'config') {
    echo json_encode([
        'success' => true,
        'enabled' => $isEnabled,
        'welcome_message' => $welcomeMsg
    ]);
    exit;
}

if (!$isEnabled) {
    echo json_encode([
        'success' => false,
        'message' => 'Chatbot is currently disabled by administrator.'
    ]);
    exit;
}

// 2. Process POST Requests
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_REQUEST;
}

$userMsg = trim($input['message'] ?? '');
$imageBase64 = $input['image'] ?? null;
$customerEmail = trim($input['email'] ?? '');
$customerPhone = trim($input['phone'] ?? '');

$replyText = "";
$products = [];
$orders = [];
$actionRequired = null;

$msgLower = strtolower($userMsg);

// INTENT A: Order Status & Account Inquiry
if (str_contains($msgLower, 'order') || str_contains($msgLower, 'track') || str_contains($msgLower, 'shipment') || str_contains($msgLower, 'delivery status') || preg_match('/#?YN-?\d+/i', $userMsg)) {
    
    preg_match('/#?YN-?(\d+)/i', $userMsg, $orderMatches);
    $searchOrderId = $orderMatches[1] ?? null;

    if (!empty($customerEmail) || !empty($customerPhone) || !empty($searchOrderId)) {
        try {
            $sql = "SELECT id, total_amount, payment_status, shipping_status, created_at FROM orders WHERE 1=1";
            $p = [];
            if ($searchOrderId) {
                $sql .= " AND id = ?";
                $p[] = (int)$searchOrderId;
            } elseif ($customerEmail) {
                $sql .= " AND customer_email = ?";
                $p[] = $customerEmail;
            } elseif ($customerPhone) {
                $sql .= " AND customer_phone = ?";
                $p[] = $customerPhone;
            }
            $sql .= " ORDER BY id DESC LIMIT 3";

            $stmtO = $pdo->prepare($sql);
            $stmtO->execute($p);
            $orders = $stmtO->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($orders)) {
                $replyText = "Here are your order status details:\n";
                foreach ($orders as $o) {
                    $replyText .= "📦 **Order #YN-" . $o['id'] . "** — Amount: ₹" . number_format($o['total_amount'], 2) . "\n";
                    $replyText .= "Status: " . ucfirst($o['shipping_status'] ?: 'Processing') . " | Payment: " . ucfirst($o['payment_status'] ?: 'Pending') . "\n\n";
                }
            } else {
                $replyText = "I couldn't find any orders matching your details. Please double-check your Order ID or registered email/phone number.";
            }
        } catch (Exception $e) {
            $replyText = "Unable to fetch order details at the moment.";
        }
    } else {
        $replyText = "I can help you track your order! Please enter your registered **Email Address** or **Order Number (e.g. YN-1002)**.";
        $actionRequired = "request_verification";
    }
    $products = []; // No product cards for order status

} elseif (!empty($imageBase64)) {
    // INTENT B: Visual Image Matching (Gemini Vision API or Smart Color Matcher)
    if (!empty($apiKey)) {
        $geminiRes = call_gemini_vision_api($apiKey, $userMsg, $imageBase64, $systemPrompt);
        if ($geminiRes) {
            $replyText = $geminiRes['text'];
            $products = search_matching_products($pdo, $geminiRes['search_keywords']);
        }
    }

    if (empty($replyText)) {
        $replyText = "I've analyzed your uploaded outfit image! Here are matching designer blouses and heritage jewellery pieces from our collection that complement your look perfectly ✨";
        $products = search_matching_products($pdo, "blouse jewellery designer");
    }

} else {
    // INTENT C: AI Conversational & Trained Intent Knowledge Base
    
    // 1. Phone / WhatsApp / Contact / Address / Location Intents
    if (str_contains($msgLower, 'contact') || str_contains($msgLower, 'phone') || str_contains($msgLower, 'number') || str_contains($msgLower, 'call') || str_contains($msgLower, 'whatsapp') || str_contains($msgLower, 'address') || str_contains($msgLower, 'location') || str_contains($msgLower, 'studio') || str_contains($msgLower, 'where')) {
        $replyText = "📍 **YosshitaNeha Fashion Studio Contact Information**\n\n" .
                     "📞 **Phone / WhatsApp**: +91 9324243011 / +91 7506628663\n" .
                     "✉️ **Email**: yosshita.neha@gmail.com\n" .
                     "🏢 **Studio Address**: 104, Shyamkamal Building B/1, Agarwal Market, Near Deenanath Mangeshkar Natya Mandir, Vile Parle East, Mumbai - 400057\n" .
                     "⏰ **Operating Hours**: Mon - Sat: 11:00 AM - 7:30 PM (IST)\n\n" .
                     "Feel free to call or WhatsApp us directly for custom orders and instant assistance!";
        $products = [];

    // 2. Customisation & Stitching Charges Intents
    } elseif (str_contains($msgLower, 'custom') || str_contains($msgLower, 'stitch') || str_contains($msgLower, 'charge') || str_contains($msgLower, 'alter') || str_contains($msgLower, 'cost') || str_contains($msgLower, 'make') || str_contains($msgLower, 'price to')) {
        $replyText = "✨ **Bespoke Customisation Services & Pricing** ✨\n\n" .
                     "• **Basic Blouse Stitching**: Starts from ₹1,500\n" .
                     "• **Heavy Hand Embroidery & Bridal Wear**: Quotes depend on fabric, zari/zardozi work, and pattern complexity.\n\n" .
                     "📱 **Get Instant Quote**: Send your reference design picture or requirements to our Master Designer via WhatsApp at **+91 9324243011** or upload your photo right here in the chat!";
        $products = [];

    // 3. Shipping & Delivery Intents
    } elseif (str_contains($msgLower, 'ship') || str_contains($msgLower, 'deliver') || str_contains($msgLower, 'days') || str_contains($msgLower, 'time')) {
        $replyText = "🚚 **Shipping & Delivery Policy**\n\n" .
                     "• **Domestic Shipping**: 3–7 business days (Free shipping over ₹5,000)\n" .
                     "• **Custom / Bespoke Orders**: 10–15 business days\n" .
                     "• **International Shipping**: Delivered worldwide within 7–12 business days!";
        $products = [];

    // 4. Product Requests -> Fetch In-Stock Catalog Items
    } elseif (str_contains($msgLower, 'blouse') || str_contains($msgLower, 'saree') || str_contains($msgLower, 'lehenga') || str_contains($msgLower, 'jewel') || str_contains($msgLower, 'earring') || str_contains($msgLower, 'necklace') || str_contains($msgLower, 'kundan') || str_contains($msgLower, 'polki') || str_contains($msgLower, 'show') || str_contains($msgLower, 'recommend') || str_contains($msgLower, 'buy')) {
        
        if (!empty($apiKey)) {
            $replyText = call_gemini_text_api($apiKey, $userMsg, $systemPrompt);
        }
        if (empty($replyText)) {
            $replyText = "Here are our top active in-stock designs matching your request:";
        }
        $products = search_matching_products($pdo, $userMsg);

    // 5. General Fashion Advice (using Gemini AI if available)
    } else {
        if (!empty($apiKey)) {
            $replyText = call_gemini_text_api($apiKey, $userMsg, $systemPrompt);
        }
        if (empty($replyText)) {
            $replyText = "Hello! Welcome to YosshitaNeha Fashion Studio. I am here to assist you with designer blouses, heritage jewellery, customisation quotes, and order tracking. How can I help you today?";
        }
        $products = [];
    }
}

echo json_encode([
    'success' => true,
    'reply' => $replyText,
    'products' => $products,
    'action_required' => $actionRequired
]);

/**
 * Helper: Search Products Catalog (Only Active In-Stock Items)
 */
function search_matching_products($pdo, $queryStr) {
    try {
        $terms = explode(' ', strtolower($queryStr));
        $cleanTerms = array_filter($terms, fn($t) => strlen($t) > 2);
        
        $where = "WHERE p.status = 'published' AND p.deleted_at IS NULL AND p.stock_qty > 0";
        $params = [];

        if (!empty($cleanTerms)) {
            $likeClauses = [];
            foreach (array_slice($cleanTerms, 0, 3) as $t) {
                $likeClauses[] = "(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
                $params[] = "%$t%";
                $params[] = "%$t%";
                $params[] = "%$t%";
            }
            $where .= " AND (" . implode(" OR ", $likeClauses) . ")";
        }

        $sql = "SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.main_image, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                $where 
                ORDER BY p.is_featured DESC, p.id DESC 
                LIMIT 4";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            $stmtF = $pdo->query("SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.main_image, c.name as category_name 
                                  FROM products p 
                                  LEFT JOIN categories c ON p.category_id = c.id 
                                  WHERE p.status = 'published' AND p.deleted_at IS NULL AND p.stock_qty > 0 
                                  ORDER BY p.id DESC LIMIT 4");
            $results = $stmtF->fetchAll(PDO::FETCH_ASSOC);
        }

        return $results;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Helper: Gemini API Text Generation Call
 */
function call_gemini_text_api($apiKey, $prompt, $systemPrompt) {
    try {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($apiKey);
        
        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $systemPrompt . "\n\nUser Question: " . $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 300
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);

        $res = curl_exec($ch);
        curl_close($ch);

        if ($res) {
            $json = json_decode($res, true);
            $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
            return $text;
        }
    } catch (Exception $e) {}
    return null;
}

/**
 * Helper: Gemini API Vision Call
 */
function call_gemini_vision_api($apiKey, $prompt, $base64Data, $systemPrompt) {
    try {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($apiKey);

        $mimeType = 'image/jpeg';
        if (str_contains($base64Data, 'data:image/png')) $mimeType = 'image/png';
        if (str_contains($base64Data, 'data:image/webp')) $mimeType = 'image/webp';
        
        $cleanBase64 = preg_replace('#^data:image/\w+;base64,#i', '', $base64Data);

        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $systemPrompt . "\n\nAnalyze this outfit/jewellery image. Suggest matching accessories or blouse styles. Keep response under 100 words."],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $cleanBase64
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $res = curl_exec($ch);
        curl_close($ch);

        if ($res) {
            $json = json_decode($res, true);
            $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
            return [
                'text' => $text,
                'search_keywords' => $prompt ?: 'designer blouse jewellery'
            ];
        }
    } catch (Exception $e) {}
    return null;
}
