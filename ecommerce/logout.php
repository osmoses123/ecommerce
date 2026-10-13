<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
unset($_SESSION['user_id']);
flash_set('success', 'You have been signed out.');
redirect('index.php');
