<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Tables;

use Bambamboole\LaravelOidc\Auth\MultiFactor\Contracts\FactorAuthenticatable;
use Bambamboole\LaravelOidc\Ui\Actions\DeletePasskeyAction;
use Laravel\Passkeys\Passkey;
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

#[AsTable('oidc.passkeys')]
class PasskeysTable extends TableDefinition
{
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
            StackColumn::make('passkey')
                ->label(__('oidc-ui::security.passkeys.column'))
                ->schema([
                    Text::bound('name'),
                    Text::bound('authenticator')->color(ColorName::Muted)->size(Size::Sm),
                    Text::bound('created_at_diff')->color(ColorName::Muted)->size(Size::Sm),
                ]),
            TextColumn::make('last_used_at_diff')->label(__('oidc-ui::security.passkeys.last-used')),
        ];
    }

    public function actions(array $row): array
    {
        return [
            Action::use(DeletePasskeyAction::class, ['passkey' => $row['id']]),
        ];
    }

    public function source(): TableSource
    {
        return new CallbackTableSource(function (TableQuery $query): TableResult {
            $user = auth()->user();

            if (! $user instanceof FactorAuthenticatable) {
                return TableResult::fromItems([]);
            }

            return TableResult::fromItems(
                $user
                    ->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn (Passkey $passkey) => [
                        'id' => $passkey->id,
                        'name' => $passkey->name,
                        'authenticator' => $passkey->authenticator ?? '',
                        'created_at_diff' => __('oidc-ui::security.passkeys.added', ['time' => $passkey->created_at?->diffForHumans() ?? '']),
                        'last_used_at_diff' => $passkey->last_used_at === null
                            ? __('oidc-ui::security.passkeys.never-used')
                            : __('oidc-ui::security.passkeys.last-used-at', ['time' => $passkey->last_used_at->diffForHumans()]),
                    ]),
            );
        });
    }
}
