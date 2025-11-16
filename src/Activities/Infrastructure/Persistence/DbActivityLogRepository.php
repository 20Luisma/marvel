<?php

declare(strict_types=1);

namespace App\Activities\Infrastructure\Persistence;

use App\Activities\Domain\ActivityEntry;
use App\Activities\Domain\ActivityLogRepository;
use DateTimeImmutable;
use DateTimeInterface;
use PDO;

final class DbActivityLogRepository implements ActivityLogRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly int $maxEntries = 100
    ) {
    }

    /**
     * @return list<ActivityEntry>
     */
    public function all(string $scope, ?string $contextId = null): array
    {
        $sql = 'SELECT scope, context_id, action, title, occurred_at FROM activity_logs WHERE scope = :scope';
        $params = ['scope' => $scope];

        if ($contextId !== null) {
            $sql .= ' AND context_id = :context_id';
            $params['context_id'] = $contextId;
        } else {
            $sql .= ' AND context_id IS NULL';
        }

        $sql .= ' ORDER BY occurred_at DESC, id DESC LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $this->maxEntries, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        return array_map(
            static fn (array $row): ActivityEntry => ActivityEntry::fromPrimitives([
                'scope' => (string) ($row['scope'] ?? ''),
                'contextId' => $row['context_id'] ?? null,
                'action' => (string) ($row['action'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'timestamp' => (string) ($row['occurred_at'] ?? ''),
            ]),
            $rows
        );
    }

    public function append(ActivityEntry $entry): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO activity_logs (scope, context_id, action, title, occurred_at, created_at) VALUES (:scope, :context_id, :action, :title, :occurred_at, :created_at)'
        );

        $stmt->execute([
            'scope' => $entry->scope(),
            'context_id' => $entry->contextId(),
            'action' => $entry->action(),
            'title' => $entry->title(),
            'occurred_at' => $this->formatDate($entry->occurredAt()),
            'created_at' => $this->formatDate(new DateTimeImmutable()),
        ]);
    }

    public function clear(string $scope, ?string $contextId = null): void
    {
        $sql = 'DELETE FROM activity_logs WHERE scope = :scope';
        $params = ['scope' => $scope];

        if ($contextId !== null) {
            $sql .= ' AND context_id = :context_id';
            $params['context_id'] = $contextId;
        } else {
            $sql .= ' AND context_id IS NULL';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    private function formatDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s.u');
    }
}
