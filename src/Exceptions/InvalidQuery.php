<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class InvalidQuery extends HttpException
{
}
