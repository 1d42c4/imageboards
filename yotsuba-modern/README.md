# Yotsuba Modern – PHP 8 + SQLite3 Imageboard

A clean, modern rewrite of the classic Futaba/Yotsuba (4chan-style) imageboard script.

## Requirements

- PHP 8.0+ (tested on 8.2/8.3)
- PDO SQLite extension (almost always enabled)
- GD extension (for thumbnails – highly recommended)
- Write permissions on `src/`, `thumb/` and the directory itself (for `board.sqlite`)

## Quick Start

1. Upload the whole folder to your web server.
2. Make sure the web server can write to the folder:
   ```bash
   chmod 755 src thumb
   chmod 666 board.sqlite   # will be created automatically
   ```
3. Edit `config.php`:
   - Change `ADMIN_PASS`
   - Change `SALT` to a long random string
4. Open the folder in your browser. The database and tables are created on first run.

## Features

- Classic Yotsuba green look & feel
- Threads + replies with images (JPEG/PNG/GIF/WebP)
- Automatic thumbnails
- Trip codes (normal + secure)
- Greentext, >>quotes, spoilers
- Sage (put "sage" in e-mail field)
- Post deletion by password or IP
- Flood protection
- Automatic pruning of old threads
- Mobile-friendly CSS
- Fully dynamic (no static HTML generation)
- Prepared statements everywhere – no SQL injection
- No `extract()`, no old mysql_* functions

## Differences from the original 2000s script

| Original                  | This version                     |
|---------------------------|----------------------------------|
| MySQL + mysqli            | SQLite3 via PDO                  |
| Static HTML files         | Dynamic PHP                      |
| `extract($_POST)`         | Explicit, filtered input         |
| Many global includes      | Self-contained                   |
| PHP 4/5 era code          | PHP 8.2+ with strict types       |
| Incomplete / broken paste | Fully working                    |

## Admin / Delete

- Use the password you entered when posting to delete your own posts.
- Or set the password field to the value of `ADMIN_PASS` in config.php.

## Security notes

- Change `ADMIN_PASS` and `SALT` before going public.
- Put the board behind Cloudflare / rate limiting for real use.
- The original script had many more anti-spam systems; this is a minimal modern base.

Enjoy.
