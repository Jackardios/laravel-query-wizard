<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Illuminate\Http\Request;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Values\Sort;
use PHPUnit\Framework\Attributes\Test;

class QueryParametersManagerTest extends TestCase
{
    // ========== Constructor Tests ==========
    #[Test]
    public function it_creates_instance_without_request(): void
    {
        $manager = new QueryParametersManager;

        $this->assertNull($manager->getRequest());
        $this->assertInstanceOf(QueryWizardConfig::class, $manager->getConfig());
    }

    #[Test]
    public function it_creates_instance_with_request(): void
    {
        $request = new Request(['filter' => ['name' => 'John']]);
        $manager = new QueryParametersManager($request);

        $this->assertSame($request, $manager->getRequest());
    }

    #[Test]
    public function it_creates_instance_with_custom_config(): void
    {
        $config = new QueryWizardConfig;
        $manager = new QueryParametersManager(null, $config);

        $this->assertSame($config, $manager->getConfig());
    }

    // ========== Filters Tests ==========
    #[Test]
    public function it_returns_empty_collection_when_no_filters(): void
    {
        $manager = new QueryParametersManager(new Request);

        $this->assertTrue($manager->getFilters()->isEmpty());
    }

    #[Test]
    public function it_parses_simple_filters(): void
    {
        $request = new Request(['filter' => ['name' => 'John', 'status' => 'active']]);
        $manager = new QueryParametersManager($request);

        $filters = $manager->getFilters();

        $this->assertEquals('John', $filters->get('name'));
        $this->assertEquals('active', $filters->get('status'));
    }

    #[Test]
    public function it_parses_nested_filters(): void
    {
        $request = new Request(['filter' => [
            'author' => ['name' => 'John', 'status' => 'active'],
        ]]);
        $manager = new QueryParametersManager($request);

        $filters = $manager->getFilters();

        $this->assertEquals(['name' => 'John', 'status' => 'active'], $filters->get('author'));
    }

    #[Test]
    public function it_parses_comma_separated_filter_values(): void
    {
        $request = new Request(['filter' => ['status' => 'active,pending,completed']]);
        $manager = new QueryParametersManager($request);

        $filters = $manager->getFilters();

        $this->assertEquals(['active', 'pending', 'completed'], $filters->get('status'));
    }

    #[Test]
    public function it_converts_true_string_to_boolean(): void
    {
        $request = new Request(['filter' => ['active' => 'true']]);
        $manager = new QueryParametersManager($request);

        $filters = $manager->getFilters();

        $this->assertTrue($filters->get('active'));
    }

    #[Test]
    public function it_converts_false_string_to_boolean(): void
    {
        $request = new Request(['filter' => ['active' => 'false']]);
        $manager = new QueryParametersManager($request);

        $filters = $manager->getFilters();

        $this->assertFalse($filters->get('active'));
    }

    #[Test]
    public function it_preserves_array_filter_values(): void
    {
        $request = new Request(['filter' => ['ids' => [1, 2, 3]]]);
        $manager = new QueryParametersManager($request);

        $filters = $manager->getFilters();

        $this->assertEquals([1, 2, 3], $filters->get('ids'));
    }

    #[Test]
    public function it_handles_string_filter_parameter_as_empty(): void
    {
        $manager = new QueryParametersManager(new Request);
        $manager->setFiltersParameter('invalid_string');

        $this->assertTrue($manager->getFilters()->isEmpty());
    }

    #[Test]
    public function get_filter_value_returns_direct_match(): void
    {
        $request = new Request(['filter' => ['name' => 'John']]);
        $manager = new QueryParametersManager($request);

        $this->assertEquals('John', $manager->getFilterValue('name'));
    }

    #[Test]
    public function get_filter_value_returns_nested_value(): void
    {
        $request = new Request(['filter' => [
            'author' => ['name' => 'John'],
        ]]);
        $manager = new QueryParametersManager($request);

        $this->assertEquals('John', $manager->getFilterValue('author.name'));
    }

    #[Test]
    public function get_filter_value_returns_null_for_non_existent(): void
    {
        $request = new Request(['filter' => ['name' => 'John']]);
        $manager = new QueryParametersManager($request);

        $this->assertNull($manager->getFilterValue('nonexistent'));
    }

    #[Test]
    public function get_filter_value_handles_deeply_nested(): void
    {
        $request = new Request(['filter' => [
            'author' => ['profile' => ['name' => 'John']],
        ]]);
        $manager = new QueryParametersManager($request);

        $this->assertEquals('John', $manager->getFilterValue('author.profile.name'));
    }

    #[Test]
    public function get_filter_value_handles_dotted_key_names(): void
    {
        $request = new Request(['filter' => ['author.name' => 'John']]);
        $manager = new QueryParametersManager($request);

        $this->assertEquals('John', $manager->getFilterValue('author.name'));
    }

    // ========== Includes Tests ==========
    #[Test]
    public function it_returns_empty_collection_when_no_includes(): void
    {
        $manager = new QueryParametersManager(new Request);

        $this->assertTrue($manager->getIncludes()->isEmpty());
    }

    #[Test]
    public function it_parses_comma_separated_includes(): void
    {
        $request = new Request(['include' => 'posts,comments,author']);
        $manager = new QueryParametersManager($request);

        $includes = $manager->getIncludes();

        $this->assertEquals(['posts', 'comments', 'author'], $includes->all());
    }

    #[Test]
    public function it_parses_array_includes(): void
    {
        $request = new Request(['include' => ['posts', 'comments']]);
        $manager = new QueryParametersManager($request);

        $includes = $manager->getIncludes();

        $this->assertEquals(['posts', 'comments'], $includes->all());
    }

    #[Test]
    public function it_handles_nested_includes(): void
    {
        $request = new Request(['include' => 'posts.comments,posts.author']);
        $manager = new QueryParametersManager($request);

        $includes = $manager->getIncludes();

        $this->assertEquals(['posts.comments', 'posts.author'], $includes->all());
    }

    #[Test]
    public function it_removes_duplicate_includes(): void
    {
        $request = new Request(['include' => 'posts,comments,posts']);
        $manager = new QueryParametersManager($request);

        $includes = $manager->getIncludes();

        $this->assertEquals(['posts', 'comments'], $includes->all());
    }

    #[Test]
    public function it_filters_empty_includes(): void
    {
        $request = new Request(['include' => 'posts,,comments']);
        $manager = new QueryParametersManager($request);

        $includes = $manager->getIncludes();

        $this->assertEquals(['posts', 'comments'], $includes->all());
    }

    // ========== Sorts Tests ==========
    #[Test]
    public function it_returns_empty_collection_when_no_sorts(): void
    {
        $manager = new QueryParametersManager(new Request);

        $this->assertTrue($manager->getSorts()->isEmpty());
    }

    #[Test]
    public function it_parses_comma_separated_sorts(): void
    {
        $request = new Request(['sort' => 'name,-created_at']);
        $manager = new QueryParametersManager($request);

        $sorts = $manager->getSorts();

        $this->assertCount(2, $sorts);
        $this->assertInstanceOf(Sort::class, $sorts[0]);
        $this->assertEquals('name', $sorts[0]->getField());
        $this->assertEquals('asc', $sorts[0]->getDirection());
        $this->assertEquals('created_at', $sorts[1]->getField());
        $this->assertEquals('desc', $sorts[1]->getDirection());
    }

    #[Test]
    public function it_parses_array_sorts(): void
    {
        $request = new Request(['sort' => ['name', '-created_at']]);
        $manager = new QueryParametersManager($request);

        $sorts = $manager->getSorts();

        $this->assertCount(2, $sorts);
    }

    #[Test]
    public function it_removes_duplicate_sorts_keeping_first(): void
    {
        $request = new Request(['sort' => 'name,-name,name']);
        $manager = new QueryParametersManager($request);

        $sorts = $manager->getSorts();

        $this->assertCount(1, $sorts);
        $this->assertEquals('name', $sorts[0]->getField());
        $this->assertEquals('asc', $sorts[0]->getDirection());
    }

    #[Test]
    public function it_filters_empty_sorts(): void
    {
        $request = new Request(['sort' => 'name,,created_at']);
        $manager = new QueryParametersManager($request);

        $sorts = $manager->getSorts();

        $this->assertCount(2, $sorts);
    }

    // ========== Fields Tests ==========
    #[Test]
    public function it_returns_empty_collection_when_no_fields(): void
    {
        $manager = new QueryParametersManager(new Request);

        $this->assertTrue($manager->getFields()->isEmpty());
    }

    #[Test]
    public function it_parses_resource_keyed_fields(): void
    {
        $request = new Request(['fields' => [
            'users' => 'id,name,email',
            'posts' => 'id,title',
        ]]);
        $manager = new QueryParametersManager($request);

        $fields = $manager->getFields();

        $this->assertEquals(['id', 'name', 'email'], $fields->get('users'));
        $this->assertEquals(['id', 'title'], $fields->get('posts'));
    }

    #[Test]
    public function it_parses_array_fields(): void
    {
        $request = new Request(['fields' => [
            'users' => ['id', 'name', 'email'],
        ]]);
        $manager = new QueryParametersManager($request);

        $fields = $manager->getFields();

        $this->assertEquals(['id', 'name', 'email'], $fields->get('users'));
    }

    #[Test]
    public function it_parses_dotted_string_fields(): void
    {
        $request = new Request(['fields' => 'users.id,users.name,posts.title']);
        $manager = new QueryParametersManager($request);

        $fields = $manager->getFields();

        $this->assertEquals(['id', 'name'], $fields->get('users'));
        $this->assertEquals(['title'], $fields->get('posts'));
    }

    #[Test]
    public function it_groups_fields_without_resource_under_empty_key(): void
    {
        $request = new Request(['fields' => 'id,name,email']);
        $manager = new QueryParametersManager($request);

        $fields = $manager->getFields();

        $this->assertEquals(['id', 'name', 'email'], $fields->get(''));
    }

    #[Test]
    public function it_handles_mixed_fields_format(): void
    {
        $request = new Request(['fields' => 'users.id,users.name,title']);
        $manager = new QueryParametersManager($request);

        $fields = $manager->getFields();

        $this->assertEquals(['id', 'name'], $fields->get('users'));
        $this->assertEquals(['title'], $fields->get(''));
    }

    #[Test]
    public function it_removes_duplicate_fields(): void
    {
        $request = new Request(['fields' => [
            'users' => 'id,name,id,email,name',
        ]]);
        $manager = new QueryParametersManager($request);

        $fields = $manager->getFields();

        $this->assertEquals(['id', 'name', 'email'], $fields->get('users'));
    }

    #[Test]
    public function it_filters_empty_fields(): void
    {
        $request = new Request(['fields' => [
            'users' => 'id,,name',
        ]]);
        $manager = new QueryParametersManager($request);

        $fields = $manager->getFields();

        $this->assertEquals(['id', 'name'], $fields->get('users'));
    }

    // ========== Appends Tests ==========
    #[Test]
    public function it_returns_empty_collection_when_no_appends(): void
    {
        $manager = new QueryParametersManager(new Request);

        $this->assertTrue($manager->getAppends()->isEmpty());
    }

    #[Test]
    public function it_parses_comma_separated_appends(): void
    {
        $request = new Request(['append' => 'fullName,avatarUrl']);
        $manager = new QueryParametersManager($request);

        $appends = $manager->getAppends();

        $this->assertEquals(['fullName', 'avatarUrl'], $appends->all());
    }

    #[Test]
    public function it_parses_array_appends(): void
    {
        $request = new Request(['append' => ['fullName', 'avatarUrl']]);
        $manager = new QueryParametersManager($request);

        $appends = $manager->getAppends();

        $this->assertEquals(['fullName', 'avatarUrl'], $appends->all());
    }

    #[Test]
    public function it_removes_duplicate_appends(): void
    {
        $request = new Request(['append' => 'fullName,avatarUrl,fullName']);
        $manager = new QueryParametersManager($request);

        $appends = $manager->getAppends();

        $this->assertEquals(['fullName', 'avatarUrl'], $appends->all());
    }

    // ========== Manual Parameter Setting Tests ==========
    #[Test]
    public function it_can_set_filters_manually(): void
    {
        $manager = new QueryParametersManager;
        $manager->setFiltersParameter(['name' => 'John']);

        $this->assertEquals('John', $manager->getFilters()->get('name'));
    }

    #[Test]
    public function it_can_set_includes_manually(): void
    {
        $manager = new QueryParametersManager;
        $manager->setIncludesParameter(['posts', 'comments']);

        $this->assertEquals(['posts', 'comments'], $manager->getIncludes()->all());
    }

    #[Test]
    public function it_can_set_sorts_manually(): void
    {
        $manager = new QueryParametersManager;
        $manager->setSortsParameter(['name', '-created_at']);

        $sorts = $manager->getSorts();
        $this->assertCount(2, $sorts);
        $this->assertEquals('name', $sorts[0]->getField());
    }

    #[Test]
    public function it_can_set_fields_manually(): void
    {
        $manager = new QueryParametersManager;
        $manager->setFieldsParameter(['users' => 'id,name']);

        $this->assertEquals(['id', 'name'], $manager->getFields()->get('users'));
    }

    #[Test]
    public function it_can_parse_fields_from_dot_notation_string(): void
    {
        $manager = new QueryParametersManager;
        $manager->setFieldsParameter('relatedModels.id,relatedModels.name,title,otherRelation.foo');

        $fields = $manager->getFields();

        // Root fields go under empty string key
        $this->assertEquals(['title'], $fields->get(''));

        // Nested fields grouped by relation
        $this->assertEquals(['id', 'name'], $fields->get('relatedModels'));
        $this->assertEquals(['foo'], $fields->get('otherRelation'));
    }

    #[Test]
    public function it_can_parse_deeply_nested_fields_from_string(): void
    {
        $manager = new QueryParametersManager;
        $manager->setFieldsParameter('posts.comments.id,posts.comments.body,posts.title');

        $fields = $manager->getFields();

        // 'posts.comments' is the resource key, 'id' and 'body' are fields
        $this->assertEquals(['id', 'body'], $fields->get('posts.comments'));
        $this->assertEquals(['title'], $fields->get('posts'));
    }

    #[Test]
    public function it_can_set_appends_manually(): void
    {
        $manager = new QueryParametersManager;
        $manager->setAppendsParameter(['fullName', 'avatarUrl']);

        $this->assertEquals(['fullName', 'avatarUrl'], $manager->getAppends()->all());
    }

    // ========== Edge Cases ==========
    #[Test]
    public function it_handles_null_filter_values(): void
    {
        $request = new Request(['filter' => ['name' => null]]);
        $manager = new QueryParametersManager($request);

        $this->assertNull($manager->getFilters()->get('name'));
    }

    #[Test]
    public function it_handles_empty_string_filter_value(): void
    {
        $request = new Request(['filter' => ['name' => '']]);
        $manager = new QueryParametersManager($request);

        $this->assertEquals('', $manager->getFilters()->get('name'));
    }

    #[Test]
    public function it_handles_numeric_filter_values(): void
    {
        $request = new Request(['filter' => ['age' => 25, 'score' => '100']]);
        $manager = new QueryParametersManager($request);

        $this->assertEquals(25, $manager->getFilters()->get('age'));
        $this->assertEquals('100', $manager->getFilters()->get('score'));
    }

    #[Test]
    public function it_handles_special_characters_in_values(): void
    {
        $request = new Request(['filter' => ['name' => "O'Brien & Co."]]);
        $manager = new QueryParametersManager($request);

        $this->assertEquals("O'Brien & Co.", $manager->getFilters()->get('name'));
    }

    #[Test]
    public function it_caches_parsed_values(): void
    {
        $request = new Request(['filter' => ['name' => 'John']]);
        $manager = new QueryParametersManager($request);

        $first = $manager->getFilters();
        $second = $manager->getFilters();

        $this->assertSame($first, $second);
    }

    #[Test]
    public function it_handles_wildcard_field(): void
    {
        $request = new Request(['fields' => ['users' => '*']]);
        $manager = new QueryParametersManager($request);

        $this->assertEquals(['*'], $manager->getFields()->get('users'));
    }

    #[Test]
    public function it_handles_deeply_nested_resource_fields(): void
    {
        $request = new Request(['fields' => 'posts.author.profile.name']);
        $manager = new QueryParametersManager($request);

        $fields = $manager->getFields();

        $this->assertEquals(['name'], $fields->get('posts.author.profile'));
    }

    #[Test]
    public function fluent_interface_returns_self(): void
    {
        $manager = new QueryParametersManager;

        $this->assertSame($manager, $manager->setFiltersParameter([]));
        $this->assertSame($manager, $manager->setIncludesParameter([]));
        $this->assertSame($manager, $manager->setSortsParameter([]));
        $this->assertSame($manager, $manager->setFieldsParameter([]));
        $this->assertSame($manager, $manager->setAppendsParameter([]));
    }

    // ========== Trim Whitespace Tests ==========
    #[Test]
    public function it_trims_whitespace_from_includes(): void
    {
        $request = new Request(['include' => ' posts , comments ']);
        $manager = new QueryParametersManager($request);

        $includes = $manager->getIncludes();

        $this->assertEquals(['posts', 'comments'], $includes->all());
    }

    #[Test]
    public function it_trims_whitespace_from_array_includes(): void
    {
        $request = new Request(['include' => [' posts ', ' comments ']]);
        $manager = new QueryParametersManager($request);

        $includes = $manager->getIncludes();

        $this->assertEquals(['posts', 'comments'], $includes->all());
    }

    #[Test]
    public function it_trims_whitespace_from_sorts(): void
    {
        $request = new Request(['sort' => ' name , -created_at ']);
        $manager = new QueryParametersManager($request);

        $sorts = $manager->getSorts();

        $this->assertCount(2, $sorts);
        $this->assertEquals('name', $sorts[0]->getField());
        $this->assertEquals('asc', $sorts[0]->getDirection());
        $this->assertEquals('created_at', $sorts[1]->getField());
        $this->assertEquals('desc', $sorts[1]->getDirection());
    }

    #[Test]
    public function it_trims_whitespace_from_array_sorts(): void
    {
        $request = new Request(['sort' => [' name ', ' -created_at ']]);
        $manager = new QueryParametersManager($request);

        $sorts = $manager->getSorts();

        $this->assertCount(2, $sorts);
        $this->assertEquals('name', $sorts[0]->getField());
        $this->assertEquals('created_at', $sorts[1]->getField());
    }

    #[Test]
    public function it_trims_whitespace_from_appends(): void
    {
        $request = new Request(['append' => ' fullName , avatarUrl ']);
        $manager = new QueryParametersManager($request);

        $appends = $manager->getAppends();

        $this->assertEquals(['fullName', 'avatarUrl'], $appends->all());
    }

    #[Test]
    public function it_trims_whitespace_from_array_appends(): void
    {
        $request = new Request(['append' => [' fullName ', ' avatarUrl ']]);
        $manager = new QueryParametersManager($request);

        $appends = $manager->getAppends();

        $this->assertEquals(['fullName', 'avatarUrl'], $appends->all());
    }

    #[Test]
    public function it_trims_whitespace_from_fields(): void
    {
        $request = new Request(['fields' => [
            'users' => ' id , name , email ',
        ]]);
        $manager = new QueryParametersManager($request);

        $fields = $manager->getFields();

        $this->assertEquals(['id', 'name', 'email'], $fields->get('users'));
    }

    #[Test]
    public function it_trims_whitespace_from_array_fields(): void
    {
        $request = new Request(['fields' => [
            'users' => [' id ', ' name ', ' email '],
        ]]);
        $manager = new QueryParametersManager($request);

        $fields = $manager->getFields();

        $this->assertEquals(['id', 'name', 'email'], $fields->get('users'));
    }

    #[Test]
    public function it_handles_nested_includes_with_whitespace(): void
    {
        $request = new Request(['include' => ' posts.comments , posts.author ']);
        $manager = new QueryParametersManager($request);

        $includes = $manager->getIncludes();

        $this->assertEquals(['posts.comments', 'posts.author'], $includes->all());
    }
}
