<?php
// admin/config/razorpay.php
require_once __DIR__ . '/db.php';

// Fetch Razorpay credentials & mode dynamically from site_settings table
$rzp_defaults = [
    'razorpay_mode' => 'test',
    'razorpay_live_key_id' => 'rzp_live_DW1px0XkHJ4tAv',
    'razorpay_live_key_secret' => 'A52buJeuJW1E8hsEg6ssfm70',
    'razorpay_test_key_id' => 'rzp_test_4gwWqpQ2mlWxfH',
    'razorpay_test_key_secret' => 'e5DXo5IJdIkBO3apRU5zhCVd',
];

try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'razorpay_%'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $rzp_defaults[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Use defaults on error
}

$razorpay_mode = strtolower($rzp_defaults['razorpay_mode'] ?? 'test');

if (!defined('RAZORPAY_KEY_ID')) {
    if ($razorpay_mode === 'live') {
        define('RAZORPAY_KEY_ID', $rzp_defaults['razorpay_live_key_id']);
        define('RAZORPAY_KEY_SECRET', $rzp_defaults['razorpay_live_key_secret']);
    } else {
        define('RAZORPAY_KEY_ID', $rzp_defaults['razorpay_test_key_id']);
        define('RAZORPAY_KEY_SECRET', $rzp_defaults['razorpay_test_key_secret']);
    }
    define('RAZORPAY_MODE', $razorpay_mode);
}
