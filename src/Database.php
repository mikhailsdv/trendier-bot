<?php

declare(strict_types=1);

namespace Trendier;

use PDO;

/** Connects lazily, so requests that don't touch the database don't pay for the connection. */
final class Database
{
    private ?PDO $pdo = null;
    private readonly string $table;
    private readonly string $aliceTable;

    public function __construct()
    {
        $this->table = Config::get('DB_TABLE');
        $this->aliceTable = Config::get('DB_ALICE_TABLE', 'trendier_bot_alice');
    }

    /** Seconds since the last logged request where $column = $value, null if there was none. */
    public function secondsSinceLastRequest(string $column, int|string $value): ?int
    {
        $seconds = $this->query(
            "SELECT TIMESTAMPDIFF(SECOND, `date`, NOW()) FROM `$this->table` WHERE `$column` = ? ORDER BY id DESC LIMIT 1",
            [$value],
        )->fetchColumn();
        return $seconds === false ? null : (int) $seconds;
    }

    /** @param array<string, int|string> $row */
    public function logRequest(array $row): void
    {
        $columns = '`' . implode('`, `', array_keys($row)) . '`';
        $placeholders = implode(', ', array_fill(0, count($row), '?'));
        $this->query("INSERT INTO `$this->table` ($columns) VALUES ($placeholders)", array_values($row));
    }

    public function aliceLinkOf(int $userId): ?string
    {
        $url = $this->query(
            "SELECT url FROM `$this->aliceTable` WHERE is_given = 1 AND user_id = ? LIMIT 1",
            [$userId],
        )->fetchColumn();
        return $url === false ? null : $url;
    }

    /** Hands a free Alice skill link to the user; null when no free links are left. */
    public function giveAliceLink(int $userId): ?string
    {
        $taken = $this->query(
            "UPDATE `$this->aliceTable` SET is_given = 1, user_id = ? WHERE is_given = 0 LIMIT 1",
            [$userId],
        )->rowCount();
        return $taken ? $this->aliceLinkOf($userId) : null;
    }

    /** @param list<int|string> $params */
    private function query(string $sql, array $params): \PDOStatement
    {
        $this->pdo ??= new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Config::get('DB_HOST', 'localhost'),
                Config::get('DB_PORT', '3306'),
                Config::get('DB_NAME'),
            ),
            Config::get('DB_USER'),
            Config::get('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2],
        );
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement;
    }
}
