<?php

declare(strict_types=1);

namespace Hut;

use PDO;

class GameImporter
{
    public const DEFAULT_BATCH_SIZE = 300;

    private const CSV_COLUMNS = [
        'id',
        'name',
        'yearpublished',
        'rank',
        'bayesaverage',
        'average',
        'usersrated',
        'is_expansion',
        'abstracts_rank',
        'cgs_rank',
        'childrensgames_rank',
        'familygames_rank',
        'partygames_rank',
        'strategygames_rank',
        'thematic_rank',
        'wargames_rank',
    ];

    private \PDO $pdo;
    private string $driver;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
        $this->driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Parse a BGG CSV rankings export and upsert all items into the games table.
     *
     * Returns the number of games imported/updated.
     */
    public function importCsv(string $csvContent): int
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Failed to open temporary CSV buffer.');
        }

        fwrite($handle, $csvContent);
        rewind($handle);

        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if ($header === false) {
            fclose($handle);
            throw new \RuntimeException('CSV file is empty.');
        }

        $normalizedHeader = array_map(
            static fn ($value) => strtolower(trim((string) $value)),
            $header
        );
        $requiredColumns = ['id', 'name', 'yearpublished'];
        foreach ($requiredColumns as $requiredColumn) {
            if (!in_array($requiredColumn, $normalizedHeader, true)) {
                fclose($handle);
                throw new \RuntimeException('CSV is missing required column: ' . $requiredColumn);
            }
        }

        $count = 0;
        $skipped = 0;
        $firstError = null;
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }

            $record = [];
            foreach ($normalizedHeader as $index => $column) {
                $record[$column] = isset($row[$index]) ? trim((string) $row[$index]) : '';
            }

            try {
                $this->upsertCsvRecord($record);
                $count++;
            } catch (\Throwable $e) {
                $skipped++;
                if ($firstError === null) {
                    $firstError = $e->getMessage();
                }
                error_log('BGG CSV import skipped row: ' . $e->getMessage());
            }
        }

        fclose($handle);

        if ($count === 0 && $skipped > 0) {
            throw new \RuntimeException('CSV import failed for all rows. First error: ' . $firstError);
        }

        return $count;
    }

    /**
     * @return array{total:int,start_offset:int}
     */
    public function inspectCsvFile(string $filePath): array
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Failed to open uploaded CSV file.');
        }

        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if ($header === false) {
            fclose($handle);
            throw new \RuntimeException('CSV file is empty.');
        }

        $normalizedHeader = array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            $header
        );
        $this->validateRequiredCsvColumns($normalizedHeader);

        $startOffset = ftell($handle);
        if ($startOffset === false) {
            fclose($handle);
            throw new \RuntimeException('Failed to determine CSV start offset.');
        }

        $total = 0;
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($row === [null]) {
                continue;
            }
            $total++;
        }

        fclose($handle);

        return [
            'total' => $total,
            'start_offset' => (int) $startOffset,
        ];
    }

    /**
     * @return array{processed:int,imported:int,skipped:int,next_offset:int,done:bool,first_error:?string}
     */
    public function importCsvBatchFromFile(string $filePath, int $offset, int $limit): array
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Failed to open uploaded CSV file.');
        }

        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if ($header === false) {
            fclose($handle);
            throw new \RuntimeException('CSV file is empty.');
        }

        $normalizedHeader = array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            $header
        );
        $this->validateRequiredCsvColumns($normalizedHeader);

        if (fseek($handle, max(0, $offset), SEEK_SET) !== 0) {
            fclose($handle);
            throw new \RuntimeException('Failed to continue CSV import from previous offset.');
        }

        $processed = 0;
        $imported = 0;
        $skipped = 0;
        $firstError = null;

        while ($processed < $limit && ($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($row === [null]) {
                continue;
            }

            $record = [];
            foreach ($normalizedHeader as $index => $column) {
                $record[$column] = isset($row[$index]) ? trim((string) $row[$index]) : '';
            }

            $processed++;

            try {
                $this->upsertCsvRecord($record);
                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                if ($firstError === null) {
                    $firstError = $e->getMessage();
                }
                error_log('BGG CSV import skipped row: ' . $e->getMessage());
            }
        }

        $nextOffset = ftell($handle);
        $done = feof($handle);
        fclose($handle);

        return [
            'processed' => $processed,
            'imported' => $imported,
            'skipped' => $skipped,
            'next_offset' => is_int($nextOffset) ? $nextOffset : $offset,
            'done' => $done,
            'first_error' => $firstError,
        ];
    }

    public function importXml(string $xmlContent): int
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        if ($xml === false) {
            $errors = array_map(fn($e) => $e->message, libxml_get_errors());
            throw new \RuntimeException('Invalid XML: ' . implode('; ', $errors));
        }

        $count = 0;

        // BGG collection export: <items><item objecttype="thing" objectid="..."> ...
        foreach ($xml->item as $item) {
            try {
                $this->upsertItem($item);
                $count++;
            } catch (\Exception $e) {
                // Skip malformed entries, log and continue
                error_log('BGG import skipped item: ' . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * @return array{total:int,start_offset:int}
     */
    public function inspectXmlFile(string $filePath): array
    {
        $xml = $this->loadXmlFile($filePath);

        return [
            'total' => count($xml->item),
            'start_offset' => 0,
        ];
    }

    /**
     * @return array{processed:int,imported:int,skipped:int,next_offset:int,done:bool,first_error:?string}
     */
    public function importXmlBatchFromFile(string $filePath, int $offset, int $limit): array
    {
        $xml = $this->loadXmlFile($filePath);
        $items = $xml->item;
        $total = count($items);

        $offset = max(0, $offset);
        $end = min($offset + max(1, $limit), $total);

        $processed = 0;
        $imported = 0;
        $skipped = 0;
        $firstError = null;

        for ($index = $offset; $index < $end; $index++) {
            $processed++;
            try {
                $this->upsertItem($items[$index]);
                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                if ($firstError === null) {
                    $firstError = $e->getMessage();
                }
                error_log('BGG XML import skipped item: ' . $e->getMessage());
            }
        }

        return [
            'processed' => $processed,
            'imported' => $imported,
            'skipped' => $skipped,
            'next_offset' => $end,
            'done' => $end >= $total,
            'first_error' => $firstError,
        ];
    }

    /**
     * @param array<int, string> $normalizedHeader
     */
    private function validateRequiredCsvColumns(array $normalizedHeader): void
    {
        $requiredColumns = ['id', 'name', 'yearpublished'];
        foreach ($requiredColumns as $requiredColumn) {
            if (!in_array($requiredColumn, $normalizedHeader, true)) {
                throw new \RuntimeException('CSV is missing required column: ' . $requiredColumn);
            }
        }
    }

    private function loadXmlFile(string $filePath): \SimpleXMLElement
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException('Failed to read uploaded XML file.');
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        if ($xml === false) {
            $errors = array_map(static fn ($e): string => trim((string) $e->message), libxml_get_errors());
            throw new \RuntimeException('Invalid XML: ' . implode('; ', $errors));
        }

        return $xml;
    }

    private function upsertCsvRecord(array $record): void
    {
        $id = (int) ($record['id'] ?? 0);
        $name = (string) ($record['name'] ?? '');
        if ($id <= 0 || $name === '') {
            throw new \RuntimeException('CSV row is missing id or name.');
        }

        $values = [
            ':id' => $id,
            ':name' => $name,
            ':yearpublished' => $this->nullableInt($record['yearpublished'] ?? null),
            ':rank' => $this->nullableInt($record['rank'] ?? null),
            ':bayesaverage' => $this->nullableFloat($record['bayesaverage'] ?? null),
            ':average' => $this->nullableFloat($record['average'] ?? null),
            ':usersrated' => $this->nullableInt($record['usersrated'] ?? null),
            ':is_expansion' => $this->nullableInt($record['is_expansion'] ?? null),
            ':abstracts_rank' => $this->nullableInt($record['abstracts_rank'] ?? null),
            ':cgs_rank' => $this->nullableInt($record['cgs_rank'] ?? null),
            ':childrensgames_rank' => $this->nullableInt($record['childrensgames_rank'] ?? null),
            ':familygames_rank' => $this->nullableInt($record['familygames_rank'] ?? null),
            ':partygames_rank' => $this->nullableInt($record['partygames_rank'] ?? null),
            ':strategygames_rank' => $this->nullableInt($record['strategygames_rank'] ?? null),
            ':thematic_rank' => $this->nullableInt($record['thematic_rank'] ?? null),
            ':wargames_rank' => $this->nullableInt($record['wargames_rank'] ?? null),
        ];

        $columns = implode(', ', self::CSV_COLUMNS);
        $placeholders = implode(', ', array_keys($values));
        $sql = $this->buildGameUpsertSql($columns, $placeholders, array_slice(self::CSV_COLUMNS, 1));

        $this->pdo->prepare($sql)->execute($values);
    }

    private function upsertItem(\SimpleXMLElement $item): void
    {
        $id = (int) $item['objectid'];
        if (!$id) {
            return;
        }

        $name = (string) ($item->name ?? '');
        $yearpublished = (int) ($item->yearpublished ?? 0) ?: null;
        $average = (float) ($item->stats->rating->average ?? 0) ?: null;
        $usersrated = (int) ($item->stats->rating->usersrated ?? 0) ?: null;

        $insertColumns = implode(', ', [
            'id',
            'name',
            'yearpublished',
            'rank',
            'bayesaverage',
            'average',
            'usersrated',
            'is_expansion',
            'abstracts_rank',
            'cgs_rank',
            'childrensgames_rank',
            'familygames_rank',
            'partygames_rank',
            'strategygames_rank',
            'thematic_rank',
            'wargames_rank',
        ]);

        $insertValues = implode(', ', [
            ':id',
            ':name',
            ':yearpublished',
            'NULL',
            'NULL',
            ':average',
            ':usersrated',
            '0',
            'NULL',
            'NULL',
            'NULL',
            'NULL',
            'NULL',
            'NULL',
            'NULL',
            'NULL',
        ]);

        $sql = $this->buildGameUpsertSql(
            $insertColumns,
            $insertValues,
            ['name', 'yearpublished', 'average', 'usersrated']
        );

        $this->pdo->prepare($sql)->execute([
            ':id' => $id,
            ':name' => $name,
            ':yearpublished' => $yearpublished,
            ':average' => $average,
            ':usersrated' => $usersrated,
        ]);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * Build an upsert statement compatible with SQLite/PostgreSQL and MySQL/MariaDB.
     *
     * @param array<int, string> $updateColumns
     */
    private function buildGameUpsertSql(string $columns, string $values, array $updateColumns): string
    {
        if ($this->driver === 'mysql') {
            $updates = implode(",\n                    ", array_map(
                static fn (string $column) => $column . ' = VALUES(' . $column . ')',
                $updateColumns
            ));

            return <<<SQL
                INSERT INTO games ($columns)
                VALUES ($values)
                ON DUPLICATE KEY UPDATE
                    $updates
            SQL;
        }

        $updates = implode(",\n                ", array_map(
            static fn (string $column) => $column . ' = excluded.' . $column,
            $updateColumns
        ));

        return <<<SQL
            INSERT INTO games ($columns)
            VALUES ($values)
            ON CONFLICT(id) DO UPDATE SET
                $updates
        SQL;
    }
}
