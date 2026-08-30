<?php
namespace App\Core;

/**
 * Minimal front-controller router.
 * Route format: ?r=controller/action   e.g. ?r=room_types/index
 * Dispatches to App\Controllers\{Controller}Controller::{action}.
 */
class Router
{
    /**
     * @param string $route e.g. "room_types/index"
     */
    public function dispatch($route)
    {
        $route = trim($route, '/');
        if ($route === '') {
            $route = 'dashboard/index';
        }

        $parts = explode('/', $route);
        $controllerRaw = preg_replace('/[^a-zA-Z0-9_]/', '', $parts[0]);
        $controller = str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($controllerRaw))));
        // singularize a trailing "s" so "room_types" -> RoomTypeController
        if (substr($controller, -1) === 's' && substr($controller, -1) !== 'ss') {
            $controller = substr($controller, 0, -1);
        }
        $action = isset($parts[1]) && $parts[1] !== '' ? $parts[1] : 'index';

        $class = 'App\\Controllers\\' . $controller . 'Controller';
        $method = $action . 'Action';

        if (!class_exists($class)) {
            http_response_code(404);
            echo 'Controller not found: ' . htmlspecialchars($class);
            return;
        }

        $instance = new $class();

        if (!method_exists($instance, $method)) {
            http_response_code(404);
            echo 'Action not found: ' . htmlspecialchars($method);
            return;
        }

        $instance->$method();
    }

    /**
     * Build a route URL.
     */
    public static function url($route)
    {
        return 'index.php?r=' . $route;
    }
}
