<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use App\Models\SubscriberEquipment;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SubscriberController extends Controller
{
    /**
     * Display a listing of subscribers.
     */
    public function index(Request $request)
    {
        $query = Subscriber::query()->with('equipment', 'devices');

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('customer', 'like', "%{$search}%")
                    ->orWhere('account', 'like', "%{$search}%");
            });
        }

        $subscribers = $query->orderBy('name')->paginate(50);

        return view('subscribers.index', compact('subscribers'));
    }

    /**
     * Display the specified subscriber.
     */
    public function show($id)
    {
        $subscriber = Subscriber::with(['equipment', 'devices'])->findOrFail($id);

        return view('subscribers.show', compact('subscriber'));
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

        return view('subscribers.import', compact('stats'));
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

        $truncate = $request->has('truncate');
        $uploadedFiles = [];
        $stats = [
            'subscribers_created' => 0,
            'subscribers_updated' => 0,
            'equipment_created' => 0,
            'devices_linked' => 0,
        ];

        try {
            // Store uploaded files
            foreach ($request->file('csv_files') as $file) {
                $filename = 'import_' . now()->format('Y-m-d_His') . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('subscriber-imports', $filename);
                $uploadedFiles[] = storage_path('app/' . $path);
            }

            // Truncate if requested
            if ($truncate) {
                DB::table('subscriber_equipment')->truncate();
                DB::table('subscribers')->truncate();
                DB::table('devices')->update(['subscriber_id' => null]);
            }

            // Process each file
            foreach ($uploadedFiles as $filePath) {
                $fileStats = $this->processFile($filePath);
                $stats['subscribers_created'] += $fileStats['subscribers_created'];
                $stats['subscribers_updated'] += $fileStats['subscribers_updated'];
                $stats['equipment_created'] += $fileStats['equipment_created'];
            }

            // Link devices to subscribers
            $stats['devices_linked'] = $this->linkDevicesToSubscribers();

            return redirect()
                ->route('subscribers.import')
                ->with('success', 'Import completed successfully!')
                ->with('stats', $stats);

        } catch (\Exception $e) {
            return redirect()
                ->route('subscribers.import')
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
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
