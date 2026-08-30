<?php
/**
 * Sole front controller for the MVC app.
 * All application code lives under app/; this entry point boots the app
 * and dispatches the requested route.
 *
 * Route format: index.php?r=controller/action  e.g. index.php?r=billing/index
 */
require __DIR__ . '/app/bootstrap.php';

use App\Core\Router;

$route = isset($_GET['r']) ? $_GET['r'] : '';
$router = new Router();
$router->dispatch($route);
