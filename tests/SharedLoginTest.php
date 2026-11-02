<?php

declare(strict_types=1);

namespace Atwx\SharedLogin\Tests;

use Atwx\SharedLogin\Control\SharedDomainCookieJar;
use Atwx\SharedLogin\Middleware\SessionCookieCleanupMiddleware;
use Atwx\SharedLogin\SharedLogin;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Cookie_Backend;
use SilverStripe\Control\Session;
use SilverStripe\Core\Injector\Injector;
use Atwx\SharedLogin\OAuth\ExistingMemberLoginHandler;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;

class SharedLoginTest extends SapphireTest
{
    private string $env = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->env = (string) SharedLogin::config()->get('cookie_domain_env');
    }

    protected function tearDown(): void
    {
        Environment::setEnv($this->env, '');
        parent::tearDown();
    }

    public function testSubdomainsOfTheSharedDomainCountAsThisSite(): void
    {
        Environment::setEnv($this->env, '');
        $this->assertFalse(Director::is_site_url('https://a.example.org/x'));
        Environment::setEnv($this->env, '.example.org');
        $this->assertTrue(Director::is_site_url('https://a.example.org/x'));
        $this->assertTrue(Director::is_site_url('https://example.org/'));
        $this->assertFalse(Director::is_site_url('https://example.org.evil.test/'));
        $this->assertFalse(Director::is_site_url('https://evilexample.org/'));
        $this->assertFalse(Director::is_site_url('javascript://a.example.org/%0aalert(1)'));
    }

    public function testSessionCookieIsLax(): void
    {
        $this->assertSame('Lax', Session::config()->get('cookie_samesite'));
    }

    public function testDetectsALeftoverHostOnlySessionCookie(): void
    {
        $name = session_name() ?: 'PHPSESSID';
        $request = new HTTPRequest('GET', '/');
        $request->addHeader('Cookie', "{$name}=old; foo=bar; {$name}=new");
        $this->assertTrue(SessionCookieCleanupMiddleware::hasDuplicate($request));
        $request = new HTTPRequest('GET', '/');
        $request->addHeader('Cookie', "X{$name}=a; {$name}=new");
        $this->assertFalse(SessionCookieCleanupMiddleware::hasDuplicate($request));
    }

    public function testCookiesFollowTheSharedDomain(): void
    {
        $this->assertSame(
            SharedDomainCookieJar::class,
            \SilverStripe\Core\Config\Config::inst()->get(Injector::class, Cookie_Backend::class)['class']
        );
        Environment::setEnv($this->env, '');
        $this->assertNull(SharedDomainCookieJar::domainFor(null));
        Environment::setEnv($this->env, '.example.org');
        $this->assertSame('.example.org', SharedDomainCookieJar::domainFor(null));
        $this->assertSame('a.example.org', SharedDomainCookieJar::domainFor('a.example.org'), 'explicit domains stay');
    }

    public function testExternalLoginsAreRemembered(): void
    {
        $this->assertTrue(ExistingMemberLoginHandler::config()->get('remember_login'));
    }

    public function testReadsASingleSessionCookie(): void
    {
        $name = session_name() ?: 'PHPSESSID';
        $request = new HTTPRequest('GET', '/');
        $request->addHeader('Cookie', "foo=bar; {$name}=abc123");
        $this->assertSame('abc123', SessionCookieCleanupMiddleware::sessionCookie($request));
        $request = new HTTPRequest('GET', '/');
        $request->addHeader('Cookie', "{$name}=a; {$name}=b");
        $this->assertNull(SessionCookieCleanupMiddleware::sessionCookie($request), 'duplicates are handled apart');
    }
}
