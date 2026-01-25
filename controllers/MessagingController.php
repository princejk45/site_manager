<?php
class MessagingController
{
    private $threadModel;
    private $groupModel;
    private $userModel;
    private $emailModel;

    public function __construct($threadModel, $groupModel, $userModel, $emailModel)
    {
        $this->threadModel = $threadModel;
        $this->groupModel = $groupModel;
        $this->userModel = $userModel;
        $this->emailModel = $emailModel;
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

        if ($isGroupThread) {
            $threadId = $this->threadModel->createThread(
                $subject,
                $_SESSION['user_id'],
                [],
                $_POST['content'],
                $_POST['group_id']
            );
        } else {
            $threadId = $this->threadModel->createThread(
                $subject,
                $_SESSION['user_id'],
                $_POST['recipients'],
                $_POST['content']
            );
        }

        // Send email only for first message
        if ($threadId) {
            $this->sendEmailNotifications($threadId);
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

    private function sendEmailNotifications($threadId)
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

            error_log("Sending message notification for thread $threadId to " . count($recipients) . " recipients");

            foreach ($recipients as $recipient) {
                try {
                    $senderName = $_SESSION['username'] ?? 'System';
                    $subject = "New message: {$firstMessage['subject']}";
                    $content = "
                        <h1>New Message Notification</h1>
                        <p>You have received a new message from $senderName:</p>
                        <div class='card'>
                            " . nl2br(htmlspecialchars($firstMessage['content'])) . "
                        </div>
                        <p>View the full conversation: <a href='" . BASE_PATH . "?action=messaging&do=view&id=$threadId'>Click here</a></p>
                    ";

                    // Use your existing email template system
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

                    error_log("Email sent to {$recipient['email']}: " . ($success ? 'Success' : 'Failed'));

                    // Log the email using your existing system
                    $this->emailModel->logEmail([
                        'email_type' => 'message_notification',
                        'sent_to' => $recipient['email'],
                        'subject' => $subject,
                        'body' => $content,
                        'status' => $success ? 'sent' : 'failed',
                        'error_message' => $success ? null : $mail->ErrorInfo
                    ]);
                } catch (Exception $e) {
                    error_log("Failed to send message notification to {$recipient['email']}: " . $e->getMessage());
                    $this->emailModel->logEmail([
                        'email_type' => 'message_notification',
                        'sent_to' => $recipient['email'] ?? 'Unknown',
                        'subject' => $subject ?? 'New Message Notification',
                        'body' => $content ?? '',
                        'status' => 'failed',
                        'error_message' => $e->getMessage()
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("Error in sendEmailNotifications: " . $e->getMessage());
        }
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
}
