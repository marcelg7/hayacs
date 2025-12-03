<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Handles file uploads from devices via TR-069 Upload RPC
 *
 * When an ACS sends an Upload RPC to a device, the device will HTTP PUT
 * the requested file to the URL specified in the RPC. This controller
 * receives those uploads.
 *
 * File types:
 * - 1 Vendor Configuration File
 * - 2 Vendor Log File
 * - 3 Vendor Configuration File (alternate)
 * - X <vendor> <identifier> (vendor-specific)
 */
class DeviceUploadController extends Controller
{
    /**
     * Receive uploaded file from device
     *
     * The device sends the file via HTTP PUT with the file content in the body.
     * Authentication can be handled via HTTP Basic Auth or by token in the URL.
     *
     * URL format: /device-upload/{device_id}/{task_id}?token={random_token}
     */
    public function receive(Request $request, string $deviceId, string $taskId): Response
    {
        // Validate token (simple security - token is generated when upload task is created)
        $token = $request->query('token');

        // URL decode the device ID - in URL path segments, + stays as + but we may also get %20 for spaces
        // Try the raw ID first, then try decoding + as space
        $deviceId = urldecode($deviceId);

        Log::info('Device upload received', [
            'device_id' => $deviceId,
            'task_id' => $taskId,
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
            'method' => $request->method(),
        ]);

        // Find the device (try original, then with + decoded as space)
        $device = Device::find($deviceId);
        if (!$device && str_contains($deviceId, '+')) {
            $deviceId = str_replace('+', ' ', $deviceId);
            $device = Device::find($deviceId);
        }
        if (!$device) {
            Log::warning('Device upload rejected: device not found', ['device_id' => $deviceId]);
            return response('Device not found', 404);
        }

        // Find the task
        $task = Task::where('id', $taskId)
            ->where('device_id', $deviceId)
            ->where('task_type', 'upload')
            ->first();

        if (!$task) {
            Log::warning('Device upload rejected: task not found', [
                'device_id' => $deviceId,
                'task_id' => $taskId,
            ]);
            return response('Task not found', 404);
        }

        // Validate token
        $expectedToken = $task->parameters['upload_token'] ?? null;
        if (!$expectedToken || $token !== $expectedToken) {
            Log::warning('Device upload rejected: invalid token', [
                'device_id' => $deviceId,
                'task_id' => $taskId,
            ]);
            return response('Invalid token', 403);
        }

        // Get the raw content from the request body
        $content = $request->getContent();

        if (empty($content)) {
            Log::warning('Device upload rejected: empty content', [
                'device_id' => $deviceId,
                'task_id' => $taskId,
            ]);
            return response('Empty content', 400);
        }

        // Determine file type from task parameters
        $fileType = $task->parameters['file_type'] ?? 'unknown';
        $timestamp = now()->format('Ymd_His');
        $safeDeviceId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $deviceId);

        // Determine filename based on file type
        if (str_contains($fileType, 'Configuration')) {
            $filename = "config_{$safeDeviceId}_{$timestamp}.bin";
            $directory = 'device-configs';
        } elseif (str_contains($fileType, 'Log')) {
            $filename = "log_{$safeDeviceId}_{$timestamp}.txt";
            $directory = 'device-logs';
        } else {
            $filename = "upload_{$safeDeviceId}_{$timestamp}.bin";
            $directory = 'device-uploads';
        }

        // Store the file
        $path = "{$directory}/{$filename}";
        Storage::disk('local')->put($path, $content);

        $storagePath = Storage::disk('local')->path($path);
        $fileSize = strlen($content);

        Log::info('Device upload stored successfully', [
            'device_id' => $deviceId,
            'task_id' => $taskId,
            'file_type' => $fileType,
            'path' => $storagePath,
            'size' => $fileSize,
        ]);

        // Update task with upload result
        $task->update([
            'progress_info' => array_merge($task->progress_info ?? [], [
                'uploaded_file' => $path,
                'uploaded_at' => now()->toIso8601String(),
                'file_size' => $fileSize,
                'content_type' => $request->header('Content-Type'),
            ]),
        ]);

        // Analyze the file content
        $analysis = $this->analyzeUploadedFile($content, $fileType, $device);

        if (!empty($analysis)) {
            $task->update([
                'progress_info' => array_merge($task->progress_info ?? [], [
                    'analysis' => $analysis,
                ]),
            ]);

            Log::info('Upload file analysis', [
                'device_id' => $deviceId,
                'task_id' => $taskId,
                'analysis' => $analysis,
            ]);
        }

        // Return success (device expects 2xx status)
        return response('OK', 200);
    }

    /**
     * Analyze uploaded file content
     */
    private function analyzeUploadedFile(string $content, string $fileType, Device $device): array
    {
        $analysis = [
            'raw_size' => strlen($content),
            'is_binary' => $this->isBinaryContent($content),
        ];

        // Check for common file signatures
        $signature = substr($content, 0, 4);
        $hexSignature = bin2hex($signature);

        // Common file signatures
        if ($hexSignature === '504b0304') {
            $analysis['format'] = 'ZIP archive';
            $analysis['extractable'] = true;
        } elseif ($hexSignature === '1f8b0800') {
            $analysis['format'] = 'GZIP compressed';
            $analysis['extractable'] = true;

            // Try to decompress and check content
            $decompressed = @gzdecode($content);
            if ($decompressed !== false) {
                $analysis['decompressed_size'] = strlen($decompressed);
                $analysis['decompressed_preview'] = substr($decompressed, 0, 1000);

                // Check for XML/JSON content
                if (str_starts_with(trim($decompressed), '<?xml') || str_starts_with(trim($decompressed), '<')) {
                    $analysis['content_type'] = 'XML';
                } elseif (str_starts_with(trim($decompressed), '{') || str_starts_with(trim($decompressed), '[')) {
                    $analysis['content_type'] = 'JSON';
                }
            }
        } elseif (str_starts_with(trim($content), '<?xml') || str_starts_with(trim($content), '<')) {
            $analysis['format'] = 'XML';
            $analysis['preview'] = substr($content, 0, 2000);
        } elseif (str_starts_with(trim($content), '{') || str_starts_with(trim($content), '[')) {
            $analysis['format'] = 'JSON';
            $analysis['preview'] = substr($content, 0, 2000);

            // Try to decode JSON
            $decoded = json_decode($content, true);
            if ($decoded !== null) {
                $analysis['json_valid'] = true;
                $analysis['json_keys'] = array_keys($decoded);
            }
        } else {
            // Check if content looks like text
            if (!$analysis['is_binary']) {
                $analysis['format'] = 'Text';
                $analysis['preview'] = substr($content, 0, 2000);
            } else {
                $analysis['format'] = 'Binary';
                $analysis['hex_preview'] = bin2hex(substr($content, 0, 100));
            }
        }

        // Look for WiFi-related content (our main interest)
        $contentToSearch = $decompressed ?? $content;

        if (stripos($contentToSearch, 'KeyPassphrase') !== false ||
            stripos($contentToSearch, 'PreSharedKey') !== false ||
            stripos($contentToSearch, 'WPAPassphrase') !== false ||
            stripos($contentToSearch, 'password') !== false) {
            $analysis['contains_wifi_credentials'] = true;
        }

        if (stripos($contentToSearch, 'SSID') !== false) {
            $analysis['contains_ssid'] = true;
        }

        // Look for port forwarding
        if (stripos($contentToSearch, 'PortMapping') !== false ||
            stripos($contentToSearch, 'PortForward') !== false) {
            $analysis['contains_port_mappings'] = true;
        }

        return $analysis;
    }

    /**
     * Check if content is binary (contains non-printable characters)
     */
    private function isBinaryContent(string $content): bool
    {
        // Check first 1024 bytes for binary content
        $sample = substr($content, 0, 1024);
        $nullBytes = substr_count($sample, "\0");

        // If more than 10% null bytes, consider it binary
        if ($nullBytes > strlen($sample) * 0.1) {
            return true;
        }

        // Check for control characters (except common ones like tab, newline)
        for ($i = 0; $i < strlen($sample); $i++) {
            $ord = ord($sample[$i]);
            // Allow printable ASCII, tab, newline, carriage return
            if ($ord < 32 && $ord !== 9 && $ord !== 10 && $ord !== 13) {
                if ($ord === 0 && $nullBytes > 5) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * View uploaded file content (for admin review)
     */
    public function view(Request $request, string $taskId): Response
    {
        $task = Task::where('id', $taskId)
            ->where('task_type', 'upload')
            ->firstOrFail();

        $uploadedFile = $task->progress_info['uploaded_file'] ?? null;

        if (!$uploadedFile || !Storage::disk('local')->exists($uploadedFile)) {
            return response('File not found', 404);
        }

        $content = Storage::disk('local')->get($uploadedFile);

        // Check if it's gzipped
        if (str_starts_with($content, "\x1f\x8b")) {
            $decompressed = @gzdecode($content);
            if ($decompressed !== false) {
                $content = $decompressed;
            }
        }

        return response($content)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }

    /**
     * Download uploaded file
     */
    public function download(Request $request, string $taskId): Response
    {
        $task = Task::where('id', $taskId)
            ->where('task_type', 'upload')
            ->firstOrFail();

        $uploadedFile = $task->progress_info['uploaded_file'] ?? null;

        if (!$uploadedFile || !Storage::disk('local')->exists($uploadedFile)) {
            return response('File not found', 404);
        }

        $content = Storage::disk('local')->get($uploadedFile);
        $filename = basename($uploadedFile);

        return response($content)
            ->header('Content-Type', 'application/octet-stream')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Serve config file for device restore (TR-069 Download RPC)
     *
     * This endpoint allows devices to download their config file for restore.
     * Token-based authentication to ensure only authorized downloads.
     *
     * URL format: /device-config/{taskId}?token={download_token}
     */
    public function serveConfigFile(Request $request, string $taskId): Response
    {
        $token = $request->query('token');

        // Find the download task (restore task)
        $task = Task::where('id', $taskId)
            ->where('task_type', 'config_restore')
            ->first();

        if (!$task) {
            Log::warning('Config file download rejected: task not found', ['task_id' => $taskId]);
            return response('Task not found', 404);
        }

        // Validate token
        $expectedToken = $task->parameters['download_token'] ?? null;
        if (!$expectedToken || $token !== $expectedToken) {
            Log::warning('Config file download rejected: invalid token', ['task_id' => $taskId]);
            return response('Invalid token', 403);
        }

        // Get the source file from task parameters
        $sourceFile = $task->parameters['source_file'] ?? null;
        if (!$sourceFile || !Storage::disk('local')->exists($sourceFile)) {
            Log::warning('Config file download rejected: file not found', [
                'task_id' => $taskId,
                'source_file' => $sourceFile,
            ]);
            return response('File not found', 404);
        }

        $content = Storage::disk('local')->get($sourceFile);

        Log::info('Config file served for device restore', [
            'task_id' => $taskId,
            'device_id' => $task->device_id,
            'file_size' => strlen($content),
        ]);

        // Return the raw binary config file
        return response($content)
            ->header('Content-Type', 'application/octet-stream')
            ->header('Content-Length', strlen($content));
    }

    /**
     * Serve static migration files (e.g., TR-181 pre-config files)
     * These are publicly accessible via HTTP without authentication
     * Used by TR-069 Download RPC for migration operations
     */
    public function serveMigrationFile(Request $request, string $filename): Response
    {
        // Sanitize filename - only allow alphanumeric, dash, underscore, dot
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);

        // Build path to migration file
        $filePath = storage_path('app/public/migration/' . $filename);

        if (!file_exists($filePath)) {
            Log::warning('Migration file not found', ['filename' => $filename]);
            return response('File not found', 404);
        }

        $content = file_get_contents($filePath);

        Log::info('Migration file served', [
            'filename' => $filename,
            'size' => strlen($content),
            'ip' => $request->ip(),
        ]);

        // Determine content type based on extension
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $contentType = match ($extension) {
            'xml' => 'application/xml',
            'bin' => 'application/octet-stream',
            default => 'application/octet-stream',
        };

        return response($content)
            ->header('Content-Type', $contentType)
            ->header('Content-Length', strlen($content))
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
