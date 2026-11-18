<?php

namespace App\Support;

use App\Enums\LabelRole;
use App\Models\Label;

/**
 * Holds the label (tenant) the current request or job operates on.
 */
class CurrentLabel
{
    private ?Label $label = null;

    private ?LabelRole $role = null;

    private ?int $payeeId = null;

    public function set(?Label $label, ?LabelRole $role = null, ?int $payeeId = null): void
    {
        $this->label = $label;
        $this->role = $role;
        $this->payeeId = $payeeId;
    }

    public function get(): ?Label
    {
        return $this->label;
    }

    public function id(): ?int
    {
        return $this->label?->id;
    }

    public function role(): ?LabelRole
    {
        return $this->role;
    }

    public function payeeId(): ?int
    {
        return $this->payeeId;
    }

    public function isAdmin(): bool
    {
        return $this->role === LabelRole::Admin;
    }
}
