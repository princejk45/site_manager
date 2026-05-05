<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'en' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - <?= __('app.title') ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="<?= WEB_PATH ?>/assets/css/styles.css">
    <link rel="icon" href="assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">
    <style>
        /* Set the background color outside the login card */
        body {
            background: url('assets/images/full_bg.png') no-repeat center center fixed;
            background-size: cover;
            background-color: #e9ecef;
            /* fallback color */
        }

        /* Apply background color to card header */
        .card-header {
            background-color: #1f2732;
            color: rgb(255, 230, 0);
            /* Ensure text is readable */
        }

        /* Increase the border radius of input fields */
        .form-control {
            border-radius: 10px;
            /* Adjust the radius as per your preference */
        }

        /* Optional: Adjust card margins to better position it on the page */
        .card {
            border-radius: 15px;
            /* Optional: Round the card corners */
        }

        /* Language Switcher Styles */
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

        .language-btn:hover {
            opacity: 0.8;
        }
    </style>
</head>

<body>

    <!-- Language Switcher -->
    <div class="language-switcher">
        <div class="btn-group" role="group">
            <a href="?action=login&lang=en" class="btn btn-sm btn-secondary language-btn <?= ($_SESSION['lang'] ?? 'en') === 'en' ? 'active' : '' ?>">
                <i class="fas fa-flag-usa"></i> EN
            </a>
            <a href="?action=login&lang=it" class="btn btn-sm btn-secondary language-btn <?= ($_SESSION['lang'] ?? 'en') === 'it' ? 'active' : '' ?>">
                <i class="fas fa-flag"></i> IT
            </a>
            <a href="?action=login&lang=fr" class="btn btn-sm btn-secondary language-btn <?= ($_SESSION['lang'] ?? 'en') === 'fr' ? 'active' : '' ?>">
                <i class="fas fa-flag-france"></i> FR
            </a>
        </div>
    </div>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <!-- Card for the login form -->
                <div class="card card-outline card-warning">
                    <div class="card-header text-center">
                        <!-- Logo -->
                        <img src="assets/images/weblogo.png" alt="Logo" class="img-fluid mb-4"
                            style="max-height: 100px;">
                        <h6><?= __('auth.login_title') ?></h6>
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

                        <!-- Login Form -->
                        <form method="POST" action="index.php?action=login">
                            <div class="form-group">
                                <label for="username"><?= __('auth.username') ?></label>
                                <input type="text" class="form-control" id="username" name="username"
                                    placeholder="<?= __('auth.username') ?>" required autofocus>
                            </div>
                            <div class="form-group">
                                <label for="password"><?= __('auth.password') ?></label>
                                <input type="password" class="form-control" id="password" name="password"
                                    placeholder="<?= __('auth.password') ?>" required>
                            </div>
                            <button type="submit" class="btn btn-warning btn-block">
                                <i class="fas fa-sign-in-alt mr-2"></i><?= __('auth.login') ?>
                            </button>
                        </form>

                        <!-- Links -->
                        <hr>
                        <div class="text-center mt-3">
                            <a href="?action=forgot_password&lang=<?= $_SESSION['lang'] ?? 'en' ?>" class="text-muted small">
                                <i class="fas fa-key mr-1"></i><?= __('auth.forgot_password') ?? 'Forgot Password?' ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>