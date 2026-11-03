<?php

declare(strict_types=1);

namespace Atwx\SharedLogin\OAuth;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use SilverStripe\Control\Controller;

/**
 * Who someone is at an external provider (Google & co.): the provider's ID
 * for the person, their email and whether the provider checked it, and the
 * name for a new account.
 */
final class ExternalIdentity
{
    public function __construct(
        public readonly string $provider,
        public readonly string $id,
        public readonly string $email = '',
        public readonly bool $verified = false,
        public readonly string $firstName = '',
        public readonly string $surname = '',
    ) {
    }

    /**
     * Reads the identity behind an access token. The provider name is the one
     * the sign-in was started with (kept in the session by the OAuth module).
     */
    public static function fromToken(AccessToken $token, AbstractProvider $provider, string $providerName = ''): self
    {
        $owner = $provider->getResourceOwner($token);
        $data = $owner->toArray();
        if ($providerName === '') {
            $request = Controller::curr()?->getRequest();
            $session = $request?->hasSession() ? $request->getSession() : null;
            $class = new \ReflectionClass($provider);
            $providerName = (string) ($session?->get('oauth2.provider')
                ?: ($class->isAnonymous() ? 'OAuth' : $class->getShortName()));
        }
        // Google says whether it checked the address; providers that don't say are trusted as configured.
        $verified = !array_key_exists('email_verified', $data)
            || filter_var($data['email_verified'], FILTER_VALIDATE_BOOLEAN);
        return new self(
            $providerName,
            (string) $owner->getId(),
            strtolower(trim((string) ($data['email'] ?? ''))),
            $verified,
            trim((string) ($data['given_name'] ?? $data['first_name'] ?? '')),
            trim((string) ($data['family_name'] ?? $data['last_name'] ?? '')),
        );
    }

    /**
     * The email, if the provider vouches for it.
     */
    public function verifiedEmail(): string
    {
        return $this->verified ? $this->email : '';
    }
}
