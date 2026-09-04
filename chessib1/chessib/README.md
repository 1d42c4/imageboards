# ChessIB — Chess Imageboard

A lightweight, modern PHP imageboard inspired by TinyIB / Vichan, purpose-built for chess discussion.

## Features

- **Threads + replies** with classic imageboard UX
- **Optional image uploads** (JPG, PNG, GIF, WebP) with automatic thumbnails (GD)
- **SQLite3** database — zero configuration, single file
- **Tripcodes** (`Name#secret`)
- **Markup**: `**bold**`, `*italic*`, `||spoilers||`, `>greentext`, `>>123` post links
- **Sticky / Lock** threads (admin)
- **Password deletion** of your own posts
- **IP bans** (admin)
- **CSRF protection**, prepared statements, modern PHP 8+
- Clean dark chess-themed UI, fully responsive
- No frameworks, no composer required

## Requirements

- PHP 8.1+ with extensions: `pdo_sqlite`, `gd`, `fileinfo`, `mbstring`
- Web server (Apache / nginx / PHP built-in server)
- Write permissions on `data/`, `uploads/`, `thumbs/`

## Quick Start

```bash
# 1. Copy the chessib folder to your web root
cd /var/www/html   # or wherever
# (or just serve this directory)

# 2. Make sure directories are writable
chmod 777 data uploads thumbs

# 3. Edit config.php
#    - Change ADMIN_PASSWORD
#    - Adjust limits if desired

# 4. Run with PHP built-in server (for testing)
php -S localhost:8080 -t .

# 5. Open http://localhost:8080
```

The SQLite database and tables are created automatically on first visit.

## Configuration

Edit `config.php`:

| Setting | Description |
|---------|-------------|
| `ADMIN_PASSWORD` | Password for the mod panel (`admin.php`) |
| `MAX_FILE_SIZE` | Max upload size (default 4 MB) |
| `THREADS_PER_PAGE` | Pagination |
| `ALLOW_IMAGES` | Set `false` for a pure textboard |
| `REQUIRE_IMAGE_FOR_THREAD` | Force image on new threads |

## Usage

- **Board** → `index.php`
- **Thread** → `thread.php?id=123`
- **Mod panel** → `admin.php` (login with the password from config)

From the mod panel or while logged in you can:
- Delete any post/thread
- Sticky / unsticky threads
- Lock / unlock threads
- Ban an IP from a post

## Directory Layout

```
chessib/
├── config.php
├── index.php
├── thread.php
├── post.php
├── admin.php
├── assets/style.css
├── includes/
│   ├── db.php
│   └── functions.php
├── data/          ← SQLite DB (auto-created)
├── uploads/       ← full-size images
└── thumbs/        ← thumbnails
```

## Security Notes

- Change `ADMIN_PASSWORD` immediately.
- Keep `data/` outside the web root if possible, or deny direct access via web server config.
- The app uses prepared statements and CSRF tokens.
- Uploaded files are stored with random names; only images are accepted (MIME + extension check).

## License

Public domain / MIT — do whatever you want. Built for the chess community.
