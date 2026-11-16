<?php

declare(strict_types=1);

namespace Breezedoc\V1\Api;

use Breezedoc\V1\Models\TeamAccount;

/**
 * Team API v1: the key's team.
 */
class Account extends AbstractTeamApi
{
    /**
     * `GET /v1/me`: the team of the key, the owner's plan and the team usage (read scope).
     */
    public function me(): TeamAccount
    {
        return TeamAccount::fromArray(self::unwrap($this->call('GET', '/me')));
    }
}
