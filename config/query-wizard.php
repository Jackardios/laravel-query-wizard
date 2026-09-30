<?php

return [

    /*
     * By default the package will use the `include`, `filter`, `sort`,
     * `fields` and `append` query parameters as described in the readme.
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
     * By default the package inspects query string of request using $request->query().
     * You can change this behavior to inspect only the request body payload
     * by setting this value to `body`.
     *
     * Possible values: `query_string`, `body`
     */
    'request_data_source' => 'query_string',

    /*
     * Naming conversion options.
     */
    'naming' => [
        /*
         * When true, camelCase parameter names are automatically converted to snake_case.
         *
         * Example: ?filter[firstName]=John -> internally: filter[first_name]=John
         *
         * This allows API consumers to use camelCase while your database uses snake_case.
         */
        'convert_parameters_to_snake_case' => false,
    ],

    /*
     * Separators that split list parameters.
     *
     * `default` applies to every parameter type without a separator of its own.
     *
     * Example: Use semicolon for filters to allow commas in filter values:
     *   'separators' => ['filters' => ';']
     *
     * To keep commas in a single filter only, call withoutValueSplitting() on it.
     * Partial filters never split their value.
     */
    'separators' => [
        'default' => ',',
        // 'includes' => ',',
        // 'sorts' => ',',
        // 'fields' => ',',
        // 'appends' => ',',
        // 'filters' => ',',
    ],

    /*
     * By default a request naming a filter, sort, include, field or append that is
     * not allowed is rejected with a 400 (`filter_not_allowed`, ...). Set a type to
     * true to drop such names instead. Malformed parameters are rejected either way.
     */
    'ignore_unknown' => [
        'filters' => false,
        'sorts' => false,
        'includes' => false,
        'fields' => false,
        'appends' => false,
    ],

    'includes' => [
        /*
         * Related model counts are included using the relationship name suffixed with this string.
         * For example: GET /users?include=postsCount
         */
        'count_suffix' => 'Count',

        /*
         * Relationship existence checks are included using the relationship name suffixed with this string.
         * For example: GET /users?include=postsExists
         */
        'exists_suffix' => 'Exists',
    ],

    'filters' => [
        /*
         * By default, explicit null/empty filter values skip the filter and do NOT use default().
         * Set this to true to apply filter default() even when request contains null/empty value.
         */
        'apply_default_on_null' => false,
    ],

    /*
     * Sparse fieldsets configuration.
     */
    'fields' => [
        /*
         * When true and ?fields is absent, default fields resolve in this order:
         * 1. explicit defaultFields() on the wizard
         * 2. schema defaultFields()
         * 3. effective allowed fields
         *
         * This only affects default field selection. It does not allow arbitrary
         * ?fields requests when allowed fields are not configured.
         */
        'use_allowed_as_default' => false,
    ],

    /*
     * Security limits to protect against resource exhaustion attacks.
     * Set to null to disable a specific limit.
     */
    'limits' => [
        'max_includes_count' => 10,
        'max_include_depth' => 3,
        'max_filters_count' => 20,
        'max_filter_values_count' => 1000,
        'max_fields_count' => 100,
        'max_appends_count' => 20,
        'max_append_depth' => 3,
        'max_sorts_count' => 5,
    ],
];
