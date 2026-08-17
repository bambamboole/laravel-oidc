<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Tables;

use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\Enums\FactorRole;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorEnrollment;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorRegistry;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\RecoveryCodeProvider;
use Bambamboole\LaravelOidc\Ui\Actions\RegenerateRecoveryCodesAction;
use Bambamboole\LaravelOidc\Ui\Actions\RevokeFactorAction;
use Bambamboole\LaravelOidc\Ui\Support\FactorMethodName;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Lattice\Actions\Components\Action;
use Lattice\Core\Enums\ColorName;
use Lattice\Table\Attributes\AsTable;
use Lattice\Table\CallbackTableSource;
use Lattice\Table\Columns\BadgeColumn;
use Lattice\Table\Columns\StackColumn;
use Lattice\Table\Columns\TextColumn;
use Lattice\Table\Contracts\TableSource;
use Lattice\Table\Enums\PaginationType;
use Lattice\Table\TableDefinition;
use Lattice\Table\TableQuery;
use Lattice\Table\TableResult;
use Lattice\Ui\Components\Text;
use Lattice\Ui\Enums\Size;

/**
 * Everything currently guarding the account: each confirmed enrollment across
 * every registered provider, plus a row for the recovery codes that back them
 * all up. Removing the last challengeable row is what "turning two-factor off"
 * means — there is no separate switch.
 */
#[AsTable('oidc.two-factor.methods')]
class TwoFactorMethodsTable extends TableDefinition
{
    public function __construct(
        private readonly FactorRegistry $factors,
        private readonly RecoveryCodeProvider $recoveryCodes,
    ) {}

    public function layout(): string
    {
        return 'grid';
    }

    public function pagination(): PaginationType
    {
        return PaginationType::None;
    }

    public function columns(): array
    {
        return [
            StackColumn::make('method')
                ->label(__('oidc-ui::security.methods.column'))
                ->schema([
                    Text::bound('label'),
                    Text::bound('description')->color(ColorName::Muted)->size(Size::Sm),
                ]),
            BadgeColumn::make('role')->label(__('oidc-ui::security.methods.role')),
            TextColumn::make('last_used_at_diff')->label(__('oidc-ui::security.methods.last-used')),
        ];
    }

    public function actions(array $row): array
    {
        if ($row['kind'] === 'backup') {
            return [Action::use(RegenerateRecoveryCodesAction::class)];
        }

        if ($this->factors->enrollable((string) $row['provider']) === null) {
            return [];
        }

        return [
            Action::use(RevokeFactorAction::class, [
                'provider' => $row['provider'],
                'enrollment' => $row['id'],
            ]),
        ];
    }

    public function source(): TableSource
    {
        return new CallbackTableSource(function (TableQuery $query): TableResult {
            $user = auth()->user();

            if ($user === null) {
                return TableResult::fromItems([]);
            }

            $rows = [];

            foreach ($this->factors->enrollments($user) as $enrollment) {
                if ($enrollment->confirmedAt === null || $this->factors->get($enrollment->providerKey)->isBackup()) {
                    continue;
                }

                $rows[] = $this->row($enrollment);
            }

            if ($rows !== [] && $this->recoveryCodes->total($user) > 0) {
                $rows[] = $this->recoveryCodesRow($user);
            }

            return TableResult::fromItems($rows);
        });
    }

    /**
     * @return array{kind: string, id: string, provider: string, label: string, description: string, role: string, last_used_at_diff: string}
     */
    private function row(FactorEnrollment $enrollment): array
    {
        $authenticator = $enrollment->metadata['authenticator'] ?? null;
        $method = FactorMethodName::for($enrollment->providerKey);

        return [
            'kind' => 'factor',
            'id' => $enrollment->id,
            'provider' => $enrollment->providerKey,
            'label' => $enrollment->label,
            // The AAGUID-derived authenticator name is more specific than the
            // provider ever is — it is what tells a YubiKey from a Touch ID.
            'description' => is_string($authenticator) && $authenticator !== ''
                ? $method.' · '.$authenticator
                : $method,
            'role' => $this->role($enrollment->providerKey),
            'last_used_at_diff' => $enrollment->lastUsedAt === null
                ? __('oidc-ui::security.methods.never-used')
                : __('oidc-ui::security.methods.last-used-at', ['time' => Carbon::instance($enrollment->lastUsedAt)->diffForHumans()]),
        ];
    }

    /**
     * @return array{kind: string, id: string, provider: string, label: string, description: string, role: string, last_used_at_diff: string}
     */
    private function recoveryCodesRow(Authenticatable $user): array
    {
        return [
            'kind' => 'backup',
            'id' => 'recovery-codes',
            'provider' => $this->recoveryCodes->key(),
            'label' => __('oidc-ui::security.recovery-codes.heading'),
            'description' => __('oidc-ui::security.recovery-codes.remaining', [
                'remaining' => $this->recoveryCodes->remaining($user),
                'total' => $this->recoveryCodes->total($user),
            ]),
            'role' => __('oidc-ui::security.role.backup'),
            'last_used_at_diff' => '',
        ];
    }

    /**
     * A provider's role is the reduction of its options': only when every way of
     * enrolling also signs the user in does the badge promise that.
     */
    private function role(string $providerKey): string
    {
        $options = array_filter(
            $this->factors->enrollmentOptions(),
            static fn ($option): bool => $option->providerKey === $providerKey,
        );

        $signsIn = $options !== [] && array_all(
            $options,
            static fn ($option): bool => $option->role === FactorRole::LoginAndSecondFactor,
        );

        return $signsIn
            ? __('oidc-ui::security.role.login-and-second-factor')
            : __('oidc-ui::security.role.second-factor-only');
    }
}
