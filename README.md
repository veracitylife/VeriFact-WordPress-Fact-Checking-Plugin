# Verifact — WordPress Plugin (GitHub Package)

This folder contains the uncompressed distribution for the latest version of the Verifact WordPress plugin.

## Latest Release
- Version: 2.0.5
- Package path: github/verifact-plugin-2.0.4/VeriFact-WordPress-Fact-Checking-Plugin
- No compression per project rules

## Install
- Upload the Verifact-WordPress-Fact-Checking-Plugin/verifact-plugin folder to wp-content/plugins/
- Activate in WordPress Admin → Plugins
- Configure Settings → VeriFact (API base, rate limit, cache, sources)

## Features
- Admin dashboard, analytics, logs, bulk tools
- REST route verifact/v1/check proxy to FastAPI
- Database logging wp_verifact_logs
- Rate limiting (role overrides) and caching (object cache friendly)
- WP-Cron scheduled checks infrastructure
- Optional sources: Archive.org and Grokopedia
- Remote cache upload via S3 presigned URL (Settings)
- Log and User detail modals in admin

## Setup
- Role-based rate limits:
  - Settings → API Management → Role Rate Limits (JSON)
  - Example: {"administrator":1000,"editor":300,"author":200,"subscriber":50}
- Object cache:
  - Install a Redis object cache plugin for WordPress
  - Configure connection in wp-config.php (e.g., define('WP_REDIS_HOST','127.0.0.1'))
  - The plugin automatically prefers wp_cache_get/wp_cache_set when available

## Screenshots
- Dashboard: screenshots/screenshot-dashboard.svg
- Analytics: screenshots/screenshot-analytics.svg
- History & Logs: screenshots/screenshot-history.svg
- Bulk Tools: screenshots/screenshot-bulk-tools.svg
- API Management: screenshots/screenshot-api-management.svg

## Branding
- Verifact by Veracity Integrity — www.veracityintegrity.com
- Child company of Spun Web Technology
- Website: https://spunwebtechnology.com
- Service / Contact: https://spunwebtechnology.com/service-form
- Toll Free: +1 (888) 264-6790
- WhatsApp: +1 (808) 365-6628
- Email: support@spunwebtechnology.com
