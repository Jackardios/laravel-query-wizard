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

    protected function defaultFieldsKey(): string
    {
        return $this->subject->getTable();
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
        $requestedFields = $this->getFields();
        $defaultFieldsKey = $this->getDefaultFieldsKey();
        $modelFields = $requestedFields->get($defaultFieldsKey);

        if (!empty($modelFields)) {
            $this->subject = $this->subject
                ->newInstance([], true)
                ->setRawAttributes($this->subject->only($modelFields));
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
