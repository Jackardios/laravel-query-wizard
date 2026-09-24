<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent;

/**
 * Post-processing state derived from one build of an EloquentQueryWizard.
 *
 * The wizard replaces the whole object whenever the build is invalidated, so a
 * new piece of derived state cannot be forgotten by one of the reset paths.
 *
 * @internal
 */
final class EloquentBuildState
{
    /** @var array{fields: array<string>, relations: array<string, mixed>} */
    public array $relationFieldTree = [
        'fields' => [],
        'relations' => [],
    ];

    public bool $relationFieldTreePrepared = false;

    /** @var array{appends: array<string>, relations: array<string, mixed>} */
    public array $appendTree = [
        'appends' => [],
        'relations' => [],
    ];

    public bool $appendTreePrepared = false;

    /** @var array<string> */
    public array $safeRootHiddenFields = [];

    /** @var array<string>|null */
    public ?array $rootVisibleFields = null;

    /** @var array<string, string> */
    public array $runtimeRootAttributeNamesByField = [];

    /** @var array<string> */
    public array $alwaysVisibleRuntimeRootAttributes = [];

    /** @var array<string, array<string, string>> */
    public array $runtimeRelationAttributes = [];
}
