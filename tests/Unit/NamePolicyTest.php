<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Unit;

use Jackardios\QueryWizard\Support\NamePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NamePolicyTest extends TestCase
{
    /**
     * @return array<string, array{string, array<string>, bool}>
     */
    public static function denyCases(): array
    {
        return [
            'exact' => ['posts', ['posts'], true],
            'descendant of plain entry' => ['posts.comments.author', ['posts'], true],
            'sibling with shared prefix' => ['postsCount', ['posts'], false],
            'global wildcard' => ['anything.at.all', ['*'], true],
            'direct child wildcard' => ['posts.title', ['posts.*'], true],
            'grandchild not covered by child wildcard' => ['posts.comments.body', ['posts.*'], false],
            'parent not covered by child wildcard' => ['posts', ['posts.*'], false],
            'empty list' => ['posts', [], false],
            'leading star is not a wildcard' => ['posts', ['*auto'], false],
            'leading star entry matches itself' => ['*auto', ['*auto'], true],
        ];
    }

    /**
     * @param  array<string>  $denied
     */
    #[Test]
    #[DataProvider('denyCases')]
    public function it_denies_names(string $name, array $denied, bool $expected): void
    {
        $this->assertSame($expected, NamePolicy::denying($denied)->denies($name));
    }

    #[Test]
    public function it_allows_attributes_explicitly_or_through_the_wildcard_of_their_level(): void
    {
        $policy = NamePolicy::allowing(['id', 'posts.title', 'comments.*']);

        $this->assertTrue($policy->allowsAttribute('', 'id'));
        $this->assertFalse($policy->allowsAttribute('', 'name'));
        $this->assertTrue($policy->allowsAttribute('posts', 'title'));
        $this->assertFalse($policy->allowsAttribute('posts', 'body'));
        $this->assertTrue($policy->allowsAttribute('comments', 'body'));
        $this->assertFalse($policy->allowsAttribute('comments.author', 'name'));
        $this->assertTrue(NamePolicy::allowing(['*'])->allowsAttribute('', 'name'));
        $this->assertFalse(NamePolicy::allowing(['*'])->allowsAttribute('posts', 'title'));
    }

    #[Test]
    public function it_matches_the_previous_matchers_on_a_randomized_corpus(): void
    {
        mt_srand(20260924);
        $segments = ['a', 'b', 'ab', 'a_b', '*', '', '5', 'posts', 'postsCount'];

        $randomPath = static function () use ($segments): string {
            $parts = [];
            for ($i = 0, $n = mt_rand(1, 4); $i < $n; $i++) {
                $parts[] = $segments[mt_rand(0, count($segments) - 1)];
            }

            return implode('.', $parts);
        };

        for ($round = 0; $round < 2000; $round++) {
            $list = [];
            for ($i = 0, $n = mt_rand(0, 5); $i < $n; $i++) {
                $list[] = $randomPath();
            }

            $name = $randomPath();
            $this->assertSame(
                self::legacyIsNameDisallowed($name, $list),
                NamePolicy::denying($list)->denies($name),
                sprintf('deny %s against %s', $name, json_encode($list))
            );

            $lastDot = strrpos($name, '.');
            $path = $lastDot === false ? '' : substr($name, 0, $lastDot);
            $attribute = $lastDot === false ? $name : substr($name, $lastDot + 1);
            $this->assertSame(
                self::legacyIsAttributeAllowed($path, $attribute, $list),
                NamePolicy::allowing($list)->allowsAttribute($path, $attribute),
                sprintf('allow %s against %s', $name, json_encode($list))
            );
        }
    }

    /**
     * The matcher this class replaced, kept verbatim as the parity reference.
     *
     * @param  array<string>  $disallowed
     */
    private static function legacyIsNameDisallowed(string $name, array $disallowed): bool
    {
        if (in_array('*', $disallowed, true)) {
            return true;
        }

        foreach ($disallowed as $d) {
            if ($name === $d) {
                return true;
            }

            if (str_starts_with($name, $d.'.')) {
                return true;
            }

            if (str_ends_with($d, '.*')) {
                $prefix = substr($d, 0, -2);
                if (str_starts_with($name, $prefix.'.')) {
                    $suffix = substr($name, strlen($prefix) + 1);
                    if (! str_contains($suffix, '.')) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string>  $allowed
     */
    private static function legacyIsAttributeAllowed(string $path, string $attribute, array $allowed): bool
    {
        $fullPath = $path !== '' ? "{$path}.{$attribute}" : $attribute;
        if (in_array($fullPath, $allowed, true)) {
            return true;
        }

        $wildcardPattern = $path !== '' ? "{$path}.*" : '*';

        return in_array($wildcardPattern, $allowed, true);
    }
}
