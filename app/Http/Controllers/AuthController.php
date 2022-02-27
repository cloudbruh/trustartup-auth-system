<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->only('email', 'password');

        $validator = Validator::make($data, [
            'email' => 'required|email',
            'password' => 'required|string|min:6|max:50'
        ]);
        if ($validator->fails())
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 400);

        $email = $request->email;
        $password = $request->password;

        $response = Http::post(env('API_USER') . '/attempt', [
            'email' => $email
        ]);
        if ($response->getStatusCode() == 404)
            return response()->json(['message' => 'Not found'], 404);
        else if (!$response->successful())
            return response()->json(['message' => $response->body()], 500);

        $user = $response->object();
        if (!Hash::check($password, $user->password))
            return response()->json(['message' => 'Wrong password'], 403);

        $payload = array(
            "iss" => env('APP_URL'),
            "exp" => Carbon::now()->addWeek(1)->timestamp,
            "uid" => $user->id,
        );
        $jwt = JWT::encode($payload, env('PRIVATE_KEY'), 'RS256');

        return response()->json([
            'token' => $jwt,
            'id' => $user->id,
        ], 200);
    }

    public function register(Request $request)
    {
        $data = $request->only('name', 'surname', 'email', 'password');

        $validator = Validator::make($data, [
            'name' => 'required|string|min:1|max:64',
            'surname' => 'required|string|min:1|max:64',
            'email' => 'required|email',
            'password' => 'required|string|min:6|max:50'
        ]);
        if ($validator->fails())
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 400);

        $response = Http::post(env('API_USER') . '/attempt', [
            'email' => $request->email
        ]);
        if ($response->getStatusCode() == 200)
            return response()->json(['message' => 'Already exists'], 409);
        else if($response->getStatusCode() != 404)
            return response()->json(['message' => $response->body()], 500);

        $response = Http::post(env('API_USER') . '/user', [
            'name' => $request->name,
            'surname' => $request->surname,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        if (!$response->successful())
            return response()->json(['message' => $response->body()], 500);

        $payload = array(
            "iss" => env('APP_URL'),
            "exp" => Carbon::now()->addWeek(1)->timestamp,
            "uid" => $response->object()->id,
        );
        $jwt = JWT::encode($payload, env('PRIVATE_KEY'), 'RS256');

        return response()->json([
            'token' => $jwt,
            'id' => $response->object()->id,
        ], 200);
    }
}
