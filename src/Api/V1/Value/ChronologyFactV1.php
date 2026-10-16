<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

use DateTimeImmutable;
use InvalidArgumentException;

final class ChronologyFactV1
{
    private string $date;
    private string $label;
    private string $summary;
    private string $sourceRevision;

    public function __construct(string $date, string $label, string $summary, string $sourceRevision)
    {
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $dateErrors = DateTimeImmutable::getLastErrors();

        if (
            $parsedDate === false
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $parsedDate->format('Y-m-d') !== $date
        ) {
            throw new InvalidArgumentException('Chronology dates must use YYYY-MM-DD.');
        }

        if ($label === '' || strlen($label) > 160 || $summary === '' || strlen($summary) > 500) {
            throw new InvalidArgumentException('Chronology text is outside its contract bounds.');
        }

        if ($sourceRevision === '' || strlen($sourceRevision) > 64) {
            throw new InvalidArgumentException('Chronology source revisions must be bounded.');
        }

        $this->date = $date;
        $this->label = $label;
        $this->summary = $summary;
        $this->sourceRevision = $sourceRevision;
    }

    public function date(): string
    {
        return $this->date;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function summary(): string
    {
        return $this->summary;
    }

    public function sourceRevision(): string
    {
        return $this->sourceRevision;
    }
}
