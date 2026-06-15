<?php

declare(strict_types=1);

namespace Ironflow\Database\Relations;

use Ironflow\Database\Connection;
use Ironflow\Database\Model;
use Ironflow\Database\ModelQueryBuilder;
use Ironflow\Support\Collection;

/**
 * Many-to-many relation via a pivot table.
 * Post belongsToMany Tag via post_tag.
 */
class BelongsToMany extends Relation
{
    private array $pivotColumns = [];

    public function __construct(
        Connection $connection,
        Model $related,
        private readonly string $pivotTable,
        private readonly string $foreignPivotKey,
        private readonly string $relatedPivotKey,
        mixed $parentKeyValue
    ) {
        parent::__construct($connection, $related, $foreignPivotKey, $related->getKeyName(), $parentKeyValue);
    }

    public function withPivot(string ...$columns): static
    {
        $this->pivotColumns = $columns;
        return $this;
    }

    public function getResults(): Collection
    {
        if ($this->parentKeyValue === null) {
            return new Collection();
        }

        $table = $this->related->getTableName();
        $relatedKey = $this->related->getKeyName();
        $class = get_class($this->related);
        $pivotCols = $this->pivotColumns ? ', ' . implode(', ', array_map(
            fn($c) => "{$this->pivotTable}.{$c} as pivot_{$c}",
            $this->pivotColumns
        )) : '';

        $sql = "SELECT {$table}.*{$pivotCols} FROM {$table} "
            . "INNER JOIN {$this->pivotTable} ON {$this->pivotTable}.{$this->relatedPivotKey} = {$table}.{$relatedKey} "
            . "WHERE {$this->pivotTable}.{$this->foreignPivotKey} = ?";

        $rows = $this->connection->select($sql, [$this->parentKeyValue]);

        return $this->hydrateModels($rows, $class);
    }

    public function eagerLoad(Collection $models, ?callable $constraint): Collection
    {
        $parentKey = $models->isEmpty() ? 'id' : $models->first()->getKeyName();
        $keys = $models->pluck($parentKey)->filter()->unique()->toArray();
        if (empty($keys)) {
            return new Collection();
        }

        $table = $this->related->getTableName();
        $relatedKey = $this->related->getKeyName();
        $class = get_class($this->related);
        $placeholders = implode(',', array_fill(0, count($keys), '?'));

        $sql = "SELECT {$table}.*, {$this->pivotTable}.{$this->foreignPivotKey} as _pivot_parent "
            . "FROM {$table} "
            . "INNER JOIN {$this->pivotTable} ON {$this->pivotTable}.{$this->relatedPivotKey} = {$table}.{$relatedKey} "
            . "WHERE {$this->pivotTable}.{$this->foreignPivotKey} IN ({$placeholders})";

        $rows = $this->connection->select($sql, $keys);
        return $this->hydrateModels($rows, $class);
    }

    public function match(Collection $models, Collection $results, string $relation): void
    {
        $grouped = [];
        foreach ($results as $result) {
            $key = $result->_pivot_parent ?? null;
            if ($key !== null) {
                $grouped[$key][] = $result;
            }
        }

        $parentKey = $models->isEmpty() ? 'id' : $models->first()->getKeyName();
        foreach ($models as $model) {
            $model->setRelation($relation, new Collection($grouped[$model->{$parentKey}] ?? []));
        }
    }

    public function eagerLoadCount(Collection $models, string $countKey): void
    {
        $parentKey = $models->isEmpty() ? 'id' : $models->first()->getKeyName();
        $ids = $models->pluck($parentKey)->filter()->unique()->toArray();

        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT {$this->pivotTable}.{$this->foreignPivotKey} as _k, COUNT(*) as cnt "
            . "FROM {$this->pivotTable} "
            . "WHERE {$this->pivotTable}.{$this->foreignPivotKey} IN ({$placeholders}) "
            . "GROUP BY {$this->pivotTable}.{$this->foreignPivotKey}";
        $rows = $this->connection->select($sql, $ids);

        $map = [];
        foreach ($rows as $row) {
            $map[$row['_k']] = (int) $row['cnt'];
        }

        foreach ($models as $model) {
            $parentId = $model->{$parentKey};
            $model->setRawAttribute($countKey, $map[$parentId] ?? 0);
        }
    }

    // ─────────────────────── Pivot operations ────────────────────────

    public function attach(int|array $ids, array $pivot = []): void
    {
        $ids = (array) $ids;
        foreach ($ids as $id) {
            $data = array_merge([$this->foreignPivotKey => $this->parentKeyValue, $this->relatedPivotKey => $id], $pivot);
            $this->connection->insert($this->pivotTable, $data);
        }
    }

    public function detach(int|array $ids = null): void
    {
        $sql = "DELETE FROM {$this->pivotTable} WHERE {$this->foreignPivotKey} = ?";
        $bindings = [$this->parentKeyValue];

        if ($ids !== null) {
            $ids = (array) $ids;
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " AND {$this->relatedPivotKey} IN ({$placeholders})";
            $bindings = array_merge($bindings, $ids);
        }

        $this->connection->statement($sql, $bindings);
    }

    public function sync(array $ids): void
    {
        $this->detach();
        $this->attach($ids);
    }

    public function toggle(array $ids): void
    {
        $current = $this->getResults()->pluck($this->related->getKeyName())->toArray();
        $attach = array_diff($ids, $current);
        $detach = array_intersect($current, $ids);
        $this->attach($attach);
        $this->detach($detach);
    }

    private function hydrateModels(array $rows, string $class): Collection
    {
        $models = [];
        foreach ($rows as $row) {
            $model = new $class();
            $model->setRawAttributes($row);
            $model->setOriginal($row);
            $model->setExists(true);
            $models[] = $model;
        }
        return new Collection($models);
    }
}
