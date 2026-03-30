<?php

// Compatible with PHP 7.4+

namespace MongoCI4\Libraries;

use MongoCI4\Config\Mongo;

/**
 * MongoModel — Base Model for CodeIgniter 4
 *
 * Extend this class instead of CodeIgniter's Model to get MongoDB
 * support with a familiar CI4 Model API.
 *
 * Usage:
 *
 *   namespace App\Models;
 *
 *   use MongoCI4\Libraries\MongoModel;
 *
 *   class UserModel extends MongoModel
 *   {
 *       protected string $collection   = 'users';
 *       protected bool   $softDelete   = true;
 *       protected bool   $timestamps   = true;
 *
 *       // Optional: fields allowed for mass-assignment
 *       protected array $fillable = ['name', 'email', 'role', 'status'];
 *   }
 *
 *   // Then use it:
 *   $model = new UserModel();
 *
 *   $user  = $model->find('507f1f77bcf86cd799439011');
 *   $users = $model->findAll(['active' => 1]);
 *   $id    = $model->save(['name' => 'Alice', 'email' => 'alice@example.com']);
 *   $model->update('507f...', ['name' => 'Alice Smith']);
 *   $model->delete('507f...');
 */
class MongoModel
{
    // -------------------------------------------------------------------------
    // Model Properties (override in child classes)
    // -------------------------------------------------------------------------

    /** MongoDB collection name */
    protected string $collection = '';

    /** Allow soft deletes (sets deleted_at instead of removing) */
    protected bool $softDelete = false;

    /** Auto-manage created_at / updated_at timestamps */
    protected bool $timestamps = true;

    /**
     * Fields allowed for mass-assignment.
     * Empty array = allow all fields.
     */
    protected array $fillable = [];

    /**
     * Fields NEVER returned in results.
     * Useful for hiding password hashes, tokens, etc.
     */
    protected array $hidden = [];

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    protected MongoBuilder $builder;

    public function __construct(?Mongo $config = null)
    {
        $config ??= config(\MongoCI4\Config\Mongo::class);

        // Clone builder config with model-level overrides
        $config->softDelete = $this->softDelete;
        $config->timestamps = $this->timestamps;

        $this->builder = new MongoBuilder($config);
    }

    // =========================================================================
    // FIND METHODS
    // =========================================================================

    /**
     * Find a document by its _id.
     *
     * Usage:
     *   $user = $model->find('507f1f77bcf86cd799439011');
     *
     * @return array<string,mixed>|null
     */
    public function find(string $id): ?array
    {
        $doc = $this->builder->where('_id', $id)->first($this->collection);
        return $doc ? $this->applyHidden($doc) : null;
    }

    /**
     * Find all documents, optionally filtered.
     *
     * Usage:
     *   $users = $model->findAll(['role' => 'admin']);
     *
     * @param array<string,mixed> $conditions
     * @return array<int, array<string,mixed>>
     */
    public function findAll(array $conditions = []): array
    {
        $builder = !empty($conditions)
            ? $this->builder->where($conditions)
            : $this->builder;

        $results = $builder->get($this->collection);

        return array_map(fn($doc) => $this->applyHidden($doc), $results);
    }

    /**
     * Find the first document matching conditions.
     *
     * Usage:
     *   $user = $model->findOne(['email' => 'john@example.com']);
     *
     * @param array<string,mixed> $conditions
     * @return array<string,mixed>|null
     */
    public function findOne(array $conditions = []): ?array
    {
        $doc = $this->builder->where($conditions)->first($this->collection);
        return $doc ? $this->applyHidden($doc) : null;
    }

    /**
     * Count documents, optionally filtered.
     *
     * Usage:
     *   $total = $model->countAll(['active' => 1]);
     */
    public function countAll(array $conditions = []): int
    {
        return $this->builder->where($conditions)->count($this->collection);
    }

    /**
     * Paginate documents.
     *
     * Usage:
     *   $result = $model->paginate(page: 1, perPage: 15);
     */
    public function paginate(int $page = 1, int $perPage = 10, array $conditions = []): array
    {
        if (!empty($conditions)) {
            $this->builder->where($conditions);
        }

        $result = $this->builder->paginate($this->collection, $perPage, $page);
        $result['data'] = array_map(fn($doc) => $this->applyHidden($doc), $result['data']);

        return $result;
    }

    // =========================================================================
    // WRITE METHODS
    // =========================================================================

    /**
     * Insert a new document. Applies fillable filtering.
     *
     * Usage:
     *   $insertedId = $model->save(['name' => 'Bob', 'email' => 'bob@example.com']);
     *
     * @return string|null Inserted ObjectId as string, or null on failure
     */
    public function save(array $data): ?string
    {
        $data   = $this->filterFillable($data);
        $result = $this->builder->insert($this->collection, $data);

        return ($result['success'] ?? false) ? $result['inserted_id'] : null;
    }

    /**
     * Insert multiple documents at once.
     *
     * Usage:
     *   $model->saveMany([
     *       ['name' => 'Alice'],
     *       ['name' => 'Bob'],
     *   ]);
     */
    public function saveMany(array $rows): array
    {
        $rows = array_map(fn($row) => $this->filterFillable($row), $rows);
        return $this->builder->insertBatch($this->collection, $rows);
    }

    /**
     * Update document(s) by _id or conditions.
     *
     * Usage:
     *   // Update by ID:
     *   $model->update('507f...', ['name' => 'Updated']);
     *
     *   // Update by condition (updates ALL matching):
     *   $model->update(['status' => 0], ['status' => 1]);
     *
     * @param string|array<string,mixed> $idOrConditions
     * @param array<string,mixed>        $data
     */
    public function update($idOrConditions, array $data): array
    {
        $data = $this->filterFillable($data);

        if (is_string($idOrConditions)) {
            return $this->builder
                ->where('_id', $idOrConditions)
                ->updateOne($this->collection, $data);
        }

        return $this->builder
            ->where($idOrConditions)
            ->update($this->collection, $data);
    }

    /**
     * Delete a document by _id or conditions.
     *
     * Usage:
     *   $model->delete('507f...');
     *   $model->delete(['status' => 0]);
     *
     * @param string|array<string,mixed> $idOrConditions
     */
    public function delete($idOrConditions): array
    {
        if (is_string($idOrConditions)) {
            return $this->builder
                ->where('_id', $idOrConditions)
                ->deleteOne($this->collection);
        }

        return $this->builder
            ->where($idOrConditions)
            ->delete($this->collection);
    }

    /**
     * Restore a soft-deleted document by _id.
     *
     * Usage:
     *   $model->restore('507f...');
     */
    public function restore(string $id): array
    {
        return $this->builder->where('_id', $id)->restore($this->collection);
    }

    // =========================================================================
    // FLUENT QUERY PASS-THROUGH
    // =========================================================================

    /**
     * Start a fluent query chain on the model's collection.
     * Returns the builder; call ->get(), ->first(), etc. to fetch.
     *
     * Usage:
     *   $users = $model->query()
     *       ->where(['active' => 1])
     *       ->orderBy('name', 'ASC')
     *       ->limit(20)
     *       ->get('users');
     */
    public function query(): MongoBuilder
    {
        return $this->builder;
    }

    /**
     * Proxy any MongoBuilder method call directly on the model.
     *
     * Usage:
     *   $users = $model->where(['active' => 1])->orderBy('name')->get('users');
     */
    public function __call(string $method, array $args)
    {
        if (method_exists($this->builder, $method)) {
            $result = $this->builder->$method(...$args);

            // If the result is the builder itself (chaining), return $this for model-level chaining
            if ($result instanceof MongoBuilder) {
                return $this;
            }

            return $result;
        }

        throw new \BadMethodCallException("Method [{$method}] does not exist on MongoModel.");
    }

    // =========================================================================
    // AGGREGATE HELPERS
    // =========================================================================

    /**
     * Run aggregation on this model's collection.
     *
     * Usage:
     *   $model->aggregate([
     *       ['$match'  => ['active' => 1]],
     *       ['$group'  => ['_id' => '$role', 'count' => ['$sum' => 1]]],
     *   ]);
     */
    public function aggregate(array $pipeline): array
    {
        return $this->builder->aggregate($this->collection, $pipeline);
    }

    // =========================================================================
    // SOFT DELETE SHORTCUTS
    // =========================================================================

    /**
     * Get all records including soft-deleted ones.
     */
    public function withTrashed(): self
    {
        $this->builder->withTrashed();
        return $this;
    }

    /**
     * Get ONLY soft-deleted records.
     */
    public function onlyTrashed(): self
    {
        $this->builder->onlyTrashed();
        return $this;
    }

    // =========================================================================
    // PROTECTED HELPERS
    // =========================================================================

    /**
     * Filter data to only allowed fields (if $fillable is defined).
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Remove $hidden fields from a document array.
     *
     * @param array<string,mixed> $doc
     * @return array<string,mixed>
     */
    protected function applyHidden(array $doc): array
    {
        if (empty($this->hidden)) {
            return $doc;
        }

        return array_diff_key($doc, array_flip($this->hidden));
    }

    /**
     * Get the collection name used by this model.
     */
    public function getCollection(): string
    {
        return $this->collection;
    }
}
