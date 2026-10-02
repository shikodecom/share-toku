# Operations

## Production configuration

Use HTTPS for Laravel and WordPress. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` to the public HTTPS URL, `SESSION_SECURE_COOKIE=true`, and secure MySQL credentials. Store `APP_KEY`, DB credentials, and mail credentials in a secret manager. Configure `SHARETOKU_OPERATOR_WORKSPACE_PUBLIC_ID` after creating the operator Workspace. Keep `SHARETOKU_OPERATOR_OFFERS_ENABLED=false` until consent and release checks are complete.

Run `php artisan migrate --force` during deployment. Run Laravel's scheduler every minute, e.g. `* * * * * php /path/to/artisan schedule:run`. Monitor scheduler failures and API 5xx/429 rates. Review logs without logging raw bearer tokens, authorization codes, referral codes, or URL query strings.

## Backup and restore

Back up MySQL daily with encryption and off-site retention. Test a restore into an isolated environment at least monthly: restore the SQL dump, apply migrations if appropriate, verify `/health`, member login, a scoped Site API request, and a sample Offer. Never restore a production Site Token into a public test environment.

## Revocation and emergency stop

- Site Token: Workspace administrator invokes `/workspaces/{workspace}/sites/{site}/revoke`, then reconnects the plugin if needed. Domain change and Site suspension automatically revoke existing tokens.
- Offer: system admin suspends the Offer with a recorded reason. It leaves distribution queries immediately; WordPress transient cache can persist for up to configured TTL.
- Program: change its public/external policy to `suspended` or set `is_active=false`; query checks apply immediately to new resolves.
- Site: system admin suspends the Site; token authentication fails.
- All operator offers: set `SHARETOKU_OPERATOR_OFFERS_ENABLED=false` and reload configuration. Owner offers remain available.
- Secret rotation: rotate `APP_KEY` with a planned session invalidation and event token expiry window, then revoke and reconnect Site Tokens if token material may have leaked.

## Retention

`analytics:prune` removes raw analytics events after `SHARETOKU_ANALYTICS_RETENTION_DAYS` (default 90). Daily aggregate metrics can be kept longer. Set application and reverse proxy log retention according to the published privacy policy. Access to analytics should be limited to the owning Workspace and system operators.
