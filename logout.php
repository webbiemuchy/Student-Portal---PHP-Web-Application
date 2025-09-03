<?php
require_once __DIR__ . '/../lib/session.php';

// clear session
$_SESSION = [];
session_destroy();

// redirect to login
header("Location: index.php");
exit;