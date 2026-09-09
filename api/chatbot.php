<?php
// admin/api/chatbot.php
// AI Shopping Assistant & Haute Couture Stylist Engine
// Powered by Google Gemini API + MySQL Live Product Catalog + Trained Knowledge Base
require_once __DIR__ . '/cors_header.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

// Load Database-Driven Knowledge Engine & FAQs
require_once __DIR__ . '/chatbot_training.php';
$kbData = get_chatbot_db_knowledge($pdo);
$storeInfo = $kbData['store_info'] ?? [];
$trainedIntents = $kbData['intents'] ?? [];
$aiContextStr = $kbData['ai_context'] ?? '';

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

// Auto-fallback API key check from config/secrets.php if empty in DB
if (empty($apiKey) || !isValidApiKey($apiKey)) {
    $secretsFile = __DIR__ . '/../config/secrets.php';
    if (file_exists($secretsFile)) {
        $secrets = include $secretsFile;
        if (!empty($secrets['GEMINI_API_KEY']) && isValidApiKey($secrets['GEMINI_API_KEY'])) {
            $apiKey = $secrets['GEMINI_API_KEY'];
        }
    }
}

$welcomeMsg = $settings['chatbot_welcome_message'] ?? "Namaste! ✨ I am Aalia, your YosshitaNeha AI Couture Stylist. How can I assist your styling journey today?";

// 1. Config Check Endpoint
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'config') {
    echo json_encode([
        'success' => true,
        'enabled' => $isEnabled,
        'welcome_message' => $welcomeMsg,
        'quick_chips' => [
            '🔥 Show Best Sellers',
            '💎 Match Jewellery to My Outfit',
            '👗 Bridal & Festive Blouses',
            '💰 Under ₹5,000'
        ]
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
$history = is_array($input['history'] ?? null) ? $input['history'] : [];
$imageBase64 = $input['image'] ?? null;
$customerEmail = trim($input['email'] ?? '');
$customerPhone = trim($input['phone'] ?? '');

$replyText = "";
$products = [];
$quickChips = [];
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
                $quickChips = ['🛍️ Shop More Outfits', '💬 Chat on WhatsApp', '📦 Track Another Order'];
            } else {
                $replyText = "I couldn't find any orders matching your details. Please double-check your Order ID or registered email/phone number.";
                $quickChips = ['🔍 Check Order ID', '💬 Speak to Support', '🛍️ Browse Collection'];
            }
        } catch (Exception $e) {
            $replyText = "Unable to fetch order details at the moment. Our studio team is also available directly on WhatsApp!";
        }
    } else {
        $replyText = "I can help you track your order! Please enter your registered **Email Address** or **Order Number (e.g. YN-1002)**.";
        $actionRequired = "request_verification";
        $quickChips = ['💬 Chat on WhatsApp', '👗 Continue Shopping'];
    }

} elseif (!empty($imageBase64)) {
    // INTENT B: Visual Image Analysis (Gemini Vision or Smart Color Matcher)
    if (!empty($apiKey) && isValidApiKey($apiKey)) {
        $geminiRes = call_gemini_vision_api($apiKey, $userMsg, $imageBase64, $aiContextStr);
        if ($geminiRes && !empty($geminiRes['text'])) {
            $replyText = $geminiRes['text'];
            $products = search_matching_products($pdo, $geminiRes['search_keywords'] ?? 'designer blouse jewellery');
            $quickChips = $geminiRes['quick_chips'] ?? ['✨ Match Jewellery', '👗 View Designer Blouses', '💬 WhatsApp Stylist'];
        }
    }

    if (empty($replyText)) {
        $replyText = "I've analyzed your outfit palette! For this silhouette, pairing with contrasting heritage jewellery and an embroidered designer blouse creates a striking, regal statement ✨";
        $products = search_matching_products($pdo, "blouse jewellery");
        $quickChips = ['💎 Kundan Polki Jewellery', '👗 Embroidered Blouses', '💬 Chat with Stylist'];
    }

} else {
    // INTENT C: Fashion Stylist Recommendation Engine (Gemini AI Primary + Catalog Matcher)
    $geminiSuccess = false;

    // Check if Gemini API key is valid
    if (!empty($apiKey) && isValidApiKey($apiKey)) {
        $geminiData = call_gemini_stylist_api($apiKey, $userMsg, $history, $aiContextStr);
        if ($geminiData && !empty($geminiData['reply'])) {
            $replyText = $geminiData['reply'];
            $searchKeywords = $geminiData['search_terms'] ?? [$userMsg];
            $products = search_matching_products($pdo, $searchKeywords);
            $quickChips = $geminiData['quick_chips'] ?? [];
            $geminiSuccess = true;
        }
    }

    // Graceful Fallback to High-Precision Intelligent Stylist & FAQ Engine
    if (!$geminiSuccess) {
        $fallback = run_stylist_fallback_engine($pdo, $userMsg, $trainedIntents);
        $replyText = $fallback['reply'];
        $products = $fallback['products'];
        $quickChips = $fallback['quick_chips'];
    }
}

// Ensure quick chips are present
if (empty($quickChips)) {
    $quickChips = ['💎 Match Jewellery', '👗 View Designer Blouses', '💬 Discuss on WhatsApp'];
}

echo json_encode([
    'success' => true,
    'reply' => $replyText,
    'products' => $products,
    'quick_chips' => $quickChips,
    'action_required' => $actionRequired
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit;

// ==========================================
// HELPER FUNCTIONS
// ==========================================

/**
 * Validate Gemini API key structure
 */
function isValidApiKey($key) {
    if (empty($key) || !is_string($key)) return false;
    $trimKey = trim($key);
    if (strlen($trimKey) < 25) return false;
    if (str_starts_with($trimKey, 'AQ.')) return false; // Invalid token
    if (str_starts_with($trimKey, '6r6Q')) return false; // Known placeholder
    return true;
}

/**
 * Call Gemini Flash with Structured JSON Output & Conversation History
 */
function call_gemini_stylist_api($apiKey, $prompt, $history = [], $aiContext = '') {
    $models = ['gemini-1.5-flash', 'gemini-2.0-flash'];

    $systemInstruction = "You are 'Aalia', the luxury AI Fashion Stylist & Personal Shopper at YosshitaNeha Fashion Studio (Mumbai).\n\n" .
        "Studio Specialties:\n" .
        "- Handcrafted Designer Blouses: Bridal Kimkhwab brocades, hand zardozi, rich velvets, pure raw silk, custom cuts & padded luxury fits.\n" .
        "- Heritage & Bridal Jewellery: Kundan Polki, Vilandi, antique gold, statement choker sets, bridal mathapatti, earrings, and handcrafted bangles/kadas.\n" .
        "- Saree Upcycling & Customization: Transforming vintage heirloom sarees into bespoke lehengas & modern luxury drapes.\n" .
        "- Location & Services: Studio in Vile Parle East, Mumbai. Worldwide insured shipping, bespoke measurement consultations.\n\n" .
        "Style Guidance:\n" .
        "- Tone: Warm, prestigious, knowledgeable, luxury couture stylist.\n" .
        "- Length: Concise, 2 to 4 sentences max (under 90 words). Include subtle emojis.\n" .
        "- When asked for matching or styling advice, give specific color/metal/cut contrast suggestions.\n\n" .
        "CRITICAL: Output ONLY a single valid JSON object (no markdown code blocks, no backticks) with this structure:\n" .
        "{\n" .
        "  \"reply\": \"Concise, warm styling advice with emojis\",\n" .
        "  \"search_terms\": [\"term1\", \"term2\"],\n" .
        "  \"quick_chips\": [\"✨ Chip 1\", \"👗 Chip 2\", \"💬 Chip 3\"]\n" .
        "}\n" .
        "`search_terms` will query the store's product catalog. Provide 1 to 3 search words (e.g. 'blouse', 'velvet blouse', 'kundan necklace', 'vilandi', 'bangles', 'pink blouse').\n" .
        "`quick_chips` should offer 3 engaging follow-up suggestions for the shopper.\n\n" .
        $aiContext;

    // Build Gemini contents array with history
    $contents = [];
    if (is_array($history)) {
        // Take up to last 4 turns to keep request fast and relevant
        $recentHistory = array_slice($history, -4);
        foreach ($recentHistory as $h) {
            $sender = $h['sender'] ?? '';
            $text = trim($h['text'] ?? '');
            if (empty($text)) continue;

            $role = ($sender === 'user') ? 'user' : 'model';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $text]]
            ];
        }
    }

    // Append current user prompt
    $contents[] = [
        'role' => 'user',
        'parts' => [['text' => $prompt]]
    ];

    $payload = [
        'systemInstruction' => [
            'parts' => [['text' => $systemInstruction]]
        ],
        'contents' => $contents,
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 600,
            'responseMimeType' => 'application/json'
        ]
    ];

    foreach ($models as $model) {
        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);

            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $res) {
                $json = json_decode($res, true);
                $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

                if (!empty($rawText)) {
                    // Strip any accidental markdown formatting if present
                    $cleanJsonStr = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText));
                    $data = json_decode($cleanJsonStr, true);

                    if (is_array($data) && !empty($data['reply'])) {
                        return $data;
                    }

                    // If text was returned but not valid JSON
                    return [
                        'reply' => trim($rawText),
                        'search_terms' => [$prompt],
                        'quick_chips' => ['✨ Match Jewellery', '👗 View Outfits', '💬 Chat on WhatsApp']
                    ];
                }
            }
        } catch (Exception $e) {
            // Try next model
        }
    }

    return null;
}

/**
 * Call Gemini Vision for Image Styling
 */
function call_gemini_vision_api($apiKey, $prompt, $base64Data, $systemPrompt) {
    try {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($apiKey);

        $mimeType = 'image/jpeg';
        if (str_contains($base64Data, 'data:image/png')) $mimeType = 'image/png';
        if (str_contains($base64Data, 'data:image/webp')) $mimeType = 'image/webp';

        $cleanBase64 = preg_replace('#^data:image/\w+;base64,#i', '', $base64Data);

        $instruction = "You are Aalia, Haute Couture Stylist for YosshitaNeha Fashion Studio. Analyze this user outfit/jewellery photo. Give 2 sentences of stylish color/accessory matching advice and specify search keywords for matching pieces.\n" .
            "Format: Output valid JSON:\n" .
            "{\n" .
            "  \"text\": \"2 sentences of styling advice with emojis\",\n" .
            "  \"search_keywords\": \"blouse jewellery\",\n" .
            "  \"quick_chips\": [\"✨ Match Jewellery\", \"👗 View Blouses\", \"💬 WhatsApp Stylist\"]\n" .
            "}";

        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $instruction . "\nUser Note: " . $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $cleanBase64
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 400,
                'responseMimeType' => 'application/json'
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 9);

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $res) {
            $json = json_decode($res, true);
            $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText));
            $parsed = json_decode($clean, true);

            if (is_array($parsed) && !empty($parsed['text'])) {
                return $parsed;
            }
        }
    } catch (Exception $e) {}
    return null;
}

/**
 * Intelligent Stylist Fallback Engine (Runs when Gemini API is unavailable or unconfigured)
 */
function run_stylist_fallback_engine($pdo, $userMsg, $trainedIntents) {
    $qLower = strtolower($userMsg);

    // Extract potential color from query
    $colorMap = [
        'red' => 'red', 'maroon' => 'maroon', 'ruby' => 'ruby',
        'green' => 'green', 'emerald' => 'emerald',
        'blue' => 'blue', 'sapphire' => 'sapphire',
        'pink' => 'pink', 'rani' => 'rani',
        'gold' => 'gold', 'golden' => 'gold',
        'black' => 'black',
        'white' => 'white', 'pearl' => 'pearl', 'ivory' => 'ivory',
        'orange' => 'orange', 'rust' => 'orange',
        'yellow' => 'yellow', 'mustard' => 'yellow'
    ];
    $matchedColor = null;
    foreach ($colorMap as $cWord => $cVal) {
        if (preg_match('/\b' . $cWord . '\b/i', $qLower)) {
            $matchedColor = $cVal;
            break;
        }
    }

    // Extract price budget if mentioned
    $maxPrice = null;
    if (preg_match('/(?:under|below|less than|budget)\s*(?:rs\.?|inr|₹)?\s*(\d+)/i', $qLower, $pMatch)) {
        $maxPrice = (float)$pMatch[1];
    } elseif (str_contains($qLower, 'under 5000') || str_contains($qLower, '5000')) {
        $maxPrice = 5000;
    } elseif (str_contains($qLower, 'under 3000') || str_contains($qLower, '3000')) {
        $maxPrice = 3000;
    }

    // 1. SPECIFIC ITEM & CATEGORY CLASSIFICATION (Strictly Isolated!)

    // A. EARRINGS ONLY (Must NOT return bangles or necklaces)
    $isEarring = str_contains($qLower, 'earring') || str_contains($qLower, 'jhumk') || str_contains($qLower, 'chandbali') 
        || str_contains($qLower, 'chaandbali') || str_contains($qLower, 'bugadi') || str_contains($qLower, 'dangler') 
        || preg_match('/\bstuds?\b/i', $qLower) || preg_match('/\btops?\b/i', $qLower);

    // B. BANGLES & KADAS ONLY (Must NOT return earrings or necklaces)
    $isBangle = str_contains($qLower, 'bangle') || str_contains($qLower, 'kada') || str_contains($qLower, 'bracelet') 
        || str_contains($qLower, 'kangan') || str_contains($qLower, 'chooda');

    // C. NECKLACES & CHOKERS ONLY (Must NOT return bangles or earrings)
    $isNecklace = str_contains($qLower, 'necklace') || str_contains($qLower, 'choker') || str_contains($qLower, 'mala') 
        || str_contains($qLower, 'haar') || str_contains($qLower, 'collar') || str_contains($qLower, 'pendant set');

    // D. HATHPHOOL / HAND HARNESS
    $isHathphool = str_contains($qLower, 'hathphool') || str_contains($qLower, 'hath panja') || str_contains($qLower, 'hand harness');

    // E. HEAD JEWELLERY (Maang Tikka, Mathapatti, Damini)
    $isHeadJewellery = str_contains($qLower, 'tikka') || str_contains($qLower, 'mathapatti') || str_contains($qLower, 'damini') 
        || str_contains($qLower, 'borla') || str_contains($qLower, 'hair');

    // F. BLOUSES & CHOLIS ONLY (Must NOT return jewellery)
    $isBlouse = str_contains($qLower, 'blouse') || str_contains($qLower, 'choli') || str_contains($qLower, 'crop top') 
        || str_contains($qLower, 'sleeves') || str_contains($qLower, 'back neck');

    // G. OUTFITS / SAREES / LEHENGAS
    $isOutfit = str_contains($qLower, 'lehenga') || str_contains($qLower, 'saree') || str_contains($qLower, 'sari') 
        || str_contains($qLower, 'gown') || str_contains($qLower, 'kalamkari');

    // H. OCCASIONS
    $isBride = str_contains($qLower, 'bride') || str_contains($qLower, 'bridal') || str_contains($qLower, 'wedding') 
        || str_contains($qLower, 'marriage') || str_contains($qLower, 'shaadi');
    $isBridesmaid = str_contains($qLower, 'sider') || str_contains($qLower, 'bridesmaid') || str_contains($qLower, 'sister') 
        || str_contains($qLower, 'friend wedding');
    $isReception = str_contains($qLower, 'reception') || str_contains($qLower, 'cocktail') || str_contains($qLower, 'party');
    $isSangeetOrHaldi = str_contains($qLower, 'sangeet') || str_contains($qLower, 'mehendi') || str_contains($qLower, 'haldi');
    $isMatching = str_contains($qLower, 'match') || str_contains($qLower, 'matching') || str_contains($qLower, 'pair') 
        || str_contains($qLower, 'contrast');

    // Now, handle each specific category with laser precision:
    if ($isEarring) {
        $colorTxt = $matchedColor ? ucfirst($matchedColor) . " " : "";
        $reply = "Here are our finest handcrafted {$colorTxt}earrings, temple jhumkas, and statement studs to elevate your look ✨";
        $products = search_products_by_category_type($pdo, 'earrings', $matchedColor, $maxPrice);
        $chips = ['💎 Kundan Jhumkas', '✨ American Diamond Studs', '👑 Polki Chandbalis', '👗 Match a Blouse'];
    } elseif ($isBangle) {
        $colorTxt = $matchedColor ? ucfirst($matchedColor) . " " : "";
        $reply = "Here are exquisite handcrafted {$colorTxt}bangles, royal kadas, and single-line bracelets from our heritage collection:";
        $products = search_products_by_category_type($pdo, 'bangles', $matchedColor, $maxPrice);
        $chips = ['👑 Vilandi Bangles', '✨ Antique Kadas', '💎 Hathphool Hand Harness', '👗 Match a Blouse'];
    } elseif ($isNecklace) {
        $colorTxt = $matchedColor ? ucfirst($matchedColor) . " " : "";
        $reply = "Here are our signature {$colorTxt}necklace sets, bridal chokers, and royal malas designed with artisanal grandeur:";
        $products = search_products_by_category_type($pdo, 'necklaces', $matchedColor, $maxPrice);
        $chips = ['💎 Kundan Chokers', '✨ Polki Necklace Sets', '👑 South Indian Malas', '👗 Match a Blouse'];
    } elseif ($isHathphool) {
        $reply = "Here are handcrafted bridal hathphools and hand harnesses adorned with pearls and kundan work:";
        $products = search_products_by_category_type($pdo, 'hathphool', $matchedColor, $maxPrice);
        $chips = ['👑 Bridal Hathphool', '✨ Kundan Bangles', '💎 Choker Sets', '💬 WhatsApp Stylist'];
    } elseif ($isHeadJewellery) {
        $reply = "Here are handcrafted maang tikkas, mathapattis, and royal hair accessories:";
        $products = search_products_by_category_type($pdo, 'head_jewellery', $matchedColor, $maxPrice);
        $chips = ['👑 Kundan Mathapatti', '✨ Maang Tikkas', '💎 Choker Sets', '👗 Bridal Blouses'];
    } elseif ($isBlouse) {
        $colorTxt = $matchedColor ? ucfirst($matchedColor) . " " : "";
        $reply = "Here are handcrafted {$colorTxt}designer blouses featuring master artisan embroidery and padded contour fits:";
        $products = search_products_by_category_type($pdo, 'blouses', $matchedColor, $maxPrice);
        $chips = ['👗 Velvet Blouses', '✨ Brocade Designs', '💎 Match Jewellery', '📞 Custom Stitching'];
    } elseif ($isOutfit) {
        $reply = "Here are our signature designer outfits, lehengas, and drapes crafted for grand celebrations:";
        $products = search_products_by_category_type($pdo, 'outfits', $matchedColor, $maxPrice);
        $chips = ['💃 Bridal Lehengas', '✨ Kalamkari Drapes', '💎 Match Jewellery', '📞 Book Video Call'];
    } elseif ($isBride) {
        $reply = "Congratulations on your special day! 👰 As a YosshitaNeha bride, we recommend rich Kimkhwab brocade blouses paired with royal Vilandi jewellery for timeless grandeur:";
        $products = search_products_by_category_type($pdo, 'bridal', $matchedColor, $maxPrice);
        $chips = ['👰 Bridal Blouses', '👑 Royal Vilandi Sets', '💎 Kundan Mathapatti', '📞 1-on-1 Designer Call'];
    } elseif ($isBridesmaid) {
        $reply = "For the sister of the bride or bridesmaid, vibrant elegance with effortless dance comfort is key! Here are top crowd-favorite picks:";
        $products = search_products_by_category_type($pdo, 'blouses', $matchedColor, $maxPrice);
        $chips = ['💃 Sangeet Outfits', '✨ Kundan Jhumkas', '👑 Festive Bangles', '👗 Designer Blouses'];
    } elseif ($isReception) {
        $reply = "For a chic cocktail or reception evening, sophisticated glamour is key! A sleek designer blouse paired with statement Polki or diamond earrings creates an effortless look ✨";
        $products = search_products_by_category_type($pdo, 'blouses', $matchedColor, $maxPrice);
        $chips = ['👗 Reception Blouses', '💎 Statement Earrings', '✨ Indo-Western Drapes', '💬 Discuss on WhatsApp'];
    } elseif ($isSangeetOrHaldi) {
        $reply = "For Haldi & Mehendi festivities, bright yellows, pistachio greens, and floral accents create a radiant glow! Pair with lightweight antique gold jhumkas ✨";
        $products = search_products_by_category_type($pdo, 'blouses', 'yellow', $maxPrice);
        $chips = ['💛 Haldi Blouses', '🌿 Mehendi Wear', '💎 Light Jhumkas', '👑 Colourful Bangles'];
    } elseif ($isMatching) {
        // Smart Color Contrast Matching
        if ($matchedColor === 'red' || $matchedColor === 'maroon' || $matchedColor === 'ruby') {
            $reply = "For your {$matchedColor} outfit, emerald green Kundan earrings or gold Vilandi necklaces create a breathtaking, royal contrast! Here are top pairings:";
            $products = search_products_by_category_type($pdo, 'jewellery', 'green', $maxPrice);
        } elseif ($matchedColor === 'gold' || $matchedColor === 'white' || $matchedColor === 'ivory' || $matchedColor === 'yellow') {
            $reply = "For your {$matchedColor} outfit, ruby red stones or classic uncut Polki diamonds add a rich, regal touch! Here are top pairings:";
            $products = search_products_by_category_type($pdo, 'jewellery', 'ruby', $maxPrice);
        } elseif ($matchedColor === 'black' || $matchedColor === 'blue') {
            $reply = "For your {$matchedColor} outfit, sparkling American Diamond chokers or antique Vilandi pieces create an iconic look! Here are top pairings:";
            $products = search_products_by_category_type($pdo, 'jewellery', null, $maxPrice);
        } else {
            $reply = "When styling luxury ethnic wear, contrast is queen! Pair your outfit with handcrafted heritage Kundan or Vilandi jewellery:";
            $products = search_products_by_category_type($pdo, 'jewellery', null, $maxPrice);
        }
        $chips = ['💎 Show Earrings Only', '👑 Show Chokers', '✨ Show Bangles', '💬 WhatsApp Stylist'];
    } elseif ($maxPrice !== null) {
        $reply = "Here are our most coveted couture pieces crafted under ₹" . number_format($maxPrice) . ", offering luxury design at exceptional value:";
        $products = search_products_by_category_type($pdo, 'all', null, $maxPrice);
        $chips = ['💰 Under ₹3,000', '🔥 Best Sellers', '💎 Festive Jewellery', '👗 Designer Blouses'];
    } else {
        // Fallback: Check custom FAQs in DB or General Catalog
        $matchedIntent = null;
        foreach ($trainedIntents as $key => $intentData) {
            $keywords = $intentData['keywords'] ?? [];
            foreach ($keywords as $kw) {
                if ($kw === 'wear' || $kw === 'look') continue;
                if (!empty($kw) && str_contains($qLower, $kw)) {
                    $matchedIntent = $intentData;
                    break 2;
                }
            }
        }

        if ($matchedIntent) {
            $reply = $matchedIntent['reply'];
            $searchTerm = $matchedIntent['search_term'] ?? 'blouse';
            $products = search_matching_products($pdo, $searchTerm);
            $chips = ['✨ Explore Collection', '💎 Match Jewellery', '💬 Speak with Stylist'];
        } else {
            $reply = "Namaste! ✨ Welcome to YosshitaNeha Fashion Studio. I can assist you with designer blouses, heritage Kundan & Vilandi jewellery, custom bridal stitching, and color styling. What are you looking to style today?";
            $products = search_products_by_category_type($pdo, 'blouses', null, null);
            $chips = ['💎 Show Earrings', '👑 Show Bangles', '👗 Designer Blouses', '✨ Match Jewellery'];
        }
    }

    return [
        'reply' => $reply,
        'products' => $products,
        'quick_chips' => $chips
    ];
}

/**
 * Laser-Targeted Product Search by Exact Category Type & Color Filter
 */
function search_products_by_category_type($pdo, $type, $color = null, $maxPrice = null, $limit = 4) {
    try {
        $whereClauses = ["p.status = 'published'", "p.deleted_at IS NULL", "p.stock_qty > 0"];
        $params = [];

        switch ($type) {
            case 'earrings':
                $whereClauses[] = "(
                    p.category_id = 6 
                    OR p.category_id IN (SELECT id FROM categories WHERE parent_id = 6)
                    OR p.name LIKE '%earring%' 
                    OR p.name LIKE '%jhumk%' 
                    OR p.name LIKE '%chandbali%'
                    OR p.name LIKE '%bugadi%'
                    OR p.name LIKE '%stud earring%'
                    OR p.name LIKE '%floral stud%'
                )";
                $whereClauses[] = "p.name NOT LIKE '%hath%'";
                $whereClauses[] = "p.name NOT LIKE '%bangle%'";
                $whereClauses[] = "p.name NOT LIKE '%bracelet%'";
                $whereClauses[] = "p.name NOT LIKE '%payal%'";
                $whereClauses[] = "p.name NOT LIKE '%necklace%'";
                break;

            case 'bangles':
                $whereClauses[] = "(
                    p.category_id = 7 
                    OR p.category_id IN (SELECT id FROM categories WHERE parent_id = 7)
                    OR p.name LIKE '%bangle%' 
                    OR p.name LIKE '%kada%' 
                    OR p.name LIKE '%kangan%'
                )";
                $whereClauses[] = "p.name NOT LIKE '%earring%'";
                $whereClauses[] = "p.name NOT LIKE '%necklace%'";
                break;

            case 'necklaces':
                $whereClauses[] = "(
                    p.category_id = 2 
                    OR p.category_id IN (SELECT id FROM categories WHERE parent_id = 2)
                    OR p.name LIKE '%necklace%' 
                    OR p.name LIKE '%choker%' 
                    OR p.name LIKE '%mala%'
                )";
                $whereClauses[] = "p.name NOT LIKE '%bangle%' AND p.name NOT LIKE '%hath%'";
                break;

            case 'hathphool':
                $whereClauses[] = "(p.name LIKE '%hath%' OR c.name LIKE '%hath%')";
                break;

            case 'head_jewellery':
                $whereClauses[] = "(p.name LIKE '%tikka%' OR p.name LIKE '%mathapatti%' OR p.name LIKE '%damini%' OR p.name LIKE '%borla%')";
                break;

            case 'blouses':
                $whereClauses[] = "(
                    p.category_id = 26 
                    OR p.category_id IN (SELECT id FROM categories WHERE parent_id = 26)
                    OR p.name LIKE '%blouse%' 
                    OR p.name LIKE '%choli%'
                )";
                $whereClauses[] = "p.name NOT LIKE '%necklace%' AND p.name NOT LIKE '%bangle%' AND p.name NOT LIKE '%earring%'";
                break;

            case 'outfits':
                $whereClauses[] = "(
                    p.category_id = 26 
                    OR p.category_id IN (SELECT id FROM categories WHERE parent_id = 26)
                    OR p.name LIKE '%lehenga%' 
                    OR p.name LIKE '%saree%' 
                    OR p.name LIKE '%gown%' 
                    OR p.name LIKE '%kalamkari%'
                )";
                break;

            case 'bridal':
                $whereClauses[] = "(
                    p.is_featured = 1 
                    OR p.name LIKE '%bridal%' 
                    OR p.name LIKE '%wedding%' 
                    OR p.name LIKE '%vilandi%' 
                    OR p.name LIKE '%kundan%' 
                    OR p.name LIKE '%brocade%'
                )";
                break;

            case 'jewellery':
                $whereClauses[] = "(p.category_id = 1 OR p.category_id IN (SELECT id FROM categories WHERE parent_id = 1) OR c.name LIKE '%jewel%' OR c.name LIKE '%necklace%' OR c.name LIKE '%earring%')";
                break;

            default:
                break;
        }

        // Apply color filter if present
        if (!empty($color)) {
            $whereClauses[] = "(p.name LIKE ? OR p.description LIKE ?)";
            $params[] = "%$color%";
            $params[] = "%$color%";
        }

        // Apply budget filter if present
        if ($maxPrice !== null && $maxPrice > 0) {
            $whereClauses[] = "p.price <= ?";
            $params[] = (float)$maxPrice;
        }

        $whereSql = implode(" AND ", $whereClauses);
        $sql = "SELECT p.id, p.name, p.slug, p.sku, p.price, p.sale_price, p.main_image, p.stock_qty, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE $whereSql 
                ORDER BY p.is_featured DESC, p.id DESC 
                LIMIT " . (int)$limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If color filter was too strict and yielded 0, retry without color
        if (empty($results) && !empty($color)) {
            return search_products_by_category_type($pdo, $type, null, $maxPrice, $limit);
        }

        // If still 0, fallback to featured in-stock items
        if (empty($results)) {
            $fallbackSql = "SELECT p.id, p.name, p.slug, p.sku, p.price, p.sale_price, p.main_image, p.stock_qty, c.name as category_name 
                            FROM products p 
                            LEFT JOIN categories c ON p.category_id = c.id 
                            WHERE p.status = 'published' AND p.deleted_at IS NULL AND p.stock_qty > 0 
                            ORDER BY p.is_featured DESC, p.id DESC 
                            LIMIT " . (int)$limit;
            $stmtF = $pdo->query($fallbackSql);
            $results = $stmtF->fetchAll(PDO::FETCH_ASSOC);
        }

        return $results;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Generic Fallback Search Helper
 */
function search_matching_products($pdo, $searchTermsInput, $limit = 4, $maxPrice = null) {
    $cleanQuery = is_array($searchTermsInput) ? implode(' ', $searchTermsInput) : (string)$searchTermsInput;
    $qLower = strtolower($cleanQuery);

    if (str_contains($qLower, 'earring') || str_contains($qLower, 'jhumk')) {
        return search_products_by_category_type($pdo, 'earrings', null, $maxPrice, $limit);
    }
    if (str_contains($qLower, 'bangle') || str_contains($qLower, 'kada')) {
        return search_products_by_category_type($pdo, 'bangles', null, $maxPrice, $limit);
    }
    if (str_contains($qLower, 'necklace') || str_contains($qLower, 'choker')) {
        return search_products_by_category_type($pdo, 'necklaces', null, $maxPrice, $limit);
    }
    if (str_contains($qLower, 'blouse') || str_contains($qLower, 'choli')) {
        return search_products_by_category_type($pdo, 'blouses', null, $maxPrice, $limit);
    }

    return search_products_by_category_type($pdo, 'all', null, $maxPrice, $limit);
}
