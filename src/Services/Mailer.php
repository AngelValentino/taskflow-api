<?php

namespace Api\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private PHPMailer $mail;

    public function __construct(
        PHPMailer $PHPMailer,
        string $mail_host,
        string $sender_email, 
        string $sender_password
    ) {
        $this->mail = $PHPMailer;
        
        // SMTP configuration
        $this->mail->isSMTP();
        $this->mail->Host = $mail_host;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $sender_email;
        $this->mail->Password = $sender_password;
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = 587;

        // Default From
        $this->mail->setFrom($sender_email, 'TaskFlow');
        $this->mail->isHTML(true);
    }

    public function sendResetEmail(string $to, string $resetLink): void {
        try {
            $this->mail->clearAllRecipients(); // Reset recipients
            $this->mail->addAddress($to);
            $this->mail->Subject = 'Password Reset Request';
            $this->mail->Body = "
                <p>Hi,</p>
                <p>Click the link below to reset your password:</p>
                <a href='$resetLink'>$resetLink</a>
                <p>This link will expire in 15 minutes.</p>
                <p>— The Taskflow Team</p>
            ";

            $this->mail->send();
        } 
        catch (Exception $e) {
            throw new Exception("Failed to send the email: " . $e->getMessage());
        }
    }
}