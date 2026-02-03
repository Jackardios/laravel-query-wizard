<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EloquentAppendHandler
{
    /**
     * Apply appends to result
     *
     * @param array<string> $appends
     */
    public function applyAppends(mixed $result, array $appends): mixed
    {
        if (empty($appends)) {
            return $result;
        }

        $rootAppends = [];
        /** @var array<string, array<string>> $relationAppends */
        $relationAppends = [];

        foreach ($appends as $append) {
            if (str_contains($append, '.')) {
                $lastDotPos = strrpos($append, '.');
                $relationPath = substr($append, 0, $lastDotPos);
                $appendName = substr($append, $lastDotPos + 1);
                $relationAppends[$relationPath][] = $appendName;
            } else {
                $rootAppends[] = $append;
            }
        }

        if (!empty($rootAppends)) {
            $this->applyAppendsToModels($result, $rootAppends);
        }

        foreach ($relationAppends as $relationPath => $relAppends) {
            $this->applyAppendsToRelation($result, $relationPath, $relAppends);
        }

        return $result;
    }

    /**
     * Apply appends to models (root level)
     *
     * @param array<string> $appends
     */
    public function applyAppendsToModels(mixed $models, array $appends): void
    {
        if ($models instanceof Model) {
            $models->append($appends);
        } elseif ($models instanceof Collection || is_iterable($models)) {
            foreach ($models as $model) {
                if ($model instanceof Model) {
                    $model->append($appends);
                }
            }
        }
    }

    /**
     * Apply appends to models in a relation (supports nested dot notation)
     *
     * @param array<string> $appends
     */
    public function applyAppendsToRelation(mixed $models, string $relationPath, array $appends): void
    {
        $parts = explode('.', $relationPath);

        /** @var array<Model> $modelsToProcess */
        $modelsToProcess = [];

        if ($models instanceof Model) {
            $modelsToProcess = [$models];
        } elseif ($models instanceof Collection) {
            $modelsToProcess = $models->all();
        } elseif (is_iterable($models)) {
            foreach ($models as $model) {
                if ($model instanceof Model) {
                    $modelsToProcess[] = $model;
                }
            }
        }

        foreach ($parts as $relationName) {
            /** @var array<Model> $nextModels */
            $nextModels = [];

            foreach ($modelsToProcess as $model) {
                if ($model->relationLoaded($relationName)) {
                    $related = $model->getRelation($relationName);

                    if ($related instanceof Collection) {
                        foreach ($related as $item) {
                            if ($item instanceof Model) {
                                $nextModels[] = $item;
                            }
                        }
                    } elseif ($related instanceof Model) {
                        $nextModels[] = $related;
                    }
                }
            }

            $modelsToProcess = $nextModels;
        }

        foreach ($modelsToProcess as $model) {
            $model->append($appends);
        }
    }
}
