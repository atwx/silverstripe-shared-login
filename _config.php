<?php

declare(strict_types=1);

use Atwx\SharedLogin\SharedLogin;

// Shared session cookie and central OAuth callback, from the environment.
SharedLogin::boot();
