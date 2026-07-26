<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Pages;

use Lattice\Lattice\Http\Page;
use Lattice\Lattice\Ui\Components\Heading;
use Lattice\Lattice\Ui\Components\Stack;
use Lattice\Lattice\Ui\Components\Text;
use Lattice\Lattice\Ui\Enums\Align;
use Lattice\Lattice\Ui\Enums\Gap;
use Lattice\Lattice\Ui\Enums\PageContainer;
use Lattice\Lattice\Ui\Enums\PageLayout;
use LogicException;

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

    /**
     * The controller always resolves a page through respond(), which supplies
     * the real prompt before render() ever runs — a missing prompt here means
     * that invariant broke.
     *
     * @template TPrompt of object
     *
     * @param  TPrompt|null  $prompt
     * @return TPrompt
     */
    protected function requirePrompt(?object $prompt): object
    {
        return $prompt ?? throw new LogicException(static::class.' rendered without its prompt; respond() must supply one before render() runs.');
    }
}
