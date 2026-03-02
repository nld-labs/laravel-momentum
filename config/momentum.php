<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Search Scope
    |--------------------------------------------------------------------------
    |
    | The default Eloquent scope name used for search queries. Individual
    | controllers can override this via the $searchScope property.
    |
    */

    'searchScope' => 'search',

    /*
    |--------------------------------------------------------------------------
    | Session Key Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix used for session keys when persisting index state.
    | This is appended to the controller class name.
    |
    */

    'sessionKeyPrefix' => '--saved--',

    /*
    |--------------------------------------------------------------------------
    | Root View
    |--------------------------------------------------------------------------
    |
    | The default root view for Inertia responses.
    |
    */

    'rootView' => 'app',

    /*
    |--------------------------------------------------------------------------
    | Request Parameter Names
    |--------------------------------------------------------------------------
    |
    | The query parameter names used for index state.
    |
    */

    'paramNames' => [
        'search' => 'search',
        'sort' => 'sort',
        'perPage' => 'perPage',
        'go' => 'go',
        'back' => 'back',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Wrapping
    |--------------------------------------------------------------------------
    |
    | When true, JSON resources are returned without Laravel's 'data' wrapper.
    | Recommended for Inertia/SPA applications.
    |
    */

    'resource_wrapping' => true,

];
