<?php

namespace Jackardios\QueryWizard\Abstracts;

use Illuminate\Support\Traits\ForwardsCalls;
use Jackardios\QueryWizard\QueryParametersManager;

abstract class AbstractQueryWizard
{
    use ForwardsCalls;

    protected $subject;
    protected QueryParametersManager $parametersManager;

    /**
     * @throws \Throwable
     */
    public function __construct($subject, ?QueryParametersManager $parametersManager = null)
    {
        $this->subject = $subject;
        $this->parametersManager = $parametersManager ?: app(QueryParametersManager::class);
    }

    public static function for($subject, ?QueryParametersManager $parametersManager = null): static
    {
        return new static($subject, $parametersManager);
    }

    /** @var string[] */
    protected array $baseFilterHandlerClasses = [AbstractFilter::class];

    /** @var string[] */
    protected array $baseIncludeHandlerClasses = [AbstractInclude::class];

    /** @var string[] */
    protected array $baseSortHandlerClasses = [AbstractSort::class];

    abstract public function build();

    protected function handleForwardedResult(mixed $result)
    {
        return $result;
    }

    public function __call($name, $arguments)
    {
        $result = $this->forwardCallTo($this->subject, $name, $arguments);

        /*
         * If the forwarded method call is part of a chain we can return $this
         * instead of the actual $result to keep the chain going.
         */
        if ($result === $this->subject) {
            return $this;
        }

        return $this->handleForwardedResult($result);
    }

    public function clone(): static
    {
        return clone $this;
    }

    public function __clone()
    {
        $this->subject = clone $this->subject;
    }
}
