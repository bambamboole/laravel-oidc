<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Credentials\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $user_id
 * @property string $code
 * @property CarbonInterface|null $used_at
 */
class RecoveryCode extends Model
{
    use HasUuids;

    protected $table = 'oidc_recovery_codes';

    protected $fillable = [
        'code',
    ];

    protected $hidden = [
        'code',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => 'encrypted',
            'used_at' => 'datetime',
        ];
    }
}
