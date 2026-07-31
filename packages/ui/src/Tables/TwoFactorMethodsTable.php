<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Tables;

use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorEnrollment;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorRegistry;
use Bambamboole\LaravelOidc\Ui\Actions\RevokeFactorAction;
use Bambamboole\LaravelOidc\Ui\Support\FactorMethodName;
use Illuminate\Support\Carbon;
use Lattice\Lattice\Actions\Components\Action;
use Lattice\Lattice\Attributes\AsTable;
use Lattice\Lattice\Core\Enums\ColorName;
use Lattice\Lattice\Tables\CallbackTableSource;
use Lattice\Lattice\Tables\Columns\StackColumn;
use Lattice\Lattice\Tables\Columns\TextColumn;
use Lattice\Lattice\Tables\Contracts\TableSource;
use Lattice\Lattice\Tables\Enums\PaginationType;
use Lattice\Lattice\Tables\TableDefinition;
use Lattice\Lattice\Tables\TableQuery;
use Lattice\Lattice\Tables\TableResult;
use Lattice\Lattice\Ui\Components\Text;
use Lattice\Lattice\Ui\Enums\Size;

/**
 * Every confirmed non-backup factor enrollment across all registered
 * providers, with per-enrollment revocation for enrollable providers —
 * including passkeys.
 */
#[AsTable('oidc.two-factor.methods')]
class TwoFactorMethodsTable extends TableDefinition
{
    public function __construct(private readonly FactorRegistry $factors) {}

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
                    Text::bound('method'),
                    Text::bound('label')->color(ColorName::Muted)->size(Size::Sm),
                    Text::bound('detail')->color(ColorName::Muted)->size(Size::Sm),
                ]),
            TextColumn::make('last_used_at_diff')->label(__('oidc-ui::security.methods.last-used')),
        ];
    }

    public function actions(array $row): array
    {
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

            return TableResult::fromItems($rows);
        });
    }

    /**
     * @return array{id: string, provider: string, method: string, label: string, detail: string, last_used_at_diff: string}
     */
    private function row(FactorEnrollment $enrollment): array
    {
        $authenticator = $enrollment->metadata['authenticator'] ?? null;

        return [
            'id' => $enrollment->id,
            'provider' => $enrollment->providerKey,
            'method' => FactorMethodName::for($enrollment->providerKey),
            'label' => $enrollment->label,
            'detail' => is_string($authenticator) ? $authenticator : '',
            'last_used_at_diff' => $enrollment->lastUsedAt === null
                ? __('oidc-ui::security.methods.never-used')
                : __('oidc-ui::security.methods.last-used-at', ['time' => Carbon::instance($enrollment->lastUsedAt)->diffForHumans()]),
        ];
    }
}
