<?php

namespace Api\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    public function __construct(
        private PHPMailer $mail,
        string $mail_host,
        string $sender_email, 
        string $sender_password,
        string $sender_username,
        int $sender_port 
    ) {
        
        // SMTP configuration
        $this->mail->isSMTP();
        $this->mail->Host = $mail_host;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $sender_username;
        $this->mail->Password = $sender_password;
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = $sender_port;

        // Default From
        $this->mail->setFrom($sender_email, 'TaskFlow');
        $this->mail->isHTML(true);
        $this->mail->CharSet = 'UTF-8';
    }

    public function sendResetEmail(string $to, string $resetLink): void {
        try {
            $this->mail->clearAllRecipients(); // Reset recipients
            $this->mail->addAddress($to);
            $this->mail->Subject = 'TaskFlow Account password reset request';
            $this->mail->Body = "
                <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:20px;border:1px solid #e0e0e0;border-radius:8px;background:#ffffff;'>
                    <div style='margin-bottom:20px;'>
                        <h2 style='color:#202124;margin:0 0 10px;'>TaskFlow</h2>
                    </div>
                    <div style='font-size:14px;line-height:1.6;'>
                        <p style='color:#3c4043;'>Hi,</p>
                        <p style='color:#3c4043;'>You recently requested to reset your password. Click the button below to proceed:</p>
                        <p>
                            <a href='{$resetLink}' style='display:inline-block;background:#1a73e8;color:#ffffff;padding:8px 12px;border-radius:4px;text-decoration:none;font-size:13px;font-weight:500;'>Reset Password</a>
                        </p>
                        <p style='color:#3c4043;'>If the button above doesn't work, copy and paste this link into your browser:</p>
                        <p style='word-break:break-all;color:#5f6368;'>{$resetLink}</p>
                        <p style='color:#3c4043;'>If you didn't request this, you can ignore this email or contact our support team.</p>
                        <p style='color:#3c4043;'>This link will expire in 15 minutes.</p>
                        <br>
                        <p style='color:#3c4043;'>— The TaskFlow Team</p>
                    </div>
                    <hr style='margin:30px 0;border:none;border-top:1px solid #dadce0;'/>
                    <div style='color:#5f6368;font-size:12px;text-align:center;'>
                        Need help? Contact us at 
                        <a href='mailto:info.taskflowapp@gmail.com' style='color:#1a73e8;text-decoration:none;'>info.taskflowapp@gmail.com</a>
                    </div>
                </div>
            ";

            $this->mail->AltBody = "Hi,\n\nYou recently requested to reset your password. Click the link below to proceed:\n$resetLink\n\nIf the link above doesn't work, copy and paste it into your browser.\n\nThis link will expire in 15 minutes.\n\nIf you didn't request this, you can ignore this email or contact support.\n\n— The TaskFlow Team\ninfo.taskflowapp@gmail.com";

            $this->mail->send();
        } 
        catch (Exception $e) {
            throw new Exception('Failed to send reset password email: ' . $e->getMessage());
        }
    }

    public function sendPasswordChangedConfirmation(string $to): void {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($to);
            $this->mail->Subject = 'Your TaskFlow Account password has been changed';
            $this->mail->Body = "
                <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:20px;border:1px solid #e0e0e0;border-radius:8px;background:#ffffff;'>
                    <div style='margin-bottom:20px;'>
                        <h2 style='color:#202124;margin:0 0 10px;'>TaskFlow</h2>
                    </div>
                    <div style='font-size:14px;line-height:1.6;'>
                        <p style='color:#3c4043;'>Hi,</p>
                        <p style='color:#3c4043;'>This is a confirmation that your password has been successfully changed.</p>
                        <p style='color:#3c4043;'>If you did not perform this action, please contact our support team immediately.</p>
                        <br>
                        <p style='color:#3c4043;'>— The TaskFlow Team</p>
                    </div>
                    <hr style='margin:30px 0;border:none;border-top:1px solid #dadce0;'/>
                    <div style='color:#5f6368;font-size:12px;text-align:center;'>
                        Need help? Contact us at 
                        <a href='mailto:info.taskflowapp@gmail.com' style='color:#1a73e8;text-decoration:none;'>info.taskflowapp@gmail.com</a>
                    </div>
                </div>
            ";
                
            $this->mail->AltBody = "Hi,\n\nThis is a confirmation that your password has been successfully changed.\n\nIf you did not perform this action, please contact our support team immediately.\n\n— The TaskFlow Team\ninfo.taskflowapp@gmail.com";

            $this->mail->send();
        } 
        catch (Exception $e) {
            throw new Exception('Failed to send password changed confirmation: ' . $e->getMessage());
        }
    }

    public function sendWelcomeEmail(string $to, string $userName): void {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($to);
            $this->mail->Subject = 'Welcome to TaskFlow!';
            $this->mail->Body = "
                <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:20px;border:1px solid #e0e0e0;border-radius:8px;background:#ffffff;'>
                    <div style='margin-bottom:20px;'>
                        <h2 style='color:#202124;margin:0 0 10px;'>TaskFlow</h2>
                    </div>
                    <div style='font-size:14px;line-height:1.6;'>
                        <p style='color:#3c4043;'>Hi {$userName},</p>
                        <p style='color:#3c4043;'>We're excited to have you on board! With TaskFlow, you'll be able to organize your tasks and boost productivity seamlessly.</p>
                        <p style='color:#3c4043;'>If you need any help getting started, feel free to reach out to our support team.</p>
                        <br>
                        <p style='color:#3c4043;'>— The TaskFlow Team</p>
                    </div>
                    <hr style='margin:30px 0;border:none;border-top:1px solid #dadce0;'/>
                    <div style='color:#5f6368;font-size:12px;text-align:center;'>
                        Need help? Contact us at 
                        <a href='mailto:info.taskflowapp@gmail.com' style='color:#1a73e8;text-decoration:none;'>info.taskflowapp@gmail.com</a>
                    </div>
                </div>
            ";

            $this->mail->AltBody = "Hi {$userName},\n\nWelcome to TaskFlow! We're excited to have you on board.\n\nIf you need help getting started, feel free to reach out to our support team.\n\n— The TaskFlow Team\ninfo.taskflowapp@gmail.com";

            $this->mail->send();
        } 
        catch (Exception $e) {
            throw new Exception('Failed to send welcome email: ' . $e->getMessage());
        }
    }
}