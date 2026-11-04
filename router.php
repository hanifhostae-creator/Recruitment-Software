<?php
// Local development router for `php -S localhost:8000 router.php`.
// It mimics the .htaccess rules (Apache on the live hosting uses .htaccess instead).
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (preg_match('#^/(data|uploads|inc|docs|tools)(/|$)#', $path) || preg_match('#/\.|\.(sqlite|log|md)$#', $path)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}
if ($path === '/' ) { require __DIR__ . '/index.php'; return true; }
return false;
