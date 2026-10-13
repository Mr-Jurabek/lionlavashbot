<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use Throwable;

/**
 * PDO o'rami. Barcha so'rovlar prepared statement orqali.
 * Ichma-ich tranzaksiyalar va commit'dan keyingi callback'larni qo'llaydi.
 */
final class Db
{
    private static ?PDO $pdo = null;
    private static int $depth = 0;
    /** @var list<callable> */
    private static array $afterCommit = [];

    public static function connect(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Env::require('DB_HOST'),
                Env::get('DB_PORT', '3306'),
                Env::require('DB_NAME')
            );
            self::$pdo = new PDO($dsn, Env::require('DB_USER'), Env::get('DB_PASS', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            // MySQL vaqtini PHP vaqt zonasiga moslash (shared hostingda server zonasi boshqa bo'lishi mumkin)
            self::$pdo->exec("SET time_zone = '" . date('P') . "'");
        }
        return self::$pdo;
    }

    public static function disconnect(): void
    {
        self::$pdo = null;
        self::$depth = 0;
        self::$afterCommit = [];
    }

    /** @param array<int|string, mixed> $params */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** @return array<string, mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int) self::connect()->lastInsertId();
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::connect();
        if (self::$depth === 0) {
            $pdo->beginTransaction();
        }
        self::$depth++;
        try {
            $result = $fn();
            self::$depth--;
            if (self::$depth === 0) {
                $pdo->commit();
                $callbacks = self::$afterCommit;
                self::$afterCommit = [];
                foreach ($callbacks as $cb) {
                    $cb();
                }
            }
            return $result;
        } catch (Throwable $e) {
            self::$depth--;
            if (self::$depth === 0) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                self::$afterCommit = [];
            }
            throw $e;
        }
    }

    /** Tranzaksiya muvaffaqiyatli yakunlangach bajariladi (tranzaksiya bo'lmasa — darhol). */
    public static function afterCommit(callable $fn): void
    {
        if (self::$depth === 0) {
            $fn();
            return;
        }
        self::$afterCommit[] = $fn;
    }
}
