<?php
/**
 * Mailer helper — sends emails via SMTP (Gmail).
 * Uses PHPMailer library bundled in /EMS2/lib/PHPMailer/
 *
 * Environment variables (set in Railway or .env):
 *   SMTP_EMAIL    = dranhs.smartenroll@gmail.com
 *   SMTP_PASSWORD = xxxx-xxxx-xxxx-xxxx (Gmail App Password)
 *   SMTP_NAME     = DRANHS SmartEnroll (optional, defaults to school name)
 */

require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/SMTP.php';
require_once __DIR__ . '/lib/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Check if SMTP credentials are configured.
 */
function is_smtp_configured(): bool {
    return !empty(getenv('SMTP_EMAIL')) && !empty(getenv('SMTP_PASSWORD'));
}

/**
 * Send an email via Gmail SMTP.
 *
 * @param string $to_email   Recipient email address
 * @param string $to_name    Recipient display name
 * @param string $subject    Email subject
 * @param string $html_body  Email body (HTML)
 * @return array ['success' => bool, 'message' => string]
 */
function send_email(string $to_email, string $to_name, string $subject, string $html_body): array {
    if (!is_smtp_configured()) {
        return ['success' => false, 'message' => 'SMTP not configured. Set SMTP_EMAIL and SMTP_PASSWORD environment variables.'];
    }

    $mail = new PHPMailer(true);

    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_EMAIL');
        $mail->Password   = getenv('SMTP_PASSWORD');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Sender
        $from_name = getenv('SMTP_NAME') ?: 'DRANHS SmartEnroll';
        $mail->setFrom(getenv('SMTP_EMAIL'), $from_name);

        // Recipient
        $mail->addAddress($to_email, $to_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html_body));

        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully to ' . $to_email];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Email failed: ' . $mail->ErrorInfo];
    }
}

/**
 * Build the credential notification email HTML.
 */
function build_credential_email(string $full_name, string $username, string $password, string $roles_display, string $login_url): string {
    $roles_html = $roles_display ?: 'Faculty';
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
<div style="max-width:560px;margin:40px auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(15,23,42,0.08);">
    <div style="background:linear-gradient(135deg,#009b5a 0%,#047857 100%);padding:32px 28px;text-align:center;">
        <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:800;letter-spacing:0.5px;">DRANHS SmartEnroll</h1>
        <p style="margin:6px 0 0;color:rgba(255,255,255,0.85);font-size:13px;">Enrollment Management System</p>
    </div>
    <div style="padding:28px;">
        <p style="color:#334155;font-size:15px;line-height:1.6;margin:0 0 18px;">
            Hi <strong>{$full_name}</strong>,
        </p>
        <p style="color:#475569;font-size:14px;line-height:1.7;margin:0 0 20px;">
            Your account has been created for the DRANHS Senior High School Enrollment System. Use the credentials below to log in.
        </p>
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin:0 0 20px;">
            <table style="width:100%;border-collapse:collapse;">
                <tr>
                    <td style="padding:6px 0;color:#64748b;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;width:110px;">Username</td>
                    <td style="padding:6px 0;color:#0f172a;font-size:15px;font-weight:700;">{$username}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0;color:#64748b;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Password</td>
                    <td style="padding:6px 0;color:#0f172a;font-size:15px;font-weight:700;font-family:monospace;">{$password}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0;color:#64748b;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Role</td>
                    <td style="padding:6px 0;color:#0f172a;font-size:15px;font-weight:700;">{$roles_html}</td>
                </tr>
            </table>
        </div>
        <div style="text-align:center;margin:24px 0;">
            <a href="{$login_url}" style="display:inline-block;background:#009b5a;color:#ffffff;text-decoration:none;padding:14px 36px;border-radius:10px;font-weight:800;font-size:14px;">Login to SmartEnroll</a>
        </div>
        <p style="color:#94a3b8;font-size:12px;line-height:1.6;margin:20px 0 0;text-align:center;">
            Please change your password after your first login for security.<br>
            If you did not expect this email, please contact the school admin.
        </p>
    </div>
    <div style="background:#f8fafc;padding:16px 28px;text-align:center;border-top:1px solid #e2e8f0;">
        <p style="margin:0;color:#94a3b8;font-size:11px;">Dr. Arcadio N. Habulan Sr. National High School</p>
    </div>
</div>
</body>
</html>
HTML;
}
