<?php

declare(strict_types=1);

namespace Atwx\SharedLogin\OAuth;

use Bigfork\SilverStripeOAuth\Client\Handler\LoginTokenHandler;
use Bigfork\SilverStripeOAuth\Client\Model\Passport;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * Login with Google & co. only for people who already have an account: the
 * provider's verified email must belong to a member, who is then linked to
 * that provider account (a "passport") for the next time. Nobody gets an
 * account by signing in.
 */
class ExistingMemberLoginHandler extends LoginTokenHandler
{
    public function handleToken(AccessToken $token, AbstractProvider $provider)
    {
        $member = $this->findMember($token, $provider);
        if (!$member) {
            return Security::permissionFailure(null, _t(
                self::class . '.NO_ACCOUNT',
                'There is no account for this email address. Ask the owner of your organisation to add you.'
            ));
        }
        return parent::handleToken($token, $provider);
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
        $user = $provider->getResourceOwner($token);
        $passport = Passport::get()->filter('Identifier', (string) $user->getId())->first();
        if ($passport && $passport->Member()->exists()) {
            return $passport->Member();
        }
        $data = $user->toArray();
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        // Google says whether it checked the address; providers that don't say are trusted as configured.
        $verified = !array_key_exists('email_verified', $data)
            || filter_var($data['email_verified'], FILTER_VALIDATE_BOOLEAN);
        if ($email === '' || !$verified) {
            return null;
        }
        $member = Member::get()->filter('Email', $email)->first();
        if (!$member) {
            return null;
        }
        Passport::create(['Identifier' => (string) $user->getId(), 'MemberID' => $member->ID])->write();
        return $member;
    }
}
