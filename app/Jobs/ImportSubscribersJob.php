<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\Subscriber;
use App\Models\SubscriberEquipment;
use App\Models\ImportStatus;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportSubscribersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 3600; // 1 hour

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 1;

    protected array $filePaths;
    protected bool $truncate;
    protected int $importStatusId;

    /**
     * Create a new job instance.
     */
    public function __construct(array $filePaths, bool $truncate, int $importStatusId)
    {
        $this->filePaths = $filePaths;
        $this->truncate = $truncate;
        $this->importStatusId = $importStatusId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $importStatus = ImportStatus::find($this->importStatusId);
        if (!$importStatus) {
            Log::error('ImportSubscribersJob: ImportStatus not found', ['id' => $this->importStatusId]);
            return;
        }

        $stats = [
            'subscribers_created' => 0,
            'subscribers_updated' => 0,
            'equipment_created' => 0,
            'equipment_updated' => 0,
            'devices_linked' => 0,
            'rows_processed' => 0,
        ];

        try {
            $importStatus->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            // Count total rows first for progress tracking
            $totalRows = 0;
            foreach ($this->filePaths as $filePath) {
                if (file_exists($filePath)) {
                    $totalRows += $this->countLines($filePath) - 1; // Subtract header row
                }
            }

            $importStatus->update(['total_rows' => $totalRows]);

            // Truncate if requested
            if ($this->truncate) {
                Log::info('ImportSubscribersJob: Truncating existing data');
                DB::table('subscriber_equipment')->truncate();
                DB::table('subscribers')->truncate();
                DB::table('devices')->update(['subscriber_id' => null]);
            }

            // Process each file
            foreach ($this->filePaths as $filePath) {
                if (!file_exists($filePath)) {
                    Log::warning('ImportSubscribersJob: File not found', ['path' => $filePath]);
                    continue;
                }

                $this->processFile($filePath, $stats, $importStatus);
            }

            // Link devices to subscribers
            $importStatus->update(['message' => 'Linking devices to subscribers...']);
            $stats['devices_linked'] = $this->linkDevicesToSubscribers();

            // Mark as completed
            $importStatus->update([
                'status' => 'completed',
                'completed_at' => now(),
                'subscribers_created' => $stats['subscribers_created'],
                'subscribers_updated' => $stats['subscribers_updated'],
                'equipment_created' => $stats['equipment_created'],
                'devices_linked' => $stats['devices_linked'],
                'processed_rows' => $stats['rows_processed'],
                'message' => 'Import completed successfully!',
            ]);

            Log::info('ImportSubscribersJob: Completed', $stats);

        } catch (\Exception $e) {
            Log::error('ImportSubscribersJob: Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $importStatus->update([
                'status' => 'failed',
                'completed_at' => now(),
                'message' => 'Import failed: ' . $e->getMessage(),
                'subscribers_created' => $stats['subscribers_created'],
                'subscribers_updated' => $stats['subscribers_updated'],
                'equipment_created' => $stats['equipment_created'],
                'devices_linked' => $stats['devices_linked'],
                'processed_rows' => $stats['rows_processed'],
            ]);
        }
    }

    /**
     * Count lines in a file efficiently.
     */
    protected function countLines(string $filePath): int
    {
        $count = 0;
        $handle = fopen($filePath, 'r');
        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line !== false) {
                $count++;
            }
        }
        fclose($handle);
        return $count;
    }

    /**
     * Process a single CSV file.
     */
    protected function processFile(string $filePath, array &$stats, ImportStatus $importStatus): void
    {
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
        $batchSize = 100;
        $rowsInBatch = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                continue; // Skip malformed rows
            }

            $data = array_combine($headers, $row);
            $rowStats = $this->processRow($data);

            $stats['subscribers_created'] += $rowStats['subscriber_created'];
            $stats['subscribers_updated'] += $rowStats['subscriber_updated'];
            $stats['equipment_created'] += $rowStats['equipment_created'];
            $stats['equipment_updated'] += $rowStats['equipment_updated'];
            $stats['rows_processed']++;
            $rowsInBatch++;

            // Update progress every batch
            if ($rowsInBatch >= $batchSize) {
                $importStatus->update([
                    'processed_rows' => $stats['rows_processed'],
                    'subscribers_created' => $stats['subscribers_created'],
                    'subscribers_updated' => $stats['subscribers_updated'],
                    'equipment_created' => $stats['equipment_created'],
                ]);
                $rowsInBatch = 0;
            }
        }

        // Final update for this file
        $importStatus->update([
            'processed_rows' => $stats['rows_processed'],
            'subscribers_created' => $stats['subscribers_created'],
            'subscribers_updated' => $stats['subscribers_updated'],
            'equipment_created' => $stats['equipment_created'],
        ]);

        fclose($handle);
    }

    /**
     * Process a single row of data.
     */
    protected function processRow(array $data): array
    {
        $stats = [
            'subscriber_created' => 0,
            'subscriber_updated' => 0,
            'equipment_created' => 0,
            'equipment_updated' => 0,
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

        // Only create/update equipment record if there's equipment data
        if (!empty($equipItem) || !empty($serial)) {
            // Use serial as unique key if available, otherwise use equip_item
            // This prevents duplicate equipment records on re-import
            $uniqueKey = !empty($serial)
                ? ['subscriber_id' => $subscriber->id, 'serial' => $serial]
                : ['subscriber_id' => $subscriber->id, 'equip_item' => $equipItem];

            $equipment = SubscriberEquipment::updateOrCreate(
                $uniqueKey,
                [
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
                ]
            );

            // Track created vs updated
            if ($equipment->wasRecentlyCreated) {
                $stats['equipment_created'] = 1;
            } else {
                $stats['equipment_updated'] = 1;
            }
        }

        return $stats;
    }

    /**
     * Link devices to subscribers by serial number.
     */
    protected function linkDevicesToSubscribers(): int
    {
        $linkedCount = 0;

        // Get all equipment records with serial numbers
        $equipmentWithSerials = SubscriberEquipment::whereNotNull('serial')
            ->where('serial', '!=', '')
            ->get();

        foreach ($equipmentWithSerials as $equipment) {
            // Find device by serial number (case-insensitive)
            $device = Device::whereRaw('LOWER(serial_number) = ?', [strtolower($equipment->serial)])->first();

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
    protected function parseDate(?string $dateString): ?string
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
