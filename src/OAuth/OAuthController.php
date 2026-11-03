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
 * login host. A BackURL passed with the link wins over one left in the
 * session by an earlier sign-in.
 */
class OAuthController extends Controller
{
    protected function findBackUrl(HTTPRequest $request)
    {
        $given = (string) $request->getVar('BackURL');
        if ($given !== '' && Director::is_site_url($given)) {
            return Director::absoluteURL($given);
        }
        return Director::absoluteURL((string) parent::findBackUrl($request));
    }
}
