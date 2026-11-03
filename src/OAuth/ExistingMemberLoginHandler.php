<?php

declare(strict_types=1);

namespace Atwx\SharedLogin\OAuth;

use Bigfork\SilverStripeOAuth\Client\Handler\LoginTokenHandler;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\IdentityStore;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * Login with Google & co. only for people who already have an account: the
 * external account must be linked to a member (see ConnectTokenHandler), or
 * its verified email must belong to one, who is then linked for the next
 * time. Nobody gets an account by signing in.
 *
 * The login is persistent ("keep me signed in") unless remember_login is
 * off: people stay signed in with the provider anyway, so asking them again
 * every day only costs a click and confuses.
 */
class ExistingMemberLoginHandler extends LoginTokenHandler
{
    use Configurable;

    private static bool $remember_login = true;

    public function handleToken(AccessToken $token, AbstractProvider $provider)
    {
        $member = $this->findMember($token, $provider);
        if (!$member) {
            return Security::permissionFailure(null, _t(
                self::class . '.NO_ACCOUNT',
                'This account is not connected to a login. If you have one with another email address, sign in there and connect this account in your profile; otherwise ask your organisation for an invitation.'
            ));
        }
        $result = $member->validateCanLogin();
        if (!$result->isValid()) {
            return Security::permissionFailure(null, implode('; ', array_column($result->getMessages(), 'message')));
        }
        $request = Controller::curr()?->getRequest();
        Injector::inst()->get(IdentityStore::class)
            ->logIn($member, (bool) static::config()->get('remember_login'), $request);
        return null;
    }

    protected function findOrCreateMember(AccessToken $token, AbstractProvider $provider)
    {
        return $this->findMember($token, $provider);
    }

    /**
     * The linked member, else the member with the provider's verified email
     * (then linked), else null.
     */
    protected function findMember(AccessToken $token, AbstractProvider $provider): ?Member
    {
        return ExternalAccounts::singleton()->memberFor(ExternalIdentity::fromToken($token, $provider));
    }
}
