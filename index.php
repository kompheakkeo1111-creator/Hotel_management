<?php
/**
 * Front controller for the MVC routes.
 * All pages are served through this entry point.
 *
 * Usage: index.php?r=module/action  e.g. index.php?r=rooms/index
 */
require __DIR__ . '/bootstrap.php';

use App\Core\Router;

$route = isset($_GET['r']) ? $_GET['r'] : '';

if (!isInstalled() && $route !== 'setup/index') {
    header('Location: index.php?r=setup/index');
    exit();
}

$router = new Router();
$router->dispatch($route);
