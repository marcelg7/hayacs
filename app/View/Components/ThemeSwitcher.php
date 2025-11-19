<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ThemeSwitcher extends Component
{
    public array $themes;
    public string $currentTheme;

    public function __construct()
    {
        $this->themes = config('themes');
        $this->currentTheme = session('theme', 'standard');
    }

    public function render(): View|Closure|string
    {
        return view('components.theme-switcher');
    }
}
