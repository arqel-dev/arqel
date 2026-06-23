<?php

declare(strict_types=1);

return [
    // Cards do dashboard Pulse (painel admin). Copy localizado para que um
    // painel não-inglês não renderize chrome em inglês nos cards do Pulse.
    'resources' => [
        // trans_choice — legenda pluralizada de Resources registrados. A
        // contagem é renderizada à parte (número grande); aqui é só a legenda
        // nominal, com singular/plural via formas CLDR.
        'registered' => '{1} Resource registrado|[2,*] Resources registrados',
    ],
];
