<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

use InvalidArgumentException;

/**
 * A bounded description of one source an answer cited: the provider's file reference and file
 * name, nothing else. It carries no retrieved text, no payload and no URL, and it is passed only
 * in-process to an installed extension; it is never sent to a browser.
 */
final class EvidenceCitationV1
{
    public const MAX_REFERENCE = 128;
    public const MAX_FILENAME = 255;

    private string $reference;
    private string $filename;

    public function __construct(string $reference, string $filename = '')
    {
        if (preg_match('/^[A-Za-z0-9_.:-]{1,' . self::MAX_REFERENCE . '}$/D', $reference) !== 1) {
            throw new InvalidArgumentException('A citation reference is malformed.');
        }
        if (strlen($filename) > self::MAX_FILENAME || preg_match('/[\x00-\x1F\x7F]/', $filename) === 1) {
            throw new InvalidArgumentException('A citation file name is out of bounds.');
        }

        $this->reference = $reference;
        $this->filename = trim($filename);
    }

    public function reference(): string
    {
        return $this->reference;
    }

    public function filename(): string
    {
        return $this->filename;
    }
}
