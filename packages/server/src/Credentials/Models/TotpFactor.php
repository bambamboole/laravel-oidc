<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Credentials\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $user_id
 * @property string $name
 * @property string $secret
 * @property int|null $last_used_timestep
 * @property CarbonInterface|null $confirmed_at
 * @property CarbonInterface|null $last_used_at
 */
class TotpFactor extends Model
{
    use HasUuids;

    protected $table = 'oidc_totp_factors';

    protected $fillable = [
        'name',
        'secret',
    ];

    protected $hidden = [
        'secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'confirmed_at' => 'datetime',
            'last_used_at' => 'datetime',
            'last_used_timestep' => 'integer',
        ];
    }
}
