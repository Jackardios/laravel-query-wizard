<?php

namespace Jackardios\QueryWizard\Tests\Concerns;

use Illuminate\Http\Request;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\Model\ModelQueryWizard;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Tests\App\Models\AppendModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;

trait QueryWizardTestingHelpers
{
    protected function createEloquentWizardFromQuery(array $query = [], $subject = null): EloquentQueryWizard
    {
        return EloquentQueryWizard::for($subject ?? TestModel::class, new QueryParametersManager(new Request($query)));
    }

    protected function createEloquentWizardWithAppends(string|array $appends, $subject = null): EloquentQueryWizard
    {
        return $this->createEloquentWizardFromQuery([
            'append' => $appends,
        ],  $subject ?? AppendModel::class);
    }

    protected function createEloquentWizardWithIncludes(array|string $includes, $subject = null): EloquentQueryWizard
    {
        return $this->createEloquentWizardFromQuery([
            'include' => $includes,
        ], $subject ?? null);
    }

    protected function createEloquentWizardWithFields(array|string $fields, $subject = null): EloquentQueryWizard
    {
        return $this->createEloquentWizardFromQuery([
            'fields' => $fields,
        ], $subject ?? null);
    }

    protected function createEloquentWizardWithSorts(array|string $sorts, $subject = null): EloquentQueryWizard
    {
        return $this->createEloquentWizardFromQuery([
            'sort' => $sorts,
        ], $subject ?? null);
    }

    protected function createEloquentWizardWithFilters(array $filters, $subject = null): EloquentQueryWizard
    {
        return $this->createEloquentWizardFromQuery([
            'filter' => $filters,
        ], $subject ?? null);
    }

    protected function createModelWizardFromQuery(array $query = [], $model = null): ModelQueryWizard
    {
        return ModelQueryWizard::for($model, new QueryParametersManager(new Request($query)));
    }

    protected function createModelWizardWithAppends(string|array $appends, $model = null): ModelQueryWizard
    {
        return $this->createModelWizardFromQuery([
            'append' => $appends,
        ], $model ?? AppendModel::query()->first());
    }

    protected function createModelWizardWithIncludes(array|string $includes, $model = null): ModelQueryWizard
    {
        return $this->createModelWizardFromQuery([
            'include' => $includes,
        ], $model ?? null);
    }

    protected function createModelWizardWithFields(array|string $fields, $model = null): ModelQueryWizard
    {
        return $this->createModelWizardFromQuery([
            'fields' => $fields,
        ], $model ?? null);
    }
}
