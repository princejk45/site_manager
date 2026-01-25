<?php
class AuthController
{
    private $userModel;

    public function __construct($pdo)
    {
        $this->userModel = new User($pdo);
    }

    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username']);
            $password = $_POST['password'];

            $user = $this->userModel->login($username, $password);

            if ($user) {
                // Set all session variables including role
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role']; // CRUCIAL ADDITION
                $_SESSION['last_login'] = $user['last_login'];
                $_SESSION['is_active'] = $user['is_active'];

                // Regenerate session ID to prevent fixation
                session_regenerate_id(true);

                header('Location: index.php?action=dashboard');
                exit;
            } else {
                $error = __('auth.invalid_credentials');
                require APP_PATH . '/views/auth/login.php';
                exit;
            }
        }

        require APP_PATH . '/views/auth/login.php';
    }

    public function logout()
    {
        // Unset all session variables
        $_SESSION = array();

        // Destroy the session
        session_destroy();

        // Redirect to login page
        header('Location: index.php?action=login');
        exit;
    }

    public function checkPermission($requiredRole)
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit();
        }

        // Verify session matches database
        $user = $this->userModel->getUserById($_SESSION['user_id']);
        if (!$user || !$user['is_active'] || $user['username'] !== $_SESSION['username']) {
            $this->logout();
        }

        $roleHierarchy = [
            'viewer' => 1,
            'manager' => 2,
            'super_admin' => 3
        ];

        if (!isset($roleHierarchy[$user['role']])) {
            $this->logout();
        }

        if ($roleHierarchy[$user['role']] < $roleHierarchy[$requiredRole]) {
            $_SESSION['error'] = "Accesso negato: autorizzazioni insufficienti";
            header('Location: index.php?action=dashboard');
            exit();
        }
    }

    // User Management Methods (Super Admin only)
    public function showCreateForm()
    {
        $this->checkPermission('super_admin');
        require APP_PATH . '/views/users/create.php';
    }

    public function createUser()
    {
        $this->checkPermission('super_admin');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=users&do=create');
            exit;
        }

        $data = [
            'username' => trim($_POST['username']),
            'password' => $_POST['password'],
            'email' => trim($_POST['email']), // Add this line
            'role' => $_POST['role']
        ];

        try {
            if ($this->userModel->createUser($data)) {
                $_SESSION['message'] = "Utente creato con successo";
                header('Location: index.php?action=users&do=list');
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['form_data'] = $_POST; // Preserve form input
            header('Location: index.php?action=users&do=create');
        }
        exit;
    }
    public function listUsers()
    {
        $this->checkPermission('super_admin');
        $users = $this->userModel->getAllUsers();
        require APP_PATH . '/views/users/list.php';
    }

    public function showEditForm($userId)
    {
        $this->checkPermission('super_admin');
        $user = $this->userModel->getUserById($userId);
        if (!$user) {
            $_SESSION['error'] = "Utente non trovato";
            header('Location: index.php?action=users&do=list');
            exit;
        }
        require APP_PATH . '/views/users/edit.php';
    }

    public function updateUser($userId)
    {
        $this->checkPermission('super_admin');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?action=users&do=edit&id=$userId");
            exit;
        }

        $data = [
            'id' => $userId,
            'username' => trim($_POST['username']),
            'role' => $_POST['role'],
            'email' => $_POST['email'],
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        if ($this->userModel->updateUser($data)) {
            $_SESSION['message'] = "Utente aggiornato con successo";
        } else {
            $_SESSION['error'] = "Impossibile aggiornare l'utente";
        }
        header("Location: index.php?action=users&do=edit&id=$userId");
        exit;
    }

    public function changePassword()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];

            if ($newPassword !== $confirmPassword) {
                $error = "Le nuove password non corrispondono";
            } elseif (strlen($newPassword) < 8) {
                $error = "La password deve essere lunga almeno 8 caratteri";
            } else {
                $success = $this->userModel->changePassword(
                    $_SESSION['user_id'],
                    $currentPassword,
                    $newPassword
                );

                if ($success) {
                    $message = "Password modificata con successo";
                } else {
                    $error = "La password corrente è errata";
                }
            }
        }

        require APP_PATH . '/views/settings/password.php';
    }

    public function forgotPassword()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = __('auth.error');
                require APP_PATH . '/views/auth/forgot_password.php';
                exit;
            }

            // Check if user exists
            $user = $this->userModel->getUserByEmail($email);

            if (!$user) {
                // For security, don't reveal if email exists
                $success = __('auth.reset_link_sent');
                require APP_PATH . '/views/auth/forgot_password.php';
                exit;
            }

            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

            // Store token in database
            $this->userModel->savePasswordResetToken($user['id'], $token, $expires);

            // Send email with reset link
            $this->sendPasswordResetEmail($user, $token);

            $success = __('auth.reset_link_sent');
            require APP_PATH . '/views/auth/forgot_password.php';
            exit;
        }

        require APP_PATH . '/views/auth/forgot_password.php';
    }

    public function resetPassword()
    {
        $token = trim($_GET['token'] ?? '');

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // Verify token exists and is not expired
            if (empty($token)) {
                $error = __('auth.invalid_reset_link');
                require APP_PATH . '/views/auth/reset_password.php';
                exit;
            }

            $resetData = $this->userModel->getPasswordResetToken($token);

            if (!$resetData) {
                error_log("Token not found or expired: $token");
                $error = __('auth.invalid_reset_link');
                require APP_PATH . '/views/auth/reset_password.php';
                exit;
            }

            if (strtotime($resetData['expires_at']) < time()) {
                error_log("Token expired: {$resetData['expires_at']}");
                $error = __('auth.invalid_reset_link');
                require APP_PATH . '/views/auth/reset_password.php';
                exit;
            }

            require APP_PATH . '/views/auth/reset_password.php';
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = trim($_POST['token'] ?? '');
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            // Verify token is not empty
            if (empty($token)) {
                $error = __('auth.invalid_reset_link');
                require APP_PATH . '/views/auth/reset_password.php';
                exit;
            }

            // Verify token
            $resetData = $this->userModel->getPasswordResetToken($token);

            if (!$resetData || strtotime($resetData['expires_at']) < time()) {
                error_log("Token verification failed: $token");
                $error = __('auth.invalid_reset_link');
                require APP_PATH . '/views/auth/reset_password.php';
                exit;
            }

            if ($newPassword !== $confirmPassword) {
                $error = __('auth.passwords_not_match');
                require APP_PATH . '/views/auth/reset_password.php';
                exit;
            }

            if (strlen($newPassword) < 6) {
                $error = "Password must be at least 6 characters";
                require APP_PATH . '/views/auth/reset_password.php';
                exit;
            }

            // Update password
            $success = $this->userModel->updatePasswordByToken($token, $newPassword);

            if ($success) {
                $success = __('auth.password_reset_success');
                // Clear the token
                $this->userModel->deletePasswordResetToken($token);
            } else {
                $error = "Failed to reset password";
            }

            require APP_PATH . '/views/auth/reset_password.php';
            exit;
        }
    }

    private function sendPasswordResetEmail($user, $token)
    {
        try {
            require_once APP_PATH . '/models/Email.php';
            $emailModel = new Email($GLOBALS['pdo']);

            $smtpSettings = $emailModel->getSmtpSettings();
            if (!$smtpSettings) {
                error_log("SMTP settings not configured for password reset email");
                return false;
            }

            // Build full URL for email link - must include protocol and domain
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $basePath = str_replace('\\', '/', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'));
            $resetLink = $protocol . '://' . $domain . $basePath . "?action=reset_password&token=$token&lang=" . ($_SESSION['lang'] ?? 'en');

            $subject = "Password Reset Request";
            $content = "
                <h1>Password Reset Request</h1>
                <p>You requested a password reset. Click the link below to reset your password:</p>
                <p><a href='$resetLink' style='background-color: #ffc107; padding: 10px 20px; color: #333; text-decoration: none; border-radius: 5px; display: inline-block;'>
                    " . __('auth.reset_password_link') . "
                </a></p>
                <p><small>" . __('auth.reset_link_expires') . "</small></p>
                <p>If you didn't request this, you can ignore this email.</p>
            ";

            $emailBody = $emailModel->getEmailTemplate($subject, $content);

            require_once APP_PATH . '/vendor/autoload.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail = $emailModel->configureMailer($mail, $smtpSettings);

            $mail->setFrom($smtpSettings['from_email'], $smtpSettings['from_name']);
            $mail->addAddress($user['email'], $user['username']);
            $mail->Subject = $subject;
            $mail->Body = $emailBody;
            $mail->AltBody = strip_tags($content);

            $success = $mail->send();

            error_log("Password reset email sent to {$user['email']}: " . ($success ? 'Success' : 'Failed'));

            return $success;
        } catch (Exception $e) {
            error_log("Failed to send password reset email: " . $e->getMessage());
            return false;
        }
    }
}
