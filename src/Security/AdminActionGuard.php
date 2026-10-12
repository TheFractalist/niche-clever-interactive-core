<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Security;

use InvalidArgumentException;

final class AdminActionGuard
{
    /** @var callable */
    private $capabilityCheck;
    /** @var callable */
    private $nonceCheck;

    public function __construct(callable $capabilityCheck, callable $nonceCheck)
    {
        $this->capabilityCheck = $capabilityCheck;
        $this->nonceCheck = $nonceCheck;
    }

    public static function forWordPress(): self
    {
        return new self(
            static function (): bool {
                return function_exists('current_user_can') && current_user_can('manage_options');
            },
            static function (string $action, string $nonce): bool {
                return function_exists('wp_verify_nonce') && wp_verify_nonce($nonce, $action) !== false;
            }
        );
    }

    public function allows(string $action, $nonce): bool
    {
        if (preg_match('/^[a-z0-9_]{3,100}$/', $action) !== 1 || !is_string($nonce) || $nonce === '') {
            return false;
        }

        return (bool) call_user_func($this->capabilityCheck) && (bool) call_user_func($this->nonceCheck, $action, $nonce);
    }
}
