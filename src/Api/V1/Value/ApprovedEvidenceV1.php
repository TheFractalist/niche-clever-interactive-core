<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

use InvalidArgumentException;

final class ApprovedEvidenceV1
{
    private string $title;
    private ?string $url;
    private string $approvalRevision;

    public function __construct(string $title, ?string $url, string $approvalRevision)
    {
        if ($title === '' || strlen($title) > 160) {
            throw new InvalidArgumentException('Evidence titles must be between 1 and 160 bytes.');
        }

        if ($approvalRevision === '') {
            throw new InvalidArgumentException('Evidence approval revisions must be explicit.');
        }

        if ($url !== null) {
            $parts = parse_url($url);
            if (
                $parts === false
                || ($parts['scheme'] ?? '') !== 'https'
                || !isset($parts['host'])
                || isset($parts['user'])
                || isset($parts['pass'])
                || strlen($url) > 2048
            ) {
                throw new InvalidArgumentException('Evidence URLs must be credential-free absolute HTTPS URLs.');
            }
        }

        $this->title = $title;
        $this->url = $url;
        $this->approvalRevision = $approvalRevision;
    }

    /**
     * @return array{title: string, url: string|null}
     */
    public function publicPayload(): array
    {
        return ['title' => $this->title, 'url' => $this->url];
    }
}
