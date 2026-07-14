# VeriFact WordPress 3.5 Installation

This guide installs the VeriFact 3.5 WordPress plugin, connects it to the hosted or licensed local API, configures durable processing, and verifies the inline-citation workflow.

## Requirements

- WordPress 6.2 or newer
- PHP 8.1 through 8.4
- HTTPS for the WordPress site and API
- Administrator access
- WP-CLI for the command-line installation and reliable queue runner
- A VeriFact subscription or approved managed API credential
- OpenClaw Vault or another approved server-side secret store

Do not paste an API key into WordPress options, source files, Git, shell history, or the downloadable plugin package.

## 1. Build the plugin package

Run from `C:\Users\disru\Documents\Verifact\Verifact Wordpress`:

```powershell
php tests\structural.php
php tests\contract.php
npm run check
powershell -ExecutionPolicy Bypass -File .\tools\build-plugin.ps1
Get-FileHash -Algorithm SHA256 .\dist\verifact-3.5.0.zip
```

The installable package is `dist\verifact-3.5.0.zip` and contains a top-level `verifact` plugin directory.

## 2A. Install through WordPress Admin

1. Back up the WordPress database and `wp-content/plugins/verifact` if it already exists.
2. Open **Plugins → Add New Plugin → Upload Plugin**.
3. Select `verifact-3.5.0.zip`.
4. Select **Install Now**, then **Activate Plugin**.
5. Confirm **VeriFact** appears in the administrator menu.

## 2B. Install with WP-CLI

Set the verified WordPress path and package path:

```bash
export WP_PATH=/absolute/path/to/wordpress
export VERIFACT_ZIP=/absolute/path/to/verifact-3.5.0.zip
cd "$WP_PATH"
```

Back up an existing plugin before replacement:

```bash
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
if [ -d wp-content/plugins/verifact ]; then
  mkdir -p wp-content/plugins/verifact/backup-files
  tar --exclude='./backup-files' -C wp-content/plugins/verifact -czf wp-content/plugins/verifact/backup-files/verifact-files.backup.$STAMP.tar.gz .
fi
```

Install and activate:

```bash
wp plugin install "$VERIFACT_ZIP" --force --activate
wp plugin get verifact --fields=name,status,version
```

Expected version: `3.5.0`.

For multisite network activation:

```bash
wp plugin activate verifact --network
wp site list --fields=blog_id,url
```

## 3. Configure the API connection

### Hosted service

Use this verified API base URL:

```text
https://veracityintegrity.com/verifact-api
```

Open **VeriFact → Subscription & License** and complete hosted checkout or activate an existing license. Card data is handled by the hosted billing provider, not WordPress.

### Managed Vault credential

Map these server environment names from OpenClaw Vault:

| WordPress environment name | Safe mapping |
|---|---|
| `VERIFACT_API_BASE_URL` | `https://veracityintegrity.com/verifact-api` or the approved local API URL |
| `VERIFACT_API_KEY` | `VAULT_REF_VERIFACT_API_KEY` |
| `VERIFACT_API_KEY_ID` | safe key identifier |
| `VERIFACT_API_KEY_ROTATED_AT` | safe ISO-8601 rotation timestamp |

The plugin reads `VERIFACT_API_BASE_URL` and `VERIFACT_API_KEY` from server constants or environment variables. Do not put a literal secret in `wp-config.php`.

If the host injects environment variables into PHP, the safe `wp-config.php` mapping is:

```php
define('VERIFACT_API_BASE_URL', getenv('VERIFACT_API_BASE_URL') ?: '');
define('VERIFACT_API_KEY', getenv('VERIFACT_API_KEY') ?: '');
define('VERIFACT_ALLOWED_API_HOSTS', ['veracityintegrity.com']);
```

For a licensed local API, replace the allowlist hostname with the verified private/Tailscale hostname. Do not allow arbitrary outbound hosts.

## 4. Complete onboarding

1. Open **VeriFact → Settings**.
2. Confirm the API base URL and allowed host.
3. Choose the supported post types.
4. Set maximum claims and minimum confidence.
5. Choose publication gate behavior: Off, Warn, or Block.
6. Enable compact inline citations and hover/focus notes.
7. Configure retention and privacy controls.
8. Open **VeriFact → API Management** and select **Test authenticated connection**.

A successful connection reports the server version, API contract, authentication type, and safe key identifier without exposing the secret.

## 5. Configure durable processing

Action Scheduler is used automatically when available. WP-Cron remains a fallback. For production, use system cron to run the queue every minute.

Find the absolute WP-CLI path:

```bash
command -v wp
```

Add the cron entry with the actual WordPress path and a log path outside the public web root:

```cron
* * * * * cd /absolute/path/to/wordpress && /absolute/path/to/wp verifact queue run >> /absolute/private/log/verifact-queue.log 2>&1
```

Verify manually:

```bash
cd "$WP_PATH"
wp verifact queue run
wp cron event list | grep verifact
```

## 6. Test the editorial workflow

1. Create a draft containing at least one externally verifiable factual claim.
2. Open the Gutenberg VeriFact sidebar or the Classic Editor VeriFact panel.
3. Queue verification.
4. Run `wp verifact queue run` if the result is not processed immediately.
5. Review the claim, stance, confidence, and source before approval.
6. Preview the post.
7. Confirm each approved source appears as a compact link beside the related sentence.
8. Confirm the link tooltip appears on mouse hover and keyboard focus.
9. Confirm source links open in a new tab and include `noopener noreferrer external`.
10. Confirm no separate Fact/Commentary report block is inserted.

## 7. Verify plugin health

```bash
cd "$WP_PATH"
wp plugin get verifact --fields=name,status,version
wp verifact queue run
wp eval 'echo class_exists("VeriFact_Plugin") && VeriFact_Plugin::VERSION === "3.5.0" ? "VeriFact 3.5 OK\n" : "VeriFact validation failed\n";'
```

Also review **Tools → Site Health** and the VeriFact diagnostics/support bundle. The support bundle is redacted and must not contain content or credentials.

## Upgrade

1. Back up the database and current `wp-content/plugins/verifact` directory.
2. Build or obtain the new signed ZIP.
3. Run `wp plugin install /absolute/path/to/new-verifact.zip --force`.
4. Run `wp plugin activate verifact`.
5. Confirm the version and run the queue once.
6. Test an unpublished draft before using a blocking publication gate.

## Rollback

Deactivate the new version, restore the timestamped plugin backup, and reactivate:

```bash
cd "$WP_PATH"
wp plugin deactivate verifact
mv wp-content/plugins/verifact wp-content/plugins/verifact-3.5-failed
mkdir -p wp-content/plugins/verifact
tar -xzf wp-content/plugins/verifact-3.5-failed/backup-files/verifact-files.backup.TIMESTAMP.tar.gz -C wp-content/plugins/verifact
wp plugin activate verifact
wp plugin get verifact --fields=name,status,version
```

Restore the database only when a verified schema rollback requires it. Never delete verification history or license state without a separate confirmed backup.
