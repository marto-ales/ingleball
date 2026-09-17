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
    |--------------------------------------------------------------------------
    | Attribute weights
    |--------------------------------------------------------------------------
    | How much each characteristic matters when comparing two players. Speed
    | is the heaviest, then skill; the rest share the remaining weight. They
    | must add up to 1. Used both to score a player's overall power and to
    | measure how different two players are.
    */
    'attribute_weights' => [
        'speed' => 0.30,
        'skill' => 0.25,
        'passing' => 0.15,
        'shooting' => 0.15,
        'defense' => 0.15,
    ],

    /*
    | Team sizes (players per team) the generator can produce, tried from
    | largest to smallest as attendance falls short.
    */
    'sizes' => [6, 5, 4],

    /*
    | Attribute keys shared by self-assessment and peer ratings.
    */
    'attributes' => ['speed', 'skill', 'passing', 'shooting', 'defense'],
];
