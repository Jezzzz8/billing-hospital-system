<?php


class AuthController
{
    private PDO $pdo;
    private string $logFile;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->logFile = __DIR__ . '/../../auth-debug.log';
    }

    public function login(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $this->log('=== LOGIN ATTEMPT ===');
        $this->log('Method: ' . $_SERVER['REQUEST_METHOD']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->log('❌ Not POST — aborting');
            $this->json(405, ['success' => false, 'message' => 'Method not allowed.']);
        }

        
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;

        
        $email    = trim((string)($data['email'] ?? $data['username'] ?? ''));
        $password = (string)($data['password'] ?? '');

        $this->log('Email: "' . $email . '"');
        $this->log('Password length: ' . strlen($password));

        if ($email === '' || $password === '') {
            $this->log('❌ Empty email or password');
            $this->json(422, ['success' => false, 'message' => 'Email and password are required.']);
        }

        
        $sql = 'SELECT u.user_id, u.username, u.password_hash, u.first_name, u.last_name,
                       u.email, u.is_active, r.role_id, r.role_name
                FROM `user` u
                INNER JOIN `role` r ON r.role_id = u.role_id
                WHERE u.email = ?
                LIMIT 1';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$email]);
            $user = $stmt->fetch();
        } catch (Throwable $e) {
            $this->log('❌ SQL ERROR: ' . $e->getMessage());
            $this->json(500, ['success' => false, 'message' => 'Database error.']);
        }

        if (!$user) {
            $this->log('❌ No user found with email: ' . $email);
            $this->json(401, ['success' => false, 'message' => 'Invalid email or password.']);
        }

        $this->log('✅ User found: user_id=' . $user['user_id']
                 . ', username=' . $user['username']
                 . ', email=' . $user['email']
                 . ', is_active=' . $user['is_active']
                 . ', role_id=' . $user['role_id']);

        
        $verified = password_verify($password, $user['password_hash']);
        $this->log('password_verify() result: ' . ($verified ? '✅ TRUE' : '❌ FALSE'));

        if (!$verified) {
            $this->json(401, ['success' => false, 'message' => 'Invalid email or password.']);
        }

        if ((int)$user['is_active'] !== 1) {
            $this->log('❌ Account inactive');
            $this->json(403, ['success' => false, 'message' => 'Account is inactive. Contact an administrator.']);
        }

        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'user_id'    => (int)$user['user_id'],
            'username'   => $user['username'],
            'email'      => $user['email'],
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name'],
            'role_id'    => (int)$user['role_id'],
            'role_name'  => $user['role_name'],
        ];

        $this->log('✅ Session set for user_id=' . $user['user_id']);
        $this->log('=== LOGIN SUCCESS ===');

        $this->json(200, [
            'success'  => true,
            'message'  => 'Login successful.',
            'user'     => [
                'user_id'    => (int)$user['user_id'],
                'first_name' => $user['first_name'],
                'last_name'  => $user['last_name'],
                'role_name'  => $user['role_name'],
            ],
            'redirect' => $this->redirectForRole((int)$user['role_id']),
        ]);
    }
private function redirectForRole(int $roleId): string
{
    return match ($roleId) {
        1 => '/billing_hospital/index.php?page=dashboard',   
        2 => '/billing_hospital/index.php?page=doctor',
        3 => '/billing_hospital/index.php?page=nurse',
        4 => '/billing_hospital/index.php?page=cashier',
        default => '/billing_hospital/index.php',
    };
}
    private function json(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload);
        exit;
    }

    private function log(string $message): void
    {
        file_put_contents(
            $this->logFile,
            '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n",
            FILE_APPEND
        );
    }
}