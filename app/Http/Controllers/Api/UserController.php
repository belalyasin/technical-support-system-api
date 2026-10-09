<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * User management for support staff.
 */
class UserController extends Controller
{
    /**
     * Display a listing of the resource. Filter with ?role= and ?search= (name, email or phone).
     */
    public function index(Request $request)
    {
        $users = User::query()
            ->with('roles')
            ->when($request->filled('role'), fn ($query) => $query->role($request->input('role')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%' . $request->input('search') . '%';
                $query->where(fn ($where) => $where
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->orderBy('name')
            ->paginate();

        return UserResource::collection($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:3',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|min:10|max:15|unique:users,phone',
            'password' => 'required|string|min:6',
            'role' => 'required|string|exists:roles,name',
        ]);

        if ($validator->fails()) {
            return $this->invalid($validator->errors()->first());
        }
        if (! $this->mayAssign($request, $request->input('role'))) {
            return $this->superAdminOnly();
        }

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'password' => Hash::make($request->input('password')),
        ]);
        $user->assignRole($request->input('role'));

        return response()->json([
            'message' => 'User created successfully',
            'data' => new UserResource($user->load('roles')),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::with('roles')->find($id);
        if (! $user) {
            return $this->notFound();
        }

        return response()->json([
            'data' => new UserResource($user),
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::with('roles')->find($id);
        if (! $user) {
            return $this->notFound();
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|min:3',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'string', 'min:10', 'max:15', Rule::unique('users', 'phone')->ignore($user->id)],
            'password' => 'sometimes|string|min:6',
            'role' => 'sometimes|string|exists:roles,name',
        ]);

        if ($validator->fails()) {
            return $this->invalid($validator->errors()->first());
        }
        // Only a super admin may change a super admin, or hand out that role
        if ($user->hasRole('super admin') && ! $request->user()->hasRole('super admin')) {
            return $this->superAdminOnly();
        }
        if ($request->filled('role') && ! $this->mayAssign($request, $request->input('role'))) {
            return $this->superAdminOnly();
        }

        $user->fill($request->only(['name', 'email', 'phone']));
        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
        }
        $user->save();
        if ($request->filled('role')) {
            $user->syncRoles([$request->input('role')]);
        }

        return response()->json([
            'message' => 'User updated successfully',
            'data' => new UserResource($user->load('roles')),
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->notFound();
        }
        if ($user->is($request->user())) {
            return response()->json([
                'message' => 'You cannot delete your own account.',
            ], Response::HTTP_BAD_REQUEST);
        }
        if ($user->hasRole('super admin') && ! $request->user()->hasRole('super admin')) {
            return $this->superAdminOnly();
        }

        // Replies in other people's conversations are part of their history and must stay
        $repliesElsewhere = $user->messages()
            ->whereHas('conversation', fn ($conversation) => $conversation->where('user_id', '!=', $user->id))
            ->exists();
        if ($repliesElsewhere) {
            return response()->json([
                'message' => 'This user has replied in other conversations and cannot be deleted. Change their role instead.',
            ], Response::HTTP_CONFLICT);
        }

        DB::transaction(function () use ($user) {
            $user->details()->delete();
            foreach ($user->conversations as $conversation) {
                $conversation->messages()->delete();
                $conversation->delete();
            }
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->json([
            'message' => 'User deleted successfully',
        ], Response::HTTP_OK);
    }

    private function mayAssign(Request $request, string $role): bool
    {
        return $role !== 'super admin' || $request->user()->hasRole('super admin');
    }

    private function invalid(string $message)
    {
        return response()->json(['message' => $message], Response::HTTP_BAD_REQUEST);
    }

    private function notFound()
    {
        return response()->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
    }

    private function superAdminOnly()
    {
        return response()->json([
            'message' => 'Only a super admin can do this.',
        ], Response::HTTP_FORBIDDEN);
    }
}
