<?php

declare(strict_types=1);

namespace Atwx\SharedLogin\Middleware;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;

/**
 * Removes a leftover host-only session cookie once the session is shared
 * across subdomains.
 *
 * Browsers that signed in before the shared cookie domain was set keep the old
 * cookie for e.g. a.example.org next to the new one for .example.org and send
 * both. PHP takes the first, usually the old one, so the sign-in state stored
 * on the subdomain is missing on the central login host ("Invalid session
 * state"). Expiring the cookie without a Domain attribute only removes the
 * host-only one.
 *
 * A browser with only the old host-only cookie never gets a new one (the
 * session already exists), so the session cookie is sent once more with the
 * shared domain, same value. The next request then carries both, and the
 * host-only one is expired as above.
 */
class SessionCookieCleanupMiddleware implements HTTPMiddleware
{
    /**
     * Session key noting the domain the session cookie was last sent for.
     */
    public const SESSION_KEY = 'SharedLogin.cookieDomain';

    public function process(HTTPRequest $request, callable $delegate)
    {
        $response = $delegate($request);
        $domain = (string) Config::inst()->get(Session::class, 'cookie_domain');
        if ($domain === '') {
            return $response;
        }
        if (static::hasDuplicate($request)) {
            static::expireHostOnly($request);
        } elseif ($request->hasSession() && ($id = static::sessionCookie($request)) !== null) {
            $session = $request->getSession();
            if ($session->isStarted() && $session->get(self::SESSION_KEY) !== $domain) {
                static::resendWithDomain($request, $id, $domain);
                $session->set(self::SESSION_KEY, $domain);
            }
        }
        return $response;
    }

    /**
     * The value of the session cookie, if the request carries exactly one.
     */
    public static function sessionCookie(HTTPRequest $request): ?string
    {
        $header = (string) ($request->getHeader('Cookie') ?: ($_SERVER['HTTP_COOKIE'] ?? ''));
        $name = preg_quote(session_name() ?: 'PHPSESSID', '/');
        return preg_match_all('/(?:^|;)\s*' . $name . '=([^;]*)/', $header, $match) === 1
            ? trim($match[1][0])
            : null;
    }

    /**
     * Whether the request carries the session cookie more than once.
     */
    public static function hasDuplicate(HTTPRequest $request): bool
    {
        $header = (string) ($request->getHeader('Cookie') ?: ($_SERVER['HTTP_COOKIE'] ?? ''));
        $name = preg_quote(session_name() ?: 'PHPSESSID', '/');
        return preg_match_all('/(?:^|;)\s*' . $name . '=/', $header) > 1;
    }

    protected static function resendWithDomain(HTTPRequest $request, string $id, string $domain): void
    {
        if (headers_sent() || $id === '') {
            return;
        }
        $timeout = (int) Config::inst()->get(Session::class, 'timeout');
        setcookie(session_name() ?: 'PHPSESSID', $id, [
            'expires' => $timeout > 0 ? time() + $timeout : 0,
            'path' => Config::inst()->get(Session::class, 'cookie_path') ?: '/',
            'domain' => $domain,
            'secure' => $request->getScheme() === 'https',
            'httponly' => true,
            'samesite' => (string) (Config::inst()->get(Session::class, 'cookie_samesite') ?: 'Lax'),
        ]);
    }

    protected static function expireHostOnly(HTTPRequest $request): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie(session_name() ?: 'PHPSESSID', '', [
            'expires' => 1,
            'path' => Config::inst()->get(Session::class, 'cookie_path') ?: '/',
            'secure' => $request->getScheme() === 'https',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
