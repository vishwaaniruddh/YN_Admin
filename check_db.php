<?php
require 'config/db.php';
$stmt = $pdo->query("SELECT count(*) FROM products");
echo "Current products count: " . $stmt->fetchColumn() . "\n";
