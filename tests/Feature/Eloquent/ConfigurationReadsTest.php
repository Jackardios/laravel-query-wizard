<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Config\Repository;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
class ConfigurationReadsTest extends TestCase
{
    #[Test]
    public function a_build_reads_the_package_configuration_a_bounded_number_of_times(): void
    {
        $config = new class($this->app['config']->all()) extends Repository
        {
            public int $packageReads = 0;

            public function get($key, $default = null)
            {
                if ($key === 'query-wizard') {
                    $this->packageReads++;
                }

                return parent::get($key, $default);
            }
        };
        $this->app->instance('config', $config);

        $this->createEloquentWizardFromQuery([
            'filter' => ['name' => 'a', 'id' => '1'],
            'sort' => '-name,id',
            'include' => 'relatedModels,relatedModelsCount',
            'fields' => ['testModel' => 'id,name', 'relatedModels' => 'id,name'],
        ])
            ->allowedFilters(EloquentFilter::partial('name'), 'id')
            ->allowedSorts('name', 'id')
            ->allowedIncludes('relatedModels', 'relatedModelsCount')
            ->allowedFields('id', 'name', 'relatedModels.id', 'relatedModels.name')
            ->toQuery();

        $this->assertLessThanOrEqual(2, $config->packageReads);
    }
}
