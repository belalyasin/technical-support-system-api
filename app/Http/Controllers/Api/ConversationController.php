<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ConversationController extends Controller
{
    /**
     * Conversations the signed-in user may see, most recently active first.
     * Customers see their own; staff see all of them.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->select('conversations.*')
            ->visibleTo($user)
            ->withUnreadCountFor($user)
            ->with(['user', 'latestMessage.user'])
            ->orderByDesc('last_message')
            ->orderByDesc('id')
            ->paginate();

        return ConversationResource::collection($conversations);
    }

    /**
     * Open a conversation with a first message. A customer opens one for themselves;
     * staff can open one with a customer by passing user_id.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'body' => 'required|string|max:255',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $customerId = $user->id;
        if ($request->filled('user_id') && (int) $request->input('user_id') !== $user->id) {
            if (! $user->isStaff()) {
                return response()->json([
                    'message' => 'Only support staff can open a conversation for another user.',
                ], Response::HTTP_FORBIDDEN);
            }
            $customerId = (int) $request->input('user_id');
        }

        $conversation = DB::transaction(function () use ($request, $user, $customerId) {
            $conversation = Conversation::create([
                'name' => $request->input('name'),
                'user_id' => $customerId,
                'last_message' => now(),
            ]);

            $conversation->users()->attach(array_unique([$customerId, $user->id]));
            $conversation->messages()->create([
                'body' => $request->input('body'),
                'user_id' => $user->id,
            ]);
            $conversation->markAsReadBy($user);

            return $conversation;
        });

        return response()->json([
            'message' => 'Conversation created successfully',
            'data' => new ConversationResource($conversation->load(['user', 'users', 'latestMessage.user'])),
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, Conversation $conversation)
    {
        if (! $this->canAccess($request, $conversation)) {
            return $this->forbidden();
        }

        $conversation = Conversation::query()
            ->select('conversations.*')
            ->withUnreadCountFor($request->user())
            ->with(['user', 'users', 'latestMessage.user'])
            ->findOrFail($conversation->id);

        return response()->json([
            'data' => new ConversationResource($conversation),
        ], Response::HTTP_OK);
    }

    /**
     * Mark everything in the conversation as read by the signed-in user.
     */
    public function markAsRead(Request $request, Conversation $conversation)
    {
        if (! $this->canAccess($request, $conversation)) {
            return $this->forbidden();
        }

        $conversation->markAsReadBy($request->user());

        return response()->json([
            'message' => 'Conversation marked as read',
        ], Response::HTTP_OK);
    }

    /**
     * Delete a conversation and its messages. Staff only.
     */
    public function destroy(Conversation $conversation)
    {
        DB::transaction(function () use ($conversation) {
            $conversation->messages()->delete();
            $conversation->delete();
        });

        return response()->json([
            'message' => 'Conversation deleted successfully',
        ], Response::HTTP_OK);
    }

    private function canAccess(Request $request, Conversation $conversation): bool
    {
        $user = $request->user();

        return $user->isStaff() || $conversation->hasParticipant($user);
    }

    private function forbidden()
    {
        return response()->json([
            'message' => 'You are not part of this conversation.',
        ], Response::HTTP_FORBIDDEN);
    }
}
