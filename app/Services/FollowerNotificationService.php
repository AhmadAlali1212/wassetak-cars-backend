<?php

namespace App\Services;

use App\Models\User;

class FollowerNotificationService
{
    public static function notifyCompanyFollowers(
        User $company,
        string $title,
        string $body,
        string $type,
        array $data = []
    ): void {
        $followers = $company->followers()
            ->select('users.id')
            ->get();

        foreach ($followers as $follower) {
            NotificationService::send(
                $follower->id,
                $title,
                $body,
                $type,
                array_merge($data, [
                    'company_id' => $company->id,
                ])
            );
        }
    }
}
