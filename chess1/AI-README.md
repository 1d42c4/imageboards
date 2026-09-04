# AI maintenance guide

This file is written for future coding assistants and maintainers. Read it before changing Chessboard Lite.

## Product goal

Chessboard Lite is a small, content-first discussion board for chess communities. The interface should recede into the background so posts, positions, PGN, and discussion receive attention. It deliberately avoids the crowded appearance and tightly coupled internals common in older imageboard engines.

The application should remain understandable without Composer, Twig, Node.js, a frontend build process, or a plugin framework. Small, explicit modules are preferred over clever abstractions.

## Non-negotiable design choices

Do not change these unless the operator explicitly asks:

- Never read, hash, log, or store visitor IP addresses.
- Do not add IP bans or IP-backed rate limits.
- Public post menus contain only **Report**.
- Reports use the local, one-time session CAPTCHA; do not add an external CAPTCHA provider.
- Do not restore public post passwords, quote links, permalinks, or backlink machinery.
- Board pages show opening posts with only the latest two replies nested below each one.
- Thread pages show the reply form first, then the opening post, then every reply.
- Post headers show only the date, not weekday, time, or public post number.
- New-thread forms remain collapsed behind the **New** button.
- Moderators may edit text and add, replace, or remove an image.
- Visitor theme, text-size, and text-weight choices remain in browser `localStorage` and are not sent with posts.
- Uploaded images must be decoded and re-encoded before storage.
- Keep the web root pointed at `public/`; source, database, logs, and original storage must remain outside it.

## Modular boundaries

### Public board module

- Routes: `src/Module/PublicBoardModule.php`
- HTTP behavior: `src/Controller/PublicController.php`
- Public templates: `templates/board.php`, `templates/thread.php`, and `templates/partials/`

Changes to posting, reading, uploads, and reports should stay inside this boundary whenever possible.

### Moderator module

- Routes: `src/Module/ModeratorModule.php`
- HTTP behavior: `src/Controller/ModeratorController.php`
- Templates: `templates/mod/`
- Moderator browser behavior: `public/assets/js/moderator.js`
- Moderator styling: `public/assets/css/moderator.css`

Do not mix moderator authorization checks into templates. Controllers must enforce authorization.

### Data layer

- Boards: `src/Repository/BoardRepository.php`
- Posts and threads: `src/Repository/PostRepository.php`
- Moderators, reports, and logs: `src/Repository/ModerationRepository.php`
- Database and migrations: `src/Database.php`, `migrations/`

Keep SQL in repositories or migrations. Use prepared statements. If PostgreSQL is added later, preserve repository method contracts so controllers and templates require minimal changes.

### Security services

- Sessions: `src/Security/Session.php`
- CSRF: `src/Security/Csrf.php`
- Moderator authentication: `src/Security/ModeratorAuth.php`
- Report CAPTCHA: `src/Security/Captcha.php`
- Upload validation: `src/Service/UploadService.php`
- Safe post markup: `src/Service/Markup.php`

Treat these files as security-sensitive. Make focused changes and add tests for every behavior change.

### CSS modules

The CSS entry point is `public/assets/css/app.css`. It imports small feature files:

- `foundation.css` — reset, typography baseline, navigation, headings
- `content.css` — notices, home page, content boxes, tables
- `forms.css` — posting and input forms
- `posts.css` — threads, posts, reply boxes, post menus, pagination
- `controls.css` — shared buttons and text controls
- `moderator.css` — staff pages only
- `setup.css` — setup, login, and errors
- `responsive.css` — mobile, reduced-motion, and print rules
- `preferences.css` — text-size, text-weight, and theme selector controls
- `theme-engine.css` — maps theme variables to components

Each selectable theme is an actual standalone file in `public/assets/css/themes/`. A theme should normally define variables only. Do not copy the entire board stylesheet into a theme.

The theme list and category order live in `config/themes.php`. See `docs/THEMES.md` before adding or editing a theme.

### JavaScript modules

`public/assets/js/app.js` is a small entry point. Feature behavior lives in:

- `preferences.js`
- `post-forms.js`
- `post-menus.js`
- `moderator.js`
- `navigation.js`

Do not introduce a JavaScript package manager or bundler for these small behaviors.

## Cross-platform policy

The supported baseline is PHP 8.4 or newer. Code should run on Linux VPS deployments and Windows development machines.

- Use PHP paths and application configuration rather than hard-coded Windows directories.
- `start-windows.bat` is optional convenience only; the application must not depend on it.
- Production examples should target ordinary Apache or Nginx/PHP-FPM deployments.
- Avoid shell-specific application behavior.

## Change discipline

Before editing:

1. Read the relevant module and its tests.
2. State which boundary owns the change.
3. Make the smallest focused change.
4. Avoid unrelated formatting or renaming.

Before declaring completion:

1. Run `php -l` on every PHP file.
2. Run `php tests/run.php` when PDO_SQLite, mbstring, and GD are available.
3. Check JavaScript module syntax.
4. Run `php bin/check-themes.php`.
5. Verify theme files use local assets only.
6. Run SQLite integrity checks when the bundled database changes.
7. Review the final diff for accidental changes.
8. Clearly state any check that could not run and why.

Never claim a test passed unless it actually ran.

## Security review checklist

For public changes, review:

- output escaping and stored XSS
- CSRF on state-changing requests
- SQL injection and board/thread ownership checks
- upload MIME, dimensions, pixel limits, re-encoding, paths, and deletion
- session behavior and CAPTCHA one-time use
- information disclosure in errors and logs

For moderator changes, additionally review:

- authentication and authorization on the server
- post and board identity checks
- attachment replacement/removal consistency
- audit logging

## Keep the app small

A new dependency is not automatically a module. Prefer a small owned implementation when it is easy to audit. Do not add Composer packages, Node packages, remote scripts, or remote fonts without an explicit requirement and a documented security reason.
