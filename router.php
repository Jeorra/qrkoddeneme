<?php

// C:\xampp\htdocs\qrkod\router.php

// Get the requested URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// This is the document root
$publicDir = __DIR__;

// Check if the request is for a specific file in the public directory
// and it's not a directory.
if ($uri !== '/' && file_exists($publicDir . $uri) && !is_dir($publicDir . $uri)) {
    // It's a request for a static file. Let the server handle it.
    return false;
}

// All other requests are assumed to be for our API or the main login page,
// so we'll pass them to our front controller.
// Any request for a directory (like /) will also be handled by index.php
require_once $publicDir . '/index.php';
