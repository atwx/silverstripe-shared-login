<?php

declare(strict_types=1);

namespace Atwx\SharedLogin\Control;

use Atwx\SharedLogin\SharedLogin;
use SilverStripe\Control\CookieJar;

/**
 * Cookies without an explicit domain go to the shared cookie domain, like the
 * session. Otherwise the "keep me signed in" cookies set on the central login
 * host would not reach the subdomains, and vice versa.
 */
class SharedDomainCookieJar extends CookieJar
{
    public static function domainFor(?string $domain): ?string
    {
        return $domain ?? (SharedLogin::cookieDomain() ?: null);
    }

    protected function outputCookie(
        string $name,
        string|false $value,
        int $expiry,
        ?string $path = null,
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = ''
    ): bool {
        $domain = static::domainFor($domain);
        return parent::outputCookie($name, $value, $expiry, $path, $domain, $secure, $httpOnly, $sameSite);
    }
}
