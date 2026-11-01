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
 */
class SessionCookieCleanupMiddleware implements HTTPMiddleware
{
    public function process(HTTPRequest $request, callable $delegate)
    {
        $response = $delegate($request);
        if (Config::inst()->get(Session::class, 'cookie_domain') && static::hasDuplicate($request)) {
            static::expireHostOnly($request);
        }
        return $response;
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
