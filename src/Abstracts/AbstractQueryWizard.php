<?php

namespace Jackardios\QueryWizard\Abstracts;

use ArrayAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Traits\ForwardsCalls;
use Jackardios\QueryWizard\Exceptions\InvalidQueryHandler;
use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;
use Jackardios\QueryWizard\QueryWizardRequest;

abstract class AbstractQueryWizard implements ArrayAccess
{
    use ForwardsCalls;

    protected QueryWizardRequest $request;

    /** @var AbstractQueryHandler */
    protected $queryHandler;

    protected string $queryHandlerClass;

    public function __construct($subject, ?Request $request = null)
    {
        $this->initializeRequest($request)
            ->initializeQueryHandler($subject);
    }

    /**
     * @param $subject
     * @param Request|null $request
     * @return static
     */
    public static function for($subject, ?Request $request = null)
    {
        return new static($subject, $request);
    }

    /**
     * @param Request|null $request
     * @return $this
     */
    protected function initializeRequest(?Request $request = null)
    {
        $this->request = $request
            ? QueryWizardRequest::fromRequest($request)
            : app(QueryWizardRequest::class);

        return $this;
    }

    /**
     * @return $this
     */
    protected function initializeQueryHandler($subject): self
    {
        if (is_a($this->queryHandlerClass, AbstractQueryHandler::class)) {
            throw new InvalidQueryHandler();
        }

        $this->queryHandler = new $this->queryHandlerClass($this, $subject);

        return $this;
    }

    /**
     * @return $this
     */
    public function build()
    {
        $this->queryHandler->handle();

        return $this;
    }

    public function __call($name, $arguments)
    {
        $subject = $this->queryHandler->getSubject();
        $result = $this->forwardCallTo($subject, $name, $arguments);

        /*
         * If the forwarded method call is part of a chain we can return $this
         * instead of the actual $result to keep the chain going.
         */
        if ($result === $subject) {
            return $this;
        }

        return $this->queryHandler->handleResult($result);
    }

    /**
     * @return static
     */
    public function clone()
    {
        return clone $this;
    }

    public function __clone()
    {
        $this->queryHandler = clone $this->queryHandler;
    }

    public function __get($name)
    {
        return $this->queryHandler->getSubject()->{$name};
    }

    public function __set($name, $value)
    {
        $this->queryHandler->getSubject()->{$name} = $value;
    }

    public function __isset($name): bool
    {
        return isset($this->queryHandler->getSubject()->{$name});
    }

    public function offsetExists($offset): bool
    {
        return isset($this->queryHandler->getSubject()[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->queryHandler->getSubject()[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        $this->queryHandler->getSubject()[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->queryHandler->getSubject()[$offset]);
    }
}
