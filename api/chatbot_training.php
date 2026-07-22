<?php
// admin/api/chatbot_training.php
// Dedicated Knowledge Base & Training Data for YosshitaNeha AI Assistant

return [
    'store_info' => [
        'name' => 'YosshitaNeha Fashion Studio',
        'phones' => ['+91 9324243011', '+91 7506628663'],
        'whatsapp' => '+91 9324243011',
        'email' => 'yosshita.neha@gmail.com',
        'address' => '104, Shyamkamal Building B/1, Agarwal Market, Near Deenanath Mangeshkar Natya Mandir, Vile Parle East, Mumbai - 400057',
        'google_map_link' => 'https://maps.google.com/?q=YosshitaNeha+Fashion+Studio+Vile+Parle+East+Mumbai',
        'operating_hours' => 'Monday to Saturday, 11:00 AM - 7:30 PM (IST)'
    ],

    'intents' => [
        // 1. Contact & Studio Info
        'contact' => [
            'keywords' => ['contact', 'phone', 'number', 'mobile', 'call', 'whatsapp', 'address', 'location', 'studio', 'where', 'map', 'hours', 'timing', 'open'],
            'reply' => "📍 **YosshitaNeha Fashion Studio Contact Information**\n\n" .
                       "📞 **Phone / WhatsApp**: +91 9324243011 / +91 7506628663\n" .
                       "✉️ **Email**: yosshita.neha@gmail.com\n" .
                       "🏢 **Studio Address**: 104, Shyamkamal Building B/1, Agarwal Market, Near Deenanath Mangeshkar Natya Mandir, Vile Parle East, Mumbai - 400057\n" .
                       "⏰ **Operating Hours**: Mon - Sat: 11:00 AM - 7:30 PM (IST)\n\n" .
                       "Feel free to call or WhatsApp us directly for custom orders and instant styling assistance!",
            'show_products' => false
        ],

        // 2. Customisation & Tailoring Charges
        'customisation' => [
            'keywords' => ['custom', 'stitch', 'charge', 'alter', 'cost', 'make', 'price to', 'tailor', 'bespoke', 'pattern', 'fabric'],
            'reply' => "✨ **Bespoke Customisation Services & Pricing** ✨\n\n" .
                       "• **Basic Blouse Stitching**: Starts from ₹1,500\n" .
                       "• **Heavy Hand Embroidery & Bridal Wear**: Quotes depend on fabric, zari/zardozi work, and pattern complexity.\n" .
                       "• **Padded & Designer Back Pattern Blouses**: Tailored to your exact measurement profile.\n\n" .
                       "📱 **Get Instant Quote**: Send your reference design picture to our Master Designer via WhatsApp at **+91 9324243011** or upload your photo right here in the chat!",
            'show_products' => false
        ],

        // 3. Shipping & Delivery Times
        'shipping' => [
            'keywords' => ['ship', 'deliver', 'days', 'time', 'how long', 'courier', 'express', 'dispatch', 'tracking'],
            'reply' => "🚚 **Shipping & Delivery Information**\n\n" .
                       "• **Standard Domestic Shipping**: 3–7 business days (Free shipping over ₹5,000)\n" .
                       "• **Bespoke / Customised Outfits**: 10–15 business days\n" .
                       "• **Worldwide Express International Delivery**: 7–12 business days (FedEx / DHL)\n\n" .
                       "You will receive a tracking link via SMS & Email once your package is dispatched!",
            'show_products' => false
        ],

        // 4. Return, Exchange & Cancellation Policy
        'returns' => [
            'keywords' => ['return', 'exchange', 'refund', 'cancel', 'policy', 'damage'],
            'reply' => "🔄 **Return & Exchange Policy**\n\n" .
                       "• **Readymade Apparel & Jewellery**: Eligible for exchange within 7 days of delivery if unused with original tags intact.\n" .
                       "• **Custom / Tailored Outfits**: Non-refundable as they are crafted specifically to your custom measurements. Alterations are complimentary!\n" .
                       "• **Damaged Items**: Please inform us within 24 hours of delivery with an unboxing photo/video for immediate replacement.",
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

        // 6. Sizing & Custom Measurements Guide
        'sizing' => [
            'keywords' => ['size', 'measurement', 'chart', 'fitting', 'bust', 'padded', 'alteration'],
            'reply' => "📐 **Sizing & Fitting Guide**\n\n" .
                       "• Our readymade blouses come with **2–4 inches margin** inside for easy self-alteration.\n" .
                       "• Sizes range from **32 to 44 (Bust Size)**.\n" .
                       "• Need custom fitting? Choose 'Custom Measurements' on any product page or WhatsApp your measurements to **+91 9324243011**!",
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
