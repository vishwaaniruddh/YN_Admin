<?php
// admin/api/newsletter.php

// Handle preflight CORS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    exit(0);
}

header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// Get raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$email = isset($data['email']) ? filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL) : '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address provided.']);
    exit;
}

try {
    // Check if the email already exists
    $stmt = $pdo->prepare("SELECT id, status FROM newsletters WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['status'] === 'unsubscribed') {
            // Re-subscribe them
            $update = $pdo->prepare("UPDATE newsletters SET status = 'subscribed' WHERE id = ?");
            $update->execute([$existing['id']]);
            // Send email again
            send_newsletter_email($email);
            echo json_encode(['success' => true, 'message' => 'Welcome back! You have been re-subscribed to our newsletter.']);
        } else {
            // Already subscribed
            echo json_encode(['success' => false, 'message' => 'You are already subscribed to our newsletter!']);
        }
    } else {
        // Insert new subscription
        $insert = $pdo->prepare("INSERT INTO newsletters (email, status) VALUES (?, 'subscribed')");
        $insert->execute([$email]);
        
        // Send auto-responder
        send_newsletter_email($email);
        
        echo json_encode(['success' => true, 'message' => 'Thank you for subscribing! Please check your email for a confirmation.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again later.']);
}

/**
 * Helper function to send the auto-responder email.
 */
function send_newsletter_email($to) {
    $subject = "Welcome to YosshitaNeha Fashion Studio!";
    
    // HTML Email template
    $message = "
    <html>
    <head>
        <title>Welcome to YosshitaNeha Fashion Studio</title>
        <style>
            body { font-family: 'Arial', sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; }
            .header { text-align: center; margin-bottom: 30px; }
            .header h1 { color: #C8A55C; margin: 0; }
            .content { margin-bottom: 30px; }
            .footer { text-align: center; font-size: 12px; color: #888; border-top: 1px solid #eee; padding-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>YOSSHITANEHA</h1>
            </div>
            <div class='content'>
                <p>Hello,</p>
                <p>Thank you for subscribing to the YosshitaNeha Fashion Studio newsletter!</p>
                <p>You are now on our exclusive list to receive early access to new collections, personalized styling tips, and special bridal inspiration.</p>
                <p>We're thrilled to have you with us on this journey of luxury and tradition.</p>
                <br>
                <p>Warm regards,<br><strong>The YosshitaNeha Team</strong></p>
            </div>
            <div class='footer'>
                <p>YosshitaNeha Fashion Studio<br>
                104, Shyamkamal Building B/1, Vile Parle East, Mumbai 400057</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // Required headers for HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    
    // Additional headers
    $headers .= 'From: YosshitaNeha <yosshita.neha@gmail.com>' . "\r\n";
    $headers .= 'Reply-To: yosshita.neha@gmail.com' . "\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion();

    // Use @ to suppress warnings if mail server is not configured in local environment
    @mail($to, $subject, $message, $headers);
}
