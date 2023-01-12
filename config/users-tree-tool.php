<?php
return [
    /*
     * string
     */
    'model' => config('auth.providers.users.model'),

    /*
     * bool|closure
     */
    'with-trashed' => false,

    /*
     * string
     */
    'title-field' => 'name',

    /*
     * null|closure
     */
    'title-format' => null,

    /*
     * string
     */
    'parent-field' => 'parent',

    /*
     * string
     */
    'children-field' => 'children',

    /*
     * string
     */
    'resource' => 'users',

    'search-columns' => [
        'name',
        'email',
    ],

    'search-relations-columns' => [],

    'query-columns' => ['id', 'parent_id', 'name', 'email'],

    'query-with' => [],

    /*
     * int
     */
    'search-limit' => 1,

    /*
     * bool|closure
     */
    'start-from-current' => false,

    /*
     * int|null|closure
     */
    'max-level' => null,

    /*
     * bool|closure
     */
    'can-search' => true,

    /*
     * bool|closure
     */
    'can-open-user' => true,

    'root' => [

        /*
         * string|null
         */
        'title' => null,

    ],
];
