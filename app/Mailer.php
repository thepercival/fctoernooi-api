<?php

declare(strict_types=1);

namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;

final class Mailer
{
    /**
     * @param LoggerInterface $logger
     * @param string $fromEmailaddress
     * @param string $fromName
     * @param string $adminEmailaddress
     * @param array<string, string|int>|null $smtpConfig
     */
    public function __construct(
        protected LoggerInterface $logger,
        protected string $fromEmailaddress,
        protected string $fromName,
        protected string $adminEmailaddress,
        protected array|null $smtpConfig = null
    ) {
    }

    public function sendToAdmin(string $subject, string $body, bool $text = null): void
    {
        $this->send($subject, $body, $this->adminEmailaddress, $text);
    }

    public function send(string $subject, string $body, string $toEmailaddress, bool $text = null): void
    {
        $mailer = $this->smtpConfig === null ? $this->sendInitMail() : $this->sendInitSmtp($this->smtpConfig);
        // $mailer->'MIME-Version' = '1.0';
        $mailer->ContentType = PHPMailer::CONTENT_TYPE_TEXT_HTML;
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->setFrom($this->fromEmailaddress, $this->fromName);
        $mailer->addReplyTo($this->fromEmailaddress);
        $mailer->addAddress($toEmailaddress);
        $mailer->Subject = $subject;
        if ($text === true) {
            $mailer->isHTML(false);
            $mailer->Body = $body;
        } else {
            $mailer->Body = /*$this->getStyle() .*/
                $body;
        }

        if (!$mailer->send()) {
            $this->logger->error('Mailer Error for ' . $toEmailaddress);
        } else {
            $this->logger->info('mail send to  "' . $toEmailaddress . '" with subject "' . $subject . '"');
        }
    }

    protected function sendInitMail(): PHPMailer
    {
        $mail = new PHPMailer();
        $mail->isSendmail();
        return $mail;
    }

    /**
     * @param array<string, string|int> $smtpConfig
     * @return PHPMailer
     */
    protected function sendInitSmtp(array $smtpConfig): PHPMailer
    {
        $mail = new PHPMailer();
        $mail->isSMTP();
        $mail->Host = (string)$smtpConfig["smtp_host"];
        $mail->Port = (int)$smtpConfig["smtp_port"];
        $mail->SMTPAuth = true;
        $mail->Username = (string)$smtpConfig["smtp_user"];
        $mail->Password = (string)$smtpConfig["smtp_pass"];
        return $mail;
    }

    protected function getStyle(): string
    {
        return <<<EOT
<style>
table, th, td {
  border-collapse: collapse;
  border: 1px solid black;
  padding: 0.5rem;
  border: 0;  
  border-bottom: 1px solid #ddd;
  text-align: left;
}
th {
  background-color: #3E3F3A;
  color: white;
}
</style>
EOT;
    }
}
