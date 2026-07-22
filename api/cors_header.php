<?php
// admin/api/cors_header.php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Session-Token");

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

header("Content-Type: application/json; charset=UTF-8");

function get_session_token() {
    if (!empty($_SERVER['HTTP_X_SESSION_TOKEN'])) {
        return $_SERVER['HTTP_X_SESSION_TOKEN'];
    }
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if ($headers) {
            foreach ($headers as $key => $val) {
                if (strtolower($key) === 'x-session-token') {
                    return $val;
                }
            }
        }
    }
    if (!empty($_GET['session_token'])) {
        return $_GET['session_token'];
    }
    if (!empty($_POST['session_token'])) {
        return $_POST['session_token'];
    }
    return null;
}
