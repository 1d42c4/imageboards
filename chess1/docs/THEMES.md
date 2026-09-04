# Theme authoring

Chessboard Lite themes are independent CSS variable files. They can change color, typography, corner shape, subtle texture, and background treatment without duplicating or replacing the board layout.

## Add a theme

1. Copy a nearby file in `public/assets/css/themes/`.
2. Rename it with a lowercase slug, for example `analysis-room.css`.
3. Edit the variables in that file.
4. Add the slug and display name to the desired group in `config/themes.php`.
5. Reload the board and select the theme.

A missing theme file falls back visually to the base Checkmate variables, but it should still be treated as a packaging error.

## Main variables

| Variable | Purpose |
|---|---|
| `--theme-bg` | Page background color |
| `--theme-bg-image` | Optional local image or CSS gradient |
| `--theme-text` | Main text |
| `--theme-link` | Links and secondary actions |
| `--theme-hover` | Hovered links |
| `--theme-muted` | Dates and quiet metadata |
| `--theme-heading` | Board and page headings |
| `--theme-panel` | Reply and utility-panel background |
| `--theme-border` | Main borders |
| `--theme-surface` | Raised boxes and forms |
| `--theme-accent` | Section title bars |
| `--theme-input` | Input background |
| `--theme-subject` | Post subjects |
| `--theme-name` | Poster names |
| `--theme-quote` | Greentext |
| `--theme-pgn` | PGN block background |
| `--theme-font` | Main font stack |
| `--theme-heading-font` | Heading font stack |
| `--theme-radius` | General panel corners |
| `--theme-reply-radius` | Reply-box corners |
| `--theme-control-radius` | Buttons and form controls |
| `--theme-shadow` | Optional restrained panel shadow |
| `--theme-reply-border-width` | Reply-box edge emphasis |

Every variable has a short inline explanation in the existing theme files.

## Background images

Place local decorative files in `public/assets/css/themes/images/` and reference them relative to the theme file:

```css
--theme-bg-image: url("images/my-fade.png");
--theme-bg-repeat: repeat-x;
--theme-bg-position: top center;
```

Use small, decorative images only. Never reference remote image URLs, tracking pixels, scripts, fonts, or third-party CSS. Keep text readable when an image fails to load.

## Categories

The selector uses practical categories:

- Light themes
- Mid-tone themes
- Dark themes
- Traditional themes
- Experimental themes

Place unfinished palettes in **Experimental themes**. Move them only after checking contrast, mobile behavior, forms, posts, moderator pages, and error states.

## Design rule

Themes may alter presentation, but they should not hide controls, reorder content, change permissions, or make opening posts and replies indistinguishable. The content remains the focus.
