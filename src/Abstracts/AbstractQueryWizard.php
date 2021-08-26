<?php

namespace Jackardios\QueryWizard\Abstracts;

use Illuminate\Http\Request;
use Illuminate\Support\Traits\ForwardsCalls;
use Jackardios\QueryWizard\Concerns\HandlesAppends;
use Jackardios\QueryWizard\Concerns\HandlesFields;
use Jackardios\QueryWizard\Concerns\HandlesFilters;
use Jackardios\QueryWizard\Concerns\HandlesIncludes;
use Jackardios\QueryWizard\Concerns\HandlesSorts;
use Jackardios\QueryWizard\Exceptions\InvalidQueryHandler;
use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;
use Jackardios\QueryWizard\QueryWizardRequest;

abstract class AbstractQueryWizard
{
    use ForwardsCalls;
    use HandlesAppends;
    use HandlesFields;
    use HandlesFilters;
    use HandlesIncludes;
    use HandlesSorts;

    protected QueryWizardRequest $request;
    protected AbstractQueryHandler $queryHandler;
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
     * @return static
     */
    protected function initializeRequest(?Request $request = null)
    {
        $this->request = $request
            ? QueryWizardRequest::fromRequest($request)
            : app(QueryWizardRequest::class);

        return $this;
    }

    /**
     * @return static
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
     * @return static
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
}
