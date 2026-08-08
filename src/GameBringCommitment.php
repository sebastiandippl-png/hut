<?php

declare(strict_types=1);

namespace Hut;

class GameBringCommitment
{
    /**
     * Claim "I bring it" for a game. Exclusive per game: fails if someone else already claimed it.
     * Returns true if this user holds the claim after the call.
     */
    public static function claim(int $userId, int $gameId): bool
    {
        $pdo = Database::getInstance();
        $driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $sql = $driver === 'mysql'
            ? 'INSERT IGNORE INTO game_bring_commitments (game_id, user_id) VALUES (?, ?)'
            : 'INSERT OR IGNORE INTO game_bring_commitments (game_id, user_id) VALUES (?, ?)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$gameId, $userId]);

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return self::bringerUserId($gameId) === $userId;
    }

    /**
     * Release a claim. Only the current claimant can release it.
     */
    public static function release(int $userId, int $gameId): bool
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('DELETE FROM game_bring_commitments WHERE game_id = ? AND user_id = ?');
        $stmt->execute([$gameId, $userId]);

        return $stmt->rowCount() > 0;
    }

    public static function bringerUserId(int $gameId): ?int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT user_id FROM game_bring_commitments WHERE game_id = ? LIMIT 1');
        $stmt->execute([$gameId]);
        $userId = $stmt->fetchColumn();

        return $userId !== false ? (int) $userId : null;
    }

    /**
     * @param array<int, int|string> $gameIds
     * @return array<int, array{user_id: int, name: string}>
     */
    public static function bringersForGames(array $gameIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $gameIds)));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return [];
        }

        $pdo = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT gbc.game_id, gbc.user_id, u.name
             FROM game_bring_commitments gbc
             JOIN users u ON u.id = gbc.user_id
             WHERE gbc.game_id IN ($placeholders)"
        );
        $stmt->execute($ids);

        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[(int) $row['game_id']] = [
                'user_id' => (int) $row['user_id'],
                'name' => (string) $row['name'],
            ];
        }

        return $result;
    }
}
