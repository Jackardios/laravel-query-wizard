<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Schema;

use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\SchemaContextInterface;
use Jackardios\QueryWizard\Contracts\SortInterface;

class SchemaContext implements SchemaContextInterface
{
    /** @var array<FilterInterface|string>|null */
    protected ?array $allowedFilters = null;

    /** @var array<SortInterface|string>|null */
    protected ?array $allowedSorts = null;

    /** @var array<IncludeInterface|string>|null */
    protected ?array $allowedIncludes = null;

    /** @var array<string>|null */
    protected ?array $allowedFields = null;

    /** @var array<string>|null */
    protected ?array $allowedAppends = null;

    /** @var array<string> */
    protected array $disallowedFilters = [];

    /** @var array<string> */
    protected array $disallowedSorts = [];

    /** @var array<string> */
    protected array $disallowedIncludes = [];

    /** @var array<string> */
    protected array $disallowedFields = [];

    /** @var array<string> */
    protected array $disallowedAppends = [];

    /** @var array<string>|null */
    protected ?array $defaultFields = null;

    /** @var array<string>|null */
    protected ?array $defaultIncludes = null;

    /** @var array<string>|null */
    protected ?array $defaultSorts = null;

    /** @var array<string>|null */
    protected ?array $defaultAppends = null;

    public static function make(): self
    {
        return new self();
    }

    // ========== Allowed setters ==========

    /**
     * @param array<FilterInterface|string> $filters
     * @return static
     */
    public function setAllowedFilters(array $filters): self
    {
        $this->allowedFilters = $filters;
        return $this;
    }

    /**
     * @param array<SortInterface|string> $sorts
     * @return static
     */
    public function setAllowedSorts(array $sorts): self
    {
        $this->allowedSorts = $sorts;
        return $this;
    }

    /**
     * @param array<IncludeInterface|string> $includes
     * @return static
     */
    public function setAllowedIncludes(array $includes): self
    {
        $this->allowedIncludes = $includes;
        return $this;
    }

    /**
     * @param array<string> $fields
     * @return static
     */
    public function setAllowedFields(array $fields): self
    {
        $this->allowedFields = $fields;
        return $this;
    }

    /**
     * @param array<string> $appends
     * @return static
     */
    public function setAllowedAppends(array $appends): self
    {
        $this->allowedAppends = $appends;
        return $this;
    }

    // ========== Disallowed setters ==========

    /**
     * @param array<string> $filters
     * @return static
     */
    public function setDisallowedFilters(array $filters): self
    {
        $this->disallowedFilters = $filters;
        return $this;
    }

    /**
     * @param array<string> $sorts
     * @return static
     */
    public function setDisallowedSorts(array $sorts): self
    {
        $this->disallowedSorts = $sorts;
        return $this;
    }

    /**
     * @param array<string> $includes
     * @return static
     */
    public function setDisallowedIncludes(array $includes): self
    {
        $this->disallowedIncludes = $includes;
        return $this;
    }

    /**
     * @param array<string> $fields
     * @return static
     */
    public function setDisallowedFields(array $fields): self
    {
        $this->disallowedFields = $fields;
        return $this;
    }

    /**
     * @param array<string> $appends
     * @return static
     */
    public function setDisallowedAppends(array $appends): self
    {
        $this->disallowedAppends = $appends;
        return $this;
    }

    // ========== Default setters ==========

    /**
     * @param array<string> $fields
     * @return static
     */
    public function setDefaultFields(array $fields): self
    {
        $this->defaultFields = $fields;
        return $this;
    }

    /**
     * @param array<string> $includes
     * @return static
     */
    public function setDefaultIncludes(array $includes): self
    {
        $this->defaultIncludes = $includes;
        return $this;
    }

    /**
     * @param array<string> $sorts
     * @return static
     */
    public function setDefaultSorts(array $sorts): self
    {
        $this->defaultSorts = $sorts;
        return $this;
    }

    /**
     * @param array<string> $appends
     * @return static
     */
    public function setDefaultAppends(array $appends): self
    {
        $this->defaultAppends = $appends;
        return $this;
    }

    // ========== Getters ==========

    /**
     * @return array<FilterInterface|string>|null
     */
    public function getAllowedFilters(): ?array
    {
        return $this->allowedFilters;
    }

    /**
     * @return array<SortInterface|string>|null
     */
    public function getAllowedSorts(): ?array
    {
        return $this->allowedSorts;
    }

    /**
     * @return array<IncludeInterface|string>|null
     */
    public function getAllowedIncludes(): ?array
    {
        return $this->allowedIncludes;
    }

    /**
     * @return array<string>|null
     */
    public function getAllowedFields(): ?array
    {
        return $this->allowedFields;
    }

    /**
     * @return array<string>|null
     */
    public function getAllowedAppends(): ?array
    {
        return $this->allowedAppends;
    }

    /**
     * @return array<string>
     */
    public function getDisallowedFilters(): array
    {
        return $this->disallowedFilters;
    }

    /**
     * @return array<string>
     */
    public function getDisallowedSorts(): array
    {
        return $this->disallowedSorts;
    }

    /**
     * @return array<string>
     */
    public function getDisallowedIncludes(): array
    {
        return $this->disallowedIncludes;
    }

    /**
     * @return array<string>
     */
    public function getDisallowedFields(): array
    {
        return $this->disallowedFields;
    }

    /**
     * @return array<string>
     */
    public function getDisallowedAppends(): array
    {
        return $this->disallowedAppends;
    }

    /**
     * @return array<string>|null
     */
    public function getDefaultFields(): ?array
    {
        return $this->defaultFields;
    }

    /**
     * @return array<string>|null
     */
    public function getDefaultIncludes(): ?array
    {
        return $this->defaultIncludes;
    }

    /**
     * @return array<string>|null
     */
    public function getDefaultSorts(): ?array
    {
        return $this->defaultSorts;
    }

    /**
     * @return array<string>|null
     */
    public function getDefaultAppends(): ?array
    {
        return $this->defaultAppends;
    }
}
