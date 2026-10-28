<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Admin;

use NicheCleverInteractive\Core\AI\ConnectionTestService;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Profile\ProfileDirectory;
use NicheCleverInteractive\Core\Security\AdminActionGuard;

/**
 * Lets an administrator run Core's File Search connection test for an extension profile.
 * The extension's profiles screen posts here with the nonce Core issued for that screen;
 * Core resolves the profile itself, runs the test and stores the result in its own record,
 * so an extension can never mark a profile as tested.
 */
final class ProfileConnectionTest
{
    public const ACTION = 'ncli_test_profile';
    public const NONCE_FIELD = '_ncli_ext_nonce';
    public const SCREEN = 'profiles';

    private AdminActionGuard $guard;
    private ProfileDirectory $profiles;
    private ConnectionTestService $service;
    /** @var callable(): CoreSettings */
    private $settings;
    /** @var callable(string): void */
    private $redirect;

    /**
     * @param callable(): CoreSettings $settings
     * @param callable(string): void $redirect Receives the outcome code: passed, failed or unavailable.
     */
    public function __construct(ProfileDirectory $profiles, ConnectionTestService $service, ?AdminActionGuard $guard = null, ?callable $settings = null, ?callable $redirect = null)
    {
        $this->profiles = $profiles;
        $this->service = $service;
        $this->guard = $guard ?? AdminActionGuard::forWordPress();
        $this->settings = $settings ?? static function (): CoreSettings {
            $stored = function_exists('get_option') ? get_option(CoreSettings::OPTION_NAME, CoreSettings::defaults()) : CoreSettings::defaults();
            return CoreSettings::fromArray(is_array($stored) ? $stored : CoreSettings::defaults());
        };
        $this->redirect = $redirect ?? [$this, 'redirectBack'];
    }

    public function register(): void
    {
        if (function_exists('add_action')) {
            add_action('admin_post_' . self::ACTION, [$this, 'handle']);
        }
    }

    public function handle(): void
    {
        $nonce = AdminRequest::postText(self::NONCE_FIELD);
        if (!$this->guard->allows(ExtensionScreens::NONCE_PREFIX . self::SCREEN, $nonce)) {
            if (function_exists('wp_die')) {
                wp_die('You do not have permission to do that.', '', ['response' => 403]);
            }
            return;
        }

        $id = AdminRequest::postText('profile_id');
        $settings = call_user_func($this->settings);
        $profile = is_string($id) && $id !== CoreSettings::DEFAULT_PROFILE_ID && preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $id) === 1
            ? $this->profiles->resolve($id, $settings)
            : null;
        if ($profile === null) {
            call_user_func($this->redirect, 'unavailable');
            return;
        }

        $result = $this->service->testProfile($profile, $settings);
        $this->profiles->recordTest($profile, $result->status(), $result->fingerprint(), gmdate('Y-m-d\TH:i:s\Z'));
        call_user_func($this->redirect, $result->passed() ? 'passed' : 'failed');
    }

    public function redirectBack(string $outcome): void
    {
        if (!function_exists('wp_safe_redirect') || !function_exists('admin_url')) {
            return;
        }

        $base = function_exists('wp_get_referer') && is_string(wp_get_referer()) && wp_get_referer() !== ''
            ? (string) wp_get_referer()
            : admin_url('admin.php?page=' . SettingsPage::PAGE_SLUG . '&view=profiles');
        $url = function_exists('add_query_arg') ? add_query_arg(['ncli_profile_test' => $outcome], $base) : $base;
        wp_safe_redirect($url);
        exit;
    }
}
