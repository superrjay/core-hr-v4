<?php
declare(strict_types=1);

interface MailTransport
{
    public function send(string $to, string $subject, string $html, string $text): bool;
}

function mail_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

final class LogMailTransport implements MailTransport
{
    public function send(string $to, string $subject, string $html, string $text): bool
    {
        if (!security_is_local()) {
            security_log('Refused to write mail to the local log outside APP_ENV=local.');
            return false;
        }
        $dir = dirname(__DIR__) . '/storage/logs';
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            security_log('Local mail log directory is not writable.');
            return false;
        }
        $entry = "----\n" . date('c') . "\nTo: {$to}\nSubject: {$subject}\n\n{$text}\n\n{$html}\n";
        if (@file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX) === false) {
            security_log('Local mail log write failed.');
            return false;
        }
        return true;
    }
}

final class SmtpMailTransport implements MailTransport
{
    public function send(string $to, string $subject, string $html, string $text): bool
    {
        require_once __DIR__ . '/lib/PHPMailer/Exception.php';
        require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
        require_once __DIR__ . '/lib/PHPMailer/SMTP.php';

        $config = security_config();
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $config['MAIL_HOST'];
            $mail->Port = (int) $config['MAIL_PORT'];
            $mail->Timeout = 10;
            $mail->SMTPAuth = $config['MAIL_USERNAME'] !== '';
            $mail->Username = $config['MAIL_USERNAME'];
            $mail->Password = $config['MAIL_PASSWORD'];
            $mail->SMTPDebug = 0;
            if ($config['MAIL_ENCRYPTION'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                $mail->SMTPAutoTLS = false;
            } elseif ($config['MAIL_ENCRYPTION'] === 'tls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->CharSet = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
            $from = $config['MAIL_FROM_ADDRESS'];
            if ($from === '') {
                security_log('Mail send failed: MAIL_FROM_ADDRESS is empty.');
                return false;
            }
            $mail->setFrom($from, $config['MAIL_FROM_NAME']);
            $mail->addReplyTo($from, $config['MAIL_FROM_NAME']);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text;
            if (!$mail->send()) {
                security_log('Mail send failed.');
                return false;
            }
            return true;
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            if ($config['MAIL_PASSWORD'] !== '') {
                $message = str_replace($config['MAIL_PASSWORD'], '[redacted]', $message);
            }
            security_log('Mail send failed: ' . $message);
            return false;
        }
    }
}

function mail_transport(): MailTransport
{
    if (security_is_local()) {
        return new LogMailTransport();
    }
    // MAIL_DRIVER=smtp is the built-in network transport.
    // An HTTPS API provider can implement MailTransport and be returned here.
    return new SmtpMailTransport();
}

function send_mail(string $to, string $subject, string $html, string $text): bool
{
    try {
        return mail_transport()->send($to, $subject, $html, $text);
    } catch (Throwable $exception) {
        security_log('Mail transport error.');
        return false;
    }
}
