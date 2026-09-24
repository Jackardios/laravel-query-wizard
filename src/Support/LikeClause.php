<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Grammars\PostgresGrammar;

/**
 * A `column LIKE ?` condition that matches the bound value literally on every grammar.
 *
 * The value is escaped with `!`: a backslash breaks drivers that rewrite `?`
 * placeholders themselves (pdo_pgsql reads `'\'` as an unterminated literal
 * and hides every later placeholder), and SQLite has no default escape
 * character. PostgreSQL compares the column as text, so non-text columns work.
 *
 * @internal
 */
final class LikeClause implements Expression
{
    private const ESCAPE_CHARACTER = '!';

    private function __construct(
        private readonly string $sql,
    ) {}

    /**
     * `column LIKE ? ESCAPE '!'`, with the column lowercased when $lowercase.
     *
     * @param  Builder<Model>  $builder
     */
    public static function for(Builder $builder, string $column, bool $lowercase = false, bool $not = false): self
    {
        $grammar = $builder->getQuery()->getGrammar();
        $sql = $grammar->wrap($builder->qualifyColumn($column));

        if ($grammar instanceof PostgresGrammar) {
            $sql .= '::text';
        }

        if ($lowercase) {
            $sql = "LOWER({$sql})";
        }

        $operator = $not ? 'NOT LIKE' : 'LIKE';

        return new self("{$sql} {$operator} ? ESCAPE '".self::ESCAPE_CHARACTER."'");
    }

    /**
     * The pattern matching values that contain $value.
     */
    public static function containing(string $value, bool $lowercase = false): string
    {
        if ($lowercase) {
            $value = mb_strtolower($value, 'UTF-8');
        }

        return '%'.self::escape($value).'%';
    }

    public function getValue(Grammar $grammar): string
    {
        return $this->sql;
    }

    private static function escape(string $value): string
    {
        $escape = self::ESCAPE_CHARACTER;

        return strtr($value, [
            $escape => $escape.$escape,
            '%' => $escape.'%',
            '_' => $escape.'_',
        ]);
    }
}
