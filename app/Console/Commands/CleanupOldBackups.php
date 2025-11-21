<?php

namespace App\Console\Commands;

use App\Models\ConfigBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupOldBackups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backups:cleanup
                            {--auto-days=7 : Retention days for automated backups}
                            {--user-days=90 : Retention days for user-created backups}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old backups with tiered retention (Initial: Never, User: 90 days, Auto: 7 days)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $autoDays = $this->option('auto-days');
        $userDays = $this->option('user-days');

        $this->info("Backup Cleanup - Tiered Retention Policy");
        $this->info("========================================");
        $this->info("  Initial backups: Never deleted");
        $this->info("  User backups: {$userDays} days");
        $this->info("  Automated backups: {$autoDays} days");
        $this->newLine();

        $totalDeleted = 0;
        $totalSize = 0;

        // 1. Clean up automated backups older than 7 days
        $autoCutoff = now()->subDays($autoDays);
        $autoBackups = ConfigBackup::where('created_at', '<', $autoCutoff)
            ->where('is_auto', true)
            ->where('description', 'not like', '%first TR-069 connection%')
            ->get();

        if ($autoBackups->isNotEmpty()) {
            $this->info("Cleaning automated backups older than {$autoDays} days ({$autoCutoff->format('Y-m-d')})...");
            foreach ($autoBackups as $backup) {
                $size = strlen(json_encode($backup->backup_data));
                $totalSize += $size;
                $this->info("  [AUTO] {$backup->name} - {$backup->device->id} ({$backup->created_at->format('Y-m-d')})");

                try {
                    Log::info('Deleting automated backup', [
                        'backup_id' => $backup->id,
                        'device_id' => $backup->device_id,
                        'age_days' => $backup->created_at->diffInDays(now()),
                    ]);
                } catch (\Exception $logError) {
                    // Ignore logging errors
                }

                $backup->delete();
                $totalDeleted++;
            }
        }

        // 2. Clean up user-created backups older than 90 days
        $userCutoff = now()->subDays($userDays);
        $userBackups = ConfigBackup::where('created_at', '<', $userCutoff)
            ->where('is_auto', false)
            ->where('description', 'not like', '%first TR-069 connection%')
            ->get();

        if ($userBackups->isNotEmpty()) {
            $this->info("Cleaning user-created backups older than {$userDays} days ({$userCutoff->format('Y-m-d')})...");
            foreach ($userBackups as $backup) {
                $size = strlen(json_encode($backup->backup_data));
                $totalSize += $size;
                $this->info("  [USER] {$backup->name} - {$backup->device->id} ({$backup->created_at->format('Y-m-d')})");

                try {
                    Log::info('Deleting user backup', [
                        'backup_id' => $backup->id,
                        'device_id' => $backup->device_id,
                        'age_days' => $backup->created_at->diffInDays(now()),
                    ]);
                } catch (\Exception $logError) {
                    // Ignore logging errors
                }

                $backup->delete();
                $totalDeleted++;
            }
        }

        if ($totalDeleted === 0) {
            $this->info('No old backups found to clean up.');
        } else {
            $sizeMB = round($totalSize / 1024 / 1024, 2);
            $this->newLine();
            $this->info("Cleanup complete:");
            $this->info("  Deleted: {$totalDeleted} backups");
            $this->info("  Freed: {$sizeMB} MB");
        }

        // Show retention summary
        $totalBackups = ConfigBackup::count();
        $initialBackups = ConfigBackup::where('description', 'like', '%first TR-069 connection%')->count();
        $userBackups = ConfigBackup::where('is_auto', false)
            ->where('description', 'not like', '%first TR-069 connection%')
            ->count();
        $autoBackups = ConfigBackup::where('is_auto', true)
            ->where('description', 'not like', '%first TR-069 connection%')
            ->count();

        $this->newLine();
        $this->info("Retention summary:");
        $this->info("  Total backups: {$totalBackups}");
        $this->info("  ├─ Initial (protected): {$initialBackups}");
        $this->info("  ├─ User created ({$userDays}d): {$userBackups}");
        $this->info("  └─ Automated ({$autoDays}d): {$autoBackups}");

        try {
            Log::info('Backup cleanup completed', [
                'deleted_count' => $totalDeleted,
                'freed_mb' => $sizeMB ?? 0,
                'auto_retention_days' => $autoDays,
                'user_retention_days' => $userDays,
                'total_remaining' => $totalBackups,
                'protected_initial' => $initialBackups,
                'user_backups' => $userBackups,
                'auto_backups' => $autoBackups,
            ]);
        } catch (\Exception $logError) {
            // Ignore logging errors
        }

        return Command::SUCCESS;
    }
}
