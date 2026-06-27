# MobileSentrix Authentication

This app uses MobileSentrix OAuth credentials to sync parts categories and products into the local catalog. Long-lived MobileSentrix secrets are stored encrypted in the `mobilesentrix_api_settings` table.

## Required Environment Values

Set these values in `.env`:

```dotenv
MOBILESENTRIX_ENV=staging
MOBILESENTRIX_BASE_URL=https://preprod.mobilesentrix.ca
MOBILESENTRIX_CONSUMER_NAME=
MOBILESENTRIX_CONSUMER_KEY=
MOBILESENTRIX_CONSUMER_SECRET=
MOBILESENTRIX_CALLBACK_URL=http://127.0.0.1:8000/admin/parts/mobilesentrix/callback
MOBILESENTRIX_ALLOW_BROWSER_SECRET_REDIRECT=false
MOBILESENTRIX_AUTH_TRANSPORT=oauth_header
MOBILESENTRIX_SYNC_ENABLED=false
```

Do not commit `.env`.

After changing these values, clear cached config:

```bash
php artisan config:clear
php artisan cache:clear
```

## Migrations

Run migrations before authenticating:

```bash
php artisan migrate
```

The relevant table is `mobilesentrix_api_settings`. The app encrypts `consumer_key`, `consumer_secret`, `access_token`, and `access_token_secret` through Laravel encrypted model casts.

## Authenticate From Admin

1. Sign in as an admin.
2. Open `/admin/parts/mobilesentrix`.
3. Click `Authenticate Server-Side`.
4. The Laravel backend calls the MobileSentrix OAuth identifier endpoint.
5. If MobileSentrix returns `oauth_token` and `oauth_verifier`, the app exchanges them for `access_token` and `access_token_secret`.
6. If MobileSentrix requires browser authorization, the app stops and shows safe guidance instead of exposing credentials in the browser URL.
7. Return to `/admin/parts/mobilesentrix` and confirm the status fields show:
   - Consumer Name configured: Yes
   - Consumer Key configured: Yes
   - Consumer Secret configured: Yes
   - Access Token configured: Yes
   - Access Token Secret configured: Yes

Use `Re-authenticate Server-Side` on the same page when tokens need to be rotated.

The admin page also includes an OAuth preflight checklist and a safe support message. The support message intentionally excludes the Consumer Key, Consumer Secret, Access Token, and Access Token Secret.

## Security Note About Browser OAuth

The MobileSentrix browser redirect method may expose `consumer_key` and `consumer_secret` in the browser address bar because the identifier URL uses query parameters.

Eclise disables browser redirects containing secrets by default:

```dotenv
MOBILESENTRIX_ALLOW_BROWSER_SECRET_REDIRECT=false
```

Use server-side authentication first through the admin page or:

```bash
php artisan mobilesentrix:authenticate
```

If server-side authentication returns HTTP 403, contact MobileSentrix support using the safe support message on `/admin/parts/mobilesentrix`. Ask them to confirm the correct Canada preprod flow, whether backend-only authentication is supported, and whether the public/server IP must be whitelisted.

If Consumer Key or Consumer Secret values were exposed in screenshots, logs, browser history, or shared URLs, rotate/regenerate those credentials with MobileSentrix.

Only set `MOBILESENTRIX_ALLOW_BROWSER_SECRET_REDIRECT=true` if MobileSentrix confirms in writing that browser-based authentication with credentials in the query string is required.

## Authenticate From CLI

Run:

```bash
php artisan mobilesentrix:authenticate
```

If MobileSentrix returns temporary OAuth credentials directly, the command exchanges them and stores the encrypted access tokens.

If MobileSentrix requires browser authorization, contact MobileSentrix before enabling any browser redirect that exposes credentials. If you already have the full callback URL containing `oauth_token` and `oauth_verifier`, you can complete the exchange from CLI:

```bash
php artisan mobilesentrix:authenticate --callback-url="http://127.0.0.1:8000/admin/parts/mobilesentrix/callback?oauth_token=...&oauth_verifier=..."
```

The command masks final token values in console output.

## Test Connection

From admin, click `Test Live Connection`.

From CLI, run:

```bash
php artisan mobilesentrix:test-connection
```

This uses active encrypted credentials from `mobilesentrix_api_settings` and calls `/api/rest/categories`.

To test a specific protected API authorization format, run:

```bash
php artisan mobilesentrix:test-connection --auth-transport=oauth_header
php artisan mobilesentrix:test-connection --auth-transport=query_params
```

The default is:

```dotenv
MOBILESENTRIX_AUTH_TRANSPORT=oauth_header
```

`oauth_header` sends OAuth 1.0 PLAINTEXT values in the server-side Authorization header. `query_params` appends the Consumer Key, Consumer Secret, Access Token, and Access Token Secret to server-side API requests only. Do not expose either request format in browser URLs, logs, screenshots, or support messages.

For safe credential-source diagnostics, run:

```bash
php artisan mobilesentrix:debug-auth
```

This command prints only environment, base URL, Yes/No credential presence, the active DB settings row ID, last authentication timestamp, token source, and auth transport.

## Sync Commands

After authentication succeeds:

```bash
php artisan mobilesentrix:sync-categories
php artisan mobilesentrix:sync-parts
```

Optional category-limited sync:

```bash
php artisan mobilesentrix:sync-categories --category=165
php artisan mobilesentrix:sync-parts --category=165
```

Refresh one part:

```bash
php artisan mobilesentrix:refresh-part MS-9001
```

## Troubleshooting

If you see:

```text
MobileSentrix is not authenticated yet. Run php artisan mobilesentrix:authenticate or use the admin Authenticate Server-Side button.
```

then `access_token` and/or `access_token_secret` are missing from the active `mobilesentrix_api_settings` row. Re-run authentication.

If authentication says configuration is incomplete, confirm these `.env` values are set and clear cached config if needed:

```bash
php artisan config:clear
```

If the admin callback does not complete, verify `MOBILESENTRIX_CALLBACK_URL` exactly matches the callback URL registered with MobileSentrix and points to:

```text
/admin/parts/mobilesentrix/callback
```

For local development, the callback can be:

```text
http://127.0.0.1:8000/admin/parts/mobilesentrix/callback
```

If MobileSentrix does not allow `localhost` or `127.0.0.1` callbacks, use a public HTTPS staging domain or HTTPS tunnel and register that exact URL with MobileSentrix. The app accepts HTTPS callback URLs and common local development hosts.

If Cloudflare blocks the OAuth identifier URL with HTTP 403:

1. Confirm the correct Canada preprod base URL with MobileSentrix.
2. Confirm the Consumer Key and Consumer Secret are enabled for Canada preprod.
3. Confirm the callback URL is registered with MobileSentrix.
4. Ask MobileSentrix whether your server or public IP must be whitelisted.
5. Send MobileSentrix the Cloudflare Ray ID and blocked IP.
6. Rotate Consumer Key and Consumer Secret if they were exposed in screenshots or logs.

The app never logs or displays the full OAuth identifier URL because it contains sensitive query parameters.

## Troubleshooting HTTP 401 After OAuth Success

If OAuth succeeds and `php artisan mobilesentrix:test-connection` returns HTTP 401, the tokens exist but MobileSentrix rejected the protected API request authorization.

1. Run `php artisan mobilesentrix:debug-auth`.
2. Confirm access tokens exist in `mobilesentrix_api_settings`.
3. Confirm only one active row exists for the current `MOBILESENTRIX_ENV`.
4. Confirm the active row matches the current `MOBILESENTRIX_BASE_URL`.
5. Confirm the current `.env` Consumer Key and Consumer Secret match the credentials used during token generation.
6. Confirm `MOBILESENTRIX_BASE_URL` matches the environment where the token was generated. Canada preprod should use `https://preprod.mobilesentrix.ca`.
7. Try `php artisan mobilesentrix:test-connection --auth-transport=oauth_header`.
8. Try `php artisan mobilesentrix:test-connection --auth-transport=query_params`.
9. If both transports fail, contact MobileSentrix and ask them to confirm the API auth transport, OAuth PLAINTEXT signature format, credential rotation status, environment, and account enablement.

Do not send Consumer Key, Consumer Secret, Access Token, Access Token Secret, OAuth signature, Authorization header, or full signed request URLs to support. Send only the safe output from `mobilesentrix:debug-auth` and the HTTP status.
