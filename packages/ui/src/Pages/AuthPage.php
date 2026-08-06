<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Pages;

use Lattice\Core\Enums\PageContainer;
use Lattice\Core\Enums\PageLayout;
use Lattice\Http\Page;
use Lattice\Ui\Components\Heading;
use Lattice\Ui\Components\Stack;
use Lattice\Ui\Components\Text;
use Lattice\Ui\Enums\Align;
use Lattice\Ui\Enums\Gap;

abstract class AuthPage extends Page
{
    public function layout(): PageLayout|string|null
    {
        return PageLayout::Auth;
    }

    public function container(): PageContainer|string|null
    {
        return PageContainer::Default;
    }

    protected function heading(string $name, string $heading, ?string $subtitle = null): Stack
    {
        return Stack::make($name)
            ->gap(Gap::Small)
            ->schema([
                Heading::make($heading, 2),
                ...($subtitle === null ? [] : [Text::make($subtitle)->align(Align::Center)]),
            ]);
    }
}
