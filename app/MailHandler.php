<?php

declare(strict_types=1);

namespace App;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\NativeMailerHandler;

final class MailHandler extends NativeMailerHandler
{
    protected Mailer|null $mailer = null;

    public function setMailer(Mailer $mailer): void
    {
        $this->mailer = $mailer;
    }

    /**
     * {@inheritdoc}
     */
    protected function send(string $content, array $records): void
    {
        if ($this->mailer === null) {
            return;
        }
        $contentType = $this->getContentType() ?? ($this->isHtmlBody($content) ? 'text/html' : 'text/plain');

        if ($contentType !== 'text/html') {
            $content = wordwrap($content, $this->maxColumnWidth);
        }

//        $headers = ltrim(implode("\r\n", $this->headers) . "\r\n", "\r\n");
//        $headers .= 'Content-type: ' . $contentType . '; charset=' . $this->getEncoding() . "\r\n";
//        if ($contentType === 'text/html' && false === strpos($headers, 'MIME-Version:')) {
//            $headers .= 'MIME-Version: 1.0' . "\r\n";
//        }

        $subject = $this->subject;
        if (count($records) > 0) {
            $subjectFormatter = new LineFormatter($this->subject);
            $subject = $subjectFormatter->format($this->getHighestRecord($records));
        }

        // $parameters = implode(' ', $this->parameters);
        foreach ($this->to as $to) {
            $this->mailer->send($subject, $content, $to);
        }
    }
}
