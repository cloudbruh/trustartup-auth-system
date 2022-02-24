<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->only('email', 'password');

        //validate user request
        $validator = Validator::make($data, [
            'email' => 'required|email',
            'password' => 'required|string|min:6|max:50'
        ]);
        if ($validator->fails())
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 400);

        $email = $request->email;
        $password = $request->password;

        //request to the User System
        $response = Http::post(env('API_USER_ATTEMPT'), [
            'email' => $email
        ]);
        if (!$response->successful()) {
            return response()->json(['message' => $response->body()], $response->getStatusCode());
        }

        //validating response
        $user = $response->object();
        if (!Hash::check($password, $user->password))
            return response()->json(['message' => 'Wrong password'], 403);

        $roles = collect($user->roles)->pluck('type')->toArray();

        //generating jwt token
        $payload = array(
            "iss" => env('APP_URL'),
            "exp" => Carbon::now()->addWeek(1)->timestamp,
            "uid" => $user->id,
            "rls" => $roles,
        );
        $jwt = JWT::encode($payload, env('PRIVATE_KEY'), 'RS256');

        return response()->json([
            'token' => $jwt,
            'id' => $user->id,
            'roles' => $roles,
        ], 200);

        //$decoded = JWT::decode($jwt, new Key(env('PUBLIC_KEY'), 'RS256'));
        //dd($decoded);
    }

    public function register(Request $request)
    {
        $data = $request->only('name', 'surname', 'email', 'password');

        //validate user request
        $validator = Validator::make($data, [
            'name' => 'required|string|min:1|max:64',
            'surname' => 'required|string|min:1|max:64',
            'email' => 'required|email',
            'password' => 'required|string|min:6|max:50'
        ]);
        if ($validator->fails())
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 400);

        //request to the User System
        $response = Http::post(env('API_USER_CREATE'), [
            'name' => $request->name,
            'surname' => $request->surname,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        //validating response
        if (!$response->successful()) {
            return response()->json(['message' => $response->body()], $response->getStatusCode());
        }

        //generating jwt token
        $payload = array(
            "iss" => env('APP_URL'),
            "exp" => Carbon::now()->addWeek(1)->timestamp,
            "uid" => $response->object()->id,
            "rls" => [],
        );
        $jwt = JWT::encode($payload, env('PRIVATE_KEY'), 'RS256');

        return response()->json([
            'token' => $jwt,
            'id' => $response->object()->id,
            'roles' => [],
        ], 200);
    }
}
