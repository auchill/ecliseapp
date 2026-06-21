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
MOBILESENTRIX_SYNC_ENABLED=false
```

Do not commit `.env`.

## Migrations

Run migrations before authenticating:

```bash
php artisan migrate
```

The relevant table is `mobilesentrix_api_settings`. The app encrypts `consumer_key`, `consumer_secret`, `access_token`, and `access_token_secret` through Laravel encrypted model casts.

## Authenticate From Admin

1. Sign in as an admin.
2. Open `/admin/parts/mobilesentrix`.
3. Click `Start Live Authentication`.
4. Complete any MobileSentrix browser login or authorization prompt.
5. MobileSentrix should redirect back to `/admin/parts/mobilesentrix/callback`.
6. The callback exchanges `oauth_token` and `oauth_verifier` for `access_token` and `access_token_secret`.
7. Return to `/admin/parts/mobilesentrix` and confirm the status fields show:
   - Consumer Name configured: Yes
   - Consumer Key configured: Yes
   - Consumer Secret configured: Yes
   - Access Token configured: Yes
   - Access Token Secret configured: Yes

Use `Re-authenticate` on the same page when tokens need to be rotated.

## Authenticate From CLI

Run:

```bash
php artisan mobilesentrix:authenticate
```

If MobileSentrix returns temporary OAuth credentials directly, the command exchanges them and stores the encrypted access tokens.

If MobileSentrix requires browser authorization, use the admin button. If you already have the full callback URL containing `oauth_token` and `oauth_verifier`, you can complete the exchange from CLI:

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
MobileSentrix is not authenticated yet. Run php artisan mobilesentrix:authenticate or use the admin authentication button.
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
