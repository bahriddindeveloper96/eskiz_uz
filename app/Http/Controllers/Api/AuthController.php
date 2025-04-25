<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EskizService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    protected $eskizService;

    public function __construct(EskizService $eskizService)
    {
        $this->eskizService = $eskizService;
    }

    /**
     * Telefon raqam orqali registratsiya
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^998[0-9]{9}$/|unique:users',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $smsCode = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

        // Foydalanuvchi yaratish (parolsiz)
        $user = User::firstOrCreate(
            ['phone' => $request->phone],
            [
                'phone' => $request->phone,
                'sms_code' => $smsCode,
                'phone_verified_at' => null,
            ]
        );

        // SMS kodni cachega saqlash (5 daqiqa)
        Cache::put('sms_verification_'.$request->phone, $smsCode, now()->addMinutes(5));

        // SMS jo'natish
        $message = "Sizning tasdiqlash kodingiz: $smsCode";
        $this->eskizService->sendSms($user->phone, $message);

        return response()->json([
            'message' => 'SMS kod telefon raqamingizga yuborildi.',
            'phone' => $user->phone,
            'sms_code' => app()->environment('local') ? $smsCode : null,
        ], 201);
    }

    /**
     * SMS kodni tekshirish va token yaratish
     */
    public function verifySms(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^998[0-9]{9}$/',
            'sms_code' => 'required|string|size:4',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Cache'dan SMS kodni tekshirish
        $cachedCode = Cache::get('sms_verification_'.$request->phone);

        if (!$cachedCode || $cachedCode !== $request->sms_code) {
            return response()->json(['message' => 'Noto\'g\'ri SMS kodi yoki muddati tugagan'], 401);
        }

        $user = User::where('phone', $request->phone)->firstOrFail();

        // Token yaratish
        $token = $user->createToken('sms-auth')->plainTextToken;

        // Foydalanuvchini tasdiqlangan deb belgilash
        $user->update([
            'phone_verified_at' => now(),
            'sms_code' => null,
        ]);

        // Cache'dan SMS kodni o'chirish
        Cache::forget('sms_verification_'.$request->phone);

        return response()->json([
            'message' => 'Muvaffaqiyatli kirish',
            'token' => $token,
            'user' => $user->only(['id', 'phone', 'phone_verified_at']),
        ]);
    }

    /**
     * Yangi SMS kod jo'natish
     */
    public function resendSms(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^998[0-9]{9}$/',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $smsCode = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

        // SMS kodni yangilash
        Cache::put('sms_verification_'.$request->phone, $smsCode, now()->addMinutes(5));

        $user = User::firstOrCreate(
            ['phone' => $request->phone],
            ['phone' => $request->phone]
        );

        $message = "Sizning yangi tasdiqlash kodingiz: $smsCode";
        $this->eskizService->sendSms($user->phone, $message);

        return response()->json([
            'message' => 'Yangi SMS kod yuborildi',
            'phone' => $user->phone,
            'sms_code' => app()->environment('local') ? $smsCode : null,
        ]);
    }
}
