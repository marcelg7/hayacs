<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DocsController extends Controller
{
    /**
     * Documentation structure with sections and pages
     */
    protected array $structure = [
        'getting-started' => [
            'title' => 'Getting Started',
            'icon' => 'rocket',
            'pages' => [
                'index' => 'Overview',
                'first-login' => 'First Login & Password Setup',
                'two-factor-auth' => 'Two-Factor Authentication',
                'trusted-device' => 'Trust This Device (90-Day)',
                'navigation' => 'Navigating the Interface',
            ],
        ],
        'devices' => [
            'title' => 'Device Management',
            'icon' => 'cpu',
            'pages' => [
                'index' => 'Overview',
                'device-list' => 'Device List & Search',
                'device-dashboard' => 'Device Dashboard',
                'wifi-management' => 'WiFi Configuration',
                'speed-test' => 'Speed Testing',
                'remote-gui' => 'Remote GUI Access',
                'reboot-reset' => 'Reboot & Factory Reset',
                'troubleshooting' => 'Troubleshooting Tools',
            ],
        ],
        'reports' => [
            'title' => 'Reports',
            'icon' => 'chart',
            'pages' => [
                'index' => 'Available Reports',
                'duplicate-macs' => 'Duplicate MAC Addresses',
                'analytics' => 'Analytics Dashboard',
            ],
        ],
        'admin' => [
            'title' => 'Administration',
            'icon' => 'shield',
            'admin_only' => true,
            'pages' => [
                'index' => 'Admin Overview',
                'users' => 'User Management',
                'device-groups' => 'Device Groups',
                'workflows' => 'Workflows & Automation',
                'firmware' => 'Firmware Management',
                'tasks' => 'Task Management',
                'trusted-devices-admin' => 'Trusted Devices Admin',
            ],
        ],
        'monitoring' => [
            'title' => 'Monitoring',
            'icon' => 'chart',
            'admin_only' => true,
            'pages' => [
                'index' => 'Overview',
                'dashboards' => 'Grafana Dashboards',
                'prometheus' => 'Prometheus & Exporters',
            ],
        ],
        'reference' => [
            'title' => 'Reference',
            'icon' => 'book',
            'pages' => [
                'device-types' => 'Supported Device Types',
                'task-types' => 'Task Types',
                'glossary' => 'Glossary',
            ],
        ],
    ];

    /**
     * Show documentation index
     */
    public function index()
    {
        return view('docs.index', [
            'structure' => $this->getFilteredStructure(),
            'currentSection' => null,
            'currentPage' => null,
        ]);
    }

    /**
     * Show a specific documentation page
     */
    public function show(string $section, string $page = 'index')
    {
        $structure = $this->getFilteredStructure();

        // Validate section exists
        if (!isset($structure[$section])) {
            abort(404, 'Documentation section not found');
        }

        // Validate page exists in section
        if (!isset($structure[$section]['pages'][$page])) {
            abort(404, 'Documentation page not found');
        }

        // Check admin-only sections
        if (isset($this->structure[$section]['admin_only']) &&
            $this->structure[$section]['admin_only'] &&
            !auth()->user()->isAdmin()) {
            abort(403, 'This documentation section is for administrators only');
        }

        $viewPath = "docs.{$section}.{$page}";

        // Check if specific view exists, otherwise use generic
        if (!view()->exists($viewPath)) {
            $viewPath = 'docs.placeholder';
        }

        return view($viewPath, [
            'structure' => $structure,
            'currentSection' => $section,
            'currentPage' => $page,
            'sectionTitle' => $structure[$section]['title'],
            'pageTitle' => $structure[$section]['pages'][$page],
        ]);
    }

    /**
     * Get structure filtered by user role
     */
    protected function getFilteredStructure(): array
    {
        $filtered = $this->structure;

        // Remove admin-only sections for non-admins
        if (!auth()->user()->isAdmin()) {
            foreach ($filtered as $key => $section) {
                if (isset($section['admin_only']) && $section['admin_only']) {
                    unset($filtered[$key]);
                }
            }
        }

        return $filtered;
    }
}
