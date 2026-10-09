<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDefaultMessageRequest;
use App\Http\Requests\UpdateDefaultMessageRequest;
use App\Http\Resources\DefaultMessageCollection;
use App\Http\Resources\DefaultMessageResource;
use App\Models\DefaultMessage;
use Symfony\Component\HttpFoundation\Response;

class DefaultMessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $default_messages = DefaultMessage::with(['parent', 'user'])
//        $default_messages = DefaultMessage::leftJoin('default_messages as parents', 'parents.id', '=', 'default_messages.parent_id')
//            ->select([
//                'default_messages.*', 'parents.body as parent_name'
//            ])
            ->select('default_messages.*')
            ->orderBy('body')
            ->paginate();

//        dd($default_messages);
        return new DefaultMessageCollection($default_messages);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDefaultMessageRequest $request)
    {
        $is_saved = DefaultMessage::create([
            'body' => $request->input('body'),
            'parent_id' => $request->input('parent_id'),
            'user_id' => $request->input('user_id', $request->user()?->id),
        ]);
        $default_message_created = DefaultMessage::with(['parent', 'user'])->find($is_saved->id);
        return response()->json([
            'message' => $is_saved ? 'Default Message Created Successfully' : 'Default Message not Created',
            'data' => $is_saved ? new DefaultMessageResource($default_message_created) : 'Default Message not Created',
        ], $is_saved ? Response::HTTP_CREATED : Response::HTTP_BAD_REQUEST);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $default_message = DefaultMessage::with(['parent', 'user'])->find($id);
        if (!$default_message) {
            return $this->notFound();
        }
        return new DefaultMessageResource($default_message);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDefaultMessageRequest $request, $id)
    {
        $default_message = DefaultMessage::find($id);
        if (!$default_message) {
            return $this->notFound();
        }
        if ($request->filled('parent_id') && (int)$request->input('parent_id') === $default_message->id) {
            return response()->json([
                'message' => 'A default message cannot be its own parent',
            ], Response::HTTP_BAD_REQUEST);
        }

        $is_updated = $default_message->update($request->only(['body', 'parent_id', 'user_id']));
        $default_message_updated = DefaultMessage::with(['parent', 'user'])->find($id);
        return response()->json([
            'message' => $is_updated ? 'Default Message Updated Successfully' : 'Default Message not Updated',
            'data' => $is_updated ? new DefaultMessageResource($default_message_updated) : 'Default Message not Updated',
        ], $is_updated ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $default_message = DefaultMessage::find($id);
        if (!$default_message) {
            return $this->notFound();
        }
        if ($default_message->children()->exists()) {
            return response()->json([
                'message' => 'This default message has replies under it. Delete or move them first.',
            ], Response::HTTP_CONFLICT);
        }

        $is_deleted = $default_message->delete();
        return response()->json([
            'message' => $is_deleted ? 'Default Message Deleted Successfully' : 'Default Message not Deleted',
        ], $is_deleted ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
    }

    private function notFound()
    {
        return response()->json([
            'message' => 'Default Message not found',
        ], Response::HTTP_NOT_FOUND);
    }
}
