<?php

namespace PickBazar\Database\Repositories;

use App\Mail\ForgetPassword as MailForgetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PickBazar\Database\Models\User;
use Prettus\Validator\Exceptions\ValidatorException;
use Spatie\Permission\Models\Permission;
use PickBazar\Enums\Permission as UserPermission;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use PickBazar\Mail\ForgetPassword;
use Illuminate\Support\Facades\Mail;
use PickBazar\Database\Models\Address;
use PickBazar\Database\Models\Profile;

class UserRepository extends BaseRepository
{
    /**
     * @var array
     */
    protected $fieldSearchable = [
        'name' => 'like',
        'email' => 'like',
    ];

    /**
     * @var array
     */
    protected $dataArray = [
        'name',
        'email',
    ];

    /**
     * Configure the Model
     **/
    public function model()
    {
        return User::class;
    }

    public function boot()
    {
        try {
            $this->pushCriteria(app(RequestCriteria::class));
        } catch (RepositoryException $e) {
        }
    }

    public function storeUser($request)
    {
        
        try {
            return $request->permission;
            $user = $this->create([
                'name'     => $request->name,
                'email'    => $request->email,
                'permission'    => $request->permission,
                'password' => Hash::make($request->password),
            ]);
            
            if($request->permission){
                $user->givePermissionTo(UserPermission::SUPER_ADMIN);
            }else{
                $user->givePermissionTo(UserPermission::CUSTOMER);
            }
            
            if (isset($request['address']) && count($request['address'])) {
                $user->address()->createMany($request['address']);
            }
            if (isset($request['profile'])) {
                $user->profile()->create($request['profile']);
            }
            $user->profile = $user->profile;
            $user->address = $user->address;
            return $user;
        } catch (ValidatorException $e) {
            return ['message' => "Something went wrong!", 'success' => false, 'code' => 404];
        }
    }

    public function updateUser($request, $user)
    {
        try {
            if (isset($request['address']) && count($request['address'])) {
                foreach ($request['address'] as $address) {
                    $location = $address['address']['street_address']." ".$address['address']['city']." Portugal";
                        
                    $geo = json_decode(@file_get_contents('https://www.mapquestapi.com/geocoding/v1/address?key=z4oUxuZk2DKCB3VqPZjrN3e9YbjSIGTe&location='.urlencode($location)), true)['results'][0]['locations'][0]['displayLatLng'];
                    
                    $address['address']['lat'] = $geo['lat'];
                    $address['address']['lng'] = $geo['lng'];
                        
                    if (isset($address['id'])) {
                        Address::findOrFail($address['id'])->update($address);
                    } else {
                        
                        
                        $address['customer_id'] = $user->id;
                        
                        Address::create($address);
                    }
                }
            }
            if (isset($request['profile'])) {
                if (isset($request['profile']['id'])) {
                    Profile::findOrFail($request['profile']['id'])->update($request['profile']);
                } else {
                    $profile = $request['profile'];
                    $profile['customer_id'] = $user->id;
                    Profile::create($profile);
                }
            }
            $user->update($request->only($this->dataArray));
            $user->profile = $user->profile;
            $user->address = $user->address;
            return $user;
        } catch (ValidationException $e) {
            return ['message' => "Something went wrong!", 'success' => false, 'code' => 422];
        }
    }

    public function sendResetEmail($email, $token)
    {
        try {
            
            Mail::to($email)->send(new ForgetPassword($token));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
