<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\BackChannel;

use Bambamboole\LaravelOidc\Auth\SessionRegistry;
use Laravel\Passport\Passport;

class BackChannelLogoutNotifier
{
    public function __construct(private readonly SessionRegistry $registry) {}

    public function notify(string $sid): void
    {
        $clientIds = $this->registry->participantClientIds($sid);

        if ($clientIds === []) {
            return;
        }

        $notifiable = Passport::client()->newQuery()
            ->whereIn('id', $clientIds)
            ->whereNotNull('backchannel_logout_uri')
            ->pluck('id');

        foreach ($notifiable as $clientId) {
            SendBackChannelLogout::dispatch($sid, (string) $clientId);
        }
    }
}
