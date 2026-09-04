# Security

## Supported versions

Security fixes are applied to the latest release.

## Reporting a problem

Please report vulnerabilities privately to the site operator or project maintainer. Do not include exploit details in a public board post.

## Deployment baseline

- Serve only the `public/` directory. The database, source, and uploads are intentionally outside the web root.
- Complete setup before allowing untrusted visitors to reach a new internet-facing deployment.
- Use HTTPS in production.
- Keep PHP and the operating system current.
- Restrict `var/` so only the PHP worker and the administrative account can read it.
- Back up the SQLite database and `var/uploads/`.
- Leave `CHESSBOARD_DEBUG` disabled in production.

Uploaded files are decoded and re-encoded as JPEG, PNG, or WebP images before storage. The application does not read, hash, or store visitor IP addresses. Because IP bans and IP-backed rate limits are intentionally absent, operators should use web-server-level protections when needed without forwarding address data into the application.

## Public reports

The report form uses a short-lived, one-time arithmetic CAPTCHA stored in the visitor session. It does not call an external CAPTCHA provider and does not use or store an IP address.
