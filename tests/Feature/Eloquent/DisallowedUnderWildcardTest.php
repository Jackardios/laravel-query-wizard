<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Http\Request;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\QueryWizard\ModelQueryWizard;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModelWithHiddenName;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * disallowedFields() / disallowedAppends() apply to names a wildcard allows.
 */
#[Group('eloquent')]
#[Group('fields')]
#[Group('appends')]
class DisallowedUnderWildcardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $model = TestModel::factory()->create(['name' => 'a']);
        RelatedModel::factory()->create(['test_model_id' => $model->id]);
    }

    /**
     * @return array<string, array{array<string, mixed>, array<int, string>, array<int, string>, string}>
     */
    public static function disallowedFields(): array
    {
        return [
            'root field under the root wildcard' => [['fields' => ['testModel' => 'id,name']], ['*'], ['name'], 'name'],
            'relation field under a relation wildcard' => [
                ['include' => 'relatedModels', 'fields' => ['relatedModels' => 'id,name']],
                ['relatedModels.*'],
                ['relatedModels.name'],
                'relatedModels.name',
            ],
            'relation field under the root wildcard' => [
                ['include' => 'relatedModels', 'fields' => ['relatedModels' => 'name']],
                ['*'],
                ['relatedModels.name'],
                'relatedModels.name',
            ],
            'relation children denied with a wildcard' => [
                ['include' => 'relatedModels', 'fields' => ['relatedModels' => 'name']],
                ['*'],
                ['relatedModels.*'],
                'relatedModels.name',
            ],
            'root field in another letter case' => [['fields' => ['testModel' => 'id,NAME']], ['*'], ['name'], 'NAME'],
            'relation field in another letter case' => [
                ['include' => 'relatedModels', 'fields' => ['relatedModels' => 'Name']],
                ['*'],
                ['relatedModels.name'],
                'relatedModels.Name',
            ],
            'relation denied as a whole' => [
                ['include' => 'relatedModels', 'fields' => ['relatedModels' => 'name']],
                ['*'],
                ['relatedModels'],
                'relatedModels.name',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<int, string>  $allowed
     * @param  array<int, string>  $disallowed
     */
    #[Test]
    #[DataProvider('disallowedFields')]
    public function disallowed_fields_are_rejected_under_wildcards(array $query, array $allowed, array $disallowed, string $field): void
    {
        try {
            $this->createEloquentWizardFromQuery($query)
                ->allowedIncludes('relatedModels')
                ->allowedFields(...$allowed)
                ->disallowedFields(...$disallowed)
                ->get();
            $this->fail('Expected InvalidFieldQuery');
        } catch (InvalidFieldQuery $exception) {
            $this->assertSame('field_not_allowed', $exception->errorCode);
            $this->assertSame([$field], $exception->unknownFields->all());
            $this->assertSame("Requested field(s) `{$field}` are not allowed.", $exception->getMessage());
        }
    }

    #[Test]
    public function disallowed_fields_are_dropped_under_wildcards_when_exceptions_are_disabled(): void
    {
        config()->set('query-wizard.disable_invalid_field_query_exception', true);

        $sql = $this->createEloquentWizardFromQuery(['fields' => ['testModel' => 'id,name']])
            ->allowedFields('*')
            ->disallowedFields('name')
            ->toQuery()
            ->toSql();

        $this->assertSame('select "test_models"."id" from "test_models"', $sql);
    }

    #[Test]
    public function explicitly_allowed_names_that_are_disallowed_keep_the_listing_message(): void
    {
        try {
            $this->createEloquentWizardFromQuery(['fields' => ['testModel' => 'name,unknown']])
                ->allowedFields('id', 'name')
                ->disallowedFields('name')
                ->get();
            $this->fail('Expected InvalidFieldQuery');
        } catch (InvalidFieldQuery $exception) {
            $this->assertSame(['name', 'unknown'], $exception->unknownFields->all());
            $this->assertSame('Requested field(s) `name, unknown` are not allowed. Allowed field(s) are `id`.', $exception->getMessage());
        }
    }

    #[Test]
    public function requested_wildcard_still_selects_all_columns(): void
    {
        $sql = $this->createEloquentWizardFromQuery(['fields' => ['testModel' => '*']])
            ->allowedFields('*')
            ->disallowedFields('name')
            ->toQuery()
            ->toSql();

        $this->assertSame('select * from "test_models"', $sql);
    }

    #[Test]
    public function disallowed_default_fields_under_a_wildcard_are_dropped_silently(): void
    {
        $sql = $this->createEloquentWizardFromQuery([])
            ->allowedFields('*')
            ->disallowedFields('name')
            ->defaultFields('id', 'name')
            ->toQuery()
            ->toSql();

        $this->assertSame('select "test_models"."id" from "test_models"', $sql);
    }

    #[Test]
    public function hidden_attributes_named_in_another_letter_case_are_rejected_under_wildcards(): void
    {
        try {
            $this->createEloquentWizardFromQuery(['fields' => 'id,NAME'], TestModelWithHiddenName::query())
                ->allowedFields('*')
                ->get();
            $this->fail('Expected InvalidFieldQuery');
        } catch (InvalidFieldQuery $exception) {
            $this->assertSame('field_not_allowed', $exception->errorCode);
            $this->assertSame(['NAME'], $exception->unknownFields->all());
        }
    }

    #[Test]
    public function hidden_attributes_in_another_letter_case_are_rejected_next_to_disallowed_fields(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this->createEloquentWizardFromQuery(['fields' => 'id,NAME'], TestModelWithHiddenName::query())
            ->allowedFields('*')
            ->disallowedFields('is_visible')
            ->get();
    }

    #[Test]
    public function hidden_attributes_named_exactly_stay_hidden_under_wildcards(): void
    {
        $model = $this->createEloquentWizardFromQuery(['fields' => 'id,name'], TestModelWithHiddenName::query())
            ->allowedFields('*')
            ->get()
            ->first();

        $this->assertSame(['id'], array_keys($model->toArray()));
    }

    #[Test]
    public function other_fields_in_another_letter_case_pass_under_wildcards(): void
    {
        $sql = $this->createEloquentWizardFromQuery(['fields' => 'ID'], TestModelWithHiddenName::query())
            ->allowedFields('*')
            ->disallowedFields('name')
            ->toQuery()
            ->toSql();

        $this->assertSame('select "test_models"."ID" from "test_models"', $sql);
    }

    #[Test]
    public function disallowed_names_are_matched_after_snake_case_conversion(): void
    {
        config()->set('query-wizard.naming.convert_parameters_to_snake_case', true);

        $this->expectException(InvalidFieldQuery::class);

        $this->createEloquentWizardFromQuery(['fields' => ['testModel' => 'isVisible']])
            ->allowedFields('*')
            ->disallowedFields('isVisible')
            ->get();
    }

    /**
     * @return array<string, array{array<string, mixed>, array<int, string>, array<int, string>, string}>
     */
    public static function disallowedAppends(): array
    {
        return [
            'root append under the root wildcard' => [['append' => 'fullname'], ['*'], ['fullname'], 'fullname'],
            'relation append under a relation wildcard' => [
                ['include' => 'relatedModels', 'append' => ['relatedModels' => 'formattedName']],
                ['relatedModels.*'],
                ['relatedModels.formattedName'],
                'relatedModels.formattedName',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<int, string>  $allowed
     * @param  array<int, string>  $disallowed
     */
    #[Test]
    #[DataProvider('disallowedAppends')]
    public function disallowed_appends_are_rejected_under_wildcards(array $query, array $allowed, array $disallowed, string $append): void
    {
        try {
            $this->createEloquentWizardFromQuery($query)
                ->allowedIncludes('relatedModels')
                ->allowedAppends(...$allowed)
                ->disallowedAppends(...$disallowed)
                ->get();
            $this->fail('Expected InvalidAppendQuery');
        } catch (InvalidAppendQuery $exception) {
            $this->assertSame('append_not_allowed', $exception->errorCode);
            $this->assertSame([$append], $exception->unknownAppends->all());
            $this->assertSame("Requested append(s) `{$append}` are not allowed.", $exception->getMessage());
        }
    }

    #[Test]
    public function disallowed_appends_are_not_computed_when_exceptions_are_disabled(): void
    {
        config()->set('query-wizard.disable_invalid_append_query_exception', true);

        $model = $this->createEloquentWizardFromQuery(['append' => 'fullname'])
            ->allowedAppends('*')
            ->disallowedAppends('fullname')
            ->get()
            ->first();

        $this->assertArrayNotHasKey('fullname', $model->toArray());
    }

    #[Test]
    public function disallowed_default_appends_under_a_wildcard_are_dropped_silently(): void
    {
        $model = $this->createEloquentWizardFromQuery([])
            ->allowedAppends('*')
            ->disallowedAppends('fullname')
            ->defaultAppends('fullname')
            ->get()
            ->first();

        $this->assertArrayNotHasKey('fullname', $model->toArray());
    }

    #[Test]
    public function model_wizard_honors_disallowed_names_under_wildcards(): void
    {
        $wizard = fn (array $query) => (new ModelQueryWizard(TestModel::query()->firstOrFail(), new QueryParametersManager(new Request($query))))
            ->allowedIncludes('relatedModels')
            ->allowedFields('*')
            ->disallowedFields('relatedModels.name')
            ->allowedAppends('*')
            ->disallowedAppends('fullname');

        try {
            $wizard(['include' => 'relatedModels', 'fields' => ['relatedModels' => 'name']])->process();
            $this->fail('Expected InvalidFieldQuery');
        } catch (InvalidFieldQuery $exception) {
            $this->assertSame(['relatedModels.name'], $exception->unknownFields->all());
        }

        $this->expectException(InvalidAppendQuery::class);

        $wizard(['append' => 'fullname'])->process();
    }
}
