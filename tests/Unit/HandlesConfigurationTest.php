<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Jackardios\QueryWizard\Concerns\HandlesConfiguration;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\WizardContextInterface;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HandlesConfigurationTest extends TestCase
{
    private object $configHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configHandler = new class
        {
            use HandlesConfiguration;

            protected function getConfig(): QueryWizardConfig
            {
                return new QueryWizardConfig([]);
            }

            protected function getSchema(): ?ResourceSchemaInterface
            {
                return null;
            }

            protected function getParametersManager(): QueryParametersManager
            {
                return new QueryParametersManager(request());
            }

            public function getContext(): WizardContextInterface
            {
                return new class implements WizardContextInterface
                {
                    public function getResourceKey(): string
                    {
                        return 'test';
                    }

                    public function getIncludeAliasToRelationMap(): array
                    {
                        return [];
                    }
                };
            }

            public function testIsNameDisallowed(string $name, array $disallowed): bool
            {
                return $this->isNameDisallowed($name, $disallowed);
            }
        };
    }

    private function isNameDisallowed(string $name, array $disallowed): bool
    {
        return $this->configHandler->testIsNameDisallowed($name, $disallowed);
    }

    #[Test]
    public function global_wildcard_blocks_everything(): void
    {
        $this->assertTrue($this->isNameDisallowed('name', ['*']));
        $this->assertTrue($this->isNameDisallowed('posts.title', ['*']));
        $this->assertTrue($this->isNameDisallowed('posts.comments.author', ['*']));
    }

    #[Test]
    public function level_wildcard_blocks_direct_children_only(): void
    {
        $this->assertTrue($this->isNameDisallowed('posts.title', ['posts.*']));
        $this->assertTrue($this->isNameDisallowed('posts.author', ['posts.*']));
        $this->assertFalse($this->isNameDisallowed('posts.comments.author', ['posts.*']));
        $this->assertFalse($this->isNameDisallowed('posts', ['posts.*']));
    }

    #[Test]
    public function prefix_match_blocks_all_descendants(): void
    {
        $this->assertTrue($this->isNameDisallowed('posts.title', ['posts']));
        $this->assertTrue($this->isNameDisallowed('posts.comments.author', ['posts']));
        $this->assertTrue($this->isNameDisallowed('posts', ['posts']));
    }

    #[Test]
    public function exact_match_still_works(): void
    {
        $this->assertTrue($this->isNameDisallowed('name', ['name']));
        $this->assertFalse($this->isNameDisallowed('names', ['name']));
        $this->assertFalse($this->isNameDisallowed('nam', ['name']));
    }

    #[Test]
    public function empty_disallowed_allows_everything(): void
    {
        $this->assertFalse($this->isNameDisallowed('anything', []));
    }

    #[Test]
    public function multiple_disallowed_patterns_work(): void
    {
        $disallowed = ['secret', 'private.*', 'internal'];

        $this->assertTrue($this->isNameDisallowed('secret', $disallowed));
        $this->assertTrue($this->isNameDisallowed('private.key', $disallowed));
        $this->assertTrue($this->isNameDisallowed('internal.data', $disallowed));
        $this->assertFalse($this->isNameDisallowed('public', $disallowed));
        $this->assertFalse($this->isNameDisallowed('private.nested.key', $disallowed));
    }

    #[Test]
    public function level_wildcard_does_not_match_root(): void
    {
        $this->assertFalse($this->isNameDisallowed('posts', ['posts.*']));
        $this->assertFalse($this->isNameDisallowed('users', ['posts.*']));
    }

    #[Test]
    public function level_wildcard_matches_only_immediate_children(): void
    {
        $this->assertTrue($this->isNameDisallowed('users.name', ['users.*']));
        $this->assertTrue($this->isNameDisallowed('users.email', ['users.*']));
        $this->assertFalse($this->isNameDisallowed('users.posts.title', ['users.*']));
        $this->assertFalse($this->isNameDisallowed('users.posts.comments.body', ['users.*']));
    }
}
