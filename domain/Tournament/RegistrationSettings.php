<?php

declare(strict_types=1);

namespace FCToernooi\Tournament;

use FCToernooi\Competitor;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Registration\TextSubject;
use Sports\Category;
use SportsHelpers\Identifiable;

class RegistrationSettings extends Identifiable
{
    private string $remark;

    private string|null $acceptText = null;
    private string|null $acceptAsSubstituteText = null;
    private string|null $declineText = null;

    public const MAX_LENGTH_REMARK = 200;

    public function __construct(
        private Tournament  $tournament,
        private bool        $enabled,
        private \DateTimeImmutable  $endDateTime,
        private bool        $mailAlert,
        string             $remark
    )
    {
        $this->setRemark($remark);
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }



    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function setEndDateTime(\DateTimeImmutable $endDateTime): void
    {
        $this->endDateTime = $endDateTime;
    }

    public function getEndDateTime(): \DateTimeImmutable
    {
        return $this->endDateTime;
    }

    public function getMailAlert(): bool
    {
        return $this->mailAlert;
    }

    public function setMailAlert(bool $mailAlert): void
    {
        $this->mailAlert = $mailAlert;
    }

    public function getRemark(): string
    {
        return $this->remark;
    }

    public final function setRemark(string $remark): void
    {
        if (strlen($remark) > self::MAX_LENGTH_REMARK) {
            throw new \InvalidArgumentException(
                'de opmerking mag maximaal ' . self::MAX_LENGTH_REMARK . ' karakters bevatten',
                E_ERROR
            );
        }
        $this->remark = $remark;
    }

    public function getText(TextSubject $subject): string|null
    {
        if( $subject === TextSubject::Accept) {
            return $this->acceptText;
        } else if( $subject === TextSubject::AcceptAsSubstitute) {
            return $this->acceptAsSubstituteText;
        }
        return $this->declineText;
    }

    public final function setText(TextSubject $subject, string $text): void
    {
        if( $subject === TextSubject::Accept) {
            $this->acceptText = $text;
        } else if( $subject === TextSubject::AcceptAsSubstitute) {
            $this->acceptAsSubstituteText = $text;
        } else {
            $this->declineText = $text;
        }
    }
}
