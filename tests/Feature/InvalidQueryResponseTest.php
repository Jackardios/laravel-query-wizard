<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\Exceptions\InvalidQuery;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * A rejected query is rendered as a 400 whatever the client sent.
 */
class InvalidQueryResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestModel::factory()->create(['name' => 'first']);

        Route::get('/models', fn () => EloquentQueryWizard::for(TestModel::class)
            ->allowedFilters(EloquentFilter::exact('is_visible')->asBoolean(), 'name')
            ->allowedSorts('name')
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name')
            ->allowedAppends('*')
            ->get());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidUtf8Requests(): array
    {
        return [
            'filter value' => ['/models?filter[is_visible]=%B1%31'],
            'filter name' => ['/models?filter[%B1x]=1'],
            'sort' => ['/models?sort=%B1x'],
            'include' => ['/models?include=%B1x'],
            'fields' => ['/models?fields[testModel]=%B1x'],
            'append' => ['/models?append=%B1x'],
        ];
    }

    #[Test]
    #[DataProvider('invalidUtf8Requests')]
    public function a_rejected_value_that_is_not_utf8_is_a_400(string $uri): void
    {
        $response = $this->getJson($uri);

        $response->assertStatus(400);
        $this->assertStringContainsString('?', (string) $response->json('message'));
    }

    #[Test]
    public function a_long_rejected_value_is_shortened_in_the_message(): void
    {
        $message = (string) $this->getJson('/models?filter[is_visible]='.str_repeat('a', 10000))
            ->assertStatus(400)
            ->json('message');

        $this->assertLessThan(300, mb_strlen($message));
        $this->assertStringContainsString(str_repeat('a', 100).'…', $message);
    }

    #[Test]
    public function a_wildcard_append_request_is_a_400_even_when_every_append_is_allowed(): void
    {
        $this->getJson('/models?append=*')->assertStatus(400);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function malformedLists(): array
    {
        return [
            'nested include' => [['include' => ['a' => ['b' => 'x']]], 'getIncludes', 'invalid_include_format'],
            'keyed include' => [['include' => ['a' => 'relatedModels']], 'getIncludes', 'invalid_include_format'],
            'nested sort' => [['sort' => ['a' => ['b' => 'x']]], 'getSorts', 'invalid_sort_format'],
            'keyed sort' => [['sort' => ['a' => 'name']], 'getSorts', 'invalid_sort_format'],
            'keyed fieldset' => [['fields' => ['testModel' => ['b' => 'id']]], 'getFields', 'invalid_field_format'],
            'nested fieldset' => [['fields' => ['testModel' => [['id']]]], 'getFields', 'invalid_field_format'],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    #[Test]
    #[DataProvider('malformedLists')]
    public function a_nested_or_keyed_list_is_a_format_error(array $query, string $getter, string $errorCode): void
    {
        try {
            (new QueryParametersManager(new Request($query)))->{$getter}();
            $this->fail('Expected InvalidQuery');
        } catch (InvalidQuery $e) {
            $this->assertSame(400, $e->getStatusCode());
            $this->assertSame($errorCode, $e->errorCode);
        }
    }

    #[Test]
    public function a_nested_include_is_a_400(): void
    {
        $this->getJson('/models?include[a][b]=x')->assertStatus(400);
    }
}
