<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Balancing weights
    |--------------------------------------------------------------------------
    | A player's composite score blends their own self-assessment with the
    | ratings that other players have given them. Weights must add up to 1.
    | When a player has no ratings from others, their self-assessment is
    | used on its own.
    */
    'self_weight' => 0.4,
    'others_weight' => 0.6,

    /*
    | Team sizes (players per team) the generator can produce, tried from
    | largest to smallest as attendance falls short.
    */
    'sizes' => [5, 4, 3],

    /*
    | Attribute keys shared by self-assessment and peer ratings.
    */
    'attributes' => ['speed', 'skill', 'passing', 'shooting', 'defense'],
];
