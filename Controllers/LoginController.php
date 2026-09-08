<?php
namespace App\Controllers;

use App\Core\Controller;

class LoginController extends Controller
{
    public $authRequired = false;

    public function indexAction()
    {
        if (isLoggedIn()) {
            $this->redirect('index.php?r=dashboard/index');
        }

        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'Active'");
            $stmt->execute([$username]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['full_name'];
                $this->redirect('index.php?r=dashboard/index');
            }
            $error = 'Invalid username or password!';
        }

        require __DIR__ . '/../Views/login/index.php';
    }
}
