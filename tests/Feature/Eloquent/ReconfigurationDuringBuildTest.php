<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
class ReconfigurationDuringBuildTest extends TestCase
{
    #[Test]
    public function a_schema_method_cannot_reconfigure_the_eloquent_wizard(): void
    {
        TestModel::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A schema method cannot reconfigure the wizard it receives');

        $this->createEloquentWizardFromQuery(['include' => 'relatedModels'])
            ->schema($this->reconfiguringSchema())
            ->get();
    }

    #[Test]
    public function a_schema_method_cannot_reconfigure_the_model_wizard(): void
    {
        $model = TestModel::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A schema method cannot reconfigure the wizard it receives');

        $this->createModelWizardFromQuery(['include' => 'relatedModels'], $model)
            ->schema($this->reconfiguringSchema())
            ->process();
    }

    #[Test]
    public function a_tap_callback_cannot_reconfigure_the_wizard_while_it_builds(): void
    {
        $wizard = $this->createEloquentWizardFromQuery();
        $wizard->allowedSorts('name')->tap(function () use ($wizard): void {
            $wizard->allowedSorts('id');
        });

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The wizard cannot be reconfigured while it builds.');

        $wizard->get();
    }

    #[Test]
    public function the_schema_can_be_set_and_read_through_the_interface(): void
    {
        $configure = static fn (QueryWizardInterface $wizard): QueryWizardInterface => $wizard->schema(TestModelSchema::class);

        $wizard = $configure(EloquentQueryWizard::for(TestModel::class));

        $this->assertInstanceOf(TestModelSchema::class, $wizard->getSchema());
    }

    private function reconfiguringSchema(): ResourceSchema
    {
        return new class extends ResourceSchema
        {
            public function model(): string
            {
                return TestModel::class;
            }

            public function includes(QueryWizardInterface $wizard): array
            {
                $wizard->allowedFields('id');

                return ['relatedModels'];
            }
        };
    }
}

final class TestModelSchema extends ResourceSchema
{
    public function model(): string
    {
        return TestModel::class;
    }
}
