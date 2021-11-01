<?php

namespace PickBazar\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PickBazar\Database\Repositories\UserRepository;
use Illuminate\Validation\ValidationException;
use PickBazar\Database\Models\User;
use Illuminate\Support\Facades\Hash;
use PickBazar\Http\Requests\UserCreateRequest;
use PickBazar\Http\Requests\UserUpdateRequest;
use PickBazar\Enums\Permission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PickBazar\Http\Requests\ChangePasswordRequest;
use PickBazar\Mail\ContactAdmin;
use PickBazar\Database\Models\Profile;

class UserController extends CoreController
{
    public $repository;

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $limit = $request->limit ?   $request->limit : 15;
        return $this->repository->with(['profile', 'address'])->paginate($limit);
    }

    /**
     * Store a newly created resource in storage.
     *Í
     * @param UserCreateRequest $request
     * @return bool[]
     */
    public function store(UserCreateRequest $request)
    {
        return $this->repository->storeUser($request);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return array
     */
    public function show($id)
    {
        return $this->repository->with(['profile', 'address'])->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UserUpdateRequest $request
     * @param int $id
     * @return array
     */
    public function update(UserUpdateRequest $request, $id)
    {
        if ($request->user()->hasPermissionTo(Permission::SUPER_ADMIN)) {
            $user = $this->repository->findOrFail($id);
            return $this->repository->updateUser($request, $user);
        } elseif ($request->user()->id == $id && $request->user()->hasPermissionTo(Permission::CUSTOMER)) {
            $user = $request->user();
            return $this->repository->updateUser($request, $user);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return array
     */
    public function destroy($id)
    {
        try {
            return $this->repository->findOrFail($id)->delete();
        } catch (\Exception $e) {
            return response()->json(['message' => 'User not found!'], 404);
        }
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $user->address = $user->address;
        $user->profile = $user->profile;
        $user->orders = $user->orders;
        return $user;
    }

    public function token(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->where('is_active', true)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return ["token" => null, "permissions" => []];
        }
        return ["token" => $user->createToken('auth_token')->plainTextToken, "permissions" => $user->getPermissionNames()];
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return true;
        }
        return $request->user()->currentAccessToken()->delete();
    }

    public function register(UserCreateRequest $request)
    {
        $user = $this->repository->create([
            'name'     => $request->name,
            'email'    => $request->email,
            'permission'    => $request->permission,
            'password' => Hash::make($request->password),
        ]);

        $profile['customer_id'] = $user->id;
        $profile['contact'] = $request->contact;

        Profile::create($profile);

        if($request->permission){
            $user->givePermissionTo(Permission::SUPER_ADMIN);
        }else{
            $user->givePermissionTo(Permission::CUSTOMER);
        }

        return ["token" => $user->createToken('auth_token')->plainTextToken, "permissions" => $user->getPermissionNames()];
    }

    public function banUser(Request $request)
    {
        $user = $request->user();
        if ($user && $user->hasPermissionTo(Permission::SUPER_ADMIN) && $user->id != $request->id) {
            $banUser =  User::find($request->id);
            $banUser->is_active = false;
            $banUser->save();
            return $banUser;
        }
    }
    public function activeUser(Request $request)
    {
        $user = $request->user();
        if ($user && $user->hasPermissionTo(Permission::SUPER_ADMIN) && $user->id != $request->id) {
            $acriveUser =  User::find($request->id);
            $acriveUser->is_active = true;
            $acriveUser->save();
            return $acriveUser;
        }
    }

    public function forgetPassword(Request $request)
    {
        
        $user = $this->repository->findByField('email', $request->email);
        if (count($user) < 1) {
            return ['message' => 'User does not exist!', 'success' => false];
        }
        
        $tokenData = DB::table('password_resets')
            ->where('email', $request->email)->first();
        if (!$tokenData) {
            DB::table('password_resets')->insert([
                'email' => $request->email,
                'token' => Str::random(16),
                'created_at' => Carbon::now()
            ]);
            $tokenData = DB::table('password_resets')
                ->where('email', $request->email)->first();
        }

        if ($this->repository->sendResetEmail($request->email, $tokenData->token)) {
            return ['message' => 'Please check your inbox for password reset email.', 'success' => true];
        } else {
            return ['message' => 'Something went wrong try again!', 'success' => false];
        }
    }
    public function verifyForgetPasswordToken(Request $request)
    {
        $tokenData = DB::table('password_resets')->where('token', $request->token)->first();
        $user = $this->repository->findByField('email', $request->email);
        if (!$tokenData) {
            return ['message' => 'Invalid Token!', 'success' => false];
        }
        $user = $this->repository->findByField('email', $request->email);
        if (count($user) < 1) {
            return ['message' => 'User does not exist!', 'success' => false];
        }
        return ['message' => 'Token is valid', 'success' => true];
    }
    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'password' => 'required|string',
                'email' => 'email|required',
                'token' => 'required|string'
            ]);

            $user = $this->repository->where('email', $request->email)->first();
            $user->password = Hash::make($request->password);
            $user->save();

            DB::table('password_resets')->where('email', $user->email)->delete();

            return ['message' => 'Password Reset Successful!', 'success' => true];
        } catch (\Exception $th) {
            return ['message' => 'Something went wrong', 'success' => false];
        }
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        try {
            $user = $request->user();
            if (Hash::check($request->oldPassword, $user->password)) {
                $user->password = Hash::make($request->newPassword);
                $user->save();
                return ['message' => 'Password Change Successful!', 'success' => true];
            } else {
                return ['message' => 'Old Password is not correct', 'success' => false];
            }
        } catch (\Exception $th) {
            return ['message' => 'Something went wrong', 'success' => false];
        }
    }
    public function contactAdmin(Request $request)
    {
        try {
            $details = $request->only('subject', 'name', 'email', 'description');
            Mail::to(config('shop.admin_email'))->send(new ContactAdmin($details));
            return ['message' => 'Email sent successful!', 'success' => true];
        } catch (\Exception $e) {
            return ['message' => 'Something went wrong', 'success' => false];
        }
    }
}
