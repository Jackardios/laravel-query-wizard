<?php

namespace Jackardios\QueryWizard;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Abstracts\AbstractQueryWizard;
use Jackardios\QueryWizard\Concerns\HandlesAppends;
use Jackardios\QueryWizard\Concerns\HandlesFields;
use Jackardios\QueryWizard\Concerns\HandlesFilters;
use Jackardios\QueryWizard\Concerns\HandlesIncludes;
use Jackardios\QueryWizard\Concerns\HandlesSorts;
use Jackardios\QueryWizard\Handlers\Eloquent\EloquentQueryHandler;
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\ExactFilter;
use Jackardios\QueryWizard\Handlers\Eloquent\Includes\CountInclude;
use Jackardios\QueryWizard\Handlers\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Handlers\Eloquent\Sorts\FieldSort;

/**
 * @mixin Builder
 * @property EloquentQueryHandler $queryHandler
 * @method EloquentQueryHandler getHandler()
 * @method static EloquentQueryWizard for(Model|Builder|Relation|string $subject, \Illuminate\Http\Request|null $request = null)
 */
class EloquentQueryWizard extends AbstractQueryWizard
{
    use HandlesAppends;
    use HandlesFields;
    use HandlesFilters;
    use HandlesIncludes;
    use HandlesSorts;

    protected string $queryHandlerClass = EloquentQueryHandler::class;

    protected function defaultFieldsKey(): string
    {
        return $this->queryHandler->getSubject()->getModel()->getTable();
    }

    public function makeDefaultFilterHandler(string $filterName): ExactFilter
    {
        return new ExactFilter($filterName);
    }

    /**
     * @param string $includeName
     * @return RelationshipInclude|CountInclude
     */
    public function makeDefaultIncludeHandler(string $includeName)
    {
        $countSuffix = config('query-wizard.count_suffix');
        if (Str::endsWith($includeName, $countSuffix)) {
            $relation = Str::before($includeName, $countSuffix);
            return new CountInclude($relation, $includeName);
        }
        return new RelationshipInclude($includeName);
    }

    public function makeDefaultSortHandler(string $sortName): FieldSort
    {
        return new FieldSort($sortName);
    }
}
