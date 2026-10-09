<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class MessageController extends Controller
{
    /**
     * Messages of a conversation, oldest first. Reading them marks the conversation
     * as read for a participant.
     */
    public function index(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        $isParticipant = $conversation->hasParticipant($user);

        if (! $isParticipant && ! $user->isStaff()) {
            return $this->forbidden();
        }

        $messages = $conversation->messages()
            ->with('user')
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(50);

        if ($isParticipant) {
            $conversation->markAsReadBy($user);
        }

        return MessageResource::collection($messages);
    }

    /**
     * Send a message. Staff who reply join the conversation.
     */
    public function store(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        if (! $user->isStaff() && ! $conversation->hasParticipant($user)) {
            return $this->forbidden();
        }

        $validator = Validator::make($request->all(), [
            'body' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $message = DB::transaction(function () use ($request, $conversation, $user) {
            $message = $conversation->messages()->create([
                'body' => $request->input('body'),
                'user_id' => $user->id,
            ]);

            $conversation->update(['last_message' => $message->created_at]);
            $conversation->markAsReadBy($user);

            return $message;
        });

        return response()->json([
            'message' => 'Message sent successfully',
            'data' => new MessageResource($message->load('user')),
        ], Response::HTTP_CREATED);
    }

    private function forbidden()
    {
        return response()->json([
            'message' => 'You are not part of this conversation.',
        ], Response::HTTP_FORBIDDEN);
    }
}
