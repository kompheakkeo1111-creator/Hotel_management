<?php
namespace App\Controllers;

use App\Core\Controller;

/**
 * Handles authentication: shows the login form, processes login POST,
 * and logs the user out.
 */
class AuthController extends Controller
{
    /**
     * GET: display the login form.
     */
    public function indexAction()
    {
        if (isLoggedIn()) {
            $this->redirect('index.php?r=dashboard/index');
        }
        $this->standaloneView('auth/login', ['error' => $this->error ?? '']);
    }

    /**
     * POST: authenticate the user.
     */
    public function loginAction()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?r=auth/index');
        }

        $db = getDB();
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

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

        $this->error = 'Invalid username or password!';
        $this->standaloneView('auth/login', ['error' => $this->error]);
    }

    /**
     * Log the current user out.
     */
    public function logoutAction()
    {
        session_destroy();
        $this->redirect('index.php?r=auth/index');
    }

    /**
     * Render a full standalone page (no sidebar), used for the login form.
     */
    private function standaloneView($view, array $data = [])
    {
        extract($data);
        require __DIR__ . '/../Views/' . $view . '.php';
    }
}
