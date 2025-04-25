<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class EskizService
{
    protected $client;
    protected $token;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => env('ESKIZ_BASE_URL'),
        ]);
        $this->token = $this->getToken();
    }

    protected function getToken()
    {
        return Cache::remember('eskiz_token', 3600, function () {
            $response = $this->client->post('auth/login', [
                'form_params' => [
                    'email' => env('ESKIZ_EMAIL'),
                    'password' => env('ESKIZ_PASSWORD'),
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['data']['token'];
        });
    }

    // public function sendSms($phone, $message)
    // {
    //     $phone = preg_replace('/[^0-9]/', '', $phone);
    //     if (strlen($phone) == 9) {
    //         $phone = '998' . $phone;
    //     }

    //     $response = $this->client->post('message/sms/send', [
    //         'headers' => [
    //             'Authorization' => 'Bearer ' . $this->token,
    //         ],
    //         'form_params' => [
    //             'mobile_phone' => $phone,
    //             'message' => $message,
    //             'from' => '4546',
    //         ]
    //     ]);

    //     return json_decode($response->getBody(), true);
    // }
    public function sendSms($phone, $message)
{
    // Raqamni tozalash va formatlash
    $phone = preg_replace('/[^0-9]/', '', $phone);

    // Agar raqam 9 xonali bo'lsa, 998 qo'shamiz
    if (strlen($phone) == 9) {
        $phone = '998' . $phone;
    }

    // Faqat test rejimida ishlayotganimizni tekshiramiz
    if (env('APP_ENV') === 'local') {
        $message = "[TEST] " . $message;
    }

    try {
        $response = $this->client->post('message/sms/send', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
            ],
            'form_params' => [
                'mobile_phone' => $phone,
                'message' => $message,
                'from' => '4546', // Eskizdan olingan sender name
            ]
        ]);

        return json_decode($response->getBody(), true);
    } catch (\Exception $e) {
        // Xatolikni logga yozish
        \Log::error('SMS jo\'natishda xato: ' . $e->getMessage());

        // Test rejimida consolega chiqaramiz
        if (app()->environment('local')) {
            info("SMS jo'natiladi (test rejim): {$phone} - {$message}");
            return ['status' => 'success', 'message' => 'Test rejimida SMS consolega yozildi'];
        }

        throw $e;
    }
}
}
