<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\SocialAccount;

class SocialAccountObserver
{
    public function created(SocialAccount $account): void
    {
        AuditLog::record(
            'channel_connected',
            $account,
            [
                'platform_id' => $account->platform_id,
                'name' => $account->name,
                'username' => $account->username,
                'account_type' => $account->account_type,
            ]
        );
    }

    public function deleted(SocialAccount $account): void
    {
        AuditLog::record(
            'channel_disconnected',
            $account,
            null,
            [
                'platform_id' => $account->platform_id,
                'name' => $account->name,
                'username' => $account->username,
            ]
        );
    }
}
