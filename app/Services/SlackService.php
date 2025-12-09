<?php

namespace App\Services;

use App\Models\Feedback;
use App\Models\FeedbackComment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackService
{
    protected ?string $webhookUrl;
    protected ?string $signingSecret;
    protected bool $enabled;

    public function __construct()
    {
        $this->webhookUrl = config('services.slack.webhook_url');
        $this->signingSecret = config('services.slack.signing_secret');
        $this->enabled = config('services.slack.enabled', false) && !empty($this->webhookUrl);
    }

    /**
     * Check if Slack integration is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Send notification for new feedback
     */
    public function notifyNewFeedback(Feedback $feedback): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $typeEmoji = match($feedback->type) {
            'bug' => ':bug:',
            'feature' => ':bulb:',
            default => ':speech_balloon:',
        };

        $priorityEmoji = match($feedback->priority) {
            'critical' => ':rotating_light:',
            'high' => ':warning:',
            'medium' => ':large_blue_circle:',
            default => ':white_circle:',
        };

        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => "{$typeEmoji} New {$feedback->type_label}: {$feedback->title}",
                    'emoji' => true,
                ],
            ],
            [
                'type' => 'section',
                'fields' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Type:*\n{$feedback->type_label}",
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Priority:*\n{$priorityEmoji} {$feedback->priority_label}",
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Submitted by:*\n{$feedback->user->name}",
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Status:*\n{$feedback->status_label}",
                    ],
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Description:*\n" . $this->truncateHtml($feedback->description, 500),
                ],
            ],
            [
                'type' => 'actions',
                'elements' => [
                    [
                        'type' => 'button',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => 'View in Hay ACS',
                            'emoji' => true,
                        ],
                        'url' => route('feedback.show', $feedback),
                        'action_id' => 'view_feedback',
                    ],
                    [
                        'type' => 'button',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => 'Mark In Progress',
                            'emoji' => true,
                        ],
                        'style' => 'primary',
                        'action_id' => 'mark_in_progress',
                        'value' => (string) $feedback->id,
                    ],
                    [
                        'type' => 'button',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => 'Resolve',
                            'emoji' => true,
                        ],
                        'style' => 'danger',
                        'action_id' => 'resolve_feedback',
                        'value' => (string) $feedback->id,
                    ],
                ],
            ],
            [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "Feedback #{$feedback->id} | " . $feedback->created_at->format('M j, Y g:i A'),
                    ],
                ],
            ],
        ];

        $response = $this->sendMessage($blocks, "New {$feedback->type_label}: {$feedback->title}");

        if ($response && isset($response['ts'])) {
            // Store the message timestamp for threading
            $feedback->update(['slack_message_ts' => $response['ts']]);
        }

        return $response !== false;
    }

    /**
     * Send notification for status change
     */
    public function notifyStatusChange(Feedback $feedback, string $oldStatus, string $newStatus): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $statusEmoji = match($newStatus) {
            'in_progress' => ':hourglass_flowing_sand:',
            'resolved' => ':white_check_mark:',
            default => ':new:',
        };

        $oldLabel = Feedback::STATUSES[$oldStatus] ?? $oldStatus;
        $newLabel = Feedback::STATUSES[$newStatus] ?? $newStatus;

        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "{$statusEmoji} *Status Updated:* {$feedback->title}\n`{$oldLabel}` → `{$newLabel}`",
                ],
                'accessory' => [
                    'type' => 'button',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => 'View',
                        'emoji' => true,
                    ],
                    'url' => route('feedback.show', $feedback),
                    'action_id' => 'view_feedback',
                ],
            ],
            [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "Feedback #{$feedback->id} | Updated by system",
                    ],
                ],
            ],
        ];

        // If we have a thread ts, reply in thread
        $threadTs = $feedback->slack_message_ts;

        return $this->sendMessage($blocks, "Status updated: {$feedback->title}", $threadTs) !== false;
    }

    /**
     * Send notification for new comment
     */
    public function notifyNewComment(Feedback $feedback, FeedbackComment $comment): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $isStaff = $comment->is_staff_response ? ':shield: Staff Response' : '';
        $staffBadge = $comment->is_staff_response ? ' (Staff)' : '';

        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => ":speech_balloon: *New Comment* on *{$feedback->title}*{$staffBadge}\n\n" .
                              "*{$comment->user->name}:*\n" . $this->truncateHtml($comment->content, 300),
                ],
                'accessory' => [
                    'type' => 'button',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => 'View',
                        'emoji' => true,
                    ],
                    'url' => route('feedback.show', $feedback),
                    'action_id' => 'view_feedback',
                ],
            ],
            [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "Feedback #{$feedback->id} | " . $comment->created_at->format('M j, Y g:i A'),
                    ],
                ],
            ],
        ];

        // Reply in thread if we have the original message ts
        $threadTs = $feedback->slack_message_ts;

        return $this->sendMessage($blocks, "New comment on: {$feedback->title}", $threadTs) !== false;
    }

    /**
     * Send message to Slack
     */
    protected function sendMessage(array $blocks, string $fallbackText, ?string $threadTs = null): array|false
    {
        try {
            $payload = [
                'blocks' => $blocks,
                'text' => $fallbackText, // Fallback for notifications
            ];

            if ($threadTs) {
                $payload['thread_ts'] = $threadTs;
            }

            $response = Http::timeout(10)->post($this->webhookUrl, $payload);

            if ($response->successful()) {
                Log::info('Slack notification sent successfully', ['text' => $fallbackText]);

                // Webhooks don't return message ts, but we log success
                return ['success' => true];
            }

            Log::error('Slack notification failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Slack notification exception', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Verify Slack request signature
     */
    public function verifySignature(string $signature, string $timestamp, string $body): bool
    {
        if (empty($this->signingSecret)) {
            Log::warning('Slack signature verification failed: No signing secret configured');
            return false;
        }

        // Check timestamp is recent (within 5 minutes)
        $timeDiff = abs(time() - (int) $timestamp);
        if ($timeDiff > 300) {
            Log::warning('Slack signature verification failed: Timestamp too old', [
                'timestamp' => $timestamp,
                'time_diff' => $timeDiff,
            ]);
            return false;
        }

        $sigBasestring = "v0:{$timestamp}:{$body}";
        $mySignature = 'v0=' . hash_hmac('sha256', $sigBasestring, $this->signingSecret);

        $valid = hash_equals($mySignature, $signature);

        if (!$valid) {
            Log::warning('Slack signature verification failed: Signature mismatch', [
                'received_signature' => substr($signature, 0, 20) . '...',
                'computed_signature' => substr($mySignature, 0, 20) . '...',
                'timestamp' => $timestamp,
                'body_length' => strlen($body),
                'secret_length' => strlen($this->signingSecret),
            ]);
        }

        return $valid;
    }

    /**
     * Truncate HTML content and strip tags for Slack
     */
    protected function truncateHtml(string $html, int $maxLength): string
    {
        // Strip HTML tags
        $text = strip_tags($html);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Truncate if needed
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength) . '...';
        }

        return $text;
    }
}
