# Claire Imageboard — Modernized (PHP 8.4+)

A cleaned, strictly-typed modernization of the classic TinyIB/Claire imageboard.

## Requirements

- **PHP 8.4 or newer** (tested against the 8.4/8.5 series)
- Extensions: `pdo_sqlite`, `gd`, `fileinfo`, `mbstring` (recommended), `openssl`
- Writable `db/` directory (created automatically)

## Quick start

1. Place the files on a PHP 8.4+ host.
2. Edit the configuration block at the top of `index.php`.
3. Make sure the web server user can write to the `db/` folder.
4. Visit the board. Default admin password is `adminpassword` (change it!).

## What was improved

- `declare(strict_types=1)` + full type declarations
- Secure session cookies + CSRF protection on every form
- Modern security headers (CSP, X-Content-Type-Options, Referrer-Policy…)
- WebP support in addition to GIF/JPG/PNG
- Safer uploads (finfo MIME checks, cryptographically random filenames)
- Responsive dark UI with CSS custom properties
- PDO in exception mode + useful indexes
- Cleaner error pages and delete confirmation flow
- Captcha is larger, has noise/jitter, and sends proper cache headers
- Removed most `@` error suppression and obsolete patterns

## Configuration

All classic Claire/TinyIB defines are still present at the top of `index.php` so existing habits continue to work. Change passwords, title, thread limits, captcha salt, etc. there.

## License / origin

Derived from the public TinyIB lineage (Claire fork). Keep the attribution link if you redistribute.
