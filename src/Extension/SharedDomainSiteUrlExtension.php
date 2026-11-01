<?php

declare(strict_types=1);

namespace Atwx\SharedLogin\Extension;

use Atwx\SharedLogin\SharedLogin;
use SilverStripe\Core\Extension;

/**
 * With a shared cookie domain every subdomain counts as this site, so a login
 * on the central login host may send people back to the subdomain they came
 * from (BackURL checks use Director::is_site_url()).
 */
class SharedDomainSiteUrlExtension extends Extension
{
    protected function updateIsSiteUrl(bool &$isSiteUrl, string $url): void
    {
        if (SharedLogin::isSharedDomainUrl($url)) {
            $isSiteUrl = true;
        }
    }
}
