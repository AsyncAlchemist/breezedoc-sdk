<?php

declare(strict_types=1);

namespace Breezedoc\Api;

use Breezedoc\Models\User;

/**
 * User API resource.
 */
class Users extends AbstractApi
{
    /**
     * Get the currently authenticated user.
     */
    public function me(): User
    {
        $data = $this->get('/me');
        return User::fromArray($data);
    }
}
