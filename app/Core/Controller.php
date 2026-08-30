<?php
namespace App\Core;

/**
 * Base controller. Provides the small view/redirect helpers used by
 * controllers and mirrors the legacy requireLogin() behaviour.
 */
abstract class Controller
{
    /**
     * Render a view file with data, using the MVC-native layout (header/footer).
     *
     * @param string $view relative path under app/Views (no leading slash)
     * @param array  $data  variables extracted into the view scope
     * @param string $active nav key used by the layout header
     * @param string $pageTitle
     */
    protected function view($view, array $data = [], $active = '', $pageTitle = '')
    {
        extract($data);
        if (empty($active)) $active = $this->active ?? '';
        $GLOBALS['active'] = $active;
        $pageTitle = $pageTitle ?: ($this->pageTitle ?? '');
        require __DIR__ . '/../Views/layout/header.php';
        require __DIR__ . '/../Views/' . $view . '.php';
        require __DIR__ . '/../Views/layout/footer.php';
    }

    protected function redirect($url)
    {
        header('Location: ' . $url);
        exit;
    }

    protected function loginRequired()
    {
        requireLogin();
    }

    protected function roleRequired($roles)
    {
        requireRole($roles);
    }
}
