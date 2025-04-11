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
        $this->mail->CharSet = 'UTF-8';
    }

    public function sendResetEmail(string $to, string $resetLink): void {
        try {
            $this->mail->clearAllRecipients(); // Reset recipients
            $this->mail->addAddress($to);
            $this->mail->Subject = 'TaskFlow account password reset request';
            $this->mail->Body = "
                <p style='color:#272727'>Hi,</p>
                <p style='color:#272727'>You recently requested to reset your password. Click the button below to proceed:</p>
                <p>
                    <a href='{$resetLink}' style='display:inline-block; background:#007BFF; color:#ffffff; padding:8px 14px; text-decoration:none; border-radius:4px; font-size: 0.75rem'>Reset Password</a>
                </p>
                <p style='color:#272727'>If the button above does not work, copy and paste this link into your browser:</p>
                <p style='word-break:break-all;color:#272727'>{$resetLink}</p>
                <p style='color:#777;';>If you did not request a password reset, please ignore this email or contact our support team for assistance.</p>
                <p style='color:#777;'>This link will expire in 15 minutes.</p>
                <p style='color:#272727'>— The TaskFlow Team</p>
             ";

            $this->mail->AltBody = "Hi,\n\nClick the link below to reset your password:\n$resetLink\n\nThis link will expire in 15 minutes.\n\n— The TaskFlow Team";

            $this->mail->send();
        } 
        catch (Exception $e) {
            throw new Exception("Failed to send the email: " . $e->getMessage());
        }
    }

    public function sendPasswordChangedConfirmation(string $to): void {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($to);
            $this->mail->Subject = 'Your TaskFlow account password has been changed';
            $this->mail->Body = "
                <p style='color:#272727'>Hi,</p>
                <p style='color:#272727'>This is a confirmation that your password has been successfully changed.</p>
                <p style='color:#272727';>If you did not perform this action, please contact our support team immediately.</p>
                <p style='color:#272727'>— The TaskFlow Team</p>
            ";
                
            $this->mail->AltBody = "Hi,\n\nYour password has been successfully changed.\n\nIf you didn't do this, please contact support.\n\n— The TaskFlow Team";

            $this->mail->send();
        } 
        catch (Exception $e) {
            throw new Exception("Failed to send password changed confirmation: " . $e->getMessage());
        }
    }

    public function sendWelcomeEmail(string $to, string $userName): void {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($to);
            $this->mail->Subject = 'Welcome to TaskFlow!';
            $this->mail->Body = "
                <p style='color:#272727'>Hi $userName,</p>
                <p style='color:#272727'>We're excited to have you on board. With TaskFlow, you'll be able to organize your tasks and boost your productivity seamlessly.</p>
                <p style='color:#272727'>If you need any help getting started, feel free to reach out to our support team.</p>
                <p style='color:#272727'>— The TaskFlow Team</p>
            ";
            $this->mail->AltBody = "Hi $userName,\n\nWelcome to TaskFlow! We're glad you're here.\n\nGet started organizing your tasks today.\n\n— The TaskFlow Team";

            $this->mail->send();
        } 
        catch (Exception $e) {
            throw new Exception("Failed to send welcome email: " . $e->getMessage());
        }
    }
}