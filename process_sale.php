<?php
// Missing sale endpoint restored without changing the existing application flow.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: index.php?mainmenu=sell_product_others');
    exit;
}

require __DIR__ . '/process/connect.php';
require __DIR__ . '/process/save_sales.php';
