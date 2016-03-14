<?php

return array(

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The database connection used to store the A/B testing information.
    |
    */

    'connection' => '',

    /*
    |--------------------------------------------------------------------------
    | Experiments
    |--------------------------------------------------------------------------
    |
    | A list of experiments with their variant identifiers.
    |
    | Example: [
    |     'logo' => ['big', 'small'],
    |     'copy' => ['a', 'b']
    | ]
    |
    */

    'experiments' => [],

    /*
    |--------------------------------------------------------------------------
    | Goals
    |--------------------------------------------------------------------------
    |
    | A list of goals. This list can contain urls, route names or custom goals.
    |
    | Example: ['pricing/order', 'signup']
    |
    */

    'goals' => [],

    /*
    |--------------------------------------------------------------------------
    | Complete Multiple
    |--------------------------------------------------------------------------
    |
    | Allow goals to be completed more than once per user.
    |
    */

    'complete_multiple' => false,

);
