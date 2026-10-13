<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Webhook kirish nuqtasi uchun umumiy himoya:
 * metod tekshiruvi, secret_token, JSON tahlili, update_id bo'yicha idempotentlik.
 */
final class Webhook
{
    /**
     * So'rovni tekshiradi. Muvaffaqiyatsiz bo'lsa javob kodini yuborib, null qaytaradi.
     * @return array<string, mixed>|null
     */
    public static function receive(string $expectedSecret): ?array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            return null;
        }
        $given = (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
        if ($expectedSecret === '' || !hash_equals($expectedSecret, $given)) {
            http_response_code(403);
            return null;
        }
        $update = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($update) || !isset($update['update_id'])) {
            http_response_code(400);
            return null;
        }
        return $update;
    }

    /**
     * update_id ni birinchi marta ko'rayapmizmi? Takroriy bo'lsa false.
     * update_id har bot uchun alohida, shuning uchun bot nomi bilan saqlanadi.
     */
    public static function markProcessed(string $bot, int $updateId): bool
    {
        $stmt = Db::run(
            'INSERT IGNORE INTO processed_updates (bot, update_id) VALUES (?, ?)',
            [$bot, $updateId]
        );
        if (random_int(1, 100) === 1) {
            Db::run('DELETE FROM processed_updates WHERE created_at < NOW() - INTERVAL 3 DAY');
        }
        return $stmt->rowCount() === 1;
    }
}
