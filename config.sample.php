<?php
// Kopiera till config.php och byt hashen mot din egen kod.
// Skapa hashen i Terminal:
//   php -r 'echo password_hash("din-kod", PASSWORD_DEFAULT), "\n";'
// Koden är den som resenärerna skriver in första gången de laddar upp en bild.
return [
    'code_hash' => '$2y$10$ERSATT-MED-DIN-EGEN-HASH',
];
