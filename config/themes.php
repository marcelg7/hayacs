<?php

return [
    'standard' => [
        'name' => 'Standard Theme',
        'type' => 'light',
        'use_colorful_buttons' => true, // Each button type has its own color
        'colors' => [
            'primary' => 'indigo',
            'secondary' => 'gray',
            'success' => 'green',
            'warning' => 'yellow',
            'danger' => 'red',
            'info' => 'blue',
            'bg' => 'gray-50',
            'card' => 'white',
            'text' => 'gray-900',
            'text-muted' => 'gray-600',
            'border' => 'gray-200',
            // Button colors for colorful mode
            'btn-primary' => 'indigo',      // Main actions
            'btn-secondary' => 'gray',      // Secondary actions
            'btn-success' => 'green',       // Positive actions
            'btn-warning' => 'orange',      // Caution actions
            'btn-danger' => 'red',          // Delete/dangerous actions
            'btn-info' => 'cyan',           // Info/query actions
        ],
    ],
    'ocean' => [
        'name' => 'Ocean Blue',
        'type' => 'light',
        'use_colorful_buttons' => false, // All buttons use primary color
        'colors' => [
            'primary' => 'blue',
            'secondary' => 'cyan',
            'success' => 'teal',
            'warning' => 'amber',
            'danger' => 'rose',
            'info' => 'sky',
            'bg' => 'blue-50',
            'card' => 'white',
            'text' => 'slate-900',
            'text-muted' => 'slate-600',
            'border' => 'blue-200',
            // All buttons use primary color
            'btn-primary' => 'blue',
            'btn-secondary' => 'blue',
            'btn-success' => 'blue',
            'btn-warning' => 'blue',
            'btn-danger' => 'blue',
            'btn-info' => 'blue',
        ],
    ],
    'forest' => [
        'name' => 'Forest Green',
        'type' => 'light',
        'use_colorful_buttons' => false,
        'colors' => [
            'primary' => 'emerald',
            'secondary' => 'green',
            'success' => 'lime',
            'warning' => 'yellow',
            'danger' => 'red',
            'info' => 'teal',
            'bg' => 'emerald-50',
            'card' => 'white',
            'text' => 'gray-900',
            'text-muted' => 'gray-700',
            'border' => 'emerald-200',
            // All buttons use primary color
            'btn-primary' => 'emerald',
            'btn-secondary' => 'emerald',
            'btn-success' => 'emerald',
            'btn-warning' => 'emerald',
            'btn-danger' => 'emerald',
            'btn-info' => 'emerald',
        ],
    ],
    'sunset' => [
        'name' => 'Sunset Orange',
        'type' => 'light',
        'use_colorful_buttons' => false,
        'colors' => [
            'primary' => 'orange',
            'secondary' => 'amber',
            'success' => 'green',
            'warning' => 'yellow',
            'danger' => 'red',
            'info' => 'blue',
            'bg' => 'orange-50',
            'card' => 'white',
            'text' => 'gray-900',
            'text-muted' => 'gray-600',
            'border' => 'orange-200',
            // All buttons use primary color
            'btn-primary' => 'orange',
            'btn-secondary' => 'orange',
            'btn-success' => 'orange',
            'btn-warning' => 'orange',
            'btn-danger' => 'orange',
            'btn-info' => 'orange',
        ],
    ],
    'midnight' => [
        'name' => 'Midnight Dark',
        'type' => 'dark',
        'use_colorful_buttons' => false,
        'colors' => [
            'primary' => 'blue',
            'secondary' => 'slate',
            'success' => 'emerald',
            'warning' => 'amber',
            'danger' => 'rose',
            'info' => 'cyan',
            'bg' => 'slate-900',
            'card' => 'slate-800',
            'text' => 'slate-100',          // Light text for dark background
            'text-muted' => 'slate-400',    // Lighter muted for visibility
            'border' => 'slate-700',
            // All buttons use primary color
            'btn-primary' => 'blue',
            'btn-secondary' => 'blue',
            'btn-success' => 'blue',
            'btn-warning' => 'blue',
            'btn-danger' => 'blue',
            'btn-info' => 'blue',
        ],
    ],
    'purple-haze' => [
        'name' => 'Purple Haze',
        'type' => 'dark',
        'use_colorful_buttons' => false,
        'colors' => [
            'primary' => 'purple',
            'secondary' => 'violet',
            'success' => 'green',
            'warning' => 'yellow',
            'danger' => 'red',
            'info' => 'blue',
            'bg' => 'purple-950',
            'card' => 'purple-900',
            'text' => 'purple-100',         // Lighter text for better readability
            'text-muted' => 'purple-300',   // Lighter muted for visibility
            'border' => 'purple-700',
            // All buttons use primary color
            'btn-primary' => 'purple',
            'btn-secondary' => 'purple',
            'btn-success' => 'purple',
            'btn-warning' => 'purple',
            'btn-danger' => 'purple',
            'btn-info' => 'purple',
        ],
    ],
];
