# Security and implementation review

Reviewed 7 September 2026. Target runtime: PHP 8.5.10, Windows x64. This is a new application backend; the original Vichan PHP, Twig runtime and Composer dependencies are not used.

## Evidence

| Check | Result |
| --- | --- |
| PHP syntax and compile-time deprecations | All 52 PHP files pass on PHP 8.5.10 with all error levels enabled; every file declares strict types |
| Static analysis | PHPStan level 8, zero errors in application classes |
| Isolated integration suite | 101 checks pass, including fresh installation and HTTP mutations |
| nginx / PHP FastCGI | 27 checks pass using nginx 1.30.4 and PHP 8.5.10 on Windows |
| Edge browser interaction suite | 15 checks pass; no JavaScript exceptions or failed asset requests |
| Theme / responsive checks | All 35 styles selectable at a 390px phone viewport with no horizontal overflow; populated board and staff dashboard screenshots inspected |
| Concurrent writes | Eight simultaneous PHP processes: unique post IDs, all replies retained, final static HTML complete, SQLite integrity OK |
| Privacy verification | Schema has no network-address, fingerprint or banning fields; synthetic forwarded-address headers do not enter stored records; no external browser requests |

The integration suite ships in `tests/run.php`. The additional browser, nginx and concurrency results accompany the delivered review. Browser tests exercised a generated MP4 and an animated two-frame GIF. This is a structured development review and automated verification, not an independent penetration-test certification or a claim of complete OWASP ASVS compliance.

## Controls reviewed

- **Input and output:** bounded UTF-8 inputs, validated numeric identifiers and board names; HTML/attribute escaping by default; a small formatter that escapes text before adding its own markup. Post HTML cannot select view files or execute code. Links receive nofollow/noreferrer/noopener/ugc attributes. No raw HTML or server-side URL fetching.
- **Database:** prepared statements with bound values, foreign keys and transactions; table/column choices are fixed application code. SQLite data lives outside the web root. Text resembling SQL is treated as data.
- **Authentication:** Argon2id passwords using 19 MiB and two iterations; long passwords are not silently truncated. Session regeneration at login, role/version checks on every staff request, 30-minute idle and eight-hour absolute staff limits, and invalidation following password/role changes. Account and global login budgets reduce brute-force attempts. Unknown accounts use an Argon2id dummy hash. Initial plaintext login details are private and removed after the initial account changes its password.
- **Authorization:** administrator/moderator/janitor permissions checked on server-side actions. Janitors cannot edit posts, change flags, manage staff or configure the site. Reauthentication protects staff management and board deletion. The last administrator cannot be deleted/demoted. Destructive content forms require explicit post/board confirmation.
- **Request forgery:** POST-only mutations, random session-bound CSRF tokens, constant-time comparisons, strict cookie-only sessions, HttpOnly and SameSite=Lax cookies, rejection of browser-labelled cross-site submissions. Set Secure cookies for HTTPS in production. The installer and maintenance console are CLI-only.
- **Uploads:** extension/MIME allowlist, genuine uploaded-file checks, unpredictable stored names, bounded file count/size, total storage quota, image dimension/frame budgets, GD decoding and image re-encoding, and structural GIF/MP4 checks. MP4 metadata recursion, box counts, lengths, track descriptions and video dimensions are bounded. No uploaded file is stored at an executable public path. Media requests resolve database tokens, not filesystem paths. File deletion removes the original and thumbnail and invalidates access through the media endpoint.
- **Media delivery:** explicit content types, nosniff, safe generated disposition filenames, one bounded byte range at a time, 416 handling, streamed chunks and HEAD support. No automatic video transcoding or external processing command.
- **Public/private separation:** public pages contain no staff session token or private account data. Moderator/session responses use no-store. CSP restricts scripts and network access to the site; framing, objects and embedded third-party frames are blocked. The public root contains only entrypoints and presentation/generated files.
- **Consistency:** content mutations use a build lock; database transactions protect post operations; static pages are written to temporary files and renamed. Deleted generated pages are removed using a constrained manifest. Concurrent-writer testing verified final output.
- **Privacy:** no application read of visitor network-address or forwarded-address headers, no geolocation, no IP-derived poster IDs, no ban tables and no request-header logging. Diagnostic records contain only a random reference, timestamp, exception class, source basename and line. Staff logs record staff ID, action, target and time. Temporary rate-limit keys use random session identity; expired counters are pruned during subsequent limit checks. PHP garbage collection removes expired session files probabilistically.

## Issues corrected during verification

- Quick replies initially saved successfully but changed only the URL anchor, leaving the old static page visible. Same-page submissions now reload the generated page; browser tests verify the reply appears.
- Post wrappers were aligned with the original Vichan `div.post` selectors so original reply colours/borders apply. Media floats are contained and cleared on phones to prevent text being squeezed beside videos.
- Two theme font imports requested Google Fonts; they were removed. Two other themes referenced three background files absent from the provided source; those references now use their existing plain backgrounds.
- Binary-reading failure paths, CAPTCHA colour-allocation failures and nullable database results were tightened while raising static analysis to level 8.
- MP4 checks were expanded beyond top-level signatures to validate bounded video-track metadata. GIF traversal has block/frame/pixel limits.
- Initial credentials now disappear after password change, and the nonexistent-account password path uses the same hash algorithm as real accounts.

## Remaining limits and deployment responsibilities

1. No application audit guarantees the absence of vulnerabilities. Before public launch, review the actual host, permissions, HTTPS and cache configuration; an independent review would provide additional assurance.
2. The server/CDN may log network information independently, as intended by the requested privacy boundary. Public post text, filenames and file metadata can still reveal information voluntarily uploaded by posters. GIF/MP4 originals retain metadata.
3. A session-based limit can be evaded by starting a new session; site-wide budgets and local CAPTCHA provide separate controls. Attackers can consume shared budgets and affect other visitors. Network-level flood/DDoS protection belongs at the host/CDN. This implementation deliberately does not substitute IP hashing or fingerprinting.
4. Media validation is structural and resource-limited; it does not fully decode every video/audio sample, transcode or run an antivirus engine. Browser codec support varies. PHP/GD and browsers must continue receiving security updates. Video posters are generic icons.
5. `style-src` permits inline styles to retain the existing theme presentation. Scripts do not permit inline execution. Do not add unsafe raw HTML or arbitrary third-party JavaScript to the application.
6. SQLite and synchronous static rebuilding suit a small/moderate community. Rebuilding all pages for content changes becomes more expensive as the archive grows. Large-scale traffic/storage needs further performance testing and possibly incremental generation. Disk exhaustion can interrupt generation; the database remains the source of truth and the CLI rebuild can repair generated pages.
7. Linux PHP-FPM, a specific shared host, Safari/iOS and Android hardware were not available for live verification. The phone checks used Edge with a narrow viewport. The included nginx configuration is a domain-root example and requires adaptation on the target host.
8. This implements the agreed posting/moderation subset. Old Vichan plugins, its database schema, translation infrastructure, staff PM system and remote integrations are not compatible. There is no migration of existing board data and no automatic PHP minor-version upgrade.

## Review references

- [OWASP ASVS](https://owasp.org/www-project-application-security-verification-standard/)
- [OWASP File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)
- [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
- [PHP password hashing](https://www.php.net/manual/en/function.password-hash.php)
- [PHP releases](https://www.php.net/)
