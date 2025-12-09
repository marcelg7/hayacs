<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\FeedbackComment;
use App\Models\FeedbackNotification;
use App\Models\User;
use App\Services\SlackService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class FeedbackController extends Controller
{
    protected SlackService $slack;

    public function __construct(SlackService $slack)
    {
        $this->slack = $slack;
    }
    /**
     * Display a listing of feedback.
     */
    public function index(Request $request): View
    {
        $query = Feedback::with(['user', 'assignee'])
            ->withCount('comments');

        // Apply type filter
        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        // Apply status filter
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // Apply priority filter
        if ($priority = $request->get('priority')) {
            $query->where('priority', $priority);
        }

        // Apply search filter
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortDir = $request->get('dir', 'desc');
        $allowedSorts = ['created_at', 'upvotes', 'status', 'priority', 'type'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $feedbacks = $query->paginate(20)->withQueryString();

        // Get filter options
        $types = Feedback::getTypes();
        $statuses = Feedback::getStatuses();
        $priorities = Feedback::getPriorities();

        // Get notification count for current user
        $unreadNotifications = FeedbackNotification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->count();

        return view('feedback.index', compact(
            'feedbacks',
            'types',
            'statuses',
            'priorities',
            'unreadNotifications'
        ));
    }

    /**
     * Show the form for creating new feedback.
     */
    public function create(): View
    {
        $types = Feedback::getTypes();
        $priorities = Feedback::getPriorities();

        return view('feedback.create', compact('types', 'priorities'));
    }

    /**
     * Store a newly created feedback.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:bug,feedback,feature_request',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:low,medium,high,critical',
        ]);

        $feedback = Feedback::create([
            'user_id' => Auth::id(),
            'type' => $validated['type'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'status' => Feedback::STATUS_OPEN,
        ]);

        // Send Slack notification for new feedback
        $this->slack->notifyNewFeedback($feedback);

        return redirect()
            ->route('feedback.show', $feedback)
            ->with('success', 'Feedback submitted successfully!');
    }

    /**
     * Display the specified feedback.
     */
    public function show(Feedback $feedback): View
    {
        $feedback->load(['user', 'assignee', 'rootComments.user', 'rootComments.allReplies.user']);

        // Check if current user has upvoted
        $hasUpvoted = $feedback->hasUpvotedBy(Auth::user());

        // Get users for assignment (admin/support only)
        $assignableUsers = User::whereIn('role', ['admin', 'support'])
            ->orderBy('name')
            ->get();

        $statuses = Feedback::getStatuses();
        $priorities = Feedback::getPriorities();

        return view('feedback.show', compact(
            'feedback',
            'hasUpvoted',
            'assignableUsers',
            'statuses',
            'priorities'
        ));
    }

    /**
     * Update the specified feedback (admin/support only).
     */
    public function update(Request $request, Feedback $feedback): RedirectResponse
    {
        $user = Auth::user();

        // Only admin/support can update status and assignment
        if (!$user->isAdminOrSupport()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'status' => 'sometimes|in:open,in_progress,resolved',
            'priority' => 'sometimes|in:low,medium,high,critical',
            'assigned_to' => 'sometimes|nullable|exists:users,id',
        ]);

        $oldStatus = $feedback->status;

        if (isset($validated['status'])) {
            $feedback->status = $validated['status'];

            // Set resolved_at timestamp when resolved
            if ($validated['status'] === Feedback::STATUS_RESOLVED && $oldStatus !== Feedback::STATUS_RESOLVED) {
                $feedback->resolved_at = now();
            } elseif ($validated['status'] !== Feedback::STATUS_RESOLVED) {
                $feedback->resolved_at = null;
            }
        }

        if (isset($validated['priority'])) {
            $feedback->priority = $validated['priority'];
        }

        if (array_key_exists('assigned_to', $validated)) {
            $oldAssignee = $feedback->assigned_to;
            $feedback->assigned_to = $validated['assigned_to'];

            // Notify new assignee
            if ($validated['assigned_to'] && $validated['assigned_to'] !== $oldAssignee) {
                $assignee = User::find($validated['assigned_to']);
                FeedbackNotification::createAssigned($feedback, $assignee);
            }
        }

        $feedback->save();

        // Create notification if status changed
        if (isset($validated['status']) && $oldStatus !== $feedback->status) {
            FeedbackNotification::createStatusChanged($feedback, $oldStatus, $feedback->status);
            // Send Slack notification for status change
            $this->slack->notifyStatusChange($feedback, $oldStatus, $feedback->status);
        }

        return redirect()
            ->route('feedback.show', $feedback)
            ->with('success', 'Feedback updated successfully!');
    }

    /**
     * Toggle upvote on feedback.
     */
    public function toggleUpvote(Feedback $feedback): RedirectResponse
    {
        $added = $feedback->toggleUpvote(Auth::user());

        return redirect()
            ->route('feedback.show', $feedback)
            ->with('success', $added ? 'Upvote added!' : 'Upvote removed!');
    }

    /**
     * Add a comment to feedback.
     */
    public function addComment(Request $request, Feedback $feedback): RedirectResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'parent_id' => 'nullable|exists:feedback_comments,id',
        ]);

        $user = Auth::user();

        $comment = FeedbackComment::create([
            'feedback_id' => $feedback->id,
            'user_id' => $user->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'content' => $validated['content'],
            'is_staff_response' => $user->isAdminOrSupport(),
        ]);

        // Create notification for feedback author (if not commenting on own feedback)
        $parentId = $validated['parent_id'] ?? null;
        if ($feedback->user_id !== $user->id && !$parentId) {
            FeedbackNotification::createCommentAdded($feedback, $comment);
        }

        // Create notification for parent comment author (if replying)
        if ($parentId) {
            $parentComment = FeedbackComment::find($parentId);
            if ($parentComment && $parentComment->user_id !== $user->id) {
                FeedbackNotification::createReplyAdded($parentComment, $comment);
            }
        }

        // Send Slack notification for new comment
        $this->slack->notifyNewComment($feedback, $comment);

        return redirect()
            ->route('feedback.show', $feedback)
            ->with('success', 'Comment added successfully!');
    }

    /**
     * Display user's notifications.
     */
    public function notifications(): View
    {
        $notifications = FeedbackNotification::where('user_id', Auth::id())
            ->with('feedback')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('feedback.notifications', compact('notifications'));
    }

    /**
     * Mark notification as read.
     */
    public function markNotificationRead(FeedbackNotification $notification): RedirectResponse
    {
        // Ensure user owns this notification
        if ($notification->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $notification->markAsRead();

        return redirect()
            ->route('feedback.show', $notification->feedback)
            ->with('success', 'Notification marked as read.');
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllNotificationsRead(): RedirectResponse
    {
        FeedbackNotification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return redirect()
            ->route('feedback.notifications')
            ->with('success', 'All notifications marked as read.');
    }

    /**
     * Delete feedback (admin only).
     */
    public function destroy(Feedback $feedback): RedirectResponse
    {
        $user = Auth::user();

        // Only admin can delete feedback
        if (!$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $feedback->delete();

        return redirect()
            ->route('feedback.index')
            ->with('success', 'Feedback deleted successfully!');
    }
}
