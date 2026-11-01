<?php

declare(strict_types=1);

namespace Atwx\SharedLogin;

use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;

/**
 * Settings of the shared login, read from the environment.
 *
 * - Cookie domain (e.g. ".example.org"): the session is shared by all
 *   subdomains, so one login counts everywhere.
 * - OAuth callback URL (e.g. "https://login.example.org/oauth/callback/"):
 *   external providers call back on the central login host only.
 *
 * The names of the environment variables are configurable, so existing
 * deployments keep their variables.
 */
class SharedLogin
{
    use Configurable;

    private static string $cookie_domain_env = 'SHARED_LOGIN_COOKIE_DOMAIN';

    private static string $oauth_callback_url_env = 'SHARED_LOGIN_OAUTH_CALLBACK_URL';

    public static function cookieDomain(): string
    {
        return trim((string) Environment::getEnv(static::config()->get('cookie_domain_env')));
    }

    public static function oauthCallbackUrl(): string
    {
        return trim((string) Environment::getEnv(static::config()->get('oauth_callback_url_env')));
    }

    /**
     * Whether a URL points to the shared cookie domain or one of its subdomains.
     */
    public static function isSharedDomainUrl(string $url): bool
    {
        $domain = strtolower(ltrim(static::cookieDomain(), '.'));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        return $domain !== '' && $host !== ''
            && ($host === $domain || str_ends_with($host, '.' . $domain))
            && in_array(parse_url($url, PHP_URL_SCHEME), ['https', 'http'], true);
    }

    /**
     * Applies the settings; called from the module's _config.php.
     */
    public static function boot(): void
    {
        if ($domain = static::cookieDomain()) {
            Config::modify()->set(Session::class, 'cookie_domain', $domain);
        }
        if ($callback = static::oauthCallbackUrl()) {
            static::setOAuthCallback($callback);
        }
    }

    /**
     * Points every configured OAuth provider to the given callback URL.
     * Works whether or not the OAuth module has already added its own.
     */
    public static function setOAuthCallback(string $callback): void
    {
        $helper = 'Bigfork\\SilverStripeOAuth\\Client\\Helper\\Helper';
        $factory = 'Bigfork\\SilverStripeOAuth\\Client\\Factory\\ProviderFactory';
        if (!class_exists($helper)) {
            return;
        }
        Config::modify()->set($helper, 'default_redirect_uri', $callback);
        $providers = Config::inst()->get(Injector::class, $factory)['properties']['providers'] ?? [];
        foreach ((array) $providers as $spec) {
            if (!is_string($spec) || !str_starts_with($spec, '%$')) {
                continue;
            }
            $service = substr($spec, 2);
            $config = Config::inst()->get(Injector::class, $service);
            if (!is_array($config) || empty($config['constructor']) || !is_array($config['constructor'])) {
                continue;
            }
            $key = array_key_first($config['constructor']);
            $config['constructor'][$key]['redirectUri'] = $callback;
            Config::modify()->set(Injector::class, $service, $config);
            Injector::inst()->load([$service => $config]);
        }
    }
}
