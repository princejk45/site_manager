<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'en' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= APP_NAME ?> — License Required</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
<link rel="stylesheet" href="<?= WEB_PATH ?>/assets/css/styles.css">
<link rel="icon" href="<?= WEB_PATH ?>/assets/images/logo.png" type="image/png">
<style>
  body {
    background: url('<?= WEB_PATH ?>/assets/images/full_bg.png') no-repeat center center fixed;
    background-size: cover;
    background-color: #e9ecef;
  }
  .card { border-radius: 15px; }
  .card-header { background-color: #1f2732; color: rgb(255,230,0); border-radius: 15px 15px 0 0 !important; }
  .form-control { border-radius: 10px; }
  .license-icon { font-size: 3rem; color: #6c757d; }
  .badge-reason { font-size: .75rem; }
</style>
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh">

<div class="card shadow-lg" style="width:100%;max-width:480px">
  <div class="card-header text-center py-3">
    <div class="license-icon mb-1"><i class="fas fa-key"></i></div>
    <h5 class="mb-0 fw-bold"><?= APP_NAME ?></h5>
  </div>
  <div class="card-body p-4">

    <?php
      $reason = $status['reason'] ?? 'no_license';
      $expires_at = $status['expires_at'] ?? null;

      $messages = [
        'no_license'     => ['icon' => 'fa-lock',          'color' => 'secondary', 'title' => 'License Required',
                             'text' => 'This system requires a valid license to operate. Please enter your license key to continue.'],
        'expired'        => ['icon' => 'fa-calendar-times', 'color' => 'warning',   'title' => 'License Expired',
                             'text' => 'Your license expired on <strong>' . ($expires_at ? sm_format_date($expires_at, 'unknown') : 'unknown') . '</strong>. Please enter a new or renewed key.'],
        'invalid_signature'=>['icon'=> 'fa-shield-alt',    'color' => 'danger',    'title' => 'Invalid License',
                             'text' => 'The license key could not be verified. Please re-enter a valid key.'],
        'revoked'        => ['icon' => 'fa-ban',            'color' => 'danger',    'title' => 'License Revoked',
                             'text' => 'This license has been revoked. Please contact your provider for a new key.'],
      ];
      $msg = $messages[$reason] ?? $messages['no_license'];
    ?>

    <div class="text-center mb-3">
      <span class="text-<?= $msg['color'] ?>" style="font-size:2rem"><i class="fas <?= $msg['icon'] ?>"></i></span>
      <h6 class="mt-2 fw-bold"><?= $msg['title'] ?></h6>
      <p class="text-muted small"><?= $msg['text'] ?></p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php?action=license_gate">
      <div class="form-group">
        <label class="font-weight-semibold">License Key</label>
        <textarea name="license_key" class="form-control font-monospace"
                  rows="4" placeholder="FM-eyJ..." style="font-size:.82rem;border-radius:10px"
                  required><?= htmlspecialchars($_POST['license_key'] ?? '') ?></textarea>
        <small class="text-muted">Paste the full key exactly as provided by your license server.</small>
      </div>
      <button type="submit" class="btn btn-primary btn-block mt-3">
        <i class="fas fa-unlock-alt mr-2"></i>Activate License
      </button>
    </form>

    <hr>
    <div class="text-center">
      <small class="text-muted">
        Logged in as <strong><?= htmlspecialchars($_SESSION['username'] ?? '') ?></strong> —
        <a href="index.php?action=logout" class="text-danger">Sign out</a>
      </small>
    </div>

  </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
