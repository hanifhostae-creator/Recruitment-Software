<?php
require __DIR__ . '/inc/boot.php';
if (me()) audit('logout', 'users', me()['id']);
$_SESSION = [];
session_regenerate_id(true);
redirect('login.php');
