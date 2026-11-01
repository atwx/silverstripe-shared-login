<?php

declare(strict_types=1);

namespace Atwx\SharedLogin\Tests;

use Atwx\SharedLogin\Middleware\SessionCookieCleanupMiddleware;
use Atwx\SharedLogin\SharedLogin;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
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
}
