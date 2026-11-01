<?php

declare(strict_types=1);

namespace Atwx\SharedLogin\OAuth;

use Bigfork\SilverStripeOAuth\Client\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;

/**
 * The OAuth controller with an absolute return URL: the sign-in starts on a
 * subdomain, the provider calls back on the central login host, and
 * afterwards people land on the subdomain they came from instead of the
 * login host.
 */
class OAuthController extends Controller
{
    protected function findBackUrl(HTTPRequest $request)
    {
        return Director::absoluteURL((string) parent::findBackUrl($request));
    }
}
