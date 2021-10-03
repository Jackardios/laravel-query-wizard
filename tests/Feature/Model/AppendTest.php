<?php

namespace Jackardios\QueryWizard\Tests\Feature\Model;

use Jackardios\QueryWizard\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\QueryWizard\ModelQueryWizard;
use Jackardios\QueryWizard\Tests\App\Models\AppendModel;

/**
 * @group model
 * @group append
 * @group model-append
 */
class AppendTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        factory(AppendModel::class, 5)->create();
    }

    /** @test */
    public function it_can_append_attributes(): void
    {
        $model = $this->createWizardFromAppendRequest('fullname')
            ->setAllowedAppends('fullname')
            ->build();

        $this->assertAttributeLoaded($model, 'fullname');
    }

    /** @test */
    public function it_cannot_append_case_insensitive(): void
    {
        $this->expectException(InvalidAppendQuery::class);

        $this->createWizardFromAppendRequest('FullName')
            ->setAllowedAppends('fullname')
            ->build();
    }

    /** @test */
    public function it_guards_against_invalid_appends(): void
    {
        $this->expectException(InvalidAppendQuery::class);

        $this->createWizardFromAppendRequest('random-attribute-to-append')
            ->setAllowedAppends('attribute-to-append')
            ->build();
    }

    /** @test */
    public function it_can_allow_multiple_appends(): void
    {
        $model = $this->createWizardFromAppendRequest('fullname')
            ->setAllowedAppends('fullname', 'randomAttribute')
            ->build();

        $this->assertAttributeLoaded($model, 'fullname');
    }

    /** @test */
    public function it_can_allow_multiple_appends_as_an_array(): void
    {
        $model = $this->createWizardFromAppendRequest('fullname')
            ->setAllowedAppends(['fullname', 'randomAttribute'])
            ->build();

        $this->assertAttributeLoaded($model, 'fullname');
    }

    /** @test */
    public function it_can_append_multiple_attributes(): void
    {
        $model = $this->createWizardFromAppendRequest('fullname,reversename')
            ->setAllowedAppends(['fullname', 'reversename'])
            ->build();

        $this->assertAttributeLoaded($model, 'fullname');
        $this->assertAttributeLoaded($model, 'reversename');
    }

    protected function createWizardFromAppendRequest(string $appends): ModelQueryWizard
    {
        $request = new Request([
            'append' => $appends,
        ]);

        return ModelQueryWizard::for(AppendModel::query()->first(), $request);
    }

    /**
     * @param Model|ModelQueryWizard $model
     * @param string $attribute
     */
    protected function assertAttributeLoaded($model, string $attribute): void
    {
        $this->assertArrayHasKey($attribute, $model->toArray());
    }
}
