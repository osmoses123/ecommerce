<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
unset($_SESSION['admin_id']);
redirect('admin/login.php');
