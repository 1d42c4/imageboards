# Security

## Supported versions

Security fixes are applied to the latest release.

## Reporting a problem

Please report vulnerabilities privately to the site operator or project maintainer. Do not include exploit details in a public board post.

## Deployment baseline

- Serve only the `public/` directory. The database, application key, source, and uploads are intentionally outside the web root.
- Complete setup before allowing untrusted visitors to reach a new internet-facing deployment.
- Use HTTPS in production.
- Keep PHP and the operating system current.
- Restrict `var/` so only the PHP worker and the administrative account can read it.
- Back up the SQLite database, `var/app.key`, and `var/uploads/`.
- Leave `CHESSBOARD_DEBUG` disabled in production.

Uploaded files are decoded and re-encoded as JPEG, PNG, or WebP images before storage. The app stores a keyed hash of a client address for abuse controls instead of storing the raw address.
