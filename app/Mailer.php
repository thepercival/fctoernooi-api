<?php

declare(strict_types=1);

namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;

final class Mailer
{
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var string
     */
    private $fromEmailaddress;
    /**
     * @var string
     */
    private $fromName;
    /**
     * @var string
     */
    protected $adminEmailaddress;
    /**
     * @var array
     */
    protected $smtpConfig;

    public function __construct(
        LoggerInterface $logger,
        string $fromEmailaddress,
        string $fromName,
        string $adminEmailaddress,
        array $smtpConfig = null
    ) {
        $this->logger = $logger;
        $this->fromEmailaddress = $fromEmailaddress;
        $this->fromName = $fromName;
        $this->adminEmailaddress = $adminEmailaddress;
        $this->smtpConfig = $smtpConfig;
    }

    public function sendToAdmin(string $subject, string $body, bool $text = null)
    {
        $this->send($subject, $body, $this->adminEmailaddress, $text);
    }

    public function send(string $subject, string $body, string $toEmailaddress, bool $text = null)
    {
        $mailer = $this->smtpConfig === null ? $this->sendInitMail() : $this->sendInitSmtp();
        // $mailer->'MIME-Version' = '1.0';
        $mailer->ContentType = PHPMailer::CONTENT_TYPE_TEXT_HTML;
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->From = $this->fromEmailaddress;
        $mailer->FromName = $this->fromName;
        $mailer->addAddress($toEmailaddress);
        $mailer->addReplyTo($this->fromEmailaddress);
        $mailer->Subject = $subject;
        if ($text === true) {
            $mailer->isHTML(false);
            $mailer->Body = $body;
        } else {
            $mailer->Body = $this->getStyle() . $body;
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

    protected function sendInitSmtp(): PHPMailer
    {
        $mail = new PHPMailer();
        $mail->isSMTP();
        $mail->Host = $this->smtpConfig["smtp_host"];
        $mail->Port = $this->smtpConfig["smtp_port"];
        $mail->SMTPAuth = true;
        $mail->Username = $this->smtpConfig["smtp_user"];
        $mail->Password = $this->smtpConfig["smtp_pass"];
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
