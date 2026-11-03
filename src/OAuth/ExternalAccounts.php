<?php

declare(strict_types=1);

namespace Atwx\SharedLogin\OAuth;

use Bigfork\SilverStripeOAuth\Client\Authenticator\Authenticator;
use Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory;
use Bigfork\SilverStripeOAuth\Client\Helper\Helper;
use Bigfork\SilverStripeOAuth\Client\Model\Passport;
use SilverStripe\Control\HTTP;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\ArrayData;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Member;

/**
 * External accounts (Google & co.) linked to members, as "passports" of the
 * OAuth module: one member can have several, one external account belongs
 * to one member. A linked account signs in its member whatever its email.
 */
class ExternalAccounts
{
    use Injectable;

    /** OAuth context of "connect an external account to my account". */
    public const CONTEXT_CONNECT = 'connect';

    /** Session key of the message shown after connecting. */
    public const MESSAGE_KEY = 'SharedLogin.message';

    /**
     * Names of the configured providers (e.g. "Google").
     *
     * @return list<string>
     */
    public function providers(): array
    {
        if (!class_exists(ProviderFactory::class)) {
            return [];
        }
        return array_map('strval', array_keys(Injector::inst()->get(ProviderFactory::class)->getProviders()));
    }

    /**
     * Link that starts the OAuth flow at a provider in a context and comes
     * back to $backUrl afterwards.
     */
    public function authoriseLink(string $provider, string $context, string $backUrl): string
    {
        $scopes = Config::inst()->get(Authenticator::class, 'providers')[$provider]['scopes'] ?? [];
        return HTTP::setGetVar('BackURL', $backUrl, Helper::buildAuthorisationUrl($provider, $context, $scopes));
    }

    /**
     * The linked member, else the member with the provider's verified email
     * (then linked), else null.
     */
    public function memberFor(ExternalIdentity $identity): ?Member
    {
        $passport = $this->passportOf($identity);
        if ($passport && $passport->Member()->exists()) {
            $this->remember($passport, $identity);
            return $passport->Member();
        }
        $email = $identity->verifiedEmail();
        if ($email === '') {
            return null;
        }
        $member = Member::get()->filter('Email', $email)->first();
        if (!$member instanceof Member) {
            return null;
        }
        $this->link($member, $identity);
        return $member;
    }

    /**
     * Links an external account to a member.
     *
     * @throws ValidationException when it already belongs to someone else
     */
    public function link(Member $member, ExternalIdentity $identity): Passport
    {
        $passport = $this->passportOf($identity);
        if ($passport && $passport->Member()->exists() && (int) $passport->MemberID !== (int) $member->ID) {
            throw ValidationException::create(_t(
                self::class . '.TAKEN',
                'This {provider} account is already connected to another account.',
                ['provider' => $identity->provider]
            ));
        }
        $passport ??= Passport::create(['Identifier' => $identity->id]);
        $passport->MemberID = (int) $member->ID;
        return $this->remember($passport, $identity);
    }

    /**
     * The external accounts of a member, for display.
     */
    public function accountsOf(Member $member): ArrayList
    {
        $list = ArrayList::create();
        foreach (Passport::get()->filter('MemberID', (int) $member->ID)->sort('ID') as $passport) {
            $list->push(ArrayData::create([
                'ID' => $passport->ID,
                'Provider' => $passport->getField('Provider') ?: 'Google',
                'Email' => (string) $passport->getField('Email'),
            ]));
        }
        return $list;
    }

    /**
     * Removes one of the member's external accounts.
     */
    public function disconnect(Member $member, int $id): bool
    {
        $passport = Passport::get()->filter(['ID' => $id, 'MemberID' => (int) $member->ID])->first();
        if (!$passport instanceof Passport) {
            return false;
        }
        $passport->delete();
        return true;
    }

    /**
     * @param 'good'|'bad' $type
     */
    public static function flash(Session $session, string $text, string $type = 'good'): void
    {
        $session->set(self::MESSAGE_KEY, ['Text' => $text, 'Type' => $type]);
    }

    /**
     * The message left by the last connect, once.
     *
     * @return array{Text: string, Type: string}|null
     */
    public static function popMessage(Session $session): ?array
    {
        $message = $session->get(self::MESSAGE_KEY);
        $session->clear(self::MESSAGE_KEY);
        return is_array($message) ? $message : null;
    }

    private function passportOf(ExternalIdentity $identity): ?Passport
    {
        if ($identity->id === '') {
            return null;
        }
        /** @var DataList<Passport> $passports */
        $passports = Passport::get()->filter('Identifier', $identity->id);
        $passport = $passports->first();
        return $passport instanceof Passport ? $passport : null;
    }

    private function remember(Passport $passport, ExternalIdentity $identity): Passport
    {
        $passport->setField('Provider', $identity->provider);
        if ($identity->email !== '') {
            $passport->setField('Email', $identity->email);
        }
        if ($passport->isChanged()) {
            $passport->write();
        }
        return $passport;
    }
}
