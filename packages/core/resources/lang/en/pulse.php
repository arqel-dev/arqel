<?php

declare(strict_types=1);

return [
    // Pulse dashboard cards (admin panel). User-facing copy localised so a
    // non-English panel does not render English chrome in the Pulse cards.
    'resources' => [
        // trans_choice — pluralised registered-resources caption. The count is
        // rendered separately (big number); this is only the noun caption, so
        // the English singular/plural ("Resource registered" / "Resources
        // registered") is selected via CLDR forms, not an inline s-suffix.
        'registered' => '{1} Resource registered|[2,*] Resources registered',
    ],
];
