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

        // Benchmarking timers
        $timings = [];
        $totalStart = microtime(true);

        try {
            $importStatus->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            // Count total rows first for progress tracking
            $phaseStart = microtime(true);
            $totalRows = 0;
            foreach ($this->filePaths as $filePath) {
                if (file_exists($filePath)) {
                    $totalRows += $this->countLines($filePath) - 1; // Subtract header row
                }
            }
            $importStatus->update(['total_rows' => $totalRows]);
            $timings['count_rows'] = round(microtime(true) - $phaseStart, 2);

            // Hybrid approach: Keep subscribers (upsert), truncate equipment only
            if ($this->truncate) {
                Log::info('ImportSubscribersJob: Using hybrid approach - keeping subscribers, truncating equipment');

                // Phase 1: Clear device links
                $phaseStart = microtime(true);
                $importStatus->update(['message' => 'Clearing device links...']);
                DB::table('devices')->update(['subscriber_id' => null]);
                $timings['clear_device_links'] = round(microtime(true) - $phaseStart, 2);

                // Phase 2: Truncate equipment only (NOT subscribers)
                $phaseStart = microtime(true);
                $importStatus->update(['message' => 'Truncating equipment table...']);
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                DB::table('subscriber_equipment')->truncate();
                // Note: subscribers table NOT truncated - will use upsert
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                $timings['truncate_equipment'] = round(microtime(true) - $phaseStart, 2);
            }

            // Phase 3: Process each file
            $phaseStart = microtime(true);
            foreach ($this->filePaths as $filePath) {
                if (!file_exists($filePath)) {
                    Log::warning('ImportSubscribersJob: File not found', ['path' => $filePath]);
                    continue;
                }

                $importStatus->update(['message' => 'Processing ' . basename($filePath) . '...']);
                $this->processFile($filePath, $stats, $importStatus);
            }
            $timings['process_files'] = round(microtime(true) - $phaseStart, 2);

            // Phase 4: Link devices to subscribers
            $phaseStart = microtime(true);
            $importStatus->update(['message' => 'Linking devices to subscribers...']);
            $stats['devices_linked'] = $this->linkDevicesToSubscribers();
            $timings['link_devices'] = round(microtime(true) - $phaseStart, 2);

            // Calculate total time
            $timings['total'] = round(microtime(true) - $totalStart, 2);

            // Build timing summary for message
            $timingSummary = sprintf(
                'Timings: count=%ss, clear_links=%ss, truncate=%ss, process=%ss, link=%ss, total=%ss',
                $timings['count_rows'] ?? 0,
                $timings['clear_device_links'] ?? 0,
                $timings['truncate_equipment'] ?? 0,
                $timings['process_files'] ?? 0,
                $timings['link_devices'] ?? 0,
                $timings['total']
            );

            // Mark as completed
            $importStatus->update([
                'status' => 'completed',
                'completed_at' => now(),
                'subscribers_created' => $stats['subscribers_created'],
                'subscribers_updated' => $stats['subscribers_updated'],
                'equipment_created' => $stats['equipment_created'],
                'devices_linked' => $stats['devices_linked'],
                'processed_rows' => $stats['rows_processed'],
                'message' => 'Import completed! ' . $timingSummary,
            ]);

            Log::info('ImportSubscribersJob: Completed', array_merge($stats, ['timings' => $timings]));

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
     * Uses bulk UPDATE with JOIN - fast with indexes on both tables.
     */
    protected function linkDevicesToSubscribers(): int
    {
        // Use bulk UPDATE with JOIN - requires index on devices.serial_number
        // and subscriber_equipment.serial (both exist)
        $affected = DB::update("
            UPDATE devices d
            INNER JOIN subscriber_equipment e
                ON d.serial_number = e.serial
            SET d.subscriber_id = e.subscriber_id
            WHERE e.serial IS NOT NULL
                AND e.serial != ''
                AND e.subscriber_id IS NOT NULL
        ");

        Log::info('ImportSubscribersJob: Bulk device linking completed', [
            'devices_linked' => $affected,
        ]);

        return $affected;
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
