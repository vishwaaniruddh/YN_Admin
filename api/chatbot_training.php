<?php
// admin/api/chatbot_training.php
// Dynamic Knowledge Base & FAQ Engine reading directly from chatbot_faqs DB table

function get_chatbot_db_knowledge($pdo) {
    $s = [];
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'chatbot_%'");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $s[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {}

    $phone1 = $s['chatbot_phone_1'] ?? '+91 9324243011';
    $phone2 = $s['chatbot_phone_2'] ?? '+91 7506628663';
    $email = $s['chatbot_email'] ?? 'yosshita.neha@gmail.com';
    $address = $s['chatbot_address'] ?? '104, Shyamkamal Building B/1, Agarwal Market, Near Deenanath Mangeshkar Natya Mandir, Vile Parle East, Mumbai - 400057';
    $hours = $s['chatbot_hours'] ?? 'Mon - Sat: 11:00 AM - 7:30 PM (IST)';
    $phonesText = implode(' / ', array_filter([$phone1, $phone2]));

    // Fetch All Dynamic Custom FAQs from DB
    $customFaqs = [];
    try {
        $stmtF = $pdo->query("SELECT id, keywords, answer FROM chatbot_faqs WHERE is_active = 1 ORDER BY sort_order ASC, id DESC");
        $customFaqs = $stmtF->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}

    $intents = [];

    // 1. Built-in Primary Contact Intent
    $intents['contact'] = [
        'keywords' => ['contact', 'phone', 'number', 'mobile', 'call', 'whatsapp', 'address', 'location', 'studio', 'where', 'map', 'hours', 'timing', 'open'],
        'reply' => "📍 **YosshitaNeha Fashion Studio Contact Information**\n\n" .
                   "📞 **Phone / WhatsApp**: " . $phonesText . "\n" .
                   "✉️ **Email**: " . $email . "\n" .
                   "🏢 **Studio Address**: " . $address . "\n" .
                   "⏰ **Operating Hours**: " . $hours . "\n\n" .
                   "Feel free to call or WhatsApp us directly for custom orders and instant styling assistance!",
        'show_products' => false
    ];

    // 2. Load All Custom FAQs from DB
    foreach ($customFaqs as $f) {
        $kwList = array_map('trim', explode(',', strtolower($f['keywords'])));
        $cleanKw = array_filter($kwList, fn($k) => strlen($k) > 1);

        $intents['faq_' . $f['id']] = [
            'keywords' => $cleanKw,
            'reply' => $f['answer'],
            'show_products' => false
        ];
    }

    // 3. Product Catalog Intents
    $intents['apparel'] = [
        'keywords' => ['blouse', 'saree', 'lehenga', 'outfit', 'crop top', 'skirt', 'wear', 'apparel'],
        'reply' => "We offer an exclusive range of handcrafted designer blouses and ethnic wear! Here are top active in-stock designs:",
        'show_products' => true,
        'search_term' => 'blouse'
    ];

    $intents['jewellery'] = [
        'keywords' => ['jewel', 'earring', 'necklace', 'kundan', 'polki', 'jhumka', 'antique', 'choker', 'bangles'],
        'reply' => "Discover our heritage jewellery collection, perfect for weddings, festivities, and special occasions:",
        'show_products' => true,
        'search_term' => 'jewellery'
    ];

    // Build Gemini AI Context Knowledge String
    $aiContextStr = "STORE KNOWLEDGE BASE (LIVE DATABASE):\n";
    $aiContextStr .= "- Phone / WhatsApp: " . $phonesText . "\n";
    $aiContextStr .= "- Email: " . $email . "\n";
    $aiContextStr .= "- Address: " . $address . "\n";
    $aiContextStr .= "- Operating Hours: " . $hours . "\n\n";
    $aiContextStr .= "TRAINED FAQs & CUSTOMER POLICIES:\n";
    foreach ($customFaqs as $f) {
        $aiContextStr .= "Q Trigger: " . $f['keywords'] . "\nAnswer: " . $f['answer'] . "\n\n";
    }

    return [
        'store_info' => [
            'name' => 'YosshitaNeha Fashion Studio',
            'phone1' => $phone1,
            'phone2' => $phone2,
            'whatsapp' => $phone1,
            'email' => $email,
            'address' => $address,
            'operating_hours' => $hours
        ],
        'intents' => $intents,
        'ai_context' => $aiContextStr
    ];
}
