# Chess Message Board

A clean, modern, single-file PHP message board designed for chess discussion.

## Features

- Single file (`index.php`) — no templates or extra PHP files needed
- SQLite storage (automatic, no setup)
- Optional image uploads (JPEG, PNG, GIF, WebP) — perfect for board positions and puzzles
- Greentext support (lines starting with `>`)
- Newest posts first
- Chess-inspired design (board colors)
- Works on PHP 8.1+

## Requirements

- PHP 8.1 or newer
- PDO SQLite extension (almost always enabled)
- fileinfo extension (for secure MIME detection)
- A writable directory for the database and `uploads/`

## Installation

1. Upload the entire `chessmb` folder to your web server.
2. Make sure the folder (or at least `uploads/` and the directory itself) is writable by the web server:
   ```bash
   chmod 755 chessmb
   chmod 755 chessmb/uploads
   ```
3. Point your browser to the folder (e.g. `https://yoursite.com/chessmb/`).
4. That's it. The SQLite database (`chessboard.db`) is created automatically on first visit.

## Usage notes

- Posts require either text or an image (or both).
- Images are stored with random filenames in `uploads/`.
- Maximum image size is 3 MB (configurable at the top of `index.php`).
- The board is intentionally simple and linear — no threads, no moderation UI. Ideal for a focused chess discussion space.

## Customization

All configuration is at the top of `index.php`:

- `MAX_IMAGE_SIZE`
- Allowed MIME types
- Database path

CSS is embedded and easy to tweak.

## Security notes

- All user text is escaped
- Prepared statements used for the database
- Uploaded files are MIME-checked and given random names
- No executable files can be uploaded via the form

For production you may still want to:

- Place the app behind a reverse proxy or Cloudflare
- Add basic rate limiting if traffic grows
- Regularly back up `chessboard.db` and the `uploads/` folder
