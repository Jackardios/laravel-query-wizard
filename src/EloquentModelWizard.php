<?php

namespace Jackardios\QueryWizard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Abstracts\AbstractQueryWizard;
use Jackardios\QueryWizard\Concerns\HandlesAppends;
use Jackardios\QueryWizard\Concerns\HandlesFields;
use Jackardios\QueryWizard\Concerns\HandlesIncludes;
use Jackardios\QueryWizard\Handlers\Model\Includes\AbstractModelInclude;
use Jackardios\QueryWizard\Handlers\Model\Includes\IncludedCount;
use Jackardios\QueryWizard\Handlers\Model\Includes\IncludedRelationship;
use Jackardios\QueryWizard\Handlers\Model\EloquentModelHandler;

/**
 * @mixin Model
 * @property EloquentModelHandler $queryHandler
 * @method static EloquentModelWizard for(Model $subject, \Illuminate\Http\Request|null $request = null)
 */
class EloquentModelWizard extends AbstractQueryWizard
{
    use HandlesAppends;
    use HandlesFields;
    use HandlesIncludes;

    protected string $queryHandlerClass = EloquentModelHandler::class;

    protected function defaultFieldsKey(): string
    {
        return $this->queryHandler->getSubject()->getTable();
    }

    /**
     * @return Model
     */
    public function build()
    {
        $this->queryHandler->handle();

        return $this->queryHandler->getSubject();
    }

    /**
     * @param string $includeName
     * @return IncludedRelationship|IncludedCount
     */
    public function makeDefaultIncludeHandler(string $includeName): AbstractModelInclude
    {
        $countSuffix = config('query-wizard.count_suffix');
        if (Str::endsWith($includeName, $countSuffix)) {
            $relation = Str::before($includeName, $countSuffix);
            return new IncludedCount($relation, $includeName);
        }
        return new IncludedRelationship($includeName);
    }
}
