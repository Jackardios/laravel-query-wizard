<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Http\Request;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\QueryWizard\ModelQueryWizard;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Structurally malformed ?fields / ?append requests are client errors, not 500s.
 */
#[Group('eloquent')]
#[Group('fields')]
class MalformedFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestModel::factory()->create(['name' => 'a']);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function malformedFields(): array
    {
        return [
            'nested list' => [['fields' => [['id']]]],
            'nested value in a fieldset' => [['fields' => ['testModel' => [['id']]]]],
            'nested value next to a valid one' => [['fields' => ['testModel' => ['id', ['name']]]]],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    #[Test]
    #[DataProvider('malformedFields')]
    public function malformed_fields_are_rejected(array $query): void
    {
        try {
            $this->wizard($query)->allowedFields('id', 'name')->get();
            $this->fail('Expected InvalidFieldQuery');
        } catch (InvalidFieldQuery $exception) {
            $this->assertSame(400, $exception->getStatusCode());
            $this->assertSame('invalid_field_format', $exception->errorCode);
            $this->assertSame('fields', $exception->parameter);
            $this->assertStringStartsWith('The `fields` parameter has an invalid format.', $exception->getMessage());
        }
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function malformedAppends(): array
    {
        return [
            'nested list' => [['append' => [['fullname']]]],
            'nested value in a group' => [['append' => ['testModel' => [['fullname']]]]],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    #[Test]
    #[DataProvider('malformedAppends')]
    public function malformed_appends_are_rejected(array $query): void
    {
        try {
            $this->wizard($query)->allowedAppends('fullname')->get();
            $this->fail('Expected InvalidAppendQuery');
        } catch (InvalidAppendQuery $exception) {
            $this->assertSame(400, $exception->getStatusCode());
            $this->assertSame('invalid_append_format', $exception->errorCode);
            $this->assertSame('append', $exception->parameter);
            $this->assertStringStartsWith('The `append` parameter has an invalid format.', $exception->getMessage());
        }
    }

    #[Test]
    public function model_wizard_rejects_malformed_fields(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        (new ModelQueryWizard(TestModel::query()->firstOrFail(), new QueryParametersManager(new Request(['fields' => [['id']]]))))
            ->allowedFields('id')
            ->process();
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function snakeCaseModes(): array
    {
        return ['as sent' => [false], 'converted to snake case' => [true]];
    }

    #[Test]
    #[DataProvider('snakeCaseModes')]
    public function integer_fieldset_keys_are_unknown_fieldsets(bool $snakeCase): void
    {
        config()->set('query-wizard.naming.convert_parameters_to_snake_case', $snakeCase);

        try {
            $this->wizard(['fields' => ['testModel' => 'id', 5 => 'name']])->allowedFields('id', 'name')->get();
            $this->fail('Expected InvalidFieldQuery');
        } catch (InvalidFieldQuery $exception) {
            $this->assertSame('field_not_allowed', $exception->errorCode);
            $this->assertSame(['5.name'], $exception->unknownFields->all());
        }
    }

    #[Test]
    public function well_formed_lists_are_still_accepted(): void
    {
        $model = $this->wizard(['fields' => ['testModel' => ['id', 'name']], 'append' => ['fullname']])
            ->allowedFields('id', 'name')
            ->allowedAppends('fullname')
            ->get()
            ->first();

        $this->assertSame(['id', 'name', 'fullname'], array_keys($model->toArray()));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function wizard(array $query): EloquentQueryWizard
    {
        return new EloquentQueryWizard(TestModel::query(), new QueryParametersManager(new Request($query)));
    }
}
