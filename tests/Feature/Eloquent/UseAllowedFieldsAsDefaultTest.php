<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('fields')]
#[Group('use-allowed-as-default')]
class UseAllowedFieldsAsDefaultTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        $this->models = TestModel::factory()->count(3)->create();

        $this->models->each(function (TestModel $model) {
            RelatedModel::factory()->count(2)->create([
                'test_model_id' => $model->id,
            ]);
        });
    }

    // ========== Config Disabled (Default Behavior) ==========

    #[Test]
    public function config_disabled_no_fields_param_returns_all_columns(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', false);
        DB::flushQueryLog();

        $this
            ->createEloquentWizardFromQuery()
            ->allowedFields('id', 'name')
            ->get();

        $this->assertQueryLogContains('select * from "test_models"');
    }

    #[Test]
    public function config_disabled_no_allowed_fields_returns_all_columns(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', false);
        DB::flushQueryLog();

        $this
            ->createEloquentWizardFromQuery()
            ->get();

        $this->assertQueryLogContains('select * from "test_models"');
    }

    #[Test]
    public function config_disabled_wildcard_request_without_wildcard_allowed_throws(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', false);

        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createEloquentWizardWithFields(['testModel' => '*'])
            ->allowedFields('id', 'name')
            ->get();
    }

    // ========== Config Enabled ==========

    #[Test]
    public function config_enabled_no_fields_param_uses_allowed_fields(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);
        DB::flushQueryLog();

        $this
            ->createEloquentWizardFromQuery()
            ->allowedFields('id', 'name')
            ->get();

        $this->assertQueryLogContains('select "test_models"."id", "test_models"."name" from "test_models"');
    }

    #[Test]
    public function config_enabled_no_allowed_fields_allows_all(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);
        DB::flushQueryLog();

        $this
            ->createEloquentWizardFromQuery()
            ->get();

        $this->assertQueryLogContains('select * from "test_models"');
    }

    #[Test]
    public function config_enabled_wildcard_in_allowed_returns_all(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);
        DB::flushQueryLog();

        $this
            ->createEloquentWizardFromQuery()
            ->allowedFields('*')
            ->get();

        $this->assertQueryLogContains('select * from "test_models"');
    }

    #[Test]
    public function config_enabled_wildcard_with_other_fields_returns_all(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);
        DB::flushQueryLog();

        $this
            ->createEloquentWizardFromQuery()
            ->allowedFields('id', 'name', '*')
            ->get();

        $this->assertQueryLogContains('select * from "test_models"');
    }

    #[Test]
    public function config_enabled_explicit_fields_request_overrides_defaults(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);
        DB::flushQueryLog();

        $this
            ->createEloquentWizardWithFields(['testModel' => 'id'])
            ->allowedFields('id', 'name', 'created_at')
            ->get();

        $this->assertQueryLogContains('select "test_models"."id" from "test_models"');
    }

    #[Test]
    public function config_enabled_explicit_default_fields_takes_precedence(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);
        DB::flushQueryLog();

        $this
            ->createEloquentWizardFromQuery()
            ->allowedFields('id', 'name', 'created_at')
            ->defaultFields('name')
            ->get();

        $this->assertQueryLogContains('select "test_models"."name" from "test_models"');
    }

    #[Test]
    public function config_enabled_empty_allowed_fields_forbids_all(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);

        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createEloquentWizardWithFields(['testModel' => 'id'])
            ->allowedFields([])
            ->get();
    }

    #[Test]
    public function config_enabled_empty_allowed_fields_without_request_returns_nothing(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);
        DB::flushQueryLog();

        // Empty allowedFields means "no specific fields allowed"
        // Without request, this means "no field restriction" -> select *
        // But with use_allowed_as_default, empty allowed = empty default = select *
        $this
            ->createEloquentWizardFromQuery()
            ->allowedFields([])
            ->get();

        // Empty allowed fields array means no SELECT restriction when no request
        $this->assertQueryLogContains('select * from "test_models"');
    }

    // ========== Wildcard Request Tests ==========

    #[Test]
    public function config_enabled_wildcard_request_works_when_wildcard_allowed(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);
        DB::flushQueryLog();

        $models = $this
            ->createEloquentWizardWithFields(['testModel' => '*'])
            ->allowedFields('*')
            ->get();

        $this->assertQueryLogContains('select * from "test_models"');
        $this->assertNotNull($models->first()->name);
        $this->assertNotNull($models->first()->created_at);
    }

    #[Test]
    public function config_enabled_wildcard_request_rejected_when_specific_fields_allowed(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);

        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createEloquentWizardWithFields(['testModel' => '*'])
            ->allowedFields('id', 'name')
            ->get();
    }

    #[Test]
    public function config_enabled_no_allowed_fields_rejects_wildcard_request(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);

        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createEloquentWizardWithFields(['testModel' => '*'])
            ->get();
    }

    #[Test]
    public function config_enabled_no_allowed_fields_rejects_field_request(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);

        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createEloquentWizardWithFields(['testModel' => 'id,name,created_at'])
            ->get();
    }

    // ========== Relation Fields ==========

    #[Test]
    public function config_enabled_root_fields_selected_correctly_with_includes(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);
        DB::flushQueryLog();

        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        // Root fields should use allowed fields as default
        $this->assertQueryLogContains('select "test_models"."id", "test_models"."name"');
    }

    #[Test]
    public function config_enabled_ignores_relation_only_allowed_fields_for_root_defaults(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);
        DB::flushQueryLog();

        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'relatedModels',
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('relatedModels.id')
            ->get();

        $this->assertTrue($models->first()->relationLoaded('relatedModels'));
        $this->assertQueryLogContains('select * from "test_models"');
    }

    // ========== Disallowed Fields Interaction ==========

    #[Test]
    public function config_enabled_disallowed_fields_removed_from_defaults(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);
        DB::flushQueryLog();

        $this
            ->createEloquentWizardFromQuery()
            ->allowedFields('id', 'name', 'created_at')
            ->disallowedFields('created_at')
            ->get();

        $sql = $this->getLastQuery();
        $this->assertStringContainsString('"id"', $sql);
        $this->assertStringContainsString('"name"', $sql);
        $this->assertStringNotContainsString('created_at', $sql);
    }

    #[Test]
    public function config_enabled_disallowed_wildcard_blocks_all(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);
        DB::flushQueryLog();

        // disallowedFields('*') should result in empty effective fields
        // which means select * (no restriction)
        $this
            ->createEloquentWizardFromQuery()
            ->allowedFields('id', 'name')
            ->disallowedFields('*')
            ->get();

        $this->assertQueryLogContains('select * from "test_models"');
    }

    // ========== ModelQueryWizard ==========

    #[Test]
    public function config_enabled_model_wizard_hides_non_allowed_fields(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);

        $model = $this
            ->createModelWizardFromQuery()
            ->allowedFields('id', 'name')
            ->process();

        $attributes = array_keys($model->toArray());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
        $this->assertNotContains('created_at', $attributes);
    }

    #[Test]
    public function config_enabled_model_wizard_wildcard_shows_all(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);

        $model = $this
            ->createModelWizardFromQuery()
            ->allowedFields('*')
            ->process();

        $attributes = array_keys($model->toArray());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
        $this->assertContains('created_at', $attributes);
    }

    #[Test]
    public function config_enabled_model_wizard_no_allowed_shows_all(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);

        $model = $this
            ->createModelWizardFromQuery()
            ->process();

        $attributes = array_keys($model->toArray());
        $this->assertContains('id', $attributes);
        $this->assertContains('name', $attributes);
        $this->assertContains('created_at', $attributes);
    }

    // ========== Edge Cases ==========

    #[Test]
    public function config_enabled_preserves_field_order(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);
        DB::flushQueryLog();

        $this
            ->createEloquentWizardFromQuery()
            ->allowedFields('created_at', 'id', 'name')
            ->get();

        $sql = $this->getLastQuery();
        $createdAtPos = strpos($sql, 'created_at');
        $idPos = strpos($sql, '"id"');
        $namePos = strpos($sql, '"name"');

        $this->assertLessThan($idPos, $createdAtPos);
        $this->assertLessThan($namePos, $idPos);
    }

    #[Test]
    public function config_enabled_schema_default_fields_take_precedence_over_config_fallback(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);
        DB::flushQueryLog();

        $schema = $this->createSchemaWithFields(
            fields: ['id', 'name', 'created_at'],
            defaultFields: ['name'],
        );

        $this
            ->createEloquentWizardFromQuery()
            ->schema($schema)
            ->get();

        $this->assertQueryLogContains('select "test_models"."name" from "test_models"');
    }

    #[Test]
    public function config_enabled_schema_fields_become_defaults_when_schema_default_fields_are_empty(): void
    {
        config()->set('query-wizard.fields.use_allowed_as_default', true);
        DB::flushQueryLog();

        $schema = $this->createSchemaWithFields(
            fields: ['id', 'name'],
            defaultFields: [],
        );

        $this
            ->createEloquentWizardFromQuery()
            ->schema($schema)
            ->get();

        $this->assertQueryLogContains('select "test_models"."id", "test_models"."name" from "test_models"');
    }

    private function createSchemaWithFields(array $fields, array $defaultFields = []): ResourceSchema
    {
        return new class($fields, $defaultFields) extends ResourceSchema
        {
            /**
             * @param  array<string>  $fields
             * @param  array<string>  $defaultFields
             */
            public function __construct(
                private array $fields,
                private array $defaultFields,
            ) {}

            public function model(): string
            {
                return TestModel::class;
            }

            public function fields(QueryWizardInterface $wizard): array
            {
                return $this->fields;
            }

            public function defaultFields(QueryWizardInterface $wizard): array
            {
                return $this->defaultFields;
            }
        };
    }

    protected function getLastQuery(): string
    {
        $log = DB::getQueryLog();

        return end($log)['query'] ?? '';
    }
}
