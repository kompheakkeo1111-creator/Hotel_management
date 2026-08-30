<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

class UserController extends Controller
{
    protected $active = 'users';

    private $roles = ['Administrator', 'Receptionist', 'Manager', 'Housekeeping Staff', 'Accountant'];
    private $statuses = ['Active', 'Inactive'];

    public function __construct()
    {
        $this->loginRequired();
        $this->user = new User();
    }

    public function indexAction()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
        }

        $this->view('users/index', [
            'users'    => $this->user->all(),
            'roles'    => $this->roles,
            'statuses' => $this->statuses,
            'message'  => $this->message ?? '',
            'error'    => $this->error ?? '',
        ], 'users', 'Users');
    }

    private function handlePost()
    {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'add' || $action === 'edit') {
                $data = [
                    'username' => trim($_POST['username'] ?? ''),
                    'full_name'=> trim($_POST['full_name'] ?? ''),
                    'email'    => trim($_POST['email'] ?? ''),
                    'phone'    => trim($_POST['phone'] ?? ''),
                    'role'     => in_array($_POST['role'] ?? '', $this->roles, true) ? $_POST['role'] : 'Receptionist',
                    'status'   => in_array($_POST['status'] ?? '', $this->statuses, true) ? $_POST['status'] : 'Active',
                    'password' => $_POST['password'] ?? '',
                ];

                if ($data['username'] === '' || $data['full_name'] === '' || $data['email'] === '') {
                    throw new \Exception('Username, full name, and email are required.');
                }
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new \Exception('Invalid email address.');
                }

                $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

                if ($action === 'add') {
                    if ($data['password'] === '') throw new \Exception('Password is required for new users.');
                    if (strlen($data['password']) < 4) throw new \Exception('Password must be at least 4 characters.');
                    if ($this->user->usernameExists($data['username'])) throw new \Exception('Username already exists.');
                    if ($this->user->emailExists($data['email'])) throw new \Exception('Email already exists.');
                    $this->user->create($data);
                    $this->message = 'User added successfully.';
                } else {
                    if ($data['password'] !== '' && strlen($data['password']) < 4) throw new \Exception('Password must be at least 4 characters.');
                    if ($this->user->usernameExists($data['username'], $id)) throw new \Exception('Username already exists.');
                    if ($this->user->emailExists($data['email'], $id)) throw new \Exception('Email already exists.');
                    $this->user->update($id, $data);
                    $this->message = 'User updated successfully.';
                }
            } elseif ($action === 'delete') {
                $id = (int)$_POST['id'];
                if ($id === (int)($_SESSION['user_id'] ?? 0)) throw new \Exception('You cannot delete your own account.');
                $this->user->delete($id);
                $this->message = 'User deleted successfully.';
            }
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }
}
