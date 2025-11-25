<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Subscriber;
use App\Models\SubscriberEquipment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportSubscriberData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriber:import
                            {files* : Path(s) to CSV file(s) to import}
                            {--truncate : Truncate existing data before import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import subscriber and equipment data from NISC Ivue CSV exports';

    protected $stats = [
        'subscribers_created' => 0,
        'subscribers_updated' => 0,
        'equipment_created' => 0,
        'devices_linked' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $files = $this->argument('files');

        $this->info('Starting subscriber data import...');

        if ($this->option('truncate')) {
            if ($this->confirm('This will delete all existing subscriber and equipment data. Continue?')) {
                $this->warn('Truncating subscriber data...');
                DB::table('subscriber_equipment')->truncate();
                DB::table('subscribers')->truncate();
                DB::table('devices')->update(['subscriber_id' => null]);
                $this->info('Existing data cleared.');
            } else {
                $this->error('Import cancelled.');
                return 1;
            }
        }

        foreach ($files as $file) {
            if (!file_exists($file)) {
                $this->error("File not found: {$file}");
                continue;
            }

            $this->info("Processing: {$file}");
            $this->processFile($file);
        }

        // Link devices to subscribers by serial number
        $this->info('Linking devices to subscribers...');
        $this->linkDevicesToSubscribers();

        // Display summary
        $this->newLine();
        $this->info('Import Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Subscribers Created', $this->stats['subscribers_created']],
                ['Subscribers Updated', $this->stats['subscribers_updated']],
                ['Equipment Records', $this->stats['equipment_created']],
                ['Devices Linked', $this->stats['devices_linked']],
            ]
        );

        return 0;
    }

    protected function processFile($filePath)
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error("Unable to open file: {$filePath}");
            return;
        }

        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            $this->error("Invalid CSV file: {$filePath}");
            fclose($handle);
            return;
        }

        $headers = array_map('trim', $headers);
        $rowNumber = 1;

        $bar = $this->output->createProgressBar();
        $bar->start();

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count($row) !== count($headers)) {
                continue; // Skip malformed rows
            }

            $data = array_combine($headers, $row);
            $this->processRow($data);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        fclose($handle);
    }

    protected function processRow($data)
    {
        // Clean up data
        $customer = trim($data['Customer'] ?? '');
        $account = trim($data['Account'] ?? '');
        $agreement = trim($data['Agreement'] ?? '');
        $name = trim($data['Name'] ?? '');
        $serviceType = trim($data['Serv Type'] ?? '');
        $connDate = $this->parseDate($data['Conn Dt'] ?? '');

        if (empty($customer) || empty($account) || empty($name)) {
            return; // Skip rows without essential data
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
            $this->stats['subscribers_created']++;
        } else {
            $this->stats['subscribers_updated']++;
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

            $this->stats['equipment_created']++;
        }
    }

    protected function linkDevicesToSubscribers()
    {
        // Get all equipment records with serial numbers
        $equipmentWithSerials = SubscriberEquipment::whereNotNull('serial')
            ->where('serial', '!=', '')
            ->get();

        $bar = $this->output->createProgressBar($equipmentWithSerials->count());
        $bar->start();

        foreach ($equipmentWithSerials as $equipment) {
            // Find device by serial number
            $device = Device::where('serial_number', $equipment->serial)->first();

            if ($device && $equipment->subscriber_id) {
                $device->subscriber_id = $equipment->subscriber_id;
                $device->save();
                $this->stats['devices_linked']++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

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
