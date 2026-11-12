<?php

return [

    /*
    | Tipo de cambio: cuántos dólares (USD) vale 1 euro (EUR).
    | Se puede cambiar en el .env sin tocar el código: TASA_EUR_USD=1.10
    */
    'tasa_eur_usd' => (float) env('TASA_EUR_USD', 1.08),

];
