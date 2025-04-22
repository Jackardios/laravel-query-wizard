<?php

namespace Jackardios\QueryWizard\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Abstracts\AbstractQueryWizard;
use Jackardios\QueryWizard\Concerns\HandlesAppends;
use Jackardios\QueryWizard\Concerns\HandlesFields;
use Jackardios\QueryWizard\Concerns\HandlesIncludes;
use Jackardios\QueryWizard\Model\Includes\CountInclude;
use Jackardios\QueryWizard\Model\Includes\RelationshipInclude;
use Jackardios\QueryWizard\QueryParametersManager;

/**
 * @method static static for(Model $subject, QueryParametersManager|null $parametersManager = null)
 * @mixin Model
 */
class ModelQueryWizard extends AbstractQueryWizard
{
    use HandlesAppends;
    use HandlesFields;
    use HandlesIncludes;

    /** @var Model */
    protected $subject;

    protected array $baseIncludeHandlerClasses = [ModelInclude::class];

    public function __construct(Model $subject, ?QueryParametersManager $parametersManager = null)
    {
        parent::__construct($subject, $parametersManager);
    }

    public function rootFieldsKey(): string
    {
        return Str::camel(class_basename($this->subject));
    }

    public function build(): Model
    {
        $this->handleFields()
            ->handleIncludes()
            ->handleAppends();

        return $this->subject;
    }

    /**
     * @param string $includeName
     * @return RelationshipInclude|CountInclude
     */
    public function makeDefaultIncludeHandler(string $includeName): ModelInclude
    {
        $countSuffix = config('query-wizard.count_suffix');
        if (Str::endsWith($includeName, $countSuffix)) {
            $relation = Str::before($includeName, $countSuffix);
            return new CountInclude($relation, $includeName);
        }
        return new RelationshipInclude($includeName);
    }


    protected function handleFields(): static
    {
        $requestedRootFields = $this->getRootFields();

        if (! $requestedRootFields || in_array('*', $requestedRootFields)) {
            return $this;
        }

        $allRootFields = $this->getAllowedFields($this->rootFieldsKey())->all();
        $fieldsToHide = array_diff($allRootFields, $requestedRootFields);

        if ($fieldsToHide) {
            $this->subject->makeHidden($fieldsToHide);
        }

        return $this;
    }

    protected function handleIncludes(): static
    {
        $requestedIncludes = $this->getIncludes();
        $handlers = $this->getAllowedIncludes();

        $requestedIncludes->each(function($include) use ($handlers) {
            /** @var ModelInclude|null $handler */
            $handler = $handlers->get($include);
            $handler?->handle($this, $this->subject);
        });

        return $this;
    }

    protected function handleAppends(): static
    {
        $requestedAppends = $this->getAppends();

        if ($requestedAppends->isNotEmpty()) {
            $this->subject->append($requestedAppends->toArray());
        }

        return $this;
    }
}
