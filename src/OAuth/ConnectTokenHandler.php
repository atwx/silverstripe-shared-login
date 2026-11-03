<?php

declare(strict_types=1);

namespace Atwx\SharedLogin\OAuth;

use Bigfork\SilverStripeOAuth\Client\Handler\TokenHandler;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Security\Security;

/**
 * "Connect my Google account": links the external account to the member
 * who is signed in, whatever its email. Afterwards it signs that member in.
 * The outcome is left as a message in the session (ExternalAccounts::popMessage).
 */
class ConnectTokenHandler implements TokenHandler
{
    public function handleToken(AccessToken $token, AbstractProvider $provider)
    {
        $session = Controller::curr()?->getRequest()->getSession();
        if (!$session) {
            return null;
        }
        $member = Security::getCurrentUser();
        if (!$member) {
            ExternalAccounts::flash($session, _t(self::class . '.SIGN_IN_FIRST', 'Please sign in first.'), 'bad');
            return null;
        }
        $identity = ExternalIdentity::fromToken($token, $provider);
        try {
            ExternalAccounts::singleton()->link($member, $identity);
            ExternalAccounts::flash($session, _t(
                self::class . '.CONNECTED',
                'Connected with {provider} ({email}). You can now sign in with it.',
                ['provider' => $identity->provider, 'email' => $identity->email ?: '–']
            ));
        } catch (ValidationException $e) {
            ExternalAccounts::flash($session, $e->getMessage(), 'bad');
        }
        return null;
    }
}
