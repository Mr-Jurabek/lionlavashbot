<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Telegram Bot API mijozi (curl). Har bot o'z tokeni bilan alohida obyekt.
 * Xatolar logga yoziladi va null qaytariladi — bot oqimi tarmoq xatosidan to'xtab qolmasin.
 */
class Telegram
{
    public function __construct(private readonly string $token)
    {
    }

    /**
     * @param array<string, mixed> $params
     * @return mixed natija (`result`) yoki xatoda null
     */
    public function call(string $method, array $params = []): mixed
    {
        $ch = curl_init("https://api.telegram.org/bot{$this->token}/{$method}");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
        ]);
        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            Logger::error('Telegram tarmoq xatosi', ['method' => $method, 'error' => $curlError]);
            return null;
        }
        $data = json_decode((string) $raw, true);
        if (!is_array($data) || ($data['ok'] ?? false) !== true) {
            $description = is_array($data) ? (string) ($data['description'] ?? '') : (string) $raw;
            // Bir xil matn bilan tahrirlash — xato emas
            if (str_contains($description, 'message is not modified')) {
                return true;
            }
            Logger::warning('Telegram API xatosi', ['method' => $method, 'description' => $description]);
            return null;
        }
        return $data['result'];
    }

    public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null): mixed
    {
        return $this->call('sendMessage', array_filter([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
            'reply_markup' => $replyMarkup,
        ], static fn($v) => $v !== null));
    }
}
