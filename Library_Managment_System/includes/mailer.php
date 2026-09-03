<?php
/**
 * Real Email Delivery Helper via PHPMailer & SMTP
 */

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailException;

function sendPasswordResetEmail(string $toEmail, string $toName, string $resetLink): array {
    require_once __DIR__ . '/../config/mail.php';

    // ── DEV MODE OVERRIDE (If explicitly enabled in mail.php) ──
    if (defined('MAIL_DEV_MODE') && MAIL_DEV_MODE === true) {
        return ['success' => true, 'dev_link' => $resetLink];
    }

    // Check if SMTP credentials are still default placeholders
    if (MAIL_USERNAME === 'your_email@gmail.com' || strpos(MAIL_PASSWORD, 'xxxx') !== false) {
        return [
            'success' => false,
            'error'   => 'ኢሜይል ለመላክ በ config/mail.php ውስጥ ትክክለኛ የ Gmail ኢሜይልዎን እና 16-ዲጂት App Password ያስገቡ። (Please update your_email@gmail.com & App Password in config/mail.php)'
        ];
    }

    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = (strtolower(MAIL_ENCRYPTION) === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 15; // 15s timeout

        // Recipients
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

        // Content
        $mail->isHTML(true);
        $mail->Subject = '🔒 የይለፍ ቃል መቀየሪያ ሊንክ - Password Reset Link';
        $mail->Body    = buildResetEmailHTML($toName, $resetLink);
        $mail->AltBody = "Hello {$toName},\n\nClick this link to reset your password:\n{$resetLink}\n\nThis link expires in " . RESET_TOKEN_EXPIRY_MINUTES . " minutes.\n\nIf you did not request this, ignore this email.";

        $mail->send();
        return ['success' => true];
    } catch (MailException $e) {
        error_log("PHPMailer error: " . $mail->ErrorInfo);
        return ['success' => false, 'error' => 'Email delivery failed: ' . $mail->ErrorInfo];
    } catch (Exception $e) {
        error_log("Mail error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Mail error: ' . $e->getMessage()];
    }
}

function buildResetEmailHTML(string $name, string $link): string {
    $expiry = defined('RESET_TOKEN_EXPIRY_MINUTES') ? RESET_TOKEN_EXPIRY_MINUTES : 60;
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0f172a;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td align="center" style="padding:40px 20px;">
<table width="560" style="background:#1e293b;border-radius:16px;overflow:hidden;border:1px solid rgba(255,255,255,0.08);">
  <tr><td style="background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:30px;text-align:center;">
    <h1 style="margin:0;color:#fff;font-size:22px;">🔒 የይለፍ ቃል መቀየሪያ / Password Reset Request</h1>
  </td></tr>
  <tr><td style="padding:32px;">
    <p style="color:#94a3b8;margin:0 0 12px;">ሰላም <strong style="color:#f1f5f9;">{$name}</strong>,</p>
    <p style="color:#94a3b8;margin:0 0 24px;">የይለፍ ቃልዎን ለመቀየር ጥያቄ ቀርቧል። ከታች ያለውን ቁልፍ ተጭነው አዲስ የይለፍ ቃል ያስገቡ (We received a request to reset your password. Click below):</p>
    <div style="text-align:center;margin:28px 0;">
      <a href="{$link}" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;text-decoration:none;padding:14px 32px;border-radius:10px;font-weight:600;font-size:15px;display:inline-block;">🔑 የይለፍ ቃል ቀይር / Reset My Password</a>
    </div>
    <p style="color:#64748b;font-size:13px;margin:0 0 6px;">ወይም ይህንን ሊንክ በብሮውዘርዎ ይክፈቱ (Or copy this link):</p>
    <p style="background:#0f172a;padding:12px;border-radius:8px;word-break:break-all;font-size:12px;color:#818cf8;margin:0 0 24px;">{$link}</p>
    <hr style="border:none;border-top:1px solid rgba(255,255,255,0.07);margin:20px 0;">
    <p style="color:#f59e0b;font-size:13px;margin:0;">⏰ ይህ ሊንክ ለ <strong>{$expiry} ደቂቃዎች</strong> ብቻ የሚያገለግል ነው (Expires in {$expiry} minutes).</p>
    <p style="color:#64748b;font-size:13px;margin:8px 0 0;">ጥያቄውን ካላቀረቡ ይህንን ኢሜይል ችላ ይበሉት።</p>
  </td></tr>
</table></td></tr></table>
</body></html>
HTML;
}
