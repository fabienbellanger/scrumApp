<?php

    /*
    |--------------------------------------------------------------------------
    | User Routes
    |--------------------------------------------------------------------------
    |
    */

    Route::group(['prefix' => 'users/{id}'], function()
    {
        // Team
        // ----
        Route::get('/teams', 'UserController@getTeams')->where('id', '[0-9]+');

        // Sprints
        // -------
        include_once('sprint.php');
    });
