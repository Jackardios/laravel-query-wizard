<?php

namespace Jackardios\QueryWizard\Handlers\Model;

use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;
use Jackardios\QueryWizard\EloquentModelWizard;
use Jackardios\QueryWizard\Handlers\Model\Includes\AbstractModelInclude;

/**
 * @property EloquentModelWizard $wizard
 * @property Model $subject
 * @method EloquentModelWizard getWizard()
 * @method Model getSubject()
 */
class EloquentModelHandler extends AbstractQueryHandler
{
    protected static string $baseIncludeHandlerClass = AbstractModelInclude::class;

    /**
     * @param EloquentModelWizard $wizard
     * @param Model $subject
     */
    public function __construct(EloquentModelWizard $wizard, Model $subject)
    {
        parent::__construct($wizard, $subject);
    }

    /**
     * @return EloquentModelHandler
     */
    public function handle(): EloquentModelHandler
    {
        return $this->handleFields()
            ->handleIncludes()
            ->handleAppends();
    }

    protected function handleFields(): self
    {
        $requestedFields = $this->wizard->getFields();
        $defaultFieldsKey = $this->wizard->getDefaultFieldsKey();
        $modelFields = $requestedFields->get($defaultFieldsKey);

        if (!empty($modelFields)) {
            $this->subject = $this->subject
                ->newInstance([], true)
                ->setRawAttributes($this->subject->only($modelFields));
        }

        return $this;
    }

    protected function handleIncludes(): self
    {
        $requestedIncludes = $this->wizard->getIncludes();
        $handlers = $this->wizard->getAllowedIncludes();

        $requestedIncludes->each(function($include) use ($handlers) {
            $handler = $handlers->get($include);
            if ($handler) {
                $handler->handle($this, $this->subject);
            }
        });

        return $this;
    }

    protected function handleAppends(): self
    {
        $requestedAppends = $this->wizard->getAppends();

        if ($requestedAppends->isNotEmpty()) {
            $this->subject->append($requestedAppends->toArray());
        }

        return $this;
    }
}
