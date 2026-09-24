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
     * @return array<string, array{array<string, mixed>, array<int, string>, string}>
     */
    public static function invalidTokens(): array
    {
        return [
            'alias under the root wildcard' => [['fields' => ['testModel' => 'name as id']], ['*'], 'name as id'],
            'expression under the root wildcard' => [['fields' => ['testModel' => 'count(*)']], ['*'], 'count(*)'],
            'digits under the root wildcard' => [['fields' => ['testModel' => '1']], ['*'], '1'],
            'allowed relation field sent as a root field' => [['fields' => ['testModel' => 'relatedModels.name']], ['name', 'relatedModels.name'], 'relatedModels.name'],
            'alias under a relation wildcard' => [
                ['include' => 'relatedModels', 'fields' => ['relatedModels' => 'name as test_model_id']],
                ['relatedModels.*'],
                'relatedModels.name as test_model_id',
            ],
            'dotted relation field' => [
                ['include' => 'relatedModels', 'fields' => ['relatedModels' => 'nestedRelatedModels.name']],
                ['relatedModels.name', 'relatedModels.nestedRelatedModels.name'],
                'relatedModels.nestedRelatedModels.name',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<int, string>  $allowedFields
     */
    #[Test]
    #[DataProvider('invalidTokens')]
    public function tokens_that_are_not_field_names_are_rejected(array $query, array $allowedFields, string $token): void
    {
        try {
            $this->wizard($query)
                ->allowedIncludes('relatedModels', 'relatedModels.nestedRelatedModels')
                ->allowedFields(...$allowedFields)
                ->get();
            $this->fail('Expected InvalidFieldQuery');
        } catch (InvalidFieldQuery $exception) {
            $this->assertSame('invalid_field_format', $exception->errorCode);
            $this->assertSame("The `fields` parameter has an invalid format. `{$token}` is not a valid field name.", $exception->getMessage());
        }
    }

    #[Test]
    public function identifier_tokens_are_accepted_under_wildcards(): void
    {
        $sql = $this->wizard(['fields' => ['testModel' => 'name,is_visible']])
            ->allowedFields('*')
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('select "test_models"."name", "test_models"."is_visible" from', $sql);
    }

    #[Test]
    public function tokens_no_rule_allows_are_reported_as_not_allowed(): void
    {
        try {
            $this->wizard(['fields' => ['testModel' => 'name as id']])->allowedFields('name')->get();
            $this->fail('Expected InvalidFieldQuery');
        } catch (InvalidFieldQuery $exception) {
            $this->assertSame('field_not_allowed', $exception->errorCode);
            $this->assertSame(['name as id'], $exception->unknownFields->all());
        }
    }

    #[Test]
    public function explicitly_allowed_names_are_not_checked_for_their_shape(): void
    {
        $sql = $this->wizard(['fields' => ['testModel' => 'name,legacy-code']])
            ->allowedFields('name', 'legacy-code')
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('"test_models"."legacy-code"', $sql);
    }

    #[Test]
    public function tokens_that_are_not_field_names_never_reach_the_select_when_exceptions_are_disabled(): void
    {
        config()->set('query-wizard.disable_invalid_field_query_exception', true);

        $sql = $this->wizard(['fields' => ['testModel' => 'name,name as id,relatedModels.name']])
            ->allowedFields('*')
            ->toQuery()
            ->toSql();

        $this->assertSame('select "test_models"."name" from "test_models"', $sql);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function wizard(array $query): EloquentQueryWizard
    {
        return new EloquentQueryWizard(TestModel::query(), new QueryParametersManager(new Request($query)));
    }
}
