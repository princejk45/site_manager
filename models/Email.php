<?php
class Email
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }


    public function getSmtpSettings()
    {
        $stmt = $this->pdo->query("SELECT * FROM smtp_settings ORDER BY id DESC LIMIT 1");
        $settings = $stmt->fetch();

        // if ($settings && !empty($settings['password'])) {
        //  try {
        //      $settings['password'] = $this->decryptPassword($settings['password']);
        //  } catch (Exception $e) {
        //     error_log("Failed to decrypt SMTP password: " . $e->getMessage());
        //     $settings['password'] = ''; // Fallback to empty password
        //  }
        //  }

        return $settings;
    }

    public function updateSmtpSettings($data)
    {
        // Remove all encryption-related code
        $stmt = $this->pdo->prepare("
            INSERT INTO smtp_settings 
            (host, port, username, password, encryption, from_email, from_name, cc_email) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            host = VALUES(host), 
            port = VALUES(port), 
            username = VALUES(username), 
            password = VALUES(password), 
            encryption = VALUES(encryption), 
            from_email = VALUES(from_email), 
            from_name = VALUES(from_name),
            cc_email = VALUES(cc_email)
        ");

        return $stmt->execute([
            $data['host'],
            (int)$data['port'],
            $data['username'],
            $data['password'], // Store plain text
            $data['encryption'],
            $data['from_email'],
            $data['from_name'],
            $data['cc_email'] ?? null
        ]);
    }


    public function getEmailTemplate($title, $content)
    {
        $headerBgColor = '#1f2732';
        $footerBgColor = '#1f2732';
        $highlightColor = '#f39200'; // Logo color

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$title</title>
    <style type="text/css">
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            line-height: 1.6; 
            color: #333333; 
            margin: 0; 
            padding: 0; 
        }
        .email-container { 
            max-width: 600px; 
            margin: 20px auto; 
            border-radius: 8px; 
            overflow: hidden; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
        }
        .email-header { 
            background-color: $headerBgColor; 
            padding: 30px 20px; 
            text-align: center; 
        }
        .logo-container {
            margin-bottom: 1px;
        }
        .company-name {
            color: white;
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0 5px;
        }
        .company-name span {
            color: $highlightColor;
        }
        .slogan {
            color: rgba(255,255,255,0.8);
            font-size: 14px;
            font-style: italic;
            margin-top: 5px;
        }
        .email-body { 
            padding: 30px; 
            background-color: #ffffff; 
        }
        .email-footer { 
            background-color: $footerBgColor; 
            color: #ffffff; 
            padding: 20px; 
            text-align: center; 
            font-size: 12px; 
            line-height: 1.4; 
        }
        h1 { 
            color: #2c3e50; 
            margin-top: 0; 
        }
        p { 
            margin-bottom: 15px; 
        }
        .card {
            background: #f9f9f9;
            border-left: 4px solid $highlightColor;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: $highlightColor;
            color: white !important;
            text-decoration: none;
            border-radius: 4px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <div class="logo-container">
                <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 100 100">
                    <path d="M 42 8 L 40 10 L 37 10 L 36 11 L 33 11 L 32 12 L 31 12 L 31 13 L 30 14 L 29 14 L 28 13 L 27 13 L 25 11 L 24 11 L 23 10 L 13 10 L 12 11 L 10 11 L 9 12 L 8 12 L 7 13 L 6 13 L 6 14 L 5 15 L 4 15 L 4 16 L 3 17 L 2 17 L 2 18 L 1 19 L 1 21 L 0 22 L 0 34 L 1 35 L 1 37 L 2 38 L 2 39 L 3 39 L 4 40 L 4 41 L 5 41 L 6 42 L 6 43 L 7 43 L 9 45 L 14 45 L 15 46 L 20 46 L 21 45 L 25 45 L 27 43 L 29 43 L 29 42 L 30 41 L 31 41 L 31 40 L 33 38 L 33 37 L 34 36 L 34 35 L 35 34 L 35 22 L 34 21 L 34 19 L 32 17 L 34 15 L 39 15 L 40 14 L 44 14 L 45 15 L 51 15 L 52 16 L 56 16 L 57 17 L 59 17 L 60 18 L 62 18 L 63 19 L 64 19 L 65 20 L 66 20 L 67 21 L 68 21 L 70 23 L 71 23 L 77 29 L 77 30 L 78 30 L 79 31 L 79 33 L 81 35 L 81 39 L 82 40 L 82 44 L 81 45 L 81 49 L 79 51 L 79 53 L 76 56 L 76 57 L 75 58 L 74 58 L 74 59 L 73 60 L 72 60 L 70 62 L 69 62 L 68 63 L 67 63 L 65 65 L 63 65 L 61 67 L 58 67 L 57 68 L 55 68 L 54 69 L 48 69 L 47 70 L 38 70 L 37 69 L 30 69 L 30 71 L 29 72 L 28 72 L 27 71 L 26 71 L 24 69 L 23 69 L 22 68 L 21 68 L 18 65 L 17 65 L 16 64 L 16 63 L 15 63 L 14 62 L 14 61 L 13 60 L 12 60 L 11 59 L 11 57 L 9 55 L 9 54 L 7 52 L 7 46 L 4 46 L 3 47 L 3 56 L 4 57 L 4 61 L 5 62 L 5 64 L 6 65 L 6 66 L 8 68 L 8 69 L 10 71 L 10 73 L 12 73 L 13 74 L 13 75 L 14 75 L 15 76 L 15 77 L 16 77 L 17 78 L 17 79 L 18 80 L 19 80 L 20 81 L 21 81 L 22 82 L 23 82 L 25 84 L 27 84 L 28 85 L 29 85 L 30 86 L 32 86 L 33 87 L 34 87 L 35 88 L 39 88 L 41 90 L 62 90 L 63 89 L 64 89 L 65 88 L 68 88 L 69 87 L 70 87 L 71 86 L 74 86 L 75 85 L 76 85 L 77 84 L 78 84 L 79 83 L 80 83 L 81 82 L 82 82 L 84 80 L 85 80 L 86 79 L 86 78 L 87 77 L 89 77 L 89 76 L 90 75 L 91 75 L 91 74 L 92 73 L 93 73 L 93 72 L 95 70 L 95 69 L 96 68 L 96 67 L 97 66 L 97 65 L 98 64 L 98 62 L 99 61 L 99 46 L 98 45 L 98 40 L 97 39 L 97 35 L 96 34 L 96 32 L 95 31 L 95 30 L 94 29 L 94 28 L 93 27 L 93 26 L 88 21 L 88 20 L 87 20 L 84 17 L 83 17 L 81 15 L 80 15 L 79 14 L 78 14 L 77 13 L 76 13 L 75 12 L 74 12 L 73 11 L 71 11 L 70 10 L 67 10 L 65 8 Z" fill="$highlightColor"/>
                </svg>
            </div>
            <div class="company-name">FULLM<span>I</span>DIA</div>
            <div class="slogan">Unica, Ogni Volta</div>
        </div>
        
        <div class="email-body">
            $content
        </div>
        
        <div class="email-footer">
<p>Questa e-mail è stata generata automaticamente. Puoi rispondere a questo messaggio o contattarci tramite Whatsapp.</p>
<p>Questa comunicazione è destinata esclusivamente all'uso del destinatario e potrebbe contenere informazioni riservate.</p>
</div>
    </div>
</body>
</html>
HTML;
    }


    public function sendRenewalNotification($websiteId, $email, $domain, $newExpiryDate, $vendita)
    {
        try {
            $subject = "Conferma del rinnovo del servizio: $domain";

            $content = '
            <h1>Conferma del rinnovo del servizio</h1>
            <p>Il tuo servizio <strong>' . $domain . '</strong> è stato rinnovato con successo.</p>
            
            <div class="card">
                <p><strong>Nuova data di scadenza:</strong> ' . $newExpiryDate . '</p>
                <p><strong>Costo di rinnovo:</strong> ' . $vendita . '</p>
            </div>
            
            <p>Grazie per la tua continua collaborazione. Se hai domande, contatta il nostro team di supporto.</p>
        ';

            $smtpSettings = $this->getSmtpSettings();
            if (!$smtpSettings) {
                throw new Exception("SMTP settings not configured");
            }

            require_once APP_PATH . '/vendor/autoload.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);

            $mail = $this->configureMailer($mail, $smtpSettings);
            $mail->setFrom($smtpSettings['from_email'], $smtpSettings['from_name']);
            $mail->addAddress($email);
            $mail->Subject = $subject;
            $mail->Body = $this->getEmailTemplate($subject, $content);
            $mail->AltBody = "Service Renewal Confirmation\n\n" .
                "Il tuo servizio - $domain è stato rinnovato fino a $newExpiryDate.\n" .
                "Costo di rinnovo: $vendita\n\n" .
                "Grazie per averci scelto!";

            $success = $mail->send();

            $this->logEmail([
                'website_id' => (int)$websiteId,
                'email_type' => 'renewal',
                'sent_to' => $email,
                'subject' => $subject,
                'body' => $content,
                'status' => $success ? 'sent' : 'failed',
                'error_message' => $success ? null : 'Unknown error'
            ]);

            return $success;
        } catch (Exception $e) {
            // Log failed email attempt using array format
            $this->logEmail([
                'website_id' => (int)$websiteId,
                'email_type' => 'renewal',
                'sent_to' => $email,
                'subject' => $subject ?? 'Renewal Notification',
                'body' => $message ?? '',
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            error_log("Impossibile inviare l'e-mail di rinnovo: " . $e->getMessage());
            return false;
        }
    }

    public function logEmail($data)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO email_logs 
            (website_id, email_type, sent_to, subject, body, status, error_message) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $data['website_id'] ?? null,
            $data['email_type'],
            $data['sent_to'],
            $data['subject'],
            $data['body'],
            $data['status'],
            $data['error_message'] ?? null
        ]);
    }

    public function getEmailLogs($limit = 100)
    {
        $stmt = $this->pdo->prepare("
            SELECT el.*, w.name as website_name 
            FROM email_logs el
            LEFT JOIN websites w ON el.website_id = w.id
            ORDER BY el.sent_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function configureMailer($mail, $smtpSettings)
    {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $smtpSettings['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtpSettings['username'];
        $mail->Password = $smtpSettings['password'];

        $mail->Port = $smtpSettings['port'];
        $mail->CharSet = 'UTF-8';


        // Handle different encryption types
        switch ($smtpSettings['encryption']) {
            case 'starttls':
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->SMTPAutoTLS = true;  // Enable STARTTLS
                break;
            case 'ssl':
            case 'tls':
                $mail->SMTPSecure = $smtpSettings['encryption'];
                break;
            default:
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
        }

        if (!empty($smtpSettings['cc_email'])) {
            $mail->addCC($smtpSettings['cc_email']);
            $mail->addReplyTo($smtpSettings['cc_email']);
        }

        // Optional: Add these for better debugging
        // $mail->SMTPDebug = 2;  // Enable verbose debug output
        // $mail->Debugoutput = function($str, $level) {
        //     error_log("SMTP debug level $level: $str");
        // };

        return $mail;
    }

    public function sendExpiryNotification($websiteId, $daysUntilExpiry)
    {
        $website = (new Website($this->pdo))->getWebsiteById($websiteId);
        if (!$website) return false;

        $smtpSettings = $this->getSmtpSettings();
        if (!$smtpSettings) return false;

        require_once APP_PATH . '/vendor/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            // Keep your original status/renewal cost handling
            $renewalCost = $website['vendita'] ?? 'N/A';

            if ($daysUntilExpiry > 0) {
                $subject = "Il tuo servizio - {$website['domain']} scade tra $daysUntilExpiry giorni";
                $content = '
            <h1>Promemoria scadenza servizio</h1>
            <h4><strong>' . $website['name'] . '</strong></h4>
            <p>Il tuo servizio  <strong>' . $website['domain'] . '</strong></p>
            <p><strong>Scade tra:</strong> ' . $daysUntilExpiry . ' giorni</p>
            <div class="card">
                <p><strong>Data di scadenza:</strong> ' . $website['expiry_date'] . '</p>
                <p><strong>Costo di rinnovo:</strong> ' . $renewalCost . '</p>
            </div>
            <h3>Rinnova ora</h3>';
            } else {
                $subject = "URGENTE: il tuo servizio - {$website['domain']} è scaduto";
                $content = '
            <h1>Servizio scaduto</h1>
            <h4><strong>' . $website['name'] . '</strong></h4>
            <p>Il tuo servizio <strong>' . $website['domain'] . '</strong></p>
            <div class="card">
                <p><strong>Scaduto il:</strong> ' . $website['expiry_date'] . '</p>
                <p><strong>Costo di rinnovo:</strong> ' . $renewalCost . '</p>
            </div>
            <p>I servizi potrebbero essere sospesi. Rinnova immediatamente per ripristinare l`accesso.</p>
            <h3>Rinnova ora</h3>';
            }

            $mail = $this->configureMailer($mail, $smtpSettings);
            $mail->setFrom($smtpSettings['from_email'], $smtpSettings['from_name']);
            $mail->addAddress($website['assigned_email']);
            $mail->Subject = $subject;
            $mail->Body = $this->getEmailTemplate($subject, $content);
            $mail->AltBody = strip_tags($content);

            $success = $mail->send();

            $this->logEmail([
                'website_id' => $websiteId,
                'email_type' => 'expiry_reminder',
                'sent_to' => $website['assigned_email'],
                'subject' => $subject,
                'body' => $content,
                'status' => $success ? 'sent' : 'failed',
                'error_message' => $success ? null : 'Unknown error'
            ]);

            return $success;
        } catch (Exception $e) {
            error_log("Impossibile inviare la notifica di scadenza: " . $e->getMessage());
            return false;
        }
    }

    public function sendStatusNotification($websiteId)
    {
        $website = (new Website($this->pdo))->getWebsiteById($websiteId);
        if (!$website || empty($website['notes'])) {
            return false;
        }

        $smtpSettings = $this->getSmtpSettings();
        if (!$smtpSettings) return false;

        require_once APP_PATH . '/vendor/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $subject = "Rapporto sullo stato per  ({$website['domain']})";
            $formattedNotes = nl2br(htmlspecialchars($website['notes']));

            $content = '
            <h1>Rapporto sullo stato del servizio</h1>
            <h4><strong>' . $website['name'] . '</strong></h4>
            <p>Ecco il rapporto sullo stato attuale per <strong>' . $website['domain'] . '</strong>:</p>
            
            <div class="card">
                ' . $formattedNotes . '
            </div>
            
            <p>Si prega di rivedere queste informazioni e di contattarci in caso di domande.</p>
        ';

            $mail = $this->configureMailer($mail, $smtpSettings);
            $mail->setFrom($smtpSettings['from_email'], $smtpSettings['from_name']);
            $mail->addAddress($website['assigned_email']);
            $mail->Subject = $subject;
            $mail->Body = $this->getEmailTemplate($subject, $content);
            $mail->AltBody = strip_tags($content);

            $mail->send();

            $this->logEmail([
                'website_id' => $websiteId,
                'email_type' => 'status_report',
                'sent_to' => $website['assigned_email'],
                'subject' => $subject,
                'body' => $content,
                'status' => 'sent'
            ]);

            return true;
        } catch (Exception $e) {
            // Log failed email
            $this->logEmail([
                'website_id' => $websiteId,
                'email_type' => 'status_report',
                'sent_to' => $website['assigned_email'],
                'subject' => 'Status Report - ' . $website['name'],
                'body' => $website['notes'] ?? '',
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            return false;
        }
    }
}
