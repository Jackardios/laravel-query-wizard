<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers;

use Jackardios\QueryWizard\Contracts\DriverInterface;

/**
 * Abstract base class for drivers that provides default implementations
 * for type support methods.
 */
abstract class AbstractDriver implements DriverInterface
{
    /** @var array<string> */
    protected array $supportedFilterTypes = [];

    /** @var array<string> */
    protected array $supportedSortTypes = [];

    /** @var array<string> */
    protected array $supportedIncludeTypes = [];

    public function supportsFilterType(string $type): bool
    {
        return in_array($type, $this->supportedFilterTypes, true);
    }

    public function supportsSortType(string $type): bool
    {
        return in_array($type, $this->supportedSortTypes, true);
    }

    public function supportsIncludeType(string $type): bool
    {
        return in_array($type, $this->supportedIncludeTypes, true);
    }

    /**
     * @return array<string>
     */
    public function getSupportedFilterTypes(): array
    {
        return $this->supportedFilterTypes;
    }

    /**
     * @return array<string>
     */
    public function getSupportedSortTypes(): array
    {
        return $this->supportedSortTypes;
    }

    /**
     * @return array<string>
     */
    public function getSupportedIncludeTypes(): array
    {
        return $this->supportedIncludeTypes;
    }
}
