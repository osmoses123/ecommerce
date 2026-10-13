<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');
echo json_encode(cart_snapshot());
