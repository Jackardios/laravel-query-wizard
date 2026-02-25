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
     * Relationship existence checks are included using the relationship name suffixed with this string.
     * For example: GET /users?include=postsExists
     */
    'exists_suffix' => 'Exists',

    /*
     * By default the package will throw an `InvalidFilterQuery` exception when a filter in the
     * URL is not allowed in the `allowedFilters()` method.
     */
    'disable_invalid_filter_query_exception' => false,

    /*
     * By default the package will throw an `InvalidSortQuery` exception when a sort in the
     * URL is not allowed in the `allowedSorts()` method.
     */
    'disable_invalid_sort_query_exception' => false,

    /*
     * By default the package will throw an `InvalidIncludeQuery` exception when an include in the
     * URL is not allowed in the `allowedIncludes()` method.
     */
    'disable_invalid_include_query_exception' => false,

    /*
     * By default the package will throw an `InvalidFieldQuery` exception when a field in the
     * URL is not allowed in the `allowedFields()` method.
     */
    'disable_invalid_field_query_exception' => false,

    /*
     * By default the package will throw an `InvalidAppendQuery` exception when an append in the
     * URL is not allowed in the `allowedAppends()` method.
     */
    'disable_invalid_append_query_exception' => false,

    /*
     * By default the package inspects query string of request using $request->query().
     * You can change this behavior to inspect the request body using $request->input()
     * by setting this value to `body`.
     *
     * Possible values: `query_string`, `body`
     */
    'request_data_source' => 'query_string',

    /*
     * By default, explicit null/empty filter values skip the filter and do NOT use default().
     * Set this to true to apply filter default() even when request contains null/empty value.
     */
    'apply_filter_default_on_null' => false,

    'array_value_separator' => ',',

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
     * Per-parameter-type separators.
     *
     * Allows using different separators for different parameter types.
     * If a type-specific separator is not set, falls back to 'array_value_separator'.
     *
     * Example: Use semicolon for filters to allow commas in filter values:
     *   'separators' => ['filters' => ';']
     */
    'separators' => [
        // 'includes' => ',',
        // 'sorts' => ',',
        // 'fields' => ',',
        // 'appends' => ',',
        // 'filters' => ',',
    ],

    /*
     * Runtime optimizations for relation field handling.
     *
     * relation_select_mode:
     *
     * - 'safe' (recommended):
     *   Automatically injects foreign key columns required for eager loading.
     *   Protects accessors by using SELECT * + makeHidden() for relations with appends.
     *   Works with: BelongsTo, HasOne, HasMany, MorphOne, MorphMany.
     *
     * - 'off':
     *   No automatic FK injection. You must manually include all required columns.
     *   WARNING: Eager loading may fail silently if FK columns are not selected.
     *   Use only when you need maximum performance and know exactly which fields are needed.
     *
     * See "Relation Field Modes" section in README for detailed documentation.
     */
    'optimizations' => [
        'relation_select_mode' => 'safe',
    ],

    /*
     * Security limits to protect against resource exhaustion attacks.
     * Set to null to disable a specific limit.
     */
    'limits' => [
        'max_includes_count' => 10,
        'max_include_depth' => 3,
        'max_filters_count' => 20,
        'max_appends_count' => 20,
        'max_append_depth' => 3,
        'max_sorts_count' => 5,
    ],
];
