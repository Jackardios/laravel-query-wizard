<?php

return [

    /*
     * By default the package will use the `include`, `filter`, `sort`
     * and `fields` query parameters as described in the readme.
     *
     * You can customize these query string parameters here.
     */
    'parameters' => [
        'includes' => 'include',

        'filters' => 'filter',

        'sorts' => 'sort',

        'fields' => 'fields',

        'appends' => 'append',
    ],

    /*
     * Related model counts are included using the relationship name suffixed with this string.
     * For example: GET /users?include=postsCount
     */
    'count_suffix' => 'Count',

    /*
     * By default the package will throw an `InvalidFilterQuery` exception when a filter in the
     * URL is not allowed in the `setAllowedFilters()` method.
     */
    'disable_invalid_filter_query_exception' => false,

    /*
     * By default the package inspects query string of request using $request->query().
     * You can change this behavior to inspect the request body using $request->input()
     * by setting this value to `body`.
     *
     * Possible values: `query_string`, `body`
     */
    'request_data_source' => 'query_string',

    'array_value_separator' => ',',

    /*
     * Custom drivers to register.
     * Key is the driver name, value is the driver class.
     *
     * Example:
     * 'drivers' => [
     *     'scout' => \App\QueryWizard\Drivers\ScoutDriver::class,
     * ],
     */
    'drivers' => [],

    /*
     * Security limits to protect against resource exhaustion attacks.
     * Set to null to disable a specific limit.
     */
    'limits' => [
        'max_include_depth' => 5,
        'max_includes_count' => 10,
        'max_filters_count' => 15,
        'max_filter_depth' => 5,
        'max_sorts_count' => 5,
    ],
];
