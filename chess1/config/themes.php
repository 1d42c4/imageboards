<?php

declare(strict_types=1);

/*
 * Theme registry.
 *
 * Add a theme by creating public/assets/css/themes/<slug>.css and listing it
 * below. Keep slugs lowercase with letters, numbers, and hyphens only.
 */

return [
    'Light themes' => [
        'checkmate' => 'Checkmate',
        'queen' => 'Queen',
        'bishop' => 'Bishop',
        'caro-kann' => 'Caro-Kann',
        'ruy-lopez' => 'Ruy Lopez',
        'italian-game' => 'Italian Game',
        'catalan' => 'Catalan',
        'london-system' => 'London System',
        'ivory' => 'Ivory',
        'endgame' => 'Endgame',
    ],
    'Mid-tone themes' => [
        'king' => 'King',
        'rook' => 'Rook',
        'knight' => 'Knight',
        'pawn' => 'Pawn',
        'sicilian' => 'Sicilian',
        'french-defense' => 'French Defense',
        'english-opening' => 'English Opening',
        'nimzo-indian' => 'Nimzo-Indian',
        'queens-indian' => "Queen's Indian",
        'scandinavian' => 'Scandinavian',
    ],
    'Dark themes' => [
        'kings-indian' => "King's Indian",
        'grunfeld' => 'Grünfeld',
        'alekhine' => 'Alekhine',
        'pirc' => 'Pirc',
        'dragon' => 'Dragon',
        'najdorf' => 'Najdorf',
        'walnut' => 'Walnut',
        'midnight-mate' => 'Midnight Mate',
    ],
    'Traditional themes' => [
        'yotsuba' => 'Yotsuba',
        'yotsuba-b' => 'Yotsuba B',
        'miku' => 'Miku',
        'tomorrow' => 'Tomorrow',
        'pink' => 'Pink',
        'green-dark' => 'Green Dark',
    ],
    'Experimental themes' => [
        'kings-gambit' => "King's Gambit",
        'queens-gambit' => "Queen's Gambit",
    ],
];
