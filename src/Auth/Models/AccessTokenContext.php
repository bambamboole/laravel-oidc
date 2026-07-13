<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $access_token_id
 * @property string $context_id
 * @property ?Carbon $created_at
 */
class AccessTokenContext extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'access_token_id';

    protected $table = 'oidc_access_token_contexts';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
