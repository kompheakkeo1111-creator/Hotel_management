<?php
/**
 * MVC bootstrap loader.
 * Loads the legacy app config (DB connection, session, shared helpers)
 * and registers a PSR-0 style autoloader for the app/ namespace.
 */
require_once __DIR__ . '/config.php';

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
