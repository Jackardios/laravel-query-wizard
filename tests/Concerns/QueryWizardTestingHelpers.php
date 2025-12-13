<?php

namespace Jackardios\QueryWizard\Tests\Concerns;

use Illuminate\Http\Request;
use Jackardios\QueryWizard\QueryWizard;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Tests\App\Models\AppendModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Wizards\ListQueryWizard;

trait QueryWizardTestingHelpers
{
    protected function createEloquentWizardFromQuery(array $query = [], mixed $subject = null): ListQueryWizard
    {
        $subject = $subject ?? TestModel::class;
        return QueryWizard::for($subject, new QueryParametersManager(new Request($query)));
    }

    protected function createEloquentWizardWithAppends(string|array $appends, $subject = null): ListQueryWizard
    {
        return $this->createEloquentWizardFromQuery([
            'append' => $appends,
        ],  $subject ?? AppendModel::class);
    }

    protected function createEloquentWizardWithIncludes(array|string $includes, $subject = null): ListQueryWizard
    {
        return $this->createEloquentWizardFromQuery([
            'include' => $includes,
        ], $subject ?? null);
    }

    protected function createEloquentWizardWithFields(array|string $fields, $subject = null): ListQueryWizard
    {
        return $this->createEloquentWizardFromQuery([
            'fields' => $fields,
        ], $subject ?? null);
    }

    protected function createEloquentWizardWithSorts(array|string $sorts, $subject = null): ListQueryWizard
    {
        return $this->createEloquentWizardFromQuery([
            'sort' => $sorts,
        ], $subject ?? null);
    }

    protected function createEloquentWizardWithFilters(array $filters, $subject = null): ListQueryWizard
    {
        return $this->createEloquentWizardFromQuery([
            'filter' => $filters,
        ], $subject ?? null);
    }

    protected function createModelWizardFromQuery(array $query = [], $model = null): ListQueryWizard
    {
        return QueryWizard::for($model, new QueryParametersManager(new Request($query)));
    }

    protected function createModelWizardWithAppends(string|array $appends, $model = null): ListQueryWizard
    {
        return $this->createModelWizardFromQuery([
            'append' => $appends,
        ], $model ?? AppendModel::query()->first());
    }

    protected function createModelWizardWithIncludes(array|string $includes, $model = null): ListQueryWizard
    {
        return $this->createModelWizardFromQuery([
            'include' => $includes,
        ], $model ?? null);
    }

    protected function createModelWizardWithFields(array|string $fields, $model = null): ListQueryWizard
    {
        return $this->createModelWizardFromQuery([
            'fields' => $fields,
        ], $model ?? null);
    }
}
