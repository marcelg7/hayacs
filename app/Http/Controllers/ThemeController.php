<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ThemeController extends Controller
{
    /**
     * Set the theme for the current session.
     */
    public function set(string $theme)
    {
        $availableThemes = array_keys(config('themes', []));

        if (!in_array($theme, $availableThemes)) {
            $theme = 'standard';
        }

        session(['theme' => $theme]);

        return redirect()->back();
    }
}
