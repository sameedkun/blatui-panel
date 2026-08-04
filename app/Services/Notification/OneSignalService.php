<?php

namespace App\Services\Notification;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class OneSignalService
{
    protected string $appId;

    protected string $restApiKey;

    protected Client $client;

    protected string $apiUrl = 'https://api.onesignal.com';

    public function __construct()
    {
        $this->appId = (string) config('services.onesignal.app_id');
        $this->restApiKey = (string) config('services.onesignal.rest_api_key');

        $this->client = new Client([
            'base_uri' => $this->apiUrl,
            'timeout' => 30,
        ]);
    }

    /**
     * Broadcast a push notification to every subscribed device.
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, id?: string|null, recipients?: int, response?: array<string, mixed>, error?: string}
     */
    public function sendToAll(string $title, string $message, array $data = [], ?string $url = null): array
    {
        try {
            $payload = [
                'app_id' => $this->appId,
                'target_channel' => 'push',
                'included_segments' => ['All'],
                'headings' => ['en' => $title],
                'contents' => ['en' => $message],
                'data' => $data,
            ];

            if ($url) {
                $payload['url'] = $url;
            }

            $response = $this->client->post('/notifications?c=push', [
                'headers' => [
                    'Authorization' => 'Key '.$this->restApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true) ?? [];

            return [
                'success' => true,
                'id' => $responseData['id'] ?? null,
                'recipients' => $responseData['recipients'] ?? 0,
                'response' => $responseData,
            ];
        } catch (RequestException $e) {
            $errorMessage = $e->getMessage();

            if ($e->hasResponse()) {
                $errorResponse = json_decode($e->getResponse()->getBody()->getContents(), true);
                $errorMessage = $errorResponse['errors'][0] ?? $e->getMessage();
            }

            Log::error('OneSignal Error: '.$errorMessage);

            return [
                'success' => false,
                'error' => $errorMessage,
            ];
        } catch (GuzzleException|Exception $e) {
            Log::error('OneSignal Error: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
