<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

final class Order
{
    /** @param array<string, mixed> $data */
    public static function create(array $data): int
    {
        return Db::insert(
            'INSERT INTO orders (user_id, status, payment_method, items_total, delivery_fee, total,
                                 address_text, latitude, longitude, phone, comment)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['user_id'], $data['status'], $data['payment_method'],
                $data['items_total'], $data['delivery_fee'], $data['total'],
                $data['address_text'], $data['latitude'], $data['longitude'],
                $data['phone'], $data['comment'],
            ]
        );
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM orders WHERE id = ?', [$id]);
    }

    /** Tranzaksiya ichida o'qib, qatorni qulflaydi. */
    public static function findForUpdate(int $id): ?array
    {
        return Db::one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public static function recentByUser(int $userId, int $limit = 10): array
    {
        return Db::all(
            'SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT ' . max(1, min($limit, 50)),
            [$userId]
        );
    }

    /** Yakunlanmagan buyurtmalar (admin paneli uchun). @return list<array<string, mixed>> */
    public static function openOrders(int $limit = 15): array
    {
        return Db::all(
            "SELECT * FROM orders WHERE status NOT IN ('delivered', 'cancelled')
             ORDER BY id DESC LIMIT " . max(1, min($limit, 50))
        );
    }

    public static function updateStatus(int $id, string $status, string $paymentStatus): void
    {
        Db::run('UPDATE orders SET status = ?, payment_status = ? WHERE id = ?', [$status, $paymentStatus, $id]);
    }

    public static function setReceipt(int $id, string $fileId): void
    {
        Db::run('UPDATE orders SET receipt_file_id = ? WHERE id = ?', [$fileId, $id]);
    }

    public static function logStatus(int $orderId, ?string $from, string $to, string $changedBy): void
    {
        Db::run(
            'INSERT INTO order_status_log (order_id, from_status, to_status, changed_by) VALUES (?, ?, ?, ?)',
            [$orderId, $from, $to, mb_substr($changedBy, 0, 64)]
        );
    }
}
