<?php
// admin/includes/offer_templates.php
// 54 curated design templates for YosshitaNeha Fashion Studio & Jewellery Offer Popups

function offer_card_style_options() {
    return [
        'classic'           => 'Classic Luxe',
        'double_gold_frame' => 'Double Filigree Frame',
        'arch_editorial'    => 'Arch Cutout Editorial',
        'glass_frosted'     => 'Frosted Glass',
        'editorial_sharp'   => 'Magazine Sharp',
        'split_ribbon'      => 'Corner Ribbon Accent',
        'soft_pill_ticket'  => 'Boutique Ticket',
        'bold_brutalist'    => 'Bold Graphic Frame',
        'dark_luxe_glow'    => 'Jewel Box Glow',
        'gradient_festive'  => 'Gradient Canvas',
    ];
}

function get_offer_design_templates() {
    // Helper closure to build concise uniform entries
    $t = function ($id, $name, $cat, $layout, $style, $bg, $sec, $txt, $acc, $badge, $head, $sub, $body, $cta, $url, $note = 'Apply code at checkout') {
        return [
            'id' => $id,
            'name' => $name,
            'category' => $cat,
            'layout_type' => $layout,
            'card_style' => $style,
            'bg_color' => $bg,
            'secondary_color' => $sec,
            'text_color' => $txt,
            'accent_color' => $acc,
            'badge_text' => $badge,
            'heading' => $head,
            'subheading' => $sub,
            'body_text' => $body,
            'cta_label' => $cta,
            'cta_url' => $url,
            'coupon_note' => $note,
        ];
    };

    return [
        // -----------------------------------------------------------------
        // 1. BRIDAL & HERITAGE COUTURE (9 Templates)
        // -----------------------------------------------------------------
        $t('bridal_polki_arch', 'Royal Polki Heritage Arch', 'Bridal & Couture', 'top_banner', 'arch_editorial', '#FAF7F3', '#EFE6D5', '#1C1917', '#B08D57',
            'BRIDAL HERITAGE 2026', 'Timeless Polki & Kundan Trousseau', 'Curated Heirloom Bridal Sets',
            'Explore hand-set uncut Polki chokers, mathapattis, and heirloom rani haars crafted for wedding grandeur.', 'Explore Bridal Sets', '/collections/necklace-sets'),

        $t('bridal_crimson_velvet', 'Crimson Bridal Velvet', 'Bridal & Couture', 'split_left', 'double_gold_frame', '#4C0519', '#881337', '#FFF1F2', '#F5D08A',
            'SHAADI SEASON EDIT', 'Flat 15% Off Bridal Chokers', 'Velvet Craftsmanship for Your Big Day',
            'Pair your wedding lehenga with bespoke jadau sets and embroidered designer blouses from our Mumbai atelier.', 'Shop Bridal Edit', '/collections/kundan'),

        $t('bridal_sangeet_midnight', 'Sangeet Midnight Glamour', 'Bridal & Couture', 'hero_overlay', 'dark_luxe_glow', '#0F172A', '#1E1B4B', '#F8FAFC', '#EAB308',
            'SANGEET NIGHTS', 'Sparkle Under the Lights', 'High-Shine American Diamond & Cocktail Sets',
            'Turn heads at your sangeet with featherlight crystal necklaces and statement chandbalis.', 'Shop Cocktail Sets', '/collections/american-diamond'),

        $t('bridal_mehendi_emerald', 'Mehendi Emerald Filigree', 'Bridal & Couture', 'split_right', 'double_gold_frame', '#052E16', '#064E3B', '#ECFDF5', '#FCD34D',
            'MEHENDI MORNINGS', 'Floral & Kundan Floral Accents', 'Lightweight Elegance for Ceremonies',
            'Vibrant mehendi-ready jhumkas, floral hath panjas, and kalamkari festive blouses.', 'View Mehendi Looks', '/collections/earrings'),

        $t('bridal_haldi_marigold', 'Haldi Marigold Festive', 'Bridal & Couture', 'top_banner', 'gradient_festive', '#FFFBEB', '#FDE68A', '#451A03', '#D97706',
            'HALDI CELEBRATIONS', 'Radiant Gold & Floral Touches', 'Warm Sunlit Bridal Styling',
            'Effortless antique micro-gold earrings, passas, and yellow festive silk blouses.', 'Shop Haldi Edit', '/collections/outfit'),

        $t('bridal_rajwadi_kundan', 'Rajwadi Palace Sharp', 'Bridal & Couture', 'split_left', 'editorial_sharp', '#F5F0E6', '#E5DCCB', '#292524', '#92400E',
            'RAJWADI COLLECTION', 'Museum-Grade Jadau Craft', 'Complimentary Personalized Styling',
            'Handcrafted multi-layered bridal sets worthy of heirloom preservation.', 'View Heritage Sets', '/collections/south-indian-set'),

        $t('bridal_trousseau_blush', 'Trousseau Rose Glass', 'Bridal & Couture', 'hero_overlay', 'glass_frosted', '#FFF1F2', '#FCE7F3', '#FFFFFF', '#F43F5E',
            'TROUSSEAU EXCLUSIVE', 'Curate Your Wedding Wardrobe', 'Special Trousseau Savings Inside',
            'Build a 3+ piece bridal jewellery box and enjoy priority dispatch across India.', 'Explore Trousseau', '/collections/jewellery'),

        $t('bridal_reception_ivory', 'Reception Pearl Minimal', 'Bridal & Couture', 'text_card', 'double_gold_frame', '#FAF8F5', '#F3EFEA', '#18181B', '#A16207',
            'RECEPTION EDIT', 'Understated Ivory & Diamond Sets', 'Modern Couture Elegance',
            'Minimalist solitaire necklaces and sculpted evening blouses tailored to measure.', 'Shop Evening Wear', '/collections/necklace-sets'),

        $t('bridal_poster_grandeur', 'Bridal Poster Banner', 'Bridal & Couture', 'banner_only', 'classic', '#18181B', '#27272A', '#FAF7F3', '#D4AF37',
            'NEW BRIDAL DROP', 'Bridal Season 2026', 'Signature Atelier Release',
            'Discover our newest handcrafted heirloom bridal collection.', 'Shop the Drop', '/collections/necklace-sets'),

        // -----------------------------------------------------------------
        // 2. JEWELLERY & NEW ARRIVALS (9 Templates)
        // -----------------------------------------------------------------
        $t('jwl_ad_spotlight', 'American Diamond Spotlight', 'Jewellery Edit', 'split_left', 'dark_luxe_glow', '#09090B', '#181826', '#FAFAFA', '#38BDF8',
            'ICED DIAMOND SERIES', 'Brilliant-Cut AD Masterpieces', 'Rhodium & Gold-Plated Precision',
            'Hand-set zirconia sparkles like real solitaire diamonds at a fraction of the cost.', 'Shop AD Sets', '/collections/american-diamond'),

        $t('jwl_temple_antique', 'Temple Antique Split', 'Jewellery Edit', 'split_right', 'double_gold_frame', '#FDF8F0', '#F5E6CC', '#3E2723', '#B45309',
            'TEMPLE ARTISTRY', 'Classic South Indian Antique', '24K Matte Micro-Gold Finish',
            'Intricate Lakshmi carvings paired with kemp stones for timeless South Indian grace.', 'Shop Temple Sets', '/collections/south-indian-set'),

        $t('jwl_choker_ribbon', 'Choker Trunk Ribbon', 'Jewellery Edit', 'top_banner', 'split_ribbon', '#FFFFFF', '#F4F4F5', '#18181B', '#B91C1C',
            'BESTSELLER EDIT', 'Most-Loved Bridal Chokers', 'Limited Pieces Restocked Today',
            'Grab our viral hand-painted Meenakari and Kundan chokers before they sell out again.', 'Shop Chokers', '/collections/kundan'),

        $t('jwl_earring_jhumka', 'Jhumka & Chandbali Trio', 'Jewellery Edit', 'top_banner', 'soft_pill_ticket', '#FFF7ED', '#FFEDD5', '#431407', '#C2410C',
            'EARRING VAULT', 'Jhumkas, Chandbalis & Drops', 'Under ₹2,999 Favorites',
            'From feathery everyday studs to shoulder-grazing festive chaandbalis.', 'Browse Earrings', '/collections/earrings'),

        $t('jwl_bangle_stack', 'Bangles & Kada Boutique', 'Jewellery Edit', 'split_left', 'bold_brutalist', '#FEFCE8', '#FEF08A', '#1E293B', '#0F172A',
            'ARM CANDY', 'Polki Kadas & Bridal Chooda', 'Custom Size Selection Available',
            'Mix-and-match stone-studded bangles designed for effortless stacking.', 'Shop Bangles', '/collections/bangles'),

        $t('jwl_mathapatti_royal', 'Mathapatti & Nath Royal', 'Jewellery Edit', 'hero_overlay', 'arch_editorial', '#27272A', '#3F3F46', '#FFFFFF', '#D4AF37',
            'HEADPIECE ATELIER', 'Damini, Borlas & Bridal Nath', 'Traditional Silhouettes Reimagined',
            'Delicate pearl strands meet heirloom Kundan centerpiece crafting.', 'Shop Headpieces', '/collections/damini-mathapatti'),

        $t('jwl_pastel_meenakari', 'Pastel Meenakari Fresh', 'Jewellery Edit', 'split_right', 'glass_frosted', '#F0FDF4', '#DCFCE7', '#14532D', '#059669',
            'PASTEL STUDIO', 'Mint & Blush Meenakari Drops', 'Day Wedding Perfection',
            'Soft mint green, powder pink, and champagne gold finishes for daytime celebrations.', 'Shop Pastel Edit', '/collections/earrings'),

        $t('jwl_noir_diamond', 'Solitaire Noir Typo Card', 'Jewellery Edit', 'text_card', 'dark_luxe_glow', '#111827', '#1F2937', '#F9FAFB', '#A78BFA',
            'EVENING SOLITAIRE', 'The Minimalist Diamond Card', 'Complimentary Luxury Jewellery Box',
            'Elevate cocktail gowns and sheer organza sarees with whisper-light diamond pendants.', 'Shop Pendants', '/collections/pendant-set'),

        $t('jwl_poster_earrings', 'Jewellery Poster Banner', 'Jewellery Edit', 'banner_only', 'editorial_sharp', '#FAF7F3', '#E7E5E4', '#18181B', '#B08D57',
            'TRENDING NOW', 'Signature Statement Earrings', 'Handcrafted Finish',
            'Tap to shop the latest earring arrivals.', 'Shop Earrings', '/collections/earrings'),

        // -----------------------------------------------------------------
        // 3. FESTIVE & SALE EVENTS (9 Templates)
        // -----------------------------------------------------------------
        $t('fest_diwali_dhamaka', 'Diwali Dhamaka Gold Glow', 'Festive & Sale', 'hero_overlay', 'gradient_festive', '#7C2D12', '#B45309', '#FFFBEB', '#FBBF24',
            'DIWALI MEGA SALE', 'Flat 20% Off Sitewide + Gift', 'Light Up Every Celebration',
            'Celebrate Diwali in handcrafted splendor with exclusive savings across all couture and jewellery.', 'Shop Diwali Sale', '/collections/jewellery', '20% OFF • Sitewide Festive Code'),

        $t('fest_akshaya_tritiya', 'Akshaya Tritiya Fortune', 'Festive & Sale', 'top_banner', 'double_gold_frame', '#FFFBEB', '#FEF3C7', '#78350F', '#D97706',
            'AKSHAYA TRITIYA', 'Auspicious Gold-Finish Savings', 'Blessed Abundance Edition',
            'Bring home radiant temple and micro-gold plated artistry with a special auspicious discount.', 'Claim Festive Offer', '/collections/necklace-sets'),

        $t('fest_karwa_chauth', 'Karwa Chauth Sindoor', 'Festive & Sale', 'split_left', 'split_ribbon', '#7F1D1D', '#991B1B', '#FEF2F2', '#FDE047',
            'KARWA CHAUTH SPECIAL', 'Red & Gold Festive Favorites', 'Same-Day Express Dispatch Ready',
            'Shop sindoor-red chokers, bridal naths, and embroidered blouses for moonrise elegance.', 'Shop Red Edit', '/collections/necklace-sets'),

        $t('fest_navratri_garba', 'Navratri Mirror & Oxidized', 'Festive & Sale', 'split_right', 'gradient_festive', '#312E81', '#6D28D9', '#FAF5FF', '#F472B6',
            'NAVRATRI 9-NIGHT EDIT', 'Vibrant Oxidized & Mirror Work', 'Buy 2 Get Extra 10% Off',
            'Curated statement oxidized jhumkas, kamar pattas, and colorful embroidered blouses.', 'Shop Oxidized', '/collections/earrings'),

        $t('fest_rakhi_gifting', 'Rakhi Boutique Gifting', 'Festive & Sale', 'text_card', 'soft_pill_ticket', '#FFF1F2', '#FFE4E6', '#881337', '#E11D48',
            'FESTIVE GIFT PASS', 'Gift Handcrafted Luxury', 'Complimentary Signature Gift Wrap',
            'Surprise her with timeless heirloom earrings or a curated jewellery box she will cherish.', 'Explore Gift Picks', '/collections/earrings'),

        $t('fest_eoss_editorial', 'End of Season Magazine', 'Festive & Sale', 'top_banner', 'editorial_sharp', '#FAFAFA', '#E4E4E7', '#09090B', '#DC2626',
            'END OF SEASON SALE', 'Up to 35% Off Studio Archive', 'Final Quantities Remaining',
            'Limited atelier inventory marked down for our seasonal archive clearance.', 'Shop Archive Sale', '/collections/jewellery'),

        $t('fest_flash_24hr', '24-Hour Flash Countdown', 'Festive & Sale', 'text_card', 'bold_brutalist', '#18181B', '#27272A', '#FFFFFF', '#FACC15',
            'FLASH 24H ONLY', 'Unlock Secret VIP Reduction', 'Ends Tonight at Midnight IST',
            'Fast-track your wishlist at rare studio pricing for the next 24 hours only.', 'Shop Flash Offer', '/collections/necklace-sets'),

        $t('fest_weekend_drop', 'Weekend Boutique Drop', 'Festive & Sale', 'split_left', 'classic', '#F8FAFC', '#E2E8F0', '#0F172A', '#2563EB',
            'WEEKEND EXCLUSIVE', 'Extra Savings on New Arrivals', 'Fresh From Mumbai Studio',
            'Discover 40+ fresh artisan designs added this weekend with limited launch pricing.', 'See New Arrivals', '/collections/outfit'),

        $t('fest_poster_sale', 'Festive Poster Full-Bleed', 'Festive & Sale', 'banner_only', 'gradient_festive', '#450A0A', '#7F1D1D', '#FEF2F2', '#FBBF24',
            'LIMITED OFFER', 'Festive Grand Sale', 'Unlock Offer Code Inside',
            'Click poster to explore discounted festive pieces.', 'Shop Now', '/collections/jewellery'),

        // -----------------------------------------------------------------
        // 4. WELCOME, FIRST ORDER & COUPON (9 Templates)
        // -----------------------------------------------------------------
        $t('welc_first_10', 'Atelier First Order 10% Gift', 'Welcome & Coupons', 'split_left', 'classic', '#FAF7F3', '#EFE6D5', '#18181B', '#B08D57',
            'WELCOME GIFT', 'Enjoy 10% Off Your First Order', 'Handcrafted Couture & Jewellery',
            'Welcome to YosshitaNeha Atelier. Use this code at checkout on orders above ₹1,000.', 'Claim 10% Off', '/collections/necklace-sets', '10% OFF • First Order Min ₹1,000'),

        $t('welc_vip_ticket', 'Atelier VIP Ticket Voucher', 'Welcome & Coupons', 'text_card', 'soft_pill_ticket', '#FDFBF7', '#F5EDE0', '#27272A', '#B08D57',
            'VIP PRIVILEGE PASS', '₹500 Off Your Curated Bag', 'Instant Checkout Reward',
            'Present this studio pass during checkout to unlock complimentary savings on handcrafted pieces.', 'Redeem Voucher', '/cart'),

        $t('welc_free_ship', 'Free Express Shipping Pass', 'Welcome & Coupons', 'top_banner', 'glass_frosted', '#F0F9FF', '#E0F2FE', '#0C4A6E', '#0284C7',
            'INSURED DELIVERY', 'Complimentary Express Shipping', 'Pan-India & Priority Handling',
            'Zero shipping fee on your order today with signature cushioned heirloom packaging.', 'Shop Free Shipping', '/collections/jewellery'),

        $t('welc_mystery_gift', 'Surprise Studio Gift Box', 'Welcome & Coupons', 'split_right', 'gradient_festive', '#FAF5FF', '#F3E8FF', '#3B0764', '#9333EA',
            'MYSTERY SURPRISE', 'Complimentary Silk Pouch on Orders', 'Handpicked Atelier Bonus',
            'Order any necklace set today and receive a handcrafted brocade jewellery organizer.', 'Unlock Bonus Gift', '/collections/necklace-sets'),

        $t('welc_ivory_minimal', 'Clean Ivory Minimalist Pass', 'Welcome & Coupons', 'text_card', 'editorial_sharp', '#FFFFFF', '#F4F4F5', '#09090B', '#18181B',
            'MEMBER INVITE', 'Private Client Savings', 'Use Invitation Code Below',
            'Discreet luxury pricing unlocked exclusively for online studio visitors.', 'Explore Studio', '/collections/jewellery'),

        $t('welc_obsidian_vip', 'Black Card Obsidian VIP', 'Welcome & Coupons', 'text_card', 'dark_luxe_glow', '#09090B', '#18181B', '#FAF7F3', '#D4AF37',
            'PRIVATE ACCESS', 'Your Black Card Privileges', 'Priority Access & Extra Savings',
            'Redeem your private studio code on bridal sets, designer blouses, and limited pieces.', 'Shop With Privilege', '/collections/necklace-sets'),

        $t('welc_birthday_treat', 'Celebration Month Voucher', 'Welcome & Coupons', 'top_banner', 'double_gold_frame', '#FFF5F5', '#FED7D7', '#4A151B', '#C53030',
            'CELEBRATE YOU', 'Your Special Occasion Gift', 'Flat Extra Savings This Week',
            'Treat yourself to fine handcrafted detail with a little celebration courtesy of YosshitaNeha.', 'Treat Yourself', '/collections/earrings'),

        $t('welc_restock_alert', 'Back-in-Stock VIP Notify', 'Welcome & Coupons', 'split_left', 'split_ribbon', '#F8FAFC', '#F1F5F9', '#0F172A', '#059669',
            'BACK IN STOCK', 'Viral Signatures Returned', 'Fresh Small-Batch Artisan Run',
            'Our highest-waitlist chokers and embroidered blouses are back in limited counts.', 'Grab Yours Now', '/collections/outfit'),

        $t('welc_poster_coupon', 'Coupon Poster Bottom Bar', 'Welcome & Coupons', 'banner_only', 'soft_pill_ticket', '#FAF7F3', '#EFE6D5', '#18181B', '#B08D57',
            'COUPON DROP', 'Instant Offer Inside', 'Copy Code & Shop',
            'Copy coupon from bar below.', 'Shop Collection', '/collections/jewellery'),

        // -----------------------------------------------------------------
        // 5. CART RECOVERY & CHECKOUT BOOSTERS (9 Templates)
        // -----------------------------------------------------------------
        $t('cart_wait_10', 'Wait! Save Extra 10% Now', 'Cart & Checkout', 'text_card', 'split_ribbon', '#FAF7F3', '#F5EFE6', '#18181B', '#B08D57',
            'BEFORE YOU GO', 'Wait — Take Extra 10% Off Today', 'Apply Directly to Your Cart',
            'Complete your order in the next 15 minutes and enjoy special atelier savings.', 'Apply & Checkout', '/checkout'),

        $t('cart_free_ship_boost', 'Unlock Free Priority Shipping', 'Cart & Checkout', 'text_card', 'soft_pill_ticket', '#ECFDF5', '#D1FAE5', '#064E3B', '#059669',
            'CART BONUS', 'Free Insured Shipping Unlocked', 'Safe Transit Guarantee',
            'Finish your checkout with insured doorstep courier delivery and real-time tracking.', 'Proceed to Checkout', '/checkout'),

        $t('cart_complete_look', 'Complete Your Bridal Pairing', 'Cart & Checkout', 'split_left', 'classic', '#FAF7F3', '#EFE6D5', '#18181B', '#B08D57',
            'STYLIST PAIRING', 'Pair With Matching Earrings', 'Complete the Ensemble',
            'Add handcrafted matching chandbalis or jhumkas to complete your look seamlessly.', 'Browse Earrings', '/collections/earrings'),

        $t('cart_checkout_vip', 'Checkout VIP Concierge', 'Cart & Checkout', 'text_card', 'dark_luxe_glow', '#141416', '#27272A', '#FAF7F3', '#D4AF37',
            'PRIORITY CHECKOUT', 'Enjoy Express Studio Dispatch', 'Razorpay Secured 256-Bit Payment',
            'Your selected pieces are handcrafted in small quantities. Secure yours before stock runs out.', 'Complete Order Now', '/checkout'),

        $t('cart_bundle_save', 'Bundle & Save Extra', 'Cart & Checkout', 'top_banner', 'gradient_festive', '#FFF7ED', '#FFEDD5', '#431407', '#EA580C',
            'BUNDLE PRIVILEGE', 'Add 2+ Pieces & Save More', 'Trousseau Multi-Item Benefit',
            'Curating multiple outfits or jewellery sets? Apply our bundle code at cart summary.', 'Add More Pieces', '/collections/jewellery'),

        $t('cart_stock_hold', 'Low Stock Reservation Alert', 'Cart & Checkout', 'text_card', 'bold_brutalist', '#FFFBEB', '#FEF3C7', '#1C1917', '#B45309',
            'LIMITED EDITION', 'Only Few Artisanal Pieces Left', 'Reserve Your Selection Now',
            'Many of our signature outfits and Kundan pieces are single-piece atelier editions.', 'Keep Shopping', '/cart'),

        $t('cart_prepaid_perk', 'Instant Online Payment Perk', 'Cart & Checkout', 'text_card', 'glass_frosted', '#F5F3FF', '#EDE9FE', '#2E1065', '#6D28D9',
            'PREPAID PRIVILEGE', 'Faster Dispatch on Online Pay', 'UPI, Cards & NetBanking Supported',
            'Pay securely via Razorpay for prioritized atelier quality inspection and dispatch.', 'Go to Checkout', '/checkout'),

        $t('cart_gift_box_upsell', 'Luxury Keepsake Packaging', 'Cart & Checkout', 'split_right', 'double_gold_frame', '#FAF7F3', '#EFE6D5', '#27272A', '#92400E',
            'ATELIER PACKAGING', 'Includes Velvet Heirloom Box', 'Zero Extra Cost',
            'Every jewellery order arrives nestled in cushioned satin-lined storage boxes.', 'View My Bag', '/cart'),

        $t('cart_abandoned_nudge', 'Forgotten Treasures Nudge', 'Cart & Checkout', 'top_banner', 'editorial_sharp', '#FFFFFF', '#F4F4F5', '#18181B', '#B08D57',
            'STILL DECIDING?', 'Your Curated Pieces Await', 'Special Offer Ready in Bag',
            'Don’t let your favorite piece slip away — apply the coupon code before completing checkout.', 'Return to Cart', '/cart'),

        // -----------------------------------------------------------------
        // 6. LOOKBOOK, BLOUSE STUDIO & CONSULTATION (9 Templates)
        // -----------------------------------------------------------------
        $t('studio_ai_tryon', 'AI Blouse Studio Try-On Invite', 'Studio & Concierge', 'hero_overlay', 'glass_frosted', '#18181B', '#27272A', '#FFFFFF', '#EAB308',
            'AI BLOUSE STUDIO', 'Preview Couture Outfits Virtually', 'Interactive Atelier Experience',
            'Visualize handcrafted designer blouses & jewellery combinations on a studio model before ordering.', 'Open AI Studio', '/blouse-studio'),

        $t('studio_consultation', 'Book Designer Video Consult', 'Studio & Concierge', 'split_left', 'double_gold_frame', '#FAF7F3', '#EFE6D5', '#18181B', '#B08D57',
            '1-ON-1 STYLING', 'Book Your Bridal Styling Call', 'Speak Directly With Our Designers',
            'Need custom blouse measurements or jewellery matching with your saree/lehenga? Reserve a free session.', 'Book Consultation', '/consultation'),

        $t('studio_sari_upcycle', 'Heirloom Sari Upcycling', 'Studio & Concierge', 'split_right', 'editorial_sharp', '#F7F4EF', '#EAE2D3', '#292524', '#78350F',
            'SUSTAINABLE COUTURE', 'Transform Your Vintage Sarees', 'Bespoke Tailoring in Mumbai',
            'Turn treasured silk sarees into structured lehengas, embroidered jackets, and statement blouses.', 'Book Upcycle Call', '/consultation'),

        $t('studio_custom_fit', 'Made-to-Measure Blouse Atelier', 'Studio & Concierge', 'top_banner', 'classic', '#FFFBEB', '#FEF3C7', '#27272A', '#B45309',
            'CUSTOM FIT', 'Hand-Embroidered Designer Blouses', 'Tailored Craftsmanship',
            'Explore pure raw silk, kalamkari, zardozi, and mirror-work blouses ready to ship.', 'Explore Blouses', '/collections/outfit'),

        $t('studio_client_diaries', 'Client Diaries Lookbook Feature', 'Studio & Concierge', 'banner_only', 'classic', '#18181B', '#27272A', '#FAF7F3', '#D4AF37',
            'REAL BRIDES', 'Browse Client Diaries', 'See How Brides Styled YN',
            'Explore real wedding moments and custom ensembles worn by YosshitaNeha brides.', 'Open Lookbook', '/lookbook'),

        $t('studio_handwoven_silk', 'Handwoven Silk Showcase', 'Studio & Concierge', 'split_left', 'arch_editorial', '#FFF7F8', '#FCE7F3', '#3F1D2B', '#9D174D',
            'ARTISAN TEXTILES', 'Handloom Kalamkari & Silk', 'Heritage Looms of India',
            'Limited edition hand-painted kalamkari blouses crafted cleanly with breathable linings.', 'View Outfits', '/collections/outfit'),

        $t('studio_intl_concierge', 'Worldwide Bridal Shipping', 'Studio & Concierge', 'text_card', 'editorial_sharp', '#0F172A', '#1E293B', '#F8FAFC', '#38BDF8',
            'GLOBAL ATELIER', 'Shipping Weddings Worldwide', 'USA, UK, UAE, Singapore & More',
            'Ordering from overseas? Book a styling consultation for express international DHL courier.', 'Explore Catalog', '/collections/jewellery'),

        $t('studio_whatsapp_vip', 'WhatsApp Personal Stylist', 'Studio & Concierge', 'text_card', 'soft_pill_ticket', '#F0FDF4', '#DCFCE7', '#14532D', '#16A34A',
            'INSTANT STYLING', 'Match Jewellery to Your Outfit', 'Live Photo Recommendations',
            'Share a photo of your saree or lehenga on WhatsApp and get instant designer pairing ideas.', 'Chat With Stylist', '/consultation'),

        $t('studio_private_trunk', 'Mumbai Studio Trunk Invite', 'Studio & Concierge', 'top_banner', 'dark_luxe_glow', '#1C1917', '#292524', '#FAF7F3', '#EAB308',
            'STUDIO VISIT', 'Visit YosshitaNeha Fashion Studio', 'Private Appointments Open',
            'Experience our bridal jewellery & couture collection in person with customized fitting.', 'Book Appointment', '/consultation'),
    ];
}
