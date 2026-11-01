# silverstripe-shared-login

One login for a Silverstripe 6 app that serves several subdomains (e.g. one per
organisation/tenant):

- **Shared session:** with a cookie domain like `.example.org`, signing in once
  counts on every subdomain. Subdomains of that domain count as this site, so
  `BackURL`s to them are allowed. A middleware removes host-only session
  cookies left from before the shared domain was set.
- **Central login host:** external providers call back on one host only
  (e.g. `https://login.example.org/oauth/callback/`); afterwards people land on
  the subdomain they started from.
- **External sign-in for existing members only** (with
  [`bigfork/silverstripe-oauth-login`](https://github.com/bigfork/silverstripe-oauth-login)):
  the provider's verified email must belong to a member, who is then linked to
  the provider account. Nobody gets an account by signing in.
- **Google** out of the box when `league/oauth2-google` is installed and
  `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` are set; otherwise only the
  password form is offered.
- Session cookie `SameSite=Lax` (Strict would drop it on the provider's redirect back).

Without the environment variables the app behaves as a single host.

## Environment

```
SHARED_LOGIN_COOKIE_DOMAIN=".example.org"
SHARED_LOGIN_OAUTH_CALLBACK_URL="https://login.example.org/oauth/callback/"
GOOGLE_CLIENT_ID="….apps.googleusercontent.com"
GOOGLE_CLIENT_SECRET="…"
```

Keep existing variable names:

```yaml
Atwx\SharedLogin\SharedLogin:
  cookie_domain_env: 'MYAPP_COOKIE_DOMAIN'
  oauth_callback_url_env: 'MYAPP_OAUTH_CALLBACK_URL'
```

Google Cloud Console → Credentials → OAuth client ID (web application) with the
redirect URI of the login host. On the server, one site for `example.org` and
`*.example.org` (wildcard certificate). If `SS_ALLOWED_HOSTS` is set, it must
list every host.

Other providers work the same way: their league/oauth2 package plus a provider
entry for `Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory` and
`Bigfork\SilverStripeOAuth\Client\Authenticator\Authenticator`.
