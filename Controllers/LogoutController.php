<?php
namespace App\Controllers;

use App\Core\Controller;

class LogoutController extends Controller
{
    public $authRequired = false;

    public function indexAction()
    {
        session_start();
        session_destroy();
        $this->redirect('index.php?r=login/index');
    }
}
