<?php

namespace Jackardios\QueryWizard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Abstracts\AbstractQueryWizard;
use Jackardios\QueryWizard\Concerns\HandlesAppends;
use Jackardios\QueryWizard\Concerns\HandlesFields;
use Jackardios\QueryWizard\Concerns\HandlesIncludes;
use Jackardios\QueryWizard\Handlers\Model\Includes\AbstractModelInclude;
use Jackardios\QueryWizard\Handlers\Model\Includes\CountInclude;
use Jackardios\QueryWizard\Handlers\Model\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Handlers\Model\ModelQueryHandler;

/**
 * @mixin Model
 * @property ModelQueryHandler $queryHandler
 * @method ModelQueryHandler getHandler()
 * @method static ModelQueryWizard for(Model $subject, \Illuminate\Http\Request|null $request = null)
 */
class ModelQueryWizard extends AbstractQueryWizard
{
    use HandlesAppends;
    use HandlesFields;
    use HandlesIncludes;

    protected string $queryHandlerClass = ModelQueryHandler::class;

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
     * @return RelationshipInclude|CountInclude
     */
    public function makeDefaultIncludeHandler(string $includeName): AbstractModelInclude
    {
        $countSuffix = config('query-wizard.count_suffix');
        if (Str::endsWith($includeName, $countSuffix)) {
            $relation = Str::before($includeName, $countSuffix);
            return new CountInclude($relation, $includeName);
        }
        return new RelationshipInclude($includeName);
    }
}
