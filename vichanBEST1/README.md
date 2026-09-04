# Discussion board

A small **imageboard-style discussion board**: boards, threads, replies, images, catalog, read-only archive, and a moderator panel.

Built to be **readable, hostable, and private by default**. No Composer, no third-party template package, no phone-home. Configure with PHP files. Runs on **modern PHP and PostgreSQL**.

| | |
|--|--|
| **Runtime** | PHP **8.5+** (current line; keep PHP updated) |
| **Database** | **PostgreSQL** only |
| **Dependencies** | None — no `composer install`, no `vendor/` |
| **Images** | **PHP GD** only (no ImageMagick/`convert`) |
| **Privacy** | Client addresses are **never read or stored** |
| **Config** | PHP files only (no web settings editor) |

---

## What it is

Think of a **board** as a section of the site (for example `/b/` for general chat). On a board:

1. Anyone can open the **index** (thread list) and the **catalog** (grid of threads).
2. Users start a **thread** (optional subject, text, optional image) or **reply** in a thread.
3. After posting, the server **rebuilds static HTML** for that thread and the board index — most visitors get plain files.
4. Moderators use **`mod.php`** to manage boards and posts, and (when enabled) approve held posts or archive threads.

There is **no account system for normal users**. Posting is anonymous (or by name/tripcode). Moderators have logins. Users cannot self-delete posts with a password; staff delete from the mod panel.

There is **no IP flood control, no IP bans, and no delete-by-IP**. Abuse tools that do work: optional captcha, per-board password, and/or post approval.

---

## How a visitor uses the site

| Page | What it is |
|------|------------|
| `/` | Public **landing page** (`index.html` — you edit this) |
| `/boards.html` | Auto-generated list of boards (updated on rebuild) |
| `/{board}/` | Board index — bumped threads; form to start a new topic |
| `/{board}/catalog.html` | All threads in a compact grid |
| `/{board}/res/{id}.html` | Full thread (OP + replies); reply form at the top |
| `/{board}/archive/` | **Read-only** archive of threads that left the live board |

**Top bar links** are **manual** in `inc/instance-config.php` (`$config['boards']`). Creating a board in mod does **not** add a nav link — you choose labels and URLs (boards and external sites mixed freely).

**Style dropdown** (top of the page) switches CSS skins (Yotsuba, Futaba, Dark, etc.).

**Images:** thumbnails on the board; click expands in place. Animated GIFs keep playing (original used as the thumb).

**Reports:** visitors can report a post with a reason; mods handle the queue in the dashboard.

---

## How moderation works

Open **`/mod.php`** (fresh install: **`admin` / `password`** — change immediately).

### Dashboard

- Boards (create / edit)
- **Report queue**
- **Post approval queue** (if any board requires approval)
- Users, mod log, recent posts
- **Rebuild** static pages

### On a thread (while logged into mod)

- Delete post / file  
- Sticky, lock, bumplock (sage)  
- **Archive** — move a live thread to the read-only archive  
- Edit post text and replace/remove images  

### Per-board options (edit board)

| Option | Off (default) | On |
|--------|----------------|-----|
| **Post approval** | Posts appear immediately | New posts wait in the approval queue |
| **Board password** | Anyone can post | Shared password required; **mods bypass** |

### Archive

When a thread falls off the live pages (`max_pages`) **or** a mod uses **[Archive]**:

- OP + replies become a static read-only page under `/{board}/archive/`
- Images stay on disk so the archive still displays them  
- No post form on archive pages  

---

## Install

### Requirements

- **PHP 8.5+** with: `pdo`, `pdo_pgsql`, `mbstring`, `gd`, `openssl`  
  (Current 8.x is usually fine; plan on staying current.)
- **PostgreSQL** 12+ (or current)
- A web server (nginx or Apache) with document root = this project directory  
  — or PHP’s built-in server for local testing (`router.php`)

### Steps

1. Put the project on the server (document root = this directory).
2. From the project root:

```bash
php tools/install_cli.php
```

The installer can create the database (if your PostgreSQL user may do so), write `inc/secrets.php` if missing, apply `install.pgsql.sql`, create the starter board **`b`**, and build initial static pages.

Optional environment overrides:

```text
VICHAN_DB_HOST  VICHAN_DB_PORT  VICHAN_DB_NAME  VICHAN_DB_USER  VICHAN_DB_PASSWORD
```

3. Log into `/mod.php`, change the admin password.
4. Edit **`index.html`** for your public home page (see `examples/landing.html`).
5. Set top navigation in **`inc/instance-config.php`** (`$config['boards']`).
6. After config or template changes, rebuild:

```bash
php tools/rebuild.php
```

Re-run schema + static setup on an existing install (careful — for development):

```bash
php tools/install_cli.php --force
```

### Local try-out (Windows helper)

```bash
start-dev.bat
```

Or manually:

```bash
php tools/install_cli.php
php -S 127.0.0.1:8080 router.php
```

Then open:

- `http://127.0.0.1:8080/` — landing  
- `http://127.0.0.1:8080/b/` — board  
- `http://127.0.0.1:8080/mod.php` — moderation  

`router.php` is only for PHP’s built-in server. Production uses PHP-FPM/CGI as usual.

### Production hardening

- HTTPS  
- Block HTTP access to `inc/`, `tools/`, `tmp/`, `templates/cache_php/`, `.installed`, SQL dumps, and secrets  
- In `inc/secrets.php`: strong salts; `$config['cookies']['secure_login_only'] = 1`  
- Do not commit `inc/secrets.php`  
- Prefer a dedicated PostgreSQL user limited to this database  

---

## Configuration

There is **no** in-browser config UI. Edit files, then rebuild if pages look stale.

| File | Role |
|------|------|
| **`inc/secrets.php`** | DB credentials, cookie/trip salts. Created by the installer. Copy from `inc/secrets.example.php` if needed. **Do not commit.** |
| **`inc/instance-config.php`** | Site overrides (nav links, captcha, etc.) |
| **`inc/config.php`** | Defaults — read it; prefer overrides in instance/secrets |
| **`{board}/config.php`** | Optional per-board overrides (loaded when that board is open) |

### Privacy

Client addresses are **never read or stored** — not on posts, reports, or mod logs. There is no ban-by-IP, no delete-by-IP, and no IP flood table.

**Abuse tools that work without addresses:**

- Optional captcha (`native` / `recaptcha` / `hcaptcha`)  
- Per-board posting password  
- Per-board post approval  

### Useful defaults

```php
$config['always_noko'] = true;     // after posting, stay on the thread
$config['always_sage'] = false;    // true = replies never bump
$config['max_body'] = 100000;      // full body stored; index may truncate preview
$config['force_image_op'] = false; // text-only threads allowed
$config['threads_per_page'] = 10;
$config['max_pages'] = 10;         // live board depth before auto-archive
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

In the name field, `Name#secret` or `Name##secret` becomes a secure tripcode (HMAC-SHA256). Use a strong `$config['secure_trip_salt']` in `secrets.php`.

### Optional captcha

```php
// instance-config.php or secrets.php
$config['captcha']['provider'] = false; // or 'native' | 'recaptcha' | 'hcaptcha'
// For recaptcha/hcaptcha, set sitekey + secret under $config['captcha']['recaptcha'] / ['hcaptcha']
// Native captcha uses securimage.php (no external service)
```

---

## How posting works (short)

1. Browser posts to **`post.php`**.
2. Server checks board rules (lock, password, captcha if enabled, approval).
3. Post is stored in PostgreSQL (`posts_{uri}`).
4. If **approval** is on, the post stays **pending** until a mod accepts it.
5. Otherwise the server rebuilds thread HTML, board index, and catalog as needed.

Public traffic is mostly **static HTML + CSS + small vanilla JS** (`js/inline-expanding.js`, `js/hide-form.js`, `js/catalog.js`). Templates use a Twig-like syntax compiled by **`inc/view.php`** into PHP under `templates/cache_php/`. There is no Composer Twig package.

### CLI tools (block from HTTP)

| Tool | Purpose |
|------|---------|
| `tools/install_cli.php` | Schema + secrets + starter board `b` + initial build |
| `tools/rebuild.php` | Rebuild all static pages / catalog / archive indexes |
| `tools/maintenance.php` | Optional filesystem cache cleanup (if `cache.enabled` is `fs`) |

---

## Layout

```text
index.html            Public landing page (yours; not overwritten after install)
boards.html           Generated board list
mod.php               Moderator login and panel
post.php              Create posts and reports
report.php            Report form entry
router.php            Front controller for php -S only
securimage.php        Native captcha image (if captcha provider is "native")
main.js               Generated front-end script (from templates/main.js)
start-dev.bat         Windows local helper (install if needed + php -S)
install.pgsql.sql     Base PostgreSQL schema
inc/                  Application code
  secrets.php         Credentials (do not commit)
  secrets.example.php Example secrets
  config.php          Defaults
  instance-config.php Site overrides
  view.php            Template compiler
  archive.php         Archive system
  board_moderation.php  Board password + approval
templates/            HTML templates (+ posts/archive SQL snippets)
  cache_php/          Compiled templates (auto; safe to delete)
js/                   Front-end helpers
stylesheets/          Base style + skins
static/               Icons and static assets
{board}/              Generated board files (index, catalog, res/, src/, thumb/, archive/)
tools/                CLI install / rebuild / maintenance
examples/             Landing page starter
tmp/                  Runtime cache/locks
```

---

## Features at a glance

- Multiple boards, threads, replies  
- Image uploads: jpg / jpeg / png / gif / webp / bmp (GD thumbnails)  
- Catalog + catalog RSS when enabled  
- Manual top navigation (boards and external links)  
- Read-only **archive** (auto and manual)  
- **Per-board** posting password and/or post approval  
- Moderator panel: delete, edit, sticky, lock, archive, reports, users, rebuild  
- Multiple built-in stylesheets  
- **No** client-address storage, **no** IP bans, **no** IP flood control  
- **No** Composer, **no** remote update checks, **no** mandatory third-party CDNs  

---

## License

See [LICENSE.md](LICENSE.md) for license terms that apply to this codebase.
