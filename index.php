<?php
/**
 * Front controller for the MVC routes.
 * Existing legacy pages (dashboard.php, rooms.php, ...) continue to work
 * directly; this entry point serves only the migrated MVC modules.
 *
 * To view a migrated module use: index.php?r=module/action
 */
require __DIR__ . '/app/bootstrap.php';

use App\Core\Router;

$route = isset($_GET['r']) ? $_GET['r'] : '';
$router = new Router();
$router->dispatch($route);
