<?php

declare(strict_types=1);

namespace Atwx\SharedLogin\Extension;

use SilverStripe\Core\Extension;

/**
 * Which provider a passport (linked external account) is from and its email
 * at the time of the last sign-in, so people can see what is connected.
 *
 * @extends Extension<\Bigfork\SilverStripeOAuth\Client\Model\Passport>
 */
class PassportExtension extends Extension
{
    private static array $db = [
        'Provider' => 'Varchar(50)',
        'Email' => 'Varchar(254)',
    ];

    private static array $indexes = [
        'Identifier' => true,
    ];
}
