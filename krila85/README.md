# Krila – PHP 8.5+ ready imageboard

Drop this folder anywhere (root or any subdirectory). Every path is relative, so each board can live in its own directory with its own `thread/`, `cdn/` and `static/`.

## Features restored / improved
- **Newest threads on top** of the main board
- **Pagination** on the index (10 threads per page, Previous / [1] [2] … / Next)
- **Reply form** on every thread page (original layout + spacing restored)
- **Images** correctly linked (`cdn/filename.ext` – no more `include=` bug)
- Works in any subdirectory without configuration
- Click ion the post image to reply, in reply mode click in top board banner image to return to the main board. 

  
## Directory layout
```
yourboard/
├── index.php
├── post.php
├── postcomment.php
├── thread.php
├── static/
│   ├── krila.css
│   └── logo.png          ← put your real logo here
├── thread/               ← auto-created .txt files
└── cdn/                  ← uploaded images
```

## PHP requirements
PHP 8.3 – 8.5+ (strict_types, null-safe, pathinfo, str_starts_with, etc.)

Just point a vhost or `php -S localhost:8000` at the folder and post.
