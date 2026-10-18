<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

use InvalidArgumentException;

/**
 * Details of the public request that produced a completed turn, handed to the recorder seam in
 * memory only. Core never stores them. An extension that keeps any of it owns the disclosure.
 */
final class RequestContextV1
{
    private ?string $visitorId;
    private ?string $clientAddress;
    private ?string $userAgent;

    public function __construct(?string $visitorId, ?string $clientAddress, ?string $userAgent)
    {
        if ($visitorId !== null && preg_match('/^[a-f0-9]{32}$/D', $visitorId) !== 1) {
            throw new InvalidArgumentException('A visitor identifier is 32 lowercase hexadecimal characters.');
        }
        if ($clientAddress !== null && filter_var($clientAddress, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('A client address must be a valid IP address.');
        }
        if ($userAgent !== null && strlen($userAgent) > 512) {
            throw new InvalidArgumentException('A user agent is at most 512 bytes.');
        }

        $this->visitorId = $visitorId;
        $this->clientAddress = $clientAddress;
        $this->userAgent = $userAgent;
    }

    /** Opaque browser-held identifier, present only when an extension asked the panel to send one. */
    public function visitorId(): ?string
    {
        return $this->visitorId;
    }

    /** Address under Core's trusted-proxy rule, or null when it cannot be established. */
    public function clientAddress(): ?string
    {
        return $this->clientAddress;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }
}
