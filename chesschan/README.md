# Chesschan

A lightweight, clean PHP imageboard designed to look like modern Node.js boards (jschan / Indiachan style).

Built specifically so you can have a nice-looking chess discussion board **without** dealing with Node.js dependency hell.

## Features

- Clean dark theme inspired by jschan / Indiachan
- Classic chan layout (OP + replies, quote links, greentext)
- Image uploads (JPG, PNG, GIF, WebP)
- Threaded discussion
- Password-based post deletion (basic)
- SQLite database (zero configuration)
- No accounts, no tracking, no external dependencies
- Mobile friendly
- Extremely easy to install

## Requirements

- PHP 8.0+ (with PDO SQLite and GD extension)
- That's it.

## Installation

1. Upload the entire `chesschan` folder to your web server.
2. Make sure the `uploads/` folder is writable (`chmod 755` or `775`).
3. Open the site in your browser. The database is created automatically on first visit.
4. Done.

No Composer. No npm. No Redis. No MongoDB.

## Configuration

Edit `config.php` to change:

- Board name / title
- Max file size
- Threads per page
- etc.

## Theming

The CSS is in `css/style.css`.  
It uses CSS variables at the top so you can easily switch between dark and light (Yotsuba-like) themes by uncommenting the light section.

## Notes

This is intentionally simple and clean.  
It is **not** a full multi-board suite like jschan (no user accounts, no advanced moderation panel, no catalog yet).  
It is meant to be a solid, good-looking foundation for a chess board that you can expand later if needed.

Enjoy your Node-free chesschan.
