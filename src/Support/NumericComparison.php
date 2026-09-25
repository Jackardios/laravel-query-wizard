<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Grammars\PostgresGrammar;

/**
 * A `column <operator> ?` condition for a number read from the request.
 *
 * PostgreSQL types an untyped parameter after the column, so a fraction, or an
 * integer beyond the column's range, compared with an integer column fails
 * (22P02, 22003). There such numbers are cast to numeric; integers keep a plain
 * binding so an index on the column stays usable.
 *
 * @internal
 */
final class NumericComparison implements Expression
{
    private function __construct(
        private readonly string $sql,
    ) {}

    /**
     * @param  Builder<Model>  $builder
     * @param  '='|'!='|'<>'|'>'|'>='|'<'|'<='  $operator
     * @return Builder<Model>
     */
    public static function where(Builder $builder, string $qualifiedColumn, string $operator, int|float|string $number): Builder
    {
        $grammar = $builder->getQuery()->getGrammar();

        if (is_int($number) || ! $grammar instanceof PostgresGrammar) {
            return $builder->where($qualifiedColumn, $operator, $number);
        }

        return $builder->whereRaw(new self($grammar->wrap($qualifiedColumn)." {$operator} CAST(? AS numeric)"), [$number]);
    }

    public function getValue(Grammar $grammar): string
    {
        return $this->sql;
    }
}
