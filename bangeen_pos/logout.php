<?php
require_once __DIR__ . '/includes/config.php';
session_destroy();
header('Location: /bangeen_pos/index.php');
exit;
