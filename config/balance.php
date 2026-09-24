<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Profile weights
    |--------------------------------------------------------------------------
    | A player's attribute profile blends their own self-assessment with the
    | average of the evaluations organizers have given them. self_weight is
    | the share of the self-assessment; the rest goes to the organizers. When
    | no organizer has evaluated the player, the self-assessment is used alone.
    */
    'self_weight' => 0.5,

    /*
    |--------------------------------------------------------------------------
    | Recent form
    |--------------------------------------------------------------------------
    | The profile is scaled by the general rating the player received in their
    | last matches. Ratings are stored 0-10; centered on the neutral 5, each
    | point above or below the neutral level moves the profile ±4%, so the
    | multiplier stays between form_min and form_max (±20%).
    */
    'form_window' => 3,
    'form_min' => 0.8,
    'form_max' => 1.2,

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
