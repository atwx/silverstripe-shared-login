<?php

declare(strict_types=1);

namespace Atwx\SharedLogin\Tests;

use Atwx\SharedLogin\OAuth\ExternalAccounts;
use Atwx\SharedLogin\OAuth\ExternalIdentity;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

class ExternalAccountsTest extends SapphireTest
{
    protected $usesDatabase = true;

    private function member(string $email): Member
    {
        $member = Member::create(['Email' => $email, 'FirstName' => 'Test']);
        $member->write();
        return $member;
    }

    public function testLinkedAccountsSignInWhateverTheirEmail(): void
    {
        $accounts = ExternalAccounts::singleton();
        $andy = $this->member('andy@example.org');
        $google = new ExternalIdentity('Google', 'g-1', 'andy.private@gmail.example', true);

        $this->assertNull($accounts->memberFor($google), 'unknown address, not linked');
        $accounts->link($andy, $google);
        $this->assertSame((int) $andy->ID, (int) $accounts->memberFor($google)?->ID);
        $this->assertSame(['andy.private@gmail.example'], $accounts->accountsOf($andy)->column('Email'));

        // An unverified address never matches an account.
        $this->assertNull($accounts->memberFor(new ExternalIdentity('Google', 'g-2', 'andy@example.org', false)));
        // A verified one does, and is linked from then on.
        $this->assertSame(
            (int) $andy->ID,
            (int) $accounts->memberFor(new ExternalIdentity('Google', 'g-3', 'andy@example.org', true))?->ID
        );
        $this->assertCount(2, $accounts->accountsOf($andy));

        $id = (int) $accounts->accountsOf($andy)->first()->ID;
        $bea = $this->member('bea@example.org');
        $this->assertFalse($accounts->disconnect($bea, $id), 'only your own');
        $this->assertTrue($accounts->disconnect($andy, $id));
        $this->assertNull($accounts->memberFor($google));
    }

    public function testAnAccountBelongsToOneMember(): void
    {
        $accounts = ExternalAccounts::singleton();
        $google = new ExternalIdentity('Google', 'g-1', 'shared@gmail.example', true);
        $accounts->link($this->member('andy@example.org'), $google);
        $this->expectException(ValidationException::class);
        $accounts->link($this->member('bea@example.org'), $google);
    }
}
