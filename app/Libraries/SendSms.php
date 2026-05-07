<?php
namespace App\Libraries;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Http;

class SendSms 
{
    public static function send($to, $message)
    {
        // Your Account SID and Auth Token from twilio.com/console
        $account_sid = env('TWILIO_SID', false);
        $auth_token = env('TWILIO_AUTH_TOKEN', false);
        // In production, these should be environment variables. E.g.:
        // $auth_token = $_ENV["TWILIO_AUTH_TOKEN"]

        // A Twilio number you own with SMS capabilities
        $twilio_number = "+12053780707";
        $client = new Client($account_sid, $auth_token);
        $client->messages->create(
            // Where to send a text message (your cell phone?)
            $to,
            array(
                'from' => $twilio_number,
                'body' => $message
            )
        );
        
        return true;
        // Log::channel('sms')->info($client->httpClient->lastResponse->content);
        // dd($client->httpClient->lastResponse->content);
    }

    public static function sendMessageWA($to, $message)
    {
        $token = env('WA_BLASH_TOKEN', false);

        $response = Http::withHeaders([
                'Authorization' => $token,
            ])->post('https://jogja.wablas.com/api/send-message', [
                'phone' => $to,
                'message' => $message,
            ]);

        return true; 
    }

    public static function sendMessageWAA($to, $message)
    {
        //$token = config('services.wa_blas.token'); // atau env(...) kalau belum config:cache
        $token = env('WA_BLASH_TOKEN', false);
        $to = str_replace('+', '', $to);
        $to = preg_replace('/^0/', '62', $to);

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => $token, // kalau perlu Bearer, jadi 'Bearer '.$token
        ])->post('https://jogja.wablas.com/api/send-message', [
            'phone' => $to,
            'message' => $message,
        ]);

        // log response
        \Illuminate\Support\Facades\Log::info('WABLAS_SEND_OUT', [
            'to' => $to,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return $response->json();
    }
}