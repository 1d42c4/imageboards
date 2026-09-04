# Discussion board

A small **imageboard-style discussion board** for the web: boards, threads, replies, images, a catalog, a read-only archive, and a moderator panel.

It is built to be **readable, hostable, and private by default**. There is no Composer install tree, no packaged template engine, and no phone-home. You configure it with PHP files and run it on **modern PHP and PostgreSQL**.

| | |
|--|--|
| **Runtime** | PHP **8.5+** (current line; keep PHP updated) |
| **Database** | **PostgreSQL** only |
| **Dependencies** | None — no `composer install`, no `vendor/` |
| **Privacy** | Client IPs are **not stored** unless you turn that on |
| **Config** | PHP files only (no web “settings editor”) |

---

## What it is (for someone new)

Think of a **board** as a section of the site (for example `/b/` for general chat, or a private club board). On a board:

1. Anyone can open the **index** (list of threads) and the **catalog** (grid of threads).
2. Users start a **thread** (optional subject, text, optional image) or **reply** in an existing thread.
3. After posting, the server **rebuilds static HTML** for that thread and the board index, so most visitors are served plain files — fast and simple.
4. Moderators log into **`mod.php`**, manage boards and posts, and (when enabled) approve held posts or archive threads.

There is **no account system for normal users**. Posting is anonymous (or by name/tripcode). Moderators have logins. Users do not self-delete posts with a password; staff remove posts from the mod panel.

---

## How a visitor uses the site

| Page | What it is |
|------|------------|
| `/` | Your **landing page** (`index.html` — you write this) |
| `/boards.html` | Simple auto-generated list of boards (updated on rebuild) |
| `/{board}/` | Board index — newest/bumped threads, post form for a new topic |
| `/{board}/catalog.html` | All threads in a compact grid |
| `/{board}/res/{id}.html` | One full thread (OP + replies); reply form at the top |
| `/{board}/archive/` | **Read-only** archive of threads that left the live board |

**Top bar links** (a / b / … or chess site links) are **manual**. Editing them does not happen when you create a board — you list URLs yourself in config so you can mix boards and external pages.

**Style dropdown** (top of the page) switches CSS skins (Yotsuba, Futaba, Dark, etc.). It scrolls with the page.

**Images:** thumbnails on the board; click expands in place (no forced new tab). Animated GIFs keep playing.

**Reports:** visitors can report a post with a reason; mods review them in the dashboard.

---

## How moderation works

Open **`/mod.php`** (default install login: **`admin` / `password`** — change immediately).

### Dashboard

- List of boards (edit each board)
- **Report queue**
- **Post approval queue** (for boards that require approval)
- Users, mod log, recent posts, **rebuild** static files
- Ban list only appears if IP storage is enabled (see Privacy)

### Everyday mod tools (on a thread, while logged into mod)

Shown on OPs and/or replies as short controls, for example:

- Delete post / file  
- Sticky, lock, bumplock (sage)  
- **Archive** — move a live thread to the read-only archive  
- Edit post text and replace/remove images  

Ban-by-IP controls only make sense if you store IPs.

### Per-board options (edit board)

Each board can enable, independently:

| Option | Off (default) | On |
|--------|----------------|-----|
| **Post approval** | Posts appear immediately | New posts wait in the **approval queue** until a mod accepts or rejects them |
| **Board password** | Anyone can post | Posters must enter a shared password (club boards); **mods bypass** |

Use either, both, or neither per board. Example: a chess club board with a password, or a public board with approval for new threads.

### Archive

When a thread falls off the live pages (board only keeps so many threads) **or** a mod uses **[Archive]**:

- OP + replies are kept as a static **read-only** page under `/{board}/archive/`
- Images stay on disk so the archive still displays them  
- No post form on archive pages  

---

## Install (website)

### Requirements

- **PHP 8.5+** with extensions: `pdo`, `pdo_pgsql`, `mbstring`, `gd`, `openssl`  
  (Also fine on current 8.x if your host is slightly older; plan on staying current.)
- **PostgreSQL** 12+ (or current)
- A web server (nginx or Apache) pointing at this project directory
- Optional: ImageMagick/`convert` if you prefer non-GD thumbnails

### Steps

1. Put the project on the server (document root = this directory).
2. Create a PostgreSQL role/database (or let the installer create the database if the user may do so).
3. From the project root:

```bash
php tools/install_cli.php
```

Optional environment overrides before install:

```text
VICHAN_DB_HOST  VICHAN_DB_PORT  VICHAN_DB_NAME  VICHAN_DB_USER  VICHAN_DB_PASSWORD
```

4. Log into `/mod.php`, change the admin password, create or edit boards.
5. Edit **`index.html`** for your public home page (see `examples/landing.html`).
6. Set top navigation links in **`inc/instance-config.php`** (`$config['boards']` — label ⇒ URL).
7. Rebuild static pages after config/template changes:

```bash
php tools/rebuild.php
```

### Local try-out (optional)

```bash
php tools/install_cli.php
php -S 127.0.0.1:8080 router.php
```

Then open `http://127.0.0.1:8080/` (landing), `/b/` (default board), `/mod.php` (moderation).  
`router.php` is only for PHP’s built-in server; production uses normal PHP-FPM/CGI.

### Production hardening

- HTTPS  
- Block HTTP access to `inc/`, `tools/`, `tmp/`, `templates/cache_php/`, `.installed`, SQL dumps, and any secrets  
- In secrets: strong salts; `$config['cookies']['secure_login_only'] = 1`  
- Do not commit `inc/secrets.php`  
- Prefer a dedicated PostgreSQL user with rights only on this database  

---

## Configuration

There is **no** in-browser config UI. Edit files, then rebuild if pages look stale.

| File | Role |
|------|------|
| **`inc/secrets.php`** | DB credentials, cookie/trip salts, privacy toggles. Created by the installer; gitignored. Copy from `inc/secrets.example.php` if needed. |
| **`inc/instance-config.php`** | Your site overrides (boardlist, flood limits for dev, etc.) |
| **`inc/config.php`** | Defaults (read it; prefer not to edit heavily — override in instance/secrets) |
| **`{board}/config.php`** | Optional per-board overrides |

### Privacy (IPs)

By default **client IPs are not written** to posts or logs. Flood protection uses a short irreversible token instead.

```php
// secrets.php — only if you need classic IP bans / IP display
$config['privacy']['store_ip'] = true;
```

With storage off, ban-list UI is hidden and mod views do not show IP chrome.

### Useful defaults (site-wide)

```php
$config['always_noko'] = true;    // after posting, go to the thread
$config['always_sage'] = false;   // true = replies never bump the thread
$config['max_body'] = 100000;     // full body allowed; index may truncate preview
$config['force_image_op'] = false; // text-only threads allowed
$config['threads_per_page'] = 10;
$config['max_pages'] = 10;        // how deep the live board goes before auto-archive
```

### Top links (manual)

```php
// instance-config.php
$config['boards'] = [
	'Home'    => '/',
	'b'       => '/b/',
	'Lichess' => 'https://lichess.org',
	// nested group example:
	// 'Tools' => [ 'Analysis' => 'https://lichess.org/analysis' ],
];
```

Creating a board in mod **does not** add a top link. Edit this list and rebuild.

### Tripcodes

In the name field, `Name#secret` or `Name##secret` becomes a secure tripcode (HMAC-SHA256). Set a strong `$config['secure_trip_salt']` in `secrets.php`.

---

## How posting works under the hood (short)

1. Browser submits the form to **`post.php`**.
2. Server checks board rules (lock, password, captcha if enabled, approval flag).
3. Post is stored in PostgreSQL (`posts_{board}` tables).
4. If **approval** is on, the post stays **pending** until a mod approves it.
5. Otherwise the server rebuilds the thread HTML and board index (and catalog as needed).

Most public traffic is static HTML + CSS + small vanilla JS (`js/inline-expanding.js`, catalog sort, form helpers). Templates use a Twig-like syntax compiled by **`inc/view.php`** into PHP under `templates/cache_php/`.

CLI helpers (not web endpoints — keep them blocked from HTTP):

| Tool | Purpose |
|------|---------|
| `tools/install_cli.php` | First-time schema + secrets + starter board |
| `tools/rebuild.php` | Rebuild all static pages / archive |
| `tools/maintenance.php` | Optional ban/cache cleanup if auto maintenance is off |

---

## Layout of the project

```text
index.html          Public landing page (yours)
boards.html         Generated board list
mod.php             Moderator login & panel
post.php            Create posts / reports
router.php          Front controller for `php -S` only
inc/                Application code
  secrets.php       Credentials (do not commit)
  config.php        Defaults
  instance-config.php  Site overrides
  view.php          Template compiler
  archive.php       Archive system
  board_moderation.php  Board password + approval
templates/          HTML templates
js/                 Small front-end scripts
stylesheets/        Base style + skins
{board}/            Generated board files (index, res/, src/, thumb/, archive/)
tools/              CLI install / rebuild / maintenance
examples/           Landing page starter
```

---

## Features at a glance

- Multiple boards, threads, replies, image uploads (jpg/png/gif/webp, …)
- Catalog + RSS-style catalog feed where enabled  
- Manual top navigation (boards and external links)  
- Read-only **archive** for old or staff-archived threads  
- **Per-board** posting password and/or post approval  
- Privacy-first IP handling  
- Moderator panel: delete, edit, sticky, lock, archive, reports, users, rebuild  
- Several built-in stylesheets  
- No Composer, no remote update checks, no mandatory third-party CDNs  

---

## License

See [LICENSE.md](LICENSE.md) for license terms that apply to this codebase.
