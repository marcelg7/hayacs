<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrustedDevice;
use App\Models\TrustedDeviceLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrustedDeviceController extends Controller
{
    /**
     * Display all trusted devices across all users.
     */
    public function index(Request $request): View
    {
        $query = TrustedDevice::with('user')
            ->orderBy('last_used_at', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter by status
        $status = $request->get('status', 'active');
        if ($status === 'active') {
            $query->where('revoked', false)->where('expires_at', '>', now());
        } elseif ($status === 'revoked') {
            $query->where('revoked', true);
        } elseif ($status === 'expired') {
            $query->where('revoked', false)->where('expires_at', '<=', now());
        }
        // 'all' shows everything

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        $trustedDevices = $query->paginate(25)->withQueryString();

        // Get users for filter dropdown
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        // Get summary stats
        $stats = [
            'total_active' => TrustedDevice::where('revoked', false)->where('expires_at', '>', now())->count(),
            'total_revoked' => TrustedDevice::where('revoked', true)->count(),
            'total_expired' => TrustedDevice::where('revoked', false)->where('expires_at', '<=', now())->count(),
            'users_with_trusted' => TrustedDevice::where('revoked', false)->where('expires_at', '>', now())->distinct('user_id')->count('user_id'),
        ];

        return view('admin.trusted-devices.index', [
            'trustedDevices' => $trustedDevices,
            'users' => $users,
            'stats' => $stats,
            'currentStatus' => $status,
            'currentUserId' => $request->get('user_id'),
        ]);
    }

    /**
     * Show details for a specific trusted device.
     */
    public function show(TrustedDevice $trustedDevice): View
    {
        $trustedDevice->load('user', 'logs');

        // Get recent activity logs
        $recentLogs = $trustedDevice->logs()
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return view('admin.trusted-devices.show', [
            'trustedDevice' => $trustedDevice,
            'recentLogs' => $recentLogs,
        ]);
    }

    /**
     * Revoke a trusted device.
     */
    public function revoke(Request $request, TrustedDevice $trustedDevice): RedirectResponse
    {
        if ($trustedDevice->revoked) {
            return back()->with('error', 'This device is already revoked.');
        }

        $trustedDevice->revoke($request->user()->name);

        return back()->with('success', "Trusted device '{$trustedDevice->device_name}' has been revoked.");
    }

    /**
     * Revoke all trusted devices for a user.
     */
    public function revokeAll(Request $request, User $user): RedirectResponse
    {
        $activeDevices = $user->activeTrustedDevices;
        $count = $activeDevices->count();

        if ($count === 0) {
            return back()->with('error', 'User has no active trusted devices to revoke.');
        }

        foreach ($activeDevices as $device) {
            $device->revoke($request->user()->name);
        }

        return back()->with('success', "Revoked {$count} trusted device(s) for {$user->name}.");
    }

    /**
     * View activity logs.
     */
    public function logs(Request $request): View
    {
        $query = TrustedDeviceLog::with(['trustedDevice.user'])
            ->orderBy('created_at', 'desc');

        // Filter by action type
        if ($request->filled('action')) {
            $query->where('action', $request->get('action'));
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->whereHas('trustedDevice', function ($q) use ($request) {
                $q->where('user_id', $request->get('user_id'));
            });
        }

        // Filter by fingerprint match
        if ($request->has('fingerprint_match')) {
            $query->where('fingerprint_matched', $request->boolean('fingerprint_match'));
        }

        $logs = $query->paginate(50)->withQueryString();

        // Get users for filter dropdown
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        // Get action types for filter
        $actionTypes = TrustedDeviceLog::distinct('action')->pluck('action');

        return view('admin.trusted-devices.logs', [
            'logs' => $logs,
            'users' => $users,
            'actionTypes' => $actionTypes,
            'currentAction' => $request->get('action'),
            'currentUserId' => $request->get('user_id'),
            'currentFingerprintMatch' => $request->get('fingerprint_match'),
        ]);
    }
}
