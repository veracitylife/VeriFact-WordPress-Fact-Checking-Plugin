# Verifact — WordPress Plugin (2.0.8)
UI + REST proxy for the VeriFact FastAPI service.

## Usage
1. Copy this folder into WordPress plugins directory or upload via Admin.
2. Go to Settings → VeriFact and set API Base URL (e.g., https://your-domain.com/verifact).
3. Add the shortcode `[verifact]` to any page/post.

## Notes
- REST route: `/wp-json/verifact/v1/check` proxies to `{API_BASE}/check`.
- Optional sources: enable Archive.org and Grokopedia in Settings; forwarded to backend via `sources`.

## Branding
- Verifact by Veracity Integrity — www.veracityintegrity.com
- Veracity Integrity is a child company of Spun Web Technology
- Website: https://spunwebtechnology.com
- Service / Contact: https://spunwebtechnology.com/service-form
- Toll Free: +1 (888) 264-6790
- WhatsApp: +1 (808) 365-6628
- Email: support@spunwebtechnology.com
