<?php
class MessagingController
{
    private $threadModel;
    private $groupModel;
    private $userModel;
    private $emailModel;
    private $emailTemplate;

    public function __construct($threadModel, $groupModel, $userModel, $emailModel, $db = null)
    {
        $this->threadModel = $threadModel;
        $this->groupModel = $groupModel;
        $this->userModel = $userModel;
        $this->emailModel = $emailModel;
        
        // Initialize EmailTemplate model if database is provided
        if ($db) {
            require_once APP_PATH . '/models/EmailTemplate.php';
            $this->emailTemplate = new EmailTemplate($db);
        }
    }

    public function inbox()
    {
        try {
            if (!isset($_SESSION['user_id'])) {
                throw new Exception("User not logged in");
            }

            $threads = $this->threadModel->getUserThreads($_SESSION['user_id']);

            if (empty($threads)) {
                error_log("No threads found for user: " . $_SESSION['user_id']);
                $threads = []; // Ensure it's at least an empty array
            }

            $debug = ['user_id' => $_SESSION['user_id'], 'thread_count' => count($threads)];

            require APP_PATH . '/views/messaging/inbox.php';
        } catch (Exception $e) {
            error_log("MessagingController Error: " . $e->getMessage());
            $message = 'Could not load messages. Error: ' . $e->getMessage();
            require APP_PATH . '/views/errors/error.php';
        }
    }

    public function viewThread($threadId)
    {
        $messages = $this->threadModel->getThreadMessages($threadId, $_SESSION['user_id']);
        $firstMessage = $this->threadModel->getFirstMessage($threadId);

        // Build thread array with subject from first message
        $thread = [
            'subject' => $firstMessage['subject'] ?? 'Message Thread',
            'id' => $threadId
        ];

        require APP_PATH . '/views/messaging/thread.php';
    }

    public function compose()
    {
        $groups = $this->groupModel->getUserGroups($_SESSION['user_id']);
        $users = $this->userModel->getAllUsers();

        require APP_PATH . '/views/messaging/compose.php';
    }

    public function send()
    {
        $isGroupThread = !empty($_POST['group_id']);
        $subject = $_POST['subject'] ?? 'No subject';
        $serviceId = !empty($_POST['service_id']) ? $_POST['service_id'] : null;
        $clientCcEmail = !empty($_POST['client_cc_email']) ? $_POST['client_cc_email'] : null;

        if ($isGroupThread) {
            // Get group members
            $groupId = $_POST['group_id'];
            $groupMembers = $this->groupModel->getMembers($groupId);
            
            $threadId = $this->threadModel->createThread(
                $subject,
                $_SESSION['user_id'],
                $groupMembers,
                $_POST['content'],
                $groupId,
                $serviceId,
                $clientCcEmail
            );
        } else {
            $threadId = $this->threadModel->createThread(
                $subject,
                $_SESSION['user_id'],
                $_POST['recipients'],
                $_POST['content'],
                null,
                $serviceId,
                $clientCcEmail
            );
        }

        // Send email only for first message
        if ($threadId) {
            $this->sendEmailNotifications($threadId, $clientCcEmail);
        }

        header("Location: ?action=messaging&do=view&id=$threadId");
        exit;
    }

    public function reply()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?action=messaging');
            exit;
        }

        $threadId = $_POST['thread_id'] ?? null;
        $content = $_POST['content'] ?? '';

        if (!$threadId || !$content) {
            header("Location: ?action=messaging&do=view&id=$threadId");
            exit;
        }

        // Add reply message
        try {
            $messageId = $this->threadModel->addMessage($threadId, $_SESSION['user_id'], $content, false);

            // Send notification emails to all thread participants except sender
            $this->sendReplyNotification($threadId, $messageId);

            header("Location: ?action=messaging&do=view&id=$threadId&lang=" . ($_SESSION['lang'] ?? 'it'));
            exit;
        } catch (Exception $e) {
            error_log("Failed to add reply: " . $e->getMessage());
            header("Location: ?action=messaging&do=view&id=$threadId");
            exit;
        }
    }

    public function listGroups()
    {
        $groups = $this->groupModel->getUserGroups($_SESSION['user_id']);

        require APP_PATH . '/views/messaging/groups/list.php';
    }

    // Show form to create a new group
    public function showCreateGroup()
    {
        $users = $this->userModel->getAllUsers();
        require APP_PATH . '/views/messaging/groups/create.php';
    }

    // Handle create group POST
    public function storeGroup()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?action=messaging&do=groups');
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $members = $_POST['members'] ?? [];

        if (empty($name)) {
            $_SESSION['flash_error'] = 'Group name is required';
            header('Location: ?action=messaging&do=groups_create');
            exit;
        }

        try {
            $groupId = $this->groupModel->create($name, $_SESSION['user_id'], $members);
            header('Location: ?action=messaging&do=groups&lang=' . ($_SESSION['lang'] ?? 'it'));
            exit;
        } catch (Exception $e) {
            error_log('Failed to create group: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Failed to create group';
            header('Location: ?action=messaging&do=groups_create');
            exit;
        }
    }

    // Show edit form
    public function showEditGroup($id)
    {
        if (!$id) {
            header('Location: ?action=messaging&do=groups');
            exit;
        }

        $group = $this->groupModel->getById($id);
        if (!$group) {
            header('Location: ?action=messaging&do=groups');
            exit;
        }

        $members = $this->groupModel->getMembers($id);
        $users = $this->userModel->getAllUsers();

        require APP_PATH . '/views/messaging/groups/edit.php';
    }

    // Handle update POST
    public function updateGroup()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?action=messaging&do=groups');
            exit;
        }

        $groupId = $_POST['group_id'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $members = $_POST['members'] ?? [];

        if (!$groupId || empty($name)) {
            $_SESSION['flash_error'] = 'Invalid input';
            header('Location: ?action=messaging&do=groups');
            exit;
        }

        try {
            $this->groupModel->update($groupId, $name, $members);
            header('Location: ?action=messaging&do=groups&lang=' . ($_SESSION['lang'] ?? 'it'));
            exit;
        } catch (Exception $e) {
            error_log('Failed to update group: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Failed to update group';
            header('Location: ?action=messaging&do=groups_edit&id=' . $groupId);
            exit;
        }
    }

    // Handle group delete (POST)
    public function deleteGroup()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?action=messaging&do=groups');
            exit;
        }

        $groupId = $_POST['group_id'] ?? null;
        if (!$groupId) {
            header('Location: ?action=messaging&do=groups');
            exit;
        }

        try {
            $this->groupModel->delete($groupId);
            header('Location: ?action=messaging&do=groups&lang=' . ($_SESSION['lang'] ?? 'it'));
            exit;
        } catch (Exception $e) {
            error_log('Failed to delete group: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Failed to delete group';
            header('Location: ?action=messaging&do=groups');
            exit;
        }
    }

    private function sendEmailNotifications($threadId, $clientCcEmail = null)
    {
        try {
            $firstMessage = $this->threadModel->getFirstMessage($threadId);
            if (!$firstMessage) {
                error_log("Failed to get first message for thread $threadId");
                return;
            }

            $recipients = $this->threadModel->getThreadParticipants($threadId, $_SESSION['user_id']);

            if (empty($recipients)) {
                error_log("No recipients found for thread $threadId");
                return;
            }

            // Check if SMTP settings are configured
            $smtpSettings = $this->emailModel->getSmtpSettings();
            if (!$smtpSettings) {
                error_log("SMTP settings not configured for messaging notifications");
                return;
            }

            // Get thread summary to get service details
            $threadSummary = $this->threadModel->getThreadSummary($threadId);
            $serviceName = null;
            if (!empty($threadSummary['service_id'])) {
                require_once APP_PATH . '/models/Website.php';
                $websiteModel = new Website($GLOBALS['pdo']);
                $service = $websiteModel->getWebsiteById($threadSummary['service_id']);
                $serviceName = $service['domain'] ?? $service['name'] ?? null;
            }

            error_log("Sending message notification for thread $threadId to " . count($recipients) . " recipients");

            foreach ($recipients as $recipient) {
                try {
                    // Check if recipient is unsubscribed (default is subscribed)
                    if ($this->isEmailUnsubscribed($recipient['email'])) {
                        error_log("Skipping email to {$recipient['email']} - user is unsubscribed");
                        continue;
                    }

                    $senderName = $_SESSION['username'] ?? 'System';
                    $messageContent = nl2br(htmlspecialchars($firstMessage['content']));
                    $threadLink = BASE_PATH . "?action=messaging&do=view&id=$threadId";
                    $subject = $firstMessage['subject'];
                    
                    // Build email body with header and footer from site settings
                    $emailBody = $this->buildMessageEmailWithHeaderFooter($subject, $senderName, $messageContent, $threadLink, $serviceName, $recipient['email']);

                    require_once APP_PATH . '/vendor/autoload.php';
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    $mail = $this->emailModel->configureMailer($mail, $smtpSettings);

                    $mail->setFrom($smtpSettings['from_email'], $smtpSettings['from_name']);
                    $mail->addAddress($recipient['email'], $recipient['username'] ?? '');
                    $mail->Subject = "New message: " . $subject;
                    $mail->Body = $emailBody;
                    $mail->AltBody = strip_tags($emailBody);
                    $mail->isHTML(true);
                    
                    // Add List-Unsubscribe header for better spam filter compliance
                    $unsubscribeUrl = BASE_PATH . "?action=messaging&do=unsubscribe&email=" . urlencode($recipient['email']);
                    $mail->addCustomHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>');
                    $mail->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

                    $success = $mail->send();

                    error_log("Email sent to {$recipient['email']}: " . ($success ? 'Success' : 'Failed'));

                    // Log the email using your existing system
                    $this->emailModel->logEmail([
                        'email_type' => 'message_notification',
                        'sent_to' => $recipient['email'],
                        'subject' => "New message: " . $subject,
                        'body' => $emailBody,
                        'status' => $success ? 'sent' : 'failed',
                        'error_message' => $success ? null : $mail->ErrorInfo
                    ]);
                } catch (Exception $e) {
                    error_log("Failed to send message notification to {$recipient['email']}: " . $e->getMessage());
                    $this->emailModel->logEmail([
                        'email_type' => 'message_notification',
                        'sent_to' => $recipient['email'] ?? 'Unknown',
                        'subject' => "New message: " . ($subject ?? 'Message'),
                        'body' => $emailBody ?? '',
                        'status' => 'failed',
                        'error_message' => $e->getMessage()
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("Error in sendEmailNotifications: " . $e->getMessage());
        }
    }

    /**
     * Build message email with header and footer from site settings
     */
    private function buildMessageEmailWithHeaderFooter($subject, $senderName, $content, $threadLink, $serviceName = null, $recipientEmail = null)
    {
        // Get site settings for header and footer
        require_once APP_PATH . '/models/SiteSettings.php';
        
        // Clear the cache to ensure we get fresh data
        SiteSettings::clearCache();
        
        $siteSettings = new SiteSettings($GLOBALS['pdo']);
        
        $globalHeader = $siteSettings->getSetting('email_global_header');
        $globalFooter = $siteSettings->getSetting('email_global_footer');
        
        error_log("Email Header: " . (empty($globalHeader) ? 'EMPTY' : 'Has content - length: ' . strlen($globalHeader)));
        error_log("Email Footer: " . (empty($globalFooter) ? 'EMPTY' : 'Has content - length: ' . strlen($globalFooter)));
        
        // If header/footer are empty, use language-aware defaults
        if (empty($globalHeader)) {
            $lang = $_SESSION['lang'] ?? DEFAULT_LANG;
            if ($lang === 'it') {
                $globalHeader = "<p>Ciao,</p><p>Di seguito trovi le informazioni importanti.</p>";
            } else {
                $globalHeader = "<p>Hello,</p><p>Here are the important details.</p>";
            }
        }
        if (empty($globalFooter)) {
            $lang = $_SESSION['lang'] ?? DEFAULT_LANG;
            if ($lang === 'it') {
                $globalFooter = "<p>Questa e-mail è stata generata automaticamente. Puoi rispondere a questo messaggio o contattarci tramite Whatsapp.</p>";
            } else {
                $globalFooter = "<p>This email was generated automatically. You may reply to this message or contact us.</p>";
            }
        }
        
        // Create absolute URL for logo
        $logoUrl = 'http://localhost/fullmidia/site_manager/assets/images/logo.png';
        $siteName = APP_NAME ?? 'FULLMIDIA';
        
        $headerBgColor = '#1f2732';
        $footerBgColor = '#1f2732';
        $highlightColor = '#f39200';
        
        // Build service info section if service is provided
        $serviceSection = '';
        if (!empty($serviceName)) {
            $serviceSection = <<<HTML
            <div class="service-info">
                <strong>Service:</strong> $serviceName
            </div>
HTML;
        }

        $emailBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$subject</title>
    <style type="text/css">
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            line-height: 1.6; 
            color: #333333; 
            margin: 0; 
            padding: 0; 
            background-color: #f5f5f5;
        }
        .email-container { 
            max-width: 600px; 
            margin: 20px auto; 
            border-radius: 8px; 
            overflow: hidden; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
            background-color: #ffffff;
        }
        .email-header { 
            background-color: $headerBgColor; 
            padding: 30px 20px; 
            text-align: center; 
        }
        .logo-container {
            margin-bottom: 10px;
        }
        .logo-container img {
            max-width: 80px;
            height: auto;
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
        .header-content {
            background-color: $headerBgColor;
            color: white;
            padding: 20px;
            border-top: 3px solid $highlightColor;
        }
        .header-content h2,
        .header-content h3,
        .header-content h4,
        .header-content p {
            margin: 10px 0;
            color: white;
        }
        .header-content a {
            color: $highlightColor;
        }
        .email-body { 
            padding: 30px; 
            background-color: #ffffff; 
        }
        .sender-info {
            background: #f0f0f0;
            border-left: 4px solid $highlightColor;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .sender-info strong {
            color: $highlightColor;
        }
        .service-info {
            background: #e8f4f8;
            border-left: 4px solid #0288d1;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 14px;
        }
        .service-info strong {
            color: #0288d1;
        }
        .message-content {
            background: #fafafa;
            padding: 15px;
            border-left: 4px solid #ddd;
            margin: 20px 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .thread-link {
            text-align: center;
            margin: 20px 0;
        }
        .thread-link a {
            display: inline-block;
            padding: 10px 20px;
            background-color: $highlightColor;
            color: white !important;
            text-decoration: none;
            border-radius: 4px;
        }
        .thread-link a:hover {
            background-color: #e68300;
        }
        .footer-content {
            background-color: $footerBgColor;
            color: white;
            padding: 20px;
            border-top: 3px solid $highlightColor;
        }
        .footer-content h2,
        .footer-content h3,
        .footer-content h4,
        .footer-content p {
            margin: 10px 0;
            color: white;
        }
        .footer-content a {
            color: $highlightColor;
        }
        .email-footer { 
            background-color: #333333; 
            color: #cccccc; 
            padding: 15px; 
            text-align: center; 
            font-size: 12px; 
            line-height: 1.4; 
            border-top: 1px solid #555555;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header Section -->
        <div class="email-header">
            <div class="logo-container">
                <img src="$logoUrl" alt="Logo" style="max-width: 80px;">
            </div>
            <div class="company-name">$siteName</div>
        </div>
        
        <!-- Global Header Content -->
        <div class="header-content">
            $globalHeader
        </div>
        
        <!-- Message Content Area -->
        <div class="email-body">
            <div class="sender-info">
                <strong>Message from:</strong> $senderName
            </div>
            $serviceSection
            
            <div class="message-content">
$content
            </div>
            
            <div class="thread-link">
                <a href="$threadLink">View Full Conversation</a>
            </div>
        </div>
        
        <!-- Global Footer Content -->
        <div class="footer-content">
            $globalFooter
        </div>
        
        <!-- Unsubscribe Section -->
        <div class="email-footer">
            <p style="margin: 0; font-size: 11px; color: #999;">
                Having trouble viewing this email? <a href="$threadLink" style="color: $highlightColor; text-decoration: none;">View it in your browser</a>
            </p>
            <p style="margin: 8px 0 0; font-size: 11px; color: #999;">
                <a href="{$threadLink}?action=messaging&unsubscribe=1&email=" . urlencode($recipientEmail) . "" style="color: $highlightColor; text-decoration: none;">Unsubscribe</a> from these notifications
            </p>
        </div>
HTML;

        return $emailBody;
    }

    private function sendReplyNotification($threadId, $messageId)
    {
        try {
            // Get the thread details
            $thread = $this->threadModel->getFirstMessage($threadId);
            if (!$thread) {
                error_log("Failed to get thread details for reply notification");
                return;
            }

            // Get all participants
            $recipients = $this->threadModel->getThreadParticipants($threadId, $_SESSION['user_id']);
            if (empty($recipients)) {
                error_log("No recipients found for reply notification");
                return;
            }

            // Get the reply message
            $replyMessage = $this->threadModel->getMessageById($messageId);
            if (!$replyMessage) {
                error_log("Failed to get reply message");
                return;
            }

            // Check SMTP settings
            $smtpSettings = $this->emailModel->getSmtpSettings();
            if (!$smtpSettings) {
                error_log("SMTP settings not configured for reply notifications");
                return;
            }

            error_log("Sending reply notification for thread $threadId to " . count($recipients) . " recipients");

            foreach ($recipients as $recipient) {
                try {
                    $senderName = $_SESSION['username'] ?? 'System';
                    $subject = "Re: {$thread['subject']}";
                    $content = "
                        <h1>New Reply to Message</h1>
                        <p>$senderName replied:</p>
                        <div class='card' style='padding: 10px; background: #f9f9f9;'>
                            " . nl2br(htmlspecialchars($replyMessage['content'])) . "
                        </div>
                        <p>View the full conversation: <a href='" . BASE_PATH . "?action=messaging&do=view&id=$threadId'>Click here</a></p>
                    ";

                    $emailBody = $this->emailModel->getEmailTemplate($subject, $content);

                    require_once APP_PATH . '/vendor/autoload.php';
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    $mail = $this->emailModel->configureMailer($mail, $smtpSettings);

                    $mail->setFrom($smtpSettings['from_email'], $smtpSettings['from_name']);
                    $mail->addAddress($recipient['email'], $recipient['username'] ?? '');
                    $mail->Subject = $subject;
                    $mail->Body = $emailBody;
                    $mail->AltBody = strip_tags($content);

                    $success = $mail->send();

                    error_log("Reply notification sent to {$recipient['email']}: " . ($success ? 'Success' : 'Failed'));

                    $this->emailModel->logEmail([
                        'email_type' => 'message_reply',
                        'sent_to' => $recipient['email'],
                        'subject' => $subject,
                        'body' => $content,
                        'status' => $success ? 'sent' : 'failed',
                        'error_message' => $success ? null : $mail->ErrorInfo
                    ]);
                } catch (Exception $e) {
                    error_log("Failed to send reply notification to {$recipient['email']}: " . $e->getMessage());
                    $this->emailModel->logEmail([
                        'email_type' => 'message_reply',
                        'sent_to' => $recipient['email'] ?? 'Unknown',
                        'subject' => $subject ?? 'New Reply to Message',
                        'body' => $content ?? '',
                        'status' => 'failed',
                        'error_message' => $e->getMessage()
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("Error in sendReplyNotification: " . $e->getMessage());
        }
    }

    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?action=messaging');
            exit;
        }

        $threadId = $_POST['thread_id'] ?? null;

        if (!$threadId) {
            header('Location: ?action=messaging');
            exit;
        }

        try {
            // Check if current user is the thread creator
            $creatorId = $this->threadModel->getThreadCreator($threadId);

            if ($creatorId != $_SESSION['user_id']) {
                throw new Exception("Only the thread creator can delete this thread");
            }

            // Delete the thread
            $this->threadModel->deleteThread($threadId);

            header('Location: ?action=messaging&lang=' . ($_SESSION['lang'] ?? 'it'));
            exit;
        } catch (Exception $e) {
            error_log("Failed to delete thread: " . $e->getMessage());
            header('Location: ?action=messaging&do=view&id=' . $threadId . '&lang=' . ($_SESSION['lang'] ?? 'it'));
            exit;
        }
    }

    // Toggle star on single thread (AJAX)
    public function toggleStar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        $threadId = $_POST['thread_id'] ?? null;
        if (!$threadId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing thread_id']);
            exit;
        }

        try {
            $isStarred = $this->threadModel->toggleStar($threadId, $_SESSION['user_id']);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'starred' => $isStarred]);
        } catch (Exception $e) {
            error_log("Failed to toggle star: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Mark thread as read/unread (AJAX)
    public function markThreadRead()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        $threadId = $_POST['thread_id'] ?? null;
        $isRead = $_POST['is_read'] ?? true;

        if (!$threadId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing thread_id']);
            exit;
        }

        try {
            if ($isRead) {
                $this->threadModel->markThreadAsRead($threadId, $_SESSION['user_id']);
            } else {
                $this->threadModel->markThreadAsUnread($threadId, $_SESSION['user_id']);
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Thread marked']);
        } catch (Exception $e) {
            error_log("Failed to mark thread: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Bulk operations
    public function bulkMarkRead()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        $threadIds = $_POST['thread_ids'] ?? [];
        $isRead = $_POST['is_read'] ?? true;

        if (empty($threadIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No threads selected']);
            exit;
        }

        try {
            if ($isRead) {
                $this->threadModel->bulkMarkAsRead($threadIds, $_SESSION['user_id']);
            } else {
                $this->threadModel->bulkMarkAsUnread($threadIds, $_SESSION['user_id']);
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'count' => count($threadIds)]);
        } catch (Exception $e) {
            error_log("Failed to bulk mark: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Bulk star
    public function bulkStar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        $threadIds = $_POST['thread_ids'] ?? [];
        $starred = $_POST['starred'] ?? true;

        if (empty($threadIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No threads selected']);
            exit;
        }

        try {
            $this->threadModel->bulkStar($threadIds, $_SESSION['user_id'], $starred);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'count' => count($threadIds)]);
        } catch (Exception $e) {
            error_log("Failed to bulk star: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Handle unsubscribe from email notifications
    private function isEmailUnsubscribed($email)
    {
        try {
            // Ensure table exists
            $this->ensureEmailPreferencesTableExists();
            
            // Check if email is in unsubscribed list (only unsubscribed if explicitly in table)
            $stmt = $GLOBALS['pdo']->prepare("SELECT id FROM email_preferences WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            return (bool) $stmt->fetch();
        } catch (Exception $e) {
            error_log("Error checking unsubscribe status: " . $e->getMessage());
            // Default to subscribed (true = send email) on error
            return false;
        }
    }

    private function ensureEmailPreferencesTableExists()
    {
        try {
            $stmt = $GLOBALS['pdo']->query("SHOW TABLES LIKE 'email_preferences'");
            if (!$stmt->fetch()) {
                // Table doesn't exist, create it
                $GLOBALS['pdo']->exec("
                    CREATE TABLE IF NOT EXISTS email_preferences (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        email VARCHAR(255) UNIQUE NOT NULL,
                        unsubscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_email (email)
                    )
                ");
            }
        } catch (Exception $e) {
            error_log("Error creating email_preferences table: " . $e->getMessage());
        }
    }

    public function unsubscribe()
    {
        $email = $_GET['email'] ?? null;
        
        if (!$email) {
            $_SESSION['error'] = 'Invalid unsubscribe link';
            header('Location: ' . BASE_PATH);
            exit;
        }

        try {
            // Ensure table exists
            $this->ensureEmailPreferencesTableExists();

            // Add email to unsubscribed list
            $stmt = $GLOBALS['pdo']->prepare("
                INSERT INTO email_preferences (email) 
                VALUES (?) 
                ON DUPLICATE KEY UPDATE unsubscribed_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$email]);

            $_SESSION['message'] = 'You have been unsubscribed from message notifications';
            header('Location: ' . BASE_PATH);
            exit;
        } catch (Exception $e) {
            error_log("Failed to unsubscribe email: " . $e->getMessage());
            $_SESSION['error'] = 'Failed to process unsubscribe request';
            header('Location: ' . BASE_PATH);
            exit;
        }
    }}