<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SemaphoreSmsService
{
    public function send(?string $number, string $message): bool
    {
        $key = (string) config('services.semaphore.key');
        $recipient = $this->normalizeNumber($number);

        if ($key === '' || $recipient === null || trim($message) === '') {
            return false;
        }

        try {
            $payload = [
                'apikey' => $key,
                'number' => $recipient,
                'message' => $message,
            ];

            $sender = trim((string) config('services.semaphore.sender'));
            if ($sender !== '') {
                $payload['sendername'] = $sender;
            }

            $response = Http::asForm()
                ->timeout(20)
                ->post((string) config('services.semaphore.url'), $payload);

            return $response->successful();
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function normalizeNumber(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number) ?? '';

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = '63'.substr($digits, 1);
        } elseif (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '63'.$digits;
        }

        return strlen($digits) === 12 && str_starts_with($digits, '63') ? $digits : null;
    }
}
