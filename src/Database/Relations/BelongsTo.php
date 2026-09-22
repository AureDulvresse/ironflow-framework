<?php

declare(strict_types=1);

namespace Ironflow\Database\Relations;

use Ironflow\Database\ModelQueryBuilder;
use Ironflow\Database\Model;
use Ironflow\Support\Collection;

/**
 * Inverse of HasOne/HasMany: a Comment belongsTo Post.
 */
class BelongsTo extends Relation
{
    public function __construct(
        \Ironflow\Database\Connection $connection,
        Model $related,
        string $foreignKey,   // e.g. post_id (on the child model)
        string $ownerKey,     // e.g. id (on the parent model)
        mixed $foreignKeyValue // the actual value of post_id on this instance
    ) {
        parent::__construct($connection, $related, $foreignKey, $ownerKey, $foreignKeyValue);
    }

    public function getResults(): ?Model
    {
        if ($this->parentKeyValue === null) {
            return null;
        }

        $class = get_class($this->related);
        return (new ModelQueryBuilder($this->connection, $this->related->getTableName(), $class))
            ->where($this->localKey, $this->parentKeyValue)
            ->first();
    }

    public function eagerLoad(Collection $models, ?callable $constraint): Collection
    {
        // For BelongsTo, foreignKey is on the child (models), ownerKey on the parent
        $keys = $models->pluck($this->foreignKey)->filter()->unique()->toArray();
        if (empty($keys)) {
            return new Collection();
        }

        $class = get_class($this->related);
        $qb = (new ModelQueryBuilder($this->connection, $this->related->getTableName(), $class))
            ->whereIn($this->localKey, $keys);

        if ($constraint !== null) {
            $constraint($qb);
        }

        return $qb->get();
    }

    public function match(Collection $models, Collection $results, string $relation): void
    {
        $map = [];
        foreach ($results as $result) {
            $map[$result->{$this->localKey}] = $result;
        }

        foreach ($models as $model) {
            $fk = $model->{$this->foreignKey};
            $model->setRelation($relation, $map[$fk] ?? null);
        }
    }

    /**
     * Overrides the base Relation::eagerLoadCount(), which would query
     * $this->related's own table (the owner side) filtered by $foreignKey —
     * a column that lives on the child table, not the owner table, so the
     * inherited SQL references a nonexistent column. A "count" of a
     * belongsTo is inherently 0 or 1 per model and needs no query: it's
     * just whether the foreign key is set.
     */
    public function eagerLoadCount(Collection $models, string $countKey): void
    {
        foreach ($models as $model) {
            $model->setRawAttribute($countKey, $model->{$this->foreignKey} !== null ? 1 : 0);
        }
    }
}
