<?php

namespace App\Http\Controllers;

use App\Jobs\ImportSubscribersJob;
use App\Models\Device;
use App\Models\ImportStatus;
use App\Models\Subscriber;
use App\Models\SubscriberEquipment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SubscriberController extends Controller
{
    /**
     * Display a listing of subscribers.
     */
    public function index(Request $request)
    {
        $query = Subscriber::query()->with('equipment', 'devices');

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('customer', 'like', "%{$search}%")
                    ->orWhere('account', 'like', "%{$search}%");
            });
        }

        // Filter by service type
        if ($request->filled('service_type')) {
            $query->where('service_type', $request->service_type);
        }

        // Filter by has devices
        if ($request->filled('has_devices')) {
            if ($request->has_devices === 'yes') {
                $query->whereHas('devices');
            } elseif ($request->has_devices === 'no') {
                $query->whereDoesntHave('devices');
            }
        }

        // Sorting
        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');

        // Validate sort field
        $allowedSorts = ['customer', 'name', 'service_type', 'connection_date', 'devices_count'];
        if (!in_array($sortField, $allowedSorts)) {
            $sortField = 'name';
        }

        // Validate direction
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        // Add devices count for sorting
        $query->withCount('devices');

        if ($sortField === 'devices_count') {
            $query->orderBy('devices_count', $sortDirection);
        } else {
            $query->orderBy($sortField, $sortDirection);
        }

        $subscribers = $query->paginate(50)->withQueryString();

        // Get unique service types for filter dropdown
        $serviceTypes = Subscriber::select('service_type')
            ->distinct()
            ->whereNotNull('service_type')
            ->where('service_type', '!=', '')
            ->orderBy('service_type')
            ->pluck('service_type');

        return view('subscribers.index', compact('subscribers', 'serviceTypes', 'sortField', 'sortDirection'));
    }

    /**
     * Display the specified subscriber.
     */
    public function show($id)
    {
        $subscriber = Subscriber::with(['equipment', 'devices'])->findOrFail($id);

        // Get other accounts under the same customer (excluding current one)
        $relatedAccounts = Subscriber::where('customer', $subscriber->customer)
            ->where('id', '!=', $subscriber->id)
            ->withCount(['equipment', 'devices'])
            ->orderBy('account')
            ->get();

        return view('subscribers.show', compact('subscriber', 'relatedAccounts'));
    }

    /**
     * Show the import form.
     */
    public function import()
    {
        $stats = [
            'total_subscribers' => Subscriber::count(),
            'total_equipment' => SubscriberEquipment::count(),
            'linked_devices' => Device::whereNotNull('subscriber_id')->count(),
        ];

        // Get recent imports and any currently running import
        $recentImports = ImportStatus::where('type', 'subscriber')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $runningImport = ImportStatus::where('type', 'subscriber')
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        return view('subscribers.import', compact('stats', 'recentImports', 'runningImport'));
    }

    /**
     * Process the CSV file upload and import.
     */
    public function processImport(Request $request)
    {
        $request->validate([
            'csv_files' => 'required',
            'csv_files.*' => 'required|file|mimes:csv,txt|max:102400', // Max 100MB per file
        ]);

        // Check if there's already an import running
        $runningImport = ImportStatus::where('type', 'subscriber')
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($runningImport) {
            return redirect()
                ->route('subscribers.import')
                ->with('error', 'An import is already in progress. Please wait for it to complete.');
        }

        $truncate = $request->has('truncate');
        $uploadedFiles = [];
        $filenames = [];

        try {
            // Store uploaded files
            foreach ($request->file('csv_files') as $file) {
                $filename = 'import_' . now()->format('Y-m-d_His') . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('subscriber-imports', $filename);
                $uploadedFiles[] = Storage::path($path);
                $filenames[] = $file->getClientOriginalName();
            }

            // Create import status record
            $importStatus = ImportStatus::create([
                'type' => 'subscriber',
                'status' => 'pending',
                'filename' => implode(', ', $filenames),
                'message' => 'Import queued, waiting to start...',
                'user_id' => auth()->id(),
            ]);

            // Dispatch the job
            ImportSubscribersJob::dispatch($uploadedFiles, $truncate, $importStatus->id);

            return redirect()
                ->route('subscribers.import')
                ->with('success', 'Import started! The file is being processed in the background. This page will update with progress.');

        } catch (\Exception $e) {
            return redirect()
                ->route('subscribers.import')
                ->with('error', 'Failed to start import: ' . $e->getMessage());
        }
    }

    /**
     * Get import status for AJAX polling.
     */
    public function importStatus(ImportStatus $importStatus)
    {
        return response()->json([
            'id' => $importStatus->id,
            'status' => $importStatus->status,
            'progress_percent' => $importStatus->progress_percent,
            'total_rows' => $importStatus->total_rows,
            'processed_rows' => $importStatus->processed_rows,
            'subscribers_created' => $importStatus->subscribers_created,
            'subscribers_updated' => $importStatus->subscribers_updated,
            'equipment_created' => $importStatus->equipment_created,
            'devices_linked' => $importStatus->devices_linked,
            'message' => $importStatus->message,
            'is_running' => $importStatus->isRunning(),
        ]);
    }

    /**
     * Process a single CSV file.
     */
    protected function processFile($filePath)
    {
        $stats = [
            'subscribers_created' => 0,
            'subscribers_updated' => 0,
            'equipment_created' => 0,
        ];

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception("Unable to open file: {$filePath}");
        }

        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            throw new \Exception("Invalid CSV file: {$filePath}");
        }

        $headers = array_map('trim', $headers);

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                continue; // Skip malformed rows
            }

            $data = array_combine($headers, $row);
            $rowStats = $this->processRow($data);

            $stats['subscribers_created'] += $rowStats['subscriber_created'];
            $stats['subscribers_updated'] += $rowStats['subscriber_updated'];
            $stats['equipment_created'] += $rowStats['equipment_created'];
        }

        fclose($handle);
        return $stats;
    }

    /**
     * Process a single row of data.
     */
    protected function processRow($data)
    {
        $stats = [
            'subscriber_created' => 0,
            'subscriber_updated' => 0,
            'equipment_created' => 0,
        ];

        // Clean up data
        $customer = trim($data['Customer'] ?? '');
        $account = trim($data['Account'] ?? '');
        $agreement = trim($data['Agreement'] ?? '');
        $name = trim($data['Name'] ?? '');
        $serviceType = trim($data['Serv Type'] ?? '');
        $connDate = $this->parseDate($data['Conn Dt'] ?? '');

        if (empty($customer) || empty($account) || empty($name)) {
            return $stats; // Skip rows without essential data
        }

        // Upsert subscriber
        $subscriber = Subscriber::updateOrCreate(
            [
                'customer' => $customer,
                'account' => $account,
            ],
            [
                'agreement' => $agreement,
                'name' => $name,
                'service_type' => $serviceType,
                'connection_date' => $connDate,
            ]
        );

        if ($subscriber->wasRecentlyCreated) {
            $stats['subscriber_created'] = 1;
        } else {
            $stats['subscriber_updated'] = 1;
        }

        // Process equipment data
        $equipItem = trim($data['Equip Item'] ?? '');
        $equipDesc = trim($data['Equip Desc'] ?? $data['Desc'] ?? '');
        $startDate = $this->parseDate($data['Start Dt'] ?? '');
        $manufacturer = trim($data['Manufacturer'] ?? '');
        $model = trim($data['Model'] ?? '');
        $serial = trim($data['Serial'] ?? '');

        // Only create equipment record if there's equipment data
        if (!empty($equipItem) || !empty($serial)) {
            SubscriberEquipment::create([
                'subscriber_id' => $subscriber->id,
                'customer' => $customer,
                'account' => $account,
                'agreement' => $agreement,
                'equip_item' => $equipItem,
                'equip_desc' => $equipDesc,
                'start_date' => $startDate,
                'manufacturer' => $manufacturer,
                'model' => $model,
                'serial' => $serial,
            ]);

            $stats['equipment_created'] = 1;
        }

        return $stats;
    }

    /**
     * Link devices to subscribers by serial number.
     */
    protected function linkDevicesToSubscribers()
    {
        $linkedCount = 0;

        // Get all equipment records with serial numbers
        $equipmentWithSerials = SubscriberEquipment::whereNotNull('serial')
            ->where('serial', '!=', '')
            ->get();

        foreach ($equipmentWithSerials as $equipment) {
            // Find device by serial number
            $device = Device::where('serial_number', $equipment->serial)->first();

            if ($device && $equipment->subscriber_id) {
                $device->subscriber_id = $equipment->subscriber_id;
                $device->save();
                $linkedCount++;
            }
        }

        return $linkedCount;
    }

    /**
     * Parse date string to Y-m-d format.
     */
    protected function parseDate($dateString)
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            return Carbon::parse($dateString)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
