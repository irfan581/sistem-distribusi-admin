<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmService
{
    /**
     * $channelId dan $sound harus cocok dengan channel yang dibuat aplikasi
     * penerima; $sound adalah nama berkas di res/raw Android tanpa ekstensi.
     */
    public static function sendNotification(
        string $token,
        string $title,
        string $body,
        array $data = [],
        string $channelId = 'high_importance_channel',
        string $sound = 'default',
    ): bool {
        if (empty($token)) {
            Log::warning('FCM: Token kosong, skip.');
            return false;
        }

        try {
            $credentialsPath = base_path(env('FIREBASE_CREDENTIALS', 'storage/app/firebase/firebase-service-account.json'));

            if (!file_exists($credentialsPath)) {
                Log::error("FCM: File credentials tidak ditemukan di {$credentialsPath}");
                return false;
            }

            $factory   = (new Factory)->withServiceAccount($credentialsPath);
            $messaging = $factory->createMessaging();

            $appUrl     = config('app.url', 'http://localhost');
            $stringData = array_merge(
                ['click_action' => 'FLUTTER_NOTIFICATION_CLICK'],
                array_map(fn($v) => (string) $v, $data)
            );

            $message = CloudMessage::fromArray([
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'data' => $stringData,
                'android' => [
                    'priority' => 'high',
                    'ttl'      => '86400s',
                    'notification' => [
                        'channel_id'            => $channelId,
                        'sound'                 => $sound,
                        'click_action'          => 'FLUTTER_NOTIFICATION_CLICK',
                        'color'                 => '#EA580C',
                        'notification_priority' => 'PRIORITY_MAX',
                        'visibility'            => 'PUBLIC',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound'             => 'default',
                            'badge'             => 1,
                            'content-available' => 1,
                        ],
                    ],
                ],
                'webpush' => [
                    'headers' => ['Urgency' => 'high'],
                    'notification' => [
                        'title'    => $title,
                        'body'     => $body,
                        'icon'     => $appUrl . '/logo-icon.png',
                        'badge'    => $appUrl . '/logo-icon.png',
                        'tag'      => 'chat-message',
                        'renotify' => true,
                    ],
                    'fcm_options' => [
                        'link' => $appUrl . '/chat',
                    ],
                ],
            ]);

            $messaging->send($message);

            Log::info("FCM sent: \"{$title}\" → " . substr($token, 0, 15) . '…');
            return true;

        } catch (\Throwable $e) {
            Log::error('FCM Error: ' . $e->getMessage());
            return false;
        }
    }
}
