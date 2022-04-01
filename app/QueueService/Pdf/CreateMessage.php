<?php

namespace App\QueueService\Pdf;

use App\Export\PdfSubject;
use FCToernooi\Tournament;

class CreateMessage
{
    public function __construct(
        protected Tournament $tournament,
        protected PdfSubject $subject,
        protected int $totalNrOfSubjects
    ) {
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getSubject(): PdfSubject
    {
        return $this->subject;
    }

    public function getTotalNrOfSubjects(): int
    {
        return $this->totalNrOfSubjects;
    }

    public function toJson(): string
    {
        $json = json_encode([
                                'tournamentId' => (string)$this->tournament->getId(),
                                'subject' => $this->subject->value,
                                'totalNrOfSubjects' => $this->totalNrOfSubjects
                            ]);
        if ($json === false) {
            throw new \Exception('json_encode went wrong', E_ERROR);
        }
        return $json;
    }
}