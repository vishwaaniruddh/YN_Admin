<?php
// admin/api/chatbot_training.php
// 100% Database-Driven Knowledge Base & Training Engine for YosshitaNeha AI Assistant

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
    $stitchingPrice = $s['chatbot_stitching_price'] ?? '1500';
    $customInfo = $s['chatbot_custom_info'] ?? 'Blouse stitching starts from ₹' . number_format((float)$stitchingPrice) . '. Custom bridal lehengas & hand embroidery charges depend on fabric, zari work, and pattern complexity.';
    $shippingInfo = $s['chatbot_shipping_info'] ?? 'Domestic shipping: 3-7 business days. Free shipping on orders above ₹5,000. International shipping: 7-12 business days worldwide.';
    $returnInfo = $s['chatbot_return_info'] ?? 'Readymade items eligible for 7-day exchange. Custom tailored outfits are non-refundable but complimentary alterations are provided.';

    $phonesText = implode(' / ', array_filter([$phone1, $phone2]));

    return [
        'store_info' => [
            'name' => 'YosshitaNeha Fashion Studio',
            'phone1' => $phone1,
            'phone2' => $phone2,
            'whatsapp' => $phone1,
            'email' => $email,
            'address' => $address,
            'operating_hours' => $hours,
            'stitching_price' => $stitchingPrice
        ],

        'intents' => [
            // 1. Contact & Studio Info
            'contact' => [
                'keywords' => ['contact', 'phone', 'number', 'mobile', 'call', 'whatsapp', 'address', 'location', 'studio', 'where', 'map', 'hours', 'timing', 'open'],
                'reply' => "📍 **YosshitaNeha Fashion Studio Contact Information**\n\n" .
                           "📞 **Phone / WhatsApp**: " . $phonesText . "\n" .
                           "✉️ **Email**: " . $email . "\n" .
                           "🏢 **Studio Address**: " . $address . "\n" .
                           "⏰ **Operating Hours**: " . $hours . "\n\n" .
                           "Feel free to call or WhatsApp us directly for custom orders and instant styling assistance!",
                'show_products' => false
            ],

            // 2. Customisation & Tailoring Charges
            'customisation' => [
                'keywords' => ['custom', 'stitch', 'charge', 'alter', 'cost', 'make', 'price to', 'tailor', 'bespoke', 'pattern', 'fabric'],
                'reply' => "✨ **Bespoke Customisation Services & Pricing** ✨\n\n" .
                           "• **Basic Blouse Stitching**: Starts from ₹" . number_format((float)$stitchingPrice) . "\n" .
                           "• **Custom Bridal Embroidery & Tailoring**: " . $customInfo . "\n\n" .
                           "📱 **Get Instant Quote**: Send your reference design picture to our Master Designer via WhatsApp at **" . $phone1 . "** or upload your photo right here in the chat!",
                'show_products' => false
            ],

            // 3. Shipping & Delivery Times
            'shipping' => [
                'keywords' => ['ship', 'deliver', 'days', 'time', 'how long', 'courier', 'express', 'dispatch', 'tracking'],
                'reply' => "🚚 **Shipping & Delivery Information**\n\n" . $shippingInfo,
                'show_products' => false
            ],

            // 4. Return, Exchange & Cancellation Policy
            'returns' => [
                'keywords' => ['return', 'exchange', 'refund', 'cancel', 'policy', 'damage'],
                'reply' => "🔄 **Return & Exchange Policy**\n\n" . $returnInfo,
                'show_products' => false
            ],

            // 5. Payment Methods
            'payments' => [
                'keywords' => ['payment', 'pay', 'card', 'upi', 'cod', 'cash on delivery', 'netbanking', 'gpay', 'paytm'],
                'reply' => "💳 **Accepted Payment Methods**\n\n" .
                           "• UPI (Google Pay, PhonePe, Paytm)\n" .
                           "• Credit & Debit Cards (Visa, MasterCard, Amex)\n" .
                           "• Net Banking (All major Indian banks)\n" .
                           "• Cash on Delivery (COD) for eligible domestic pin codes\n" .
                           "• International Card / Wire Transfer for global customers.",
                'show_products' => false
            ],

            // 6. Sizing Guide
            'sizing' => [
                'keywords' => ['size', 'measurement', 'chart', 'fitting', 'bust', 'padded', 'alteration'],
                'reply' => "📐 **Sizing & Fitting Guide**\n\n" .
                           "• Our readymade blouses come with **2–4 inches margin** inside for easy self-alteration.\n" .
                           "• Sizes range from **32 to 44 (Bust Size)**.\n" .
                           "• Need custom fitting? Choose 'Custom Measurements' on any product page or WhatsApp your measurements to **" . $phone1 . "**!",
                'show_products' => false
            ],

            // 7. Blouse & Apparel Catalog Inquiry
            'apparel' => [
                'keywords' => ['blouse', 'saree', 'lehenga', 'outfit', 'crop top', 'skirt', 'wear', 'apparel'],
                'reply' => "We offer an exclusive range of handcrafted designer blouses and ethnic wear! Here are top active in-stock designs:",
                'show_products' => true,
                'search_term' => 'blouse'
            ],

            // 8. Jewellery Catalog Inquiry
            'jewellery' => [
                'keywords' => ['jewel', 'earring', 'necklace', 'kundan', 'polki', 'jhumka', 'antique', 'choker', 'bangles'],
                'reply' => "Discover our heritage jewellery collection, perfect for weddings, festivities, and special occasions:",
                'show_products' => true,
                'search_term' => 'jewellery'
            ]
        ]
    ];
}
