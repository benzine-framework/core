<?php

namespace Benzine\Models\Traits;

use Carbon\Carbon;
use ZxcvbnPhp\Zxcvbn;

trait AuthableTrait
{
    public function setPassword(?string $password = null): parent
    {
        // Hash the password
        $this->password = password_hash($password, PASSWORD_DEFAULT);

        // Handle updating the last password change time if enabled
        if (method_exists($this, 'setPasswordLastUpdatedAt')) {
            $this->setPasswordLastUpdatedAt(Carbon::now());
        }

        // Handle password scores if enabled
        if (method_exists($this, 'setPasswordStrengthScore')) {
            $this->setPasswordStrengthScore((new Zxcvbn())->passwordStrength($password)['score']);
        }

        // Save entity.
        $this->save();

        return $this;
    }
}
