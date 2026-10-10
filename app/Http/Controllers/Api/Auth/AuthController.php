<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    //
//    public function login(Request $request)
//    {
//        $validatedData = Validator($request->all(), [
//            'email' => 'required|email|exists:users,email',
//            'password' => 'required|string|min:6'
//        ]);
//        if (!$validatedData->fails()) {
//            $user = User::where('email', '=', $request->input('email'))->first();
////            $user = User::where('email',$validatedData['email'])->first();
//            if (\Hash::check($request->input('password'), $user->password)) {
//                $token = $user->createToken('User-Api-Token');
//                $user->setAttribute('token', $token->accessToken);
////                $user
//                return response()->json([
//                    'success' => true,
//                    'message' => 'logged in successfully',
//                    'user' => $user,
//                    'token' => $token->accessToken,
//                ],
//                    Response::HTTP_OK
//                );
//            } else {
//                return response()->json([
//                    'message' => 'login failed',
//                ],
//                    Response::HTTP_BAD_REQUEST);
//            }
//        } else {
//            return response()->json([
//                'message' => $validatedData->getMessageBag()->first(),
//            ],
//                Response::HTTP_BAD_REQUEST);
//        }
//    }

    public function login(Request $request)
    {
        // Validate user input
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], Response::HTTP_BAD_REQUEST);
        }

        // Retrieve user by email
        $user = User::where('email', $request->input('email'))->first();

        // Check password validity
        if (!Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials, login failed.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Retrieve user's role(s)
        $roles = $user->getRoleNames(); // Returns a collection of role names

        $tokenName = null;
        $abilities = [];
        // Define allowed roles and assign token name accordingly
        if ($roles->contains('super admin')) {
            $tokenName = 'SuperAdmin-Token';
//            add new fet
            $abilities = ['super-admin'];
            // Optionally, log in via the appropriate guard (if you need session-based auth too)
            Auth::guard('admin')->login($user);
        } elseif ($roles->contains('admin')) {
            $tokenName = 'Admin-Token';
//            add new fet
            $abilities = ['admin'];
            Auth::guard('admin')->login($user);
        } elseif ($roles->contains('user')) {
            $tokenName = 'User-Token';
//            add new fet
            $abilities = ['user'];
            Auth::guard('user')->login($user);
        } else {
            return response()->json([
                'message' => 'Unauthorized: No valid role assigned.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Create a token specific to the user's role
//        $tokenResult = $user->createToken($tokenName);
//        $token = $tokenResult->accessToken;
//        $token = $user->createToken($tokenName)->plainTextToken;
        // Create a token with specific abilities.
        // With Sanctum, this token is not tied to a specific guard.
        $token = $user->createToken($tokenName, $abilities)->plainTextToken;

//        $token = $user->createToken('User-Api-Token')->plainTextToken;
        // Response with the assigned role and token
        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'user' => $user,
            'roles' => $roles, // List of roles
            'token' => $token,
            'token_name' => $tokenName,
        ], Response::HTTP_OK);
    }

    /**
     * Public sign-up. It always creates a customer ("user") account; staff accounts are
     * created by an administrator through the users endpoints.
     */
    public function register(Request $request)
    {
        $validator = Validator($request->all(), [
            'name' => 'required|string|min:3',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|min:10|max:15|unique:users,phone',
            'password' => 'required|string|min:6',
            'role' => 'nullable|string|in:user',
            'remember' => 'nullable|boolean',
        ], [
            'role.in' => 'Only an administrator can create accounts with that role.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->getMessageBag()->first(),
            ],
                Response::HTTP_BAD_REQUEST);
        } else {
            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'password' => Hash::make($request->input('password')),
            ]);

            $role = Role::findOrCreate('user', 'user');
            $user->assignRole($role->name);
            $isSaved = $user->save();
            return response()->json(["message" => $isSaved ?
                [
                    'Register Successfuly',
                    'user' => $user,
                    'role' => $role,
                ]
                : 'Register Failed!'
            ], $isSaved ? Response::HTTP_CREATED : Response::HTTP_BAD_REQUEST);
        }
    }

    public function logout(Request $request)
    {
//        $guard = auth('User-Api-Token')->check() ? 'user' : 'admin';
//        $token = $request->user($guard)->token();
//        $revokeToken = $token->revoke();
        $revokeToken = $request->user()->currentAccessToken()->delete();
//        $revokeToken = Auth::logout();
        return response()->json([
            'message' => $revokeToken ? 'Logged out successfully' : 'Logout failed!',
        ],
            $revokeToken ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST
        );
    }

//    public function refresh(Request $request){}
    public function me()
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated',
                ], Response::HTTP_UNAUTHORIZED);
            }

            return response()->json([
//                'data' => [
//                    'user' => $user->only(['id', 'name', 'email', 'phone']),
//                    'roles' => $user->getRoleNames(),
//                    'permissions' => $user->getAllPermissions()->pluck('name'),
//                ]
                'data' => new UserResource($user->load('roles'))
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve user data',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Email a password reset code. The response is the same whether or not the address
     * belongs to an account, so the endpoint cannot be used to find registered emails.
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->getMessageBag()->first()
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = User::where('email', '=', $request->input('email'))->first();
        if ($user) {
            $code = $user->issueVerificationCode();
            Mail::to($user->email)->send(new VerificationCodeMail($code, 'reset your password'));
        }

        return response()->json([
            'status' => true,
            'message' => 'If the email belongs to an account, a password reset code has been sent to it',
        ], Response::HTTP_OK);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator($request->all(), [
            'email' => 'required|email',
            'code' => 'required|numeric|digits:4',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->getMessageBag()->first()
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = User::where('email', '=', $request->input('email'))->first();
        if (!$user || !$user->verificationCodeMatches((string) $request->input('code'))) {
            return response()->json([
                'message' => 'reset code error, try again'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user->clearVerificationCode();
        $user->password = Hash::make($request->input('password'));
        $isSaved = $user->save();
        // Signing in again is required everywhere once the password has been reset
        $user->tokens()->delete();

        return response()->json([
            'message' => $isSaved ? 'Password reset success'
                : 'Failed to reset Password',
        ], $isSaved ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator($request->all(), [
            'password' => ['required'],
            'new_password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->symbols()->mixedCase()->uncompromised()],
        ]);
        if (Hash::check($request->input('password'), auth()->user()->password)) {
            if (!$validator->fails()) {
                $user = $request->user();
                $user->password = Hash::make($request->input('new_password'));
                $isSaved = $user->save();
                return response()->json([
                    'status' => $isSaved,
                    'message' => $isSaved ? 'Password changed successfully'
                        : 'change Password failed, try again',
                ], $isSaved ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
            } else {
                return response()->json([
                    'message' => $validator->getMessageBag()->first()
                ], Response::HTTP_BAD_REQUEST);
            }
        } else {
            return response()->json([
                'message' => 'The current password is incorrect.'
            ], Response::HTTP_BAD_REQUEST);

        }
    }

//    public function changeEmail(Request $request)
//    {
//        $validator = Validator::make($request->all(), [
//            'old_email' => 'required|email|exists:users,email',
//            'new_email' => 'required|email|unique:users,email',
//            'code' => 'required|numeric|digits:4',
//        ]);
//
//        if (!$validator->fails()) {
//            $randomInt = random_int(1000, 9999);
//            $user = $request->user();
//            $user = User::where('email', '=', $request->input('old_email'))->first();
//
//            if ($user->email !== $request->input('old_email')) {
//                return response()->json([
//                    'message' => 'The old email does not match.',
//                ], Response::HTTP_BAD_REQUEST); // 400 Bad Request
//            }
//
//            $user->verification_code = Hash::make($randomInt);
//            $isSaved = $user->save();
//
//            if ($isSaved) {
//                if (!is_null($user->verification_code)) {
//                    if (Hash::check($request->input('code'), $user->verification_code)) {
//                        $user->verification_code = null;
//                        $user->email = $request->input('new_email');
//                        $isSaved = $user->save();
//
//                        return response()->json([
//                            'message' => $isSaved ? 'Email changed successfully' : 'Failed to change email',
//                        ], $isSaved ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
//                    } else {
//                        return response()->json([
//                            'message' => 'Verification code error, try again'
//                        ], Response::HTTP_BAD_REQUEST);
//                    }
//                } else {
//                    return response()->json([
//                        'message' => 'No change email request exists, process denied'
//                    ], Response::HTTP_BAD_REQUEST);
//                }
//            } else {
//                return response()->json([
//                    'message' => 'Failed to save verification code'
//                ], Response::HTTP_BAD_REQUEST);
//            }
//        } else {
//            return response()->json([
//                'message' => $validator->getMessageBag()->first()
//            ], Response::HTTP_BAD_REQUEST);
//        }
//    }

    /**
     * Step 1 of changing the signed-in user's email: send a code to the new address.
     */
    public function initiateEmailChange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'new_email' => 'required|email|unique:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->getMessageBag()->first()
            ], Response::HTTP_BAD_REQUEST);
        }

        $code = $request->user()->issueVerificationCode();
        Mail::to($request->input('new_email'))->send(new VerificationCodeMail($code, 'confirm your new email address'));

        return response()->json([
            'message' => 'Verification code sent to new email address',
        ], Response::HTTP_OK);
    }

    /**
     * Step 2: confirm the code and switch the signed-in user's email.
     */
    public function confirmEmailChange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'new_email' => 'required|email|unique:users,email',
            'code' => 'required|numeric|digits:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->getMessageBag()->first()
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $request->user();
        if (!$user->verificationCodeMatches((string) $request->input('code'))) {
            return response()->json([
                'message' => 'Invalid verification code'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user->email = $request->input('new_email');
        $user->clearVerificationCode();
        $user->save();

        return response()->json([
            'message' => 'Email changed successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Step 1 of changing the signed-in user's phone number. No SMS provider is configured,
     * so the code goes to the account's email address.
     */
    public function initiatePhoneChange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'new_phone' => 'required|numeric|digits_between:10,15|unique:users,phone',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first()
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $request->user();
        $code = $user->issueVerificationCode();
        Mail::to($user->email)->send(new VerificationCodeMail($code, 'confirm your new phone number'));

        return response()->json([
            'message' => 'Verification code sent to your email address'
        ], Response::HTTP_OK);
    }

    /**
     * Step 2: confirm the code and switch the signed-in user's phone number.
     */
    public function confirmPhoneChange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'new_phone' => 'required|numeric|digits_between:10,15|unique:users,phone',
            'code' => 'required|numeric|digits:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first()
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $request->user();
        if (!$user->verificationCodeMatches((string) $request->input('code'))) {
            return response()->json([
                'message' => 'Invalid verification code'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user->phone = $request->input('new_phone');
        $user->clearVerificationCode();
        $user->save();

        return response()->json([
            'message' => 'Phone number changed successfully'
        ], Response::HTTP_OK);
    }
}
