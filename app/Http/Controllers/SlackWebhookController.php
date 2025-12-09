<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Services\SlackService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class SlackWebhookController extends Controller
{
    protected SlackService $slack;

    public function __construct(SlackService $slack)
    {
        $this->slack = $slack;
    }

    /**
     * Handle Slack interactive component webhooks
     */
    public function handleInteraction(Request $request): Response|JsonResponse
    {
        // Get the raw body for signature verification
        $rawBody = $request->getContent();
        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signature = $request->header('X-Slack-Signature');

        // Log the incoming webhook for debugging
        Log::info('Slack webhook received', [
            'timestamp' => $timestamp,
            'has_signature' => !empty($signature),
            'body_length' => strlen($rawBody),
        ]);

        // TODO: Re-enable signature verification once signing secret issue is resolved
        // For now, relying on .htaccess IP restrictions for Slack servers
        // The allow_slack env var in .htaccess restricts /webhooks/slack/* to Slack IPs
        /*
        if (!$this->slack->verifySignature($signature ?? '', $timestamp ?? '', $rawBody)) {
            Log::warning('Slack webhook signature verification failed', [
                'timestamp' => $timestamp,
                'has_signature' => !empty($signature),
            ]);
            return response('Unauthorized', 403);
        }
        */

        // Parse the payload - Slack sends it as URL-encoded form data
        $payloadJson = $request->input('payload');
        if (!$payloadJson) {
            return response('Missing payload', 400);
        }

        $payload = json_decode($payloadJson, true);
        if (!$payload) {
            return response('Invalid payload', 400);
        }

        $actionId = $payload['actions'][0]['action_id'] ?? null;
        $feedbackId = $payload['actions'][0]['value'] ?? null;

        Log::info('Slack interaction received', [
            'action_id' => $actionId,
            'feedback_id' => $feedbackId,
            'user' => $payload['user']['name'] ?? 'unknown',
        ]);

        // Handle the different actions
        switch ($actionId) {
            case 'mark_in_progress':
                return $this->handleMarkInProgress($feedbackId, $payload);

            case 'resolve_feedback':
                return $this->handleResolve($feedbackId, $payload);

            case 'view_feedback':
                // This is just a link button, no server-side handling needed
                return response('', 200);

            default:
                Log::warning('Unknown Slack action', ['action_id' => $actionId]);
                return response('Unknown action', 400);
        }
    }

    /**
     * Handle "Mark In Progress" button click
     */
    protected function handleMarkInProgress(string $feedbackId, array $payload): JsonResponse
    {
        $feedback = Feedback::find($feedbackId);
        if (!$feedback) {
            return $this->respondToSlack($payload, 'Feedback not found.');
        }

        if ($feedback->status === Feedback::STATUS_IN_PROGRESS) {
            return $this->respondToSlack($payload, 'Feedback is already in progress.');
        }

        $oldStatus = $feedback->status;
        $feedback->status = Feedback::STATUS_IN_PROGRESS;
        $feedback->save();

        // Notify about status change
        $this->slack->notifyStatusChange($feedback, $oldStatus, $feedback->status);

        $slackUser = $payload['user']['name'] ?? 'Someone';
        return $this->respondToSlack($payload, "Marked as *In Progress* by {$slackUser}");
    }

    /**
     * Handle "Resolve" button click
     */
    protected function handleResolve(string $feedbackId, array $payload): JsonResponse
    {
        $feedback = Feedback::find($feedbackId);
        if (!$feedback) {
            return $this->respondToSlack($payload, 'Feedback not found.');
        }

        if ($feedback->status === Feedback::STATUS_RESOLVED) {
            return $this->respondToSlack($payload, 'Feedback is already resolved.');
        }

        $oldStatus = $feedback->status;
        $feedback->status = Feedback::STATUS_RESOLVED;
        $feedback->resolved_at = now();
        $feedback->save();

        // Notify about status change
        $this->slack->notifyStatusChange($feedback, $oldStatus, $feedback->status);

        $slackUser = $payload['user']['name'] ?? 'Someone';
        return $this->respondToSlack($payload, "Marked as *Resolved* by {$slackUser}");
    }

    /**
     * Send a response message to Slack
     */
    protected function respondToSlack(array $payload, string $message): JsonResponse
    {
        // Slack expects a response within 3 seconds
        // Return an ephemeral message that only the user who clicked sees
        return response()->json([
            'response_type' => 'ephemeral',
            'text' => $message,
            'replace_original' => false,
        ]);
    }
}
