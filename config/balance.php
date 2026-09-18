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
    | last matches, relative to the group's average in those same matches. The
    | ratio is clamped so a single bad night does not wreck a profile.
    */
    'form_window' => 3,
    'form_min' => 0.5,
    'form_max' => 1.5,

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
