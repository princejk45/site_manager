<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'en' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - <?= __('auth.reset_password') ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/styles.css">
    <link rel="icon" href="assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">
    <style>
        body {
            background: url('assets/images/bg.gif') no-repeat center center fixed;
            background-size: cover;
            background-color: #e9ecef;
        }

        .card-header {
            background-color: #1f2732;
            color: rgb(255, 230, 0);
        }

        .form-control {
            border-radius: 10px;
        }

        .card {
            border-radius: 15px;
        }

        .language-switcher {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .language-switcher .btn-group {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .language-btn {
            padding: 8px 12px;
            font-size: 14px;
            font-weight: 600;
        }

        .language-btn.active {
            background-color: #ffc107;
            color: #333;
        }

        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
    </style>
</head>

<body>

    <!-- Language Switcher -->
    <div class="language-switcher">
        <div class="btn-group" role="group">
            <a href="?action=reset_password&token=<?= $_GET['token'] ?? '' ?>&lang=en" class="btn btn-sm btn-secondary language-btn <?= ($_SESSION['lang'] ?? 'en') === 'en' ? 'active' : '' ?>">
                <i class="fas fa-flag-usa"></i> EN
            </a>
            <a href="?action=reset_password&token=<?= $_GET['token'] ?? '' ?>&lang=it" class="btn btn-sm btn-secondary language-btn <?= ($_SESSION['lang'] ?? 'en') === 'it' ? 'active' : '' ?>">
                <i class="fas fa-flag"></i> IT
            </a>
            <a href="?action=reset_password&token=<?= $_GET['token'] ?? '' ?>&lang=fr" class="btn btn-sm btn-secondary language-btn <?= ($_SESSION['lang'] ?? 'en') === 'fr' ? 'active' : '' ?>">
                <i class="fas fa-flag-france"></i> FR
            </a>
        </div>
    </div>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <!-- Card for the reset password form -->
                <div class="card card-outline card-warning">
                    <div class="card-header text-center">
                        <!-- Logo -->
                        <img src="assets/images/weblogo.png" alt="Logo" class="img-fluid mb-4"
                            style="max-height: 100px;">
                        <h6><?= __('auth.reset_password') ?></h6>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <?= htmlspecialchars($error) ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($success)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle mr-2"></i>
                                <?= htmlspecialchars($success) ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="text-center mt-3">
                                <a href="?action=login&lang=<?= $_SESSION['lang'] ?? 'en' ?>" class="btn btn-warning btn-block">
                                    <i class="fas fa-sign-in-alt mr-2"></i><?= __('auth.login') ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <!-- Reset Password Form -->
                            <form method="POST" action="index.php?action=reset_password" onsubmit="return validateForm()">
                                <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token'] ?? '') ?>">

                                <div class="form-group">
                                    <label for="new_password"><?= __('auth.new_password') ?></label>
                                    <input type="password" class="form-control" id="new_password" name="new_password"
                                        placeholder="<?= __('auth.new_password') ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password"><?= __('auth.confirm_password') ?></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                        placeholder="<?= __('auth.confirm_password') ?>" required>
                                    <small class="text-danger" id="error-message"></small>
                                </div>

                                <button type="submit" class="btn btn-warning btn-block">
                                    <i class="fas fa-save mr-2"></i><?= __('auth.reset_password') ?>
                                </button>
                            </form>

                            <!-- Links -->
                            <hr>
                            <div class="text-center mt-3">
                                <a href="?action=login&lang=<?= $_SESSION['lang'] ?? 'en' ?>" class="text-muted small">
                                    <i class="fas fa-arrow-left mr-1"></i><?= __('auth.back_to_login') ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function validateForm() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const errorMessage = document.getElementById('error-message');

            if (password !== confirmPassword) {
                errorMessage.textContent = '<?= __('auth.passwords_not_match') ?>';
                return false;
            }

            if (password.length < 6) {
                errorMessage.textContent = 'Password must be at least 6 characters';
                return false;
            }

            return true;
        }

        document.getElementById('confirm_password').addEventListener('change', function() {
            document.getElementById('error-message').textContent = '';
        });
    </script>
</body>

</html>