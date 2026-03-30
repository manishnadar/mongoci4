<?php

// Compatible with PHP 7.4+

namespace MongoCI4\Libraries;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\Session;
use MongoDB\Model\BSONDocument;
use MongoDB\Model\BSONArray;
use MongoCI4\Config\Mongo;
use Exception;
use InvalidArgumentException;

/**
 * MongoBuilder — MongoDB Query Builder for CodeIgniter 4
 *
 * A fluent, chainable query builder that mirrors CodeIgniter 4's native
 * Query Builder API. Switch from MySQL to MongoDB with minimal code changes.
 *
 * Basic Usage:
 *   $users = service('mongo')
 *       ->select('name, email')
 *       ->where(['status' => 1])
 *       ->orderBy('created_at', 'DESC')
 *       ->limit(10)
 *       ->get('users');
 */
class MongoBuilder
{
    // -------------------------------------------------------------------------
    // Connection
    // -------------------------------------------------------------------------

    protected Client   $client;
    protected Database $database;
    protected Mongo    $config;

    // -------------------------------------------------------------------------
    // Query State (reset after every terminal operation)
    // -------------------------------------------------------------------------

    /** @var array<string, int> MongoDB projection document */
    protected array $selects = [];

    /** @var array<string, mixed> $and conditions */
    protected array $wheres = [];

    /** @var array<array<string, mixed>> $or conditions */
    protected array $orWheres = [];

    /** @var array<string, int> Sort document */
    protected array $orders = [];

    protected ?int $limitVal = null;
    protected int  $skipVal  = 0;

    /** @var array<array<string, mixed>> Aggregation pipeline stages */
    protected array $pipeline = [];

    // -------------------------------------------------------------------------
    // Feature Flags (per-query)
    // -------------------------------------------------------------------------

    protected bool $softDeleteEnabled  = false;
    protected bool $withTrashedFlag    = false;
    protected bool $onlyTrashedFlag    = false;
    protected bool $timestampsEnabled  = false;

    // -------------------------------------------------------------------------
    // Session (for transactions)
    // -------------------------------------------------------------------------

    protected ?Session $session = null;

    // =========================================================================
    // CONSTRUCTOR
    // =========================================================================

    public function __construct(Mongo $config)
    {
        $this->config   = $config;
        $this->client   = new Client(
            $config->uri,
            $config->uriOptions  ?? [],
            $config->driverOptions ?? []
        );
        $this->database           = $this->client->selectDatabase($config->database);
        $this->softDeleteEnabled  = $config->softDelete ?? false;
        $this->timestampsEnabled  = $config->timestamps ?? false;
    }

    // =========================================================================
    // SELECT
    // =========================================================================

    /**
     * Specify fields to return.
     *
     * Usage:
     *   ->select('name, email, age')
     *   ->select(['name', 'email'])
     *   ->select('*')   // all fields (default)
     */
    public function select($fields = '*'): self
    {
        if ($fields === '*') {
            $this->selects = [];
            return $this;
        }

        if (is_string($fields)) {
            $fields = array_map('trim', explode(',', $fields));
        }

        foreach ($fields as $field) {
            $this->selects[trim($field)] = 1;
        }

        return $this;
    }

    /**
     * Exclude specific fields from results.
     *
     * Usage:
     *   ->selectExclude('password, __v')
     */
    public function selectExclude($fields): self
    {
        if (is_string($fields)) {
            $fields = array_map('trim', explode(',', $fields));
        }

        foreach ($fields as $field) {
            $this->selects[trim($field)] = 0;
        }

        return $this;
    }

    // =========================================================================
    // WHERE CONDITIONS
    // =========================================================================

    /**
     * Add AND condition(s).
     *
     * Usage:
     *   ->where('status', 1)
     *   ->where(['status' => 1, 'role' => 'admin'])
     *   ->where('age', ['$gt' => 18])           // raw MongoDB operator
     *   ->where('_id', '507f1f77bcf86cd799439011') // auto-converts to ObjectId
     */
    public function where($key, $value = null): self
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->wheres[$k] = $this->castValue($k, $v);
            }
        } else {
            $this->wheres[$key] = $this->castValue($key, $value);
        }

        return $this;
    }

    /**
     * Add OR condition(s).
     *
     * Usage:
     *   ->orWhere('status', 0)
     *   ->orWhere('role', 'guest')
     */
    public function orWhere($key, $value = null): self
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->orWheres[] = [$k => $this->castValue($k, $v)];
            }
        } else {
            $this->orWheres[] = [$key => $this->castValue($key, $value)];
        }

        return $this;
    }

    /**
     * Field value must be one of the given values ($in).
     *
     * Usage:
     *   ->whereIn('status', [1, 2, 3])
     */
    public function whereIn(string $key, array $values): self
    {
        $values = array_map(fn($v) => $this->castValue($key, $v), $values);
        $this->wheres[$key] = ['$in' => $values];
        return $this;
    }

    /**
     * Field value must NOT be in the given values ($nin).
     *
     * Usage:
     *   ->whereNotIn('role', ['banned', 'deleted'])
     */
    public function whereNotIn(string $key, array $values): self
    {
        $values = array_map(fn($v) => $this->castValue($key, $v), $values);
        $this->wheres[$key] = ['$nin' => $values];
        return $this;
    }

    /**
     * Field value must be between $min and $max (inclusive).
     *
     * Usage:
     *   ->whereBetween('age', 18, 65)
     */
    public function whereBetween(string $key, mixed $min, mixed $max): self
    {
        $this->wheres[$key] = ['$gte' => $min, '$lte' => $max];
        return $this;
    }

    /**
     * Field must be null or not exist.
     *
     * Usage:
     *   ->whereNull('deleted_at')
     */
    public function whereNull(string $key): self
    {
        /** @phpstan-ignore-next-line */
        $this->wheres[$key] = null;
        return $this;
    }

    /**
     * Field must not be null and must exist.
     *
     * Usage:
     *   ->whereNotNull('email')
     */
    public function whereNotNull(string $key): self
    {
        $this->wheres[$key] = ['$ne' => null];
        return $this;
    }

    /**
     * Greater than.
     *
     * Usage:
     *   ->whereGt('age', 18)
     */
    public function whereGt(string $key, mixed $value): self
    {
        $this->wheres[$key] = ['$gt' => $value];
        return $this;
    }

    /**
     * Greater than or equal.
     */
    public function whereGte(string $key, mixed $value): self
    {
        $this->wheres[$key] = ['$gte' => $value];
        return $this;
    }

    /**
     * Less than.
     */
    public function whereLt(string $key, mixed $value): self
    {
        $this->wheres[$key] = ['$lt' => $value];
        return $this;
    }

    /**
     * Less than or equal.
     */
    public function whereLte(string $key, mixed $value): self
    {
        $this->wheres[$key] = ['$lte' => $value];
        return $this;
    }

    /**
     * Inject a raw MongoDB filter document directly.
     *
     * Usage:
     *   ->whereRaw(['$expr' => ['$gt' => ['$balance', '$threshold']]])
     */
    public function whereRaw(array $filter): self
    {
        $this->wheres = array_merge($this->wheres, $filter);
        return $this;
    }

    // =========================================================================
    // LIKE / REGEX
    // =========================================================================

    /**
     * Regex pattern match (case-insensitive by default).
     *
     * @param string $position  'both' | 'before' | 'after'
     *
     * Usage:
     *   ->like('name', 'john')               // LIKE '%john%'
     *   ->like('name', 'john', 'before')     // LIKE '%john'
     *   ->like('name', 'john', 'after')      // LIKE 'john%'
     */
    public function like(string $key, string $value, string $position = 'both', bool $caseSensitive = false): self
    {
        $flags   = $caseSensitive ? '' : 'i';
        $escaped = preg_quote($value, '/');

        if ($position === 'before') {
            $pattern = $escaped . '$';
        } elseif ($position === 'after') {
            $pattern = '^' . $escaped;
        } else {
            $pattern = $escaped;
        }

        $this->wheres[$key] = new Regex($pattern, $flags);
        return $this;
    }

    /**
     * NOT LIKE — excludes documents matching the pattern.
     */
    public function notLike(string $key, string $value, string $position = 'both'): self
    {
        $escaped = preg_quote($value, '/');

        if ($position === 'before') {
            $pattern = $escaped . '$';
        } elseif ($position === 'after') {
            $pattern = '^' . $escaped;
        } else {
            $pattern = $escaped;
        }

        $this->wheres[$key] = ['$not' => new Regex($pattern, 'i')];
        return $this;
    }

    // =========================================================================
    // ORDER, LIMIT, OFFSET
    // =========================================================================

    /**
     * Set sort order.
     *
     * Usage:
     *   ->orderBy('created_at', 'DESC')
     *   ->orderBy('name', 'ASC')
     */
    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $this->orders[$field] = strtoupper($direction) === 'DESC' ? -1 : 1;
        return $this;
    }

    /**
     * Limit results.
     *
     * Usage:
     *   ->limit(10)
     */
    public function limit(int $value): self
    {
        $this->limitVal = $value;
        return $this;
    }

    /**
     * Skip n results (for pagination).
     *
     * Usage:
     *   ->offset(20)
     */
    public function offset(int $value): self
    {
        $this->skipVal = $value;
        return $this;
    }

    // =========================================================================
    // READ OPERATIONS
    // =========================================================================

    /**
     * Execute query and return all matching documents.
     *
     * Usage:
     *   ->where(['active' => 1])->limit(10)->get('users')
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(string $collection): array
    {
        $col     = $this->getCollection($collection);
        $filter  = $this->compileFilter();
        $options = $this->compileOptions();

        $this->log('find', $collection, $filter, $options);

        try {
            $cursor  = $col->find($filter, $options);
            $results = [];

            foreach ($cursor as $doc) {
                $results[] = $this->documentToArray($doc);
            }

            return $results;
        } catch (Exception $e) {
            return $this->handleException($e, []);
        } finally {
            $this->reset();
        }
    }

    /**
     * Return only the first matching document.
     *
     * Usage:
     *   ->where('_id', $id)->first('users')
     *
     * @return array<string, mixed>|null
     */
    public function first(string $collection): ?array
    {
        $col     = $this->getCollection($collection);
        $filter  = $this->compileFilter();
        $options = $this->compileOptions();
        unset($options['limit']);

        $this->log('findOne', $collection, $filter, $options);

        try {
            $doc = $col->findOne($filter, $options);
            return $doc ? $this->documentToArray($doc) : null;
        } catch (Exception $e) {
            return $this->handleException($e, null);
        } finally {
            $this->reset();
        }
    }

    /**
     * Return document count matching current conditions.
     *
     * Usage:
     *   ->where(['active' => 1])->count('users')
     */
    public function count(string $collection): int
    {
        $col    = $this->getCollection($collection);
        $filter = $this->compileFilter();

        $this->log('countDocuments', $collection, $filter);

        try {
            return (int) $col->countDocuments($filter);
        } catch (Exception $e) {
            return $this->handleException($e, 0);
        } finally {
            $this->reset();
        }
    }

    /**
     * Return distinct values for a field.
     *
     * Usage:
     *   ->where(['active' => 1])->distinct('role', 'users')
     *
     * @return array<mixed>
     */
    public function distinct(string $field, string $collection): array
    {
        $col    = $this->getCollection($collection);
        $filter = $this->compileFilter();

        $this->log('distinct', $collection, $filter, ['field' => $field]);

        try {
            return $col->distinct($field, $filter);
        } catch (Exception $e) {
            return $this->handleException($e, []);
        } finally {
            $this->reset();
        }
    }

    /**
     * Paginate results — returns data + pagination metadata.
     *
     * Usage:
     *   ->where(['active' => 1])->paginate('users', perPage: 15, page: 2)
     *
     * Returns:
     *   [
     *     'data'         => [...],
     *     'total'        => 150,
     *     'per_page'     => 15,
     *     'current_page' => 2,
     *     'last_page'    => 10,
     *     'from'         => 16,
     *     'to'           => 30,
     *   ]
     */
    public function paginate(string $collection, int $perPage = 10, int $page = 1): array
    {
        $col     = $this->getCollection($collection);
        $filter  = $this->compileFilter();
        $options = $this->compileOptions();

        $options['limit'] = $perPage;
        $options['skip']  = ($page - 1) * $perPage;

        $this->log('paginate', $collection, $filter, $options);

        try {
            $total  = (int) $col->countDocuments($filter);
            $cursor = $col->find($filter, $options);
            $data   = [];

            foreach ($cursor as $doc) {
                $data[] = $this->documentToArray($doc);
            }

            $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

            return [
                'data'         => $data,
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => $lastPage,
                'from'         => $total > 0 ? ($page - 1) * $perPage + 1 : 0,
                'to'           => min($page * $perPage, $total),
            ];
        } catch (Exception $e) {
            return $this->handleException($e, []);
        } finally {
            $this->reset();
        }
    }

    // =========================================================================
    // INSERT OPERATIONS
    // =========================================================================

    /**
     * Insert a single document.
     *
     * Usage:
     *   ->insert('users', ['name' => 'John', 'email' => 'john@example.com'])
     *
     * Returns:
     *   ['success' => true, 'inserted_id' => '...', 'inserted_count' => 1]
     */
    public function insert(string $collection, array $data): array
    {
        $col  = $this->getCollection($collection);
        $data = $this->applyTimestampsOnInsert($data);

        $this->log('insertOne', $collection, $data);

        try {
            $options = $this->buildSessionOptions();
            $result  = $col->insertOne($data, $options);

            return [
                'success'        => true,
                'inserted_id'    => (string) $result->getInsertedId(),
                'inserted_count' => $result->getInsertedCount(),
            ];
        } catch (Exception $e) {
            return $this->handleException($e, ['success' => false, 'error' => $e->getMessage()]);
        } finally {
            $this->reset();
        }
    }

    /**
     * Insert multiple documents at once.
     *
     * Usage:
     *   ->insertBatch('users', [
     *       ['name' => 'Alice'],
     *       ['name' => 'Bob'],
     *   ])
     *
     * Returns:
     *   ['success' => true, 'inserted_count' => 2, 'inserted_ids' => [...]]
     */
    public function insertBatch(string $collection, array $rows): array
    {
        $col  = $this->getCollection($collection);
        $rows = array_map(fn($row) => $this->applyTimestampsOnInsert($row), $rows);

        $this->log('insertMany', $collection, $rows);

        try {
            $options = $this->buildSessionOptions();
            $result  = $col->insertMany($rows, $options);

            return [
                'success'        => true,
                'inserted_count' => $result->getInsertedCount(),
                'inserted_ids'   => array_map('strval', $result->getInsertedIds()),
            ];
        } catch (Exception $e) {
            return $this->handleException($e, ['success' => false, 'error' => $e->getMessage()]);
        } finally {
            $this->reset();
        }
    }

    // =========================================================================
    // UPDATE OPERATIONS
    // =========================================================================

    /**
     * Update ALL documents matching current where conditions.
     *
     * Usage:
     *   // Simple set:
     *   ->where('status', 0)->update('users', ['status' => 1])
     *
     *   // Raw MongoDB operators:
     *   ->where('_id', $id)->update('users', ['$inc' => ['points' => 10]])
     *
     * Returns:
     *   ['success' => true, 'matched_count' => N, 'modified_count' => N]
     */
    public function update(string $collection, array $data): array
    {
        $col    = $this->getCollection($collection);
        $filter = $this->compileFilter();
        $update = $this->buildUpdateDoc($data);

        $this->log('updateMany', $collection, $filter, $update);

        try {
            $options = $this->buildSessionOptions();
            $result  = $col->updateMany($filter, $update, $options);

            return [
                'success'        => true,
                'matched_count'  => $result->getMatchedCount(),
                'modified_count' => $result->getModifiedCount(),
            ];
        } catch (Exception $e) {
            return $this->handleException($e, ['success' => false, 'error' => $e->getMessage()]);
        } finally {
            $this->reset();
        }
    }

    /**
     * Update only the FIRST document matching current where conditions.
     *
     * Usage:
     *   ->where('_id', $id)->updateOne('users', ['name' => 'Updated'])
     */
    public function updateOne(string $collection, array $data): array
    {
        $col    = $this->getCollection($collection);
        $filter = $this->compileFilter();
        $update = $this->buildUpdateDoc($data);

        $this->log('updateOne', $collection, $filter, $update);

        try {
            $options = $this->buildSessionOptions();
            $result  = $col->updateOne($filter, $update, $options);

            return [
                'success'        => true,
                'matched_count'  => $result->getMatchedCount(),
                'modified_count' => $result->getModifiedCount(),
            ];
        } catch (Exception $e) {
            return $this->handleException($e, ['success' => false, 'error' => $e->getMessage()]);
        } finally {
            $this->reset();
        }
    }

    /**
     * Update if exists, insert if not (upsert).
     *
     * Usage:
     *   ->where('email', 'john@example.com')
     *   ->upsert('users', ['name' => 'John', 'email' => 'john@example.com'])
     */
    public function upsert(string $collection, array $data): array
    {
        $col    = $this->getCollection($collection);
        $filter = $this->compileFilter();

        if ($this->timestampsEnabled) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $update = [
            '$set'         => $data,
            '$setOnInsert' => ['created_at' => date('Y-m-d H:i:s')],
        ];

        $this->log('upsert', $collection, $filter, $update);

        try {
            $options = array_merge(['upsert' => true], $this->buildSessionOptions());
            $result  = $col->updateOne($filter, $update, $options);

            return [
                'success'        => true,
                'matched_count'  => $result->getMatchedCount(),
                'modified_count' => $result->getModifiedCount(),
                'upserted_id'    => $result->getUpsertedId()
                    ? (string) $result->getUpsertedId()
                    : null,
            ];
        } catch (Exception $e) {
            return $this->handleException($e, ['success' => false, 'error' => $e->getMessage()]);
        } finally {
            $this->reset();
        }
    }

    // =========================================================================
    // DELETE OPERATIONS
    // =========================================================================

    /**
     * Delete ALL documents matching current where conditions.
     * If soft delete is enabled, sets 'deleted_at' instead.
     *
     * Usage:
     *   ->where('status', 0)->delete('users')
     */
    public function delete(string $collection): array
    {
        // Soft delete: update deleted_at instead of removing
        if ($this->softDeleteEnabled && !$this->withTrashedFlag) {
            return $this->update($collection, ['deleted_at' => date('Y-m-d H:i:s')]);
        }

        $col    = $this->getCollection($collection);
        $filter = $this->compileFilter();

        $this->log('deleteMany', $collection, $filter);

        try {
            $options = $this->buildSessionOptions();
            $result  = $col->deleteMany($filter, $options);

            return [
                'success'       => true,
                'deleted_count' => $result->getDeletedCount(),
            ];
        } catch (Exception $e) {
            return $this->handleException($e, ['success' => false, 'error' => $e->getMessage()]);
        } finally {
            $this->reset();
        }
    }

    /**
     * Delete only the FIRST matching document.
     * If soft delete is enabled, sets 'deleted_at' on that document.
     *
     * Usage:
     *   ->where('_id', $id)->deleteOne('users')
     */
    public function deleteOne(string $collection): array
    {
        if ($this->softDeleteEnabled && !$this->withTrashedFlag) {
            return $this->updateOne($collection, ['deleted_at' => date('Y-m-d H:i:s')]);
        }

        $col    = $this->getCollection($collection);
        $filter = $this->compileFilter();

        $this->log('deleteOne', $collection, $filter);

        try {
            $options = $this->buildSessionOptions();
            $result  = $col->deleteOne($filter, $options);

            return [
                'success'       => true,
                'deleted_count' => $result->getDeletedCount(),
            ];
        } catch (Exception $e) {
            return $this->handleException($e, ['success' => false, 'error' => $e->getMessage()]);
        } finally {
            $this->reset();
        }
    }

    /**
     * Restore a soft-deleted document by removing 'deleted_at'.
     *
     * Usage:
     *   ->where('_id', $id)->restore('users')
     */
    public function restore(string $collection): array
    {
        return $this->withTrashed()->update($collection, ['$unset' => ['deleted_at' => '']]);
    }

    // =========================================================================
    // SOFT DELETE CONTROL
    // =========================================================================

    /**
     * Enable soft delete for this query (overrides config).
     */
    public function withSoftDelete(): self
    {
        $this->softDeleteEnabled = true;
        return $this;
    }

    /**
     * Include soft-deleted records in results.
     *
     * Usage:
     *   ->withTrashed()->get('users')
     */
    public function withTrashed(): self
    {
        $this->withTrashedFlag = true;
        return $this;
    }

    /**
     * Return ONLY soft-deleted records.
     *
     * Usage:
     *   ->onlyTrashed()->get('users')
     */
    public function onlyTrashed(): self
    {
        $this->softDeleteEnabled = true;
        $this->onlyTrashedFlag   = true;
        return $this;
    }

    // =========================================================================
    // AGGREGATION PIPELINE
    // =========================================================================

    /**
     * Run an aggregation pipeline.
     *
     * Usage:
     *   // Pass a full pipeline:
     *   ->aggregate('orders', [
     *       ['$match'  => ['status' => 'paid']],
     *       ['$group'  => ['_id' => '$user_id', 'total' => ['$sum' => '$amount']]],
     *       ['$sort'   => ['total' => -1]],
     *   ])
     *
     *   // Or use fluent building + aggregate:
     *   ->where(['status' => 'paid'])
     *   ->join('users', 'user_id', '_id', 'user')
     *   ->groupBy('user_id', ['total' => ['$sum' => '$amount']])
     *   ->aggregate('orders')
     *
     * @return array<int, array<string, mixed>>
     */
    public function aggregate(string $collection, array $pipeline = []): array
    {
        $col = $this->getCollection($collection);

        // Merge builder pipeline then caller's extra stages
        $fullPipeline = array_merge($this->pipeline, $pipeline);

        // Prepend $match if where() was used
        $filter = $this->compileFilter(false);
        if (!empty($filter)) {
            array_unshift($fullPipeline, ['$match' => $filter]);
        }

        // Append $sort, $skip, $limit if set
        if (!empty($this->orders)) {
            $fullPipeline[] = ['$sort' => $this->orders];
        }
        if ($this->skipVal > 0) {
            $fullPipeline[] = ['$skip' => $this->skipVal];
        }
        if ($this->limitVal !== null) {
            $fullPipeline[] = ['$limit' => $this->limitVal];
        }

        $this->log('aggregate', $collection, $fullPipeline);

        try {
            $cursor  = $col->aggregate($fullPipeline);
            $results = [];

            foreach ($cursor as $doc) {
                $results[] = $this->documentToArray($doc);
            }

            return $results;
        } catch (Exception $e) {
            return $this->handleException($e, []);
        } finally {
            $this->reset();
        }
    }

    /**
     * Append a raw aggregation stage.
     *
     * Usage:
     *   ->addStage(['$bucket' => [...]])
     *   ->addStage(['$out'    => 'result_collection'])
     */
    public function addStage(array $stage): self
    {
        $this->pipeline[] = $stage;
        return $this;
    }

    // =========================================================================
    // JOIN / LOOKUP (Aggregation-based)
    // =========================================================================

    /**
     * Add a $lookup stage — equivalent to SQL JOIN.
     *
     * Usage:
     *   ->join('orders', 'orders.user_id', '_id', 'user_orders')
     *   ->aggregate('users')
     *
     * @param string $fromCollection  The collection to join
     * @param string $localField      Field in the current collection
     * @param string $foreignField    Field in the joined collection
     * @param string $as             Output array field name
     */
    public function join(string $fromCollection, string $localField, string $foreignField, string $as): self
    {
        return $this->lookup([
            'from'         => $fromCollection,
            'localField'   => $localField,
            'foreignField' => $foreignField,
            'as'           => $as,
        ]);
    }

    /**
     * Add a raw $lookup stage (for advanced pipelines with 'let' and 'pipeline').
     *
     * Usage:
     *   ->lookup([
     *       'from'         => 'orders',
     *       'localField'   => 'user_id',
     *       'foreignField' => '_id',
     *       'as'           => 'orders',
     *   ])
     */
    public function lookup(array $config): self
    {
        $this->pipeline[] = ['$lookup' => $config];
        return $this;
    }

    /**
     * Unwind an array field into separate documents.
     *
     * Usage:
     *   ->join('orders', 'user_id', '_id', 'orders')
     *   ->unwind('orders')
     *   ->aggregate('users')
     *
     * @param bool $preserveNull Keep documents where the field is null/missing
     */
    public function unwind(string $field, bool $preserveNull = false): self
    {
        $fieldPath = '$' . ltrim($field, '$');
        $this->pipeline[] = [
            '$unwind' => [
                'path'                       => $fieldPath,
                'preserveNullAndEmptyArrays' => $preserveNull,
            ],
        ];

        return $this;
    }

    /**
     * Group documents.
     *
     * Usage:
     *   ->groupBy('category', [
     *       'total_sales' => ['$sum' => '$amount'],
     *       'avg_price'   => ['$avg' => '$price'],
     *       'count'       => ['$sum' => 1],
     *   ])
     */
    public function groupBy(string $id, array $accumulators = []): self
    {
        $groupId = (strlen($id) > 0 && $id[0] === '$') ? $id : '$' . $id;

        $this->pipeline[] = ['$group' => array_merge(
            ['_id' => $groupId],
            $accumulators
        )];

        return $this;
    }

    /**
     * Add a $project stage.
     *
     * Usage:
     *   ->project(['fullName' => ['$concat' => ['$first_name', ' ', '$last_name']], 'email' => 1])
     */
    public function project(array $fields): self
    {
        $this->pipeline[] = ['$project' => $fields];
        return $this;
    }

    /**
     * Add a $match stage to the pipeline.
     *
     * Usage:
     *   ->match(['status' => 'active'])
     */
    public function match(array $filter): self
    {
        $this->pipeline[] = ['$match' => $filter];
        return $this;
    }

    // =========================================================================
    // INDEX MANAGEMENT
    // =========================================================================

    /**
     * Create an index on a collection.
     *
     * Usage:
     *   ->createIndex('users', ['email' => 1], ['unique' => true])
     *   ->createIndex('posts', ['title' => 'text'])              // text index
     *   ->createIndex('locations', ['coords' => '2dsphere'])     // geo index
     */
    public function createIndex(string $collection, array $keys, array $options = []): string
    {
        try {
            return $this->getCollection($collection)->createIndex($keys, $options);
        } catch (Exception $e) {
            return $this->handleException($e, '');
        }
    }

    /**
     * Create multiple indexes at once.
     *
     * Usage:
     *   ->createIndexes('users', [
     *       ['key' => ['email' => 1], 'unique' => true],
     *       ['key' => ['created_at' => -1]],
     *   ])
     */
    public function createIndexes(string $collection, array $indexes): array
    {
        try {
            return $this->getCollection($collection)->createIndexes($indexes);
        } catch (Exception $e) {
            return $this->handleException($e, []);
        }
    }

    /**
     * Drop a named index.
     *
     * Usage:
     *   ->dropIndex('users', 'email_1')
     */
    public function dropIndex(string $collection, string $indexName): void
    {
        try {
            $this->getCollection($collection)->dropIndex($indexName);
        } catch (Exception $e) {
            $this->handleException($e, null);
        }
    }

    /**
     * List all indexes on a collection.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listIndexes(string $collection): array
    {
        try {
            $indexes = [];
            foreach ($this->getCollection($collection)->listIndexes() as $index) {
                $indexes[] = iterator_to_array($index);
            }
            return $indexes;
        } catch (Exception $e) {
            return $this->handleException($e, []);
        }
    }

    // =========================================================================
    // TRANSACTIONS (requires MongoDB replica set)
    // =========================================================================

    /**
     * Start a new client session.
     */
    public function startSession(): Session
    {
        $this->session = $this->client->startSession();
        return $this->session;
    }

    /**
     * Start a transaction on the current session.
     * NOTE: Requires a MongoDB replica set or sharded cluster.
     *
     * Usage:
     *   $mongo->startTransaction();
     *   try {
     *       $mongo->insert('orders', $orderData);
     *       $mongo->where('_id', $userId)->update('users', ['$inc' => ['balance' => -$amount]]);
     *       $mongo->commitTransaction();
     *   } catch (Exception $e) {
     *       $mongo->abortTransaction();
     *   } finally {
     *       $mongo->endSession();
     *   }
     */
    public function startTransaction(array $options = []): void
    {
        if (!$this->session) {
            $this->startSession();
        }

        $this->session->startTransaction($options);
    }

    /**
     * Commit the active transaction.
     */
    public function commitTransaction(): void
    {
        if ($this->session) {
            $this->session->commitTransaction();
        }
    }

    /**
     * Abort/rollback the active transaction.
     */
    public function abortTransaction(): void
    {
        if ($this->session) {
            $this->session->abortTransaction();
        }
    }

    /**
     * End and destroy the session.
     */
    public function endSession(): void
    {
        if ($this->session) {
            $this->session->endSession();
            $this->session = null;
        }
    }

    /**
     * Inject an existing session.
     */
    public function withSession(Session $session): self
    {
        $this->session = $session;
        return $this;
    }

    // =========================================================================
    // RAW ACCESS
    // =========================================================================

    /**
     * Get the raw MongoDB\Collection object for advanced operations.
     *
     * Usage:
     *   $col = service('mongo')->raw('users');
     *   $col->watch();  // change streams
     */
    public function raw(string $collection): Collection
    {
        return $this->getCollection($collection);
    }

    /**
     * Get the underlying MongoDB\Database object.
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }

    /**
     * Get the underlying MongoDB\Client object.
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Switch to a different database on the fly.
     *
     * Usage:
     *   service('mongo')->db('analytics')->get('events')
     */
    public function db(string $databaseName): self
    {
        $this->database = $this->client->selectDatabase($databaseName);
        return $this;
    }

    // =========================================================================
    // OBJECTID HELPERS
    // =========================================================================

    /**
     * Convert a string ID to MongoDB ObjectId.
     *
     * Usage:
     *   $oid = service('mongo')->toObjectId('507f1f77bcf86cd799439011');
     */
    public function toObjectId(string $id): ObjectId
    {
        if (!$this->isValidObjectId($id)) {
            throw new InvalidArgumentException("Invalid ObjectId: '{$id}'");
        }

        return new ObjectId($id);
    }

    /**
     * Check if a string is a valid 24-char hex ObjectId.
     */
    public function isValidObjectId(string $id): bool
    {
        return (bool) preg_match('/^[a-f\d]{24}$/i', $id);
    }

    /**
     * Convert current Unix timestamp to MongoDB UTCDateTime.
     */
    public function toDate(?int $timestamp = null): UTCDateTime
    {
        return new UTCDateTime(($timestamp ?? time()) * 1000);
    }

    // =========================================================================
    // PRIVATE / PROTECTED HELPERS
    // =========================================================================

    protected function getCollection(string $name): Collection
    {
        return $this->database->selectCollection($name);
    }


    /**
     * Compile all where conditions into a single MongoDB filter document.
     *
     * @param bool $applySoftDelete Whether to inject soft-delete exclusion
     */
    protected function compileFilter(bool $applySoftDelete = true): array
    {
        $filter = [];

        // Soft delete exclusion
        if ($applySoftDelete && $this->softDeleteEnabled) {
            if ($this->onlyTrashedFlag) {
                $filter['deleted_at'] = ['$ne' => null, '$exists' => true];
            } elseif (!$this->withTrashedFlag) {
                // Exclude records where deleted_at exists and is not null
                $filter['$or'] = [
                    ['deleted_at' => null],
                    ['deleted_at' => ['$exists' => false]],
                ];
            }
        }

        // AND conditions
        if (!empty($this->wheres)) {
            foreach ($this->wheres as $k => $v) {
                $filter[$k] = $v;
            }
        }

        // OR conditions
        if (!empty($this->orWheres)) {
            $existingAndConditions = $filter;
            $filter = [];

            $orParts = $this->orWheres;
            if (!empty($existingAndConditions)) {
                $orParts = array_merge([['$and' => [new \ArrayObject($existingAndConditions)]]], $orParts);
            }

            $filter['$or'] = $orParts;
        }

        return $filter;
    }

    /**
     * Compile find() options from current state.
     */
    protected function compileOptions(): array
    {
        $options = [];

        if (!empty($this->selects)) {
            $options['projection'] = $this->selects;
        }
        if (!empty($this->orders)) {
            $options['sort'] = $this->orders;
        }
        if ($this->limitVal !== null) {
            $options['limit'] = $this->limitVal;
        }
        if ($this->skipVal > 0) {
            $options['skip'] = $this->skipVal;
        }
        if ($this->session) {
            $options['session'] = $this->session;
        }

        return $options;
    }

    /**
     * Build the $set update document, supporting raw MongoDB operators.
     */
    protected function buildUpdateDoc(array $data): array
    {
        // Check if caller passed raw MongoDB operators like $set, $inc, $push...
        $hasOperator = false;
        foreach (array_keys($data) as $key) {
            if (strlen((string)$key) > 0 && ((string)$key)[0] === '$') {
                $hasOperator = true;
                break;
            }
        }

        if ($hasOperator) {
            // Inject timestamp into $set sub-document if needed
            if ($this->timestampsEnabled && isset($data['$set'])) {
                $data['$set']['updated_at'] = $data['$set']['updated_at'] ?? date('Y-m-d H:i:s');
            }
            return $data;
        }

        // Plain key-value: wrap in $set
        if ($this->timestampsEnabled) {
            $data['updated_at'] = $data['updated_at'] ?? date('Y-m-d H:i:s');
        }

        return ['$set' => $data];
    }

    /**
     * Apply created_at / updated_at on insert if timestamps enabled.
     */
    protected function applyTimestampsOnInsert(array $data): array
    {
        if ($this->timestampsEnabled) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] = $data['created_at'] ?? $now;
            $data['updated_at'] = $data['updated_at'] ?? $now;
        }

        return $data;
    }

    /**
     * Auto-cast value types (e.g. string _id → ObjectId).
     */
    protected function castValue(string $key, $value)
    {
        // Auto-cast _id strings to ObjectId
        if ($key === '_id' && is_string($value) && $this->isValidObjectId($value)) {
            return new ObjectId($value);
        }

        return $value;
    }

    /**
     * Build session options array for write operations.
     */
    protected function buildSessionOptions(): array
    {
        return $this->session ? ['session' => $this->session] : [];
    }

    /**
     * Recursively convert a BSON document/array to a plain PHP array.
     */
    protected function documentToArray(mixed $doc): array
    {
        $arr = (array) $doc;

        foreach ($arr as $key => $value) {
            if ($value instanceof ObjectId) {
                $arr[$key] = (string) $value;
            } elseif ($value instanceof BSONDocument || $value instanceof BSONArray) {
                $arr[$key] = $this->documentToArray($value);
            } elseif ($value instanceof UTCDateTime) {
                $arr[$key] = $value->toDateTime()->format('Y-m-d H:i:s');
            } elseif ($value instanceof Regex) {
                $arr[$key] = (string) $value;
            }
        }

        return $arr;
    }

    /**
     * Log query to CI4 logger when debug mode is on.
     */
    protected function log(string $operation, string $collection, mixed ...$data): void
    {
        if (!($this->config->debug ?? false)) {
            return;
        }

        $payload = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        log_message('debug', "[MongoCI4] {$operation} on `{$collection}`: {$payload}");
    }

    /**
     * Handle exceptions — log always, rethrow if throwExceptions is true.
     */
    protected function handleException(Exception $e, $default)
    {
        log_message('error', '[MongoCI4] ' . $e->getMessage() . "\n" . $e->getTraceAsString());

        if ($this->config->throwExceptions ?? true) {
            throw $e;
        }

        return $default;
    }

    /**
     * Reset query state after every terminal operation.
     * Called automatically — you should not need to call this manually.
     */
    public function reset(): self
    {
        $this->selects        = [];
        $this->wheres         = [];
        $this->orWheres       = [];
        $this->orders         = [];
        $this->limitVal       = null;
        $this->skipVal        = 0;
        $this->pipeline       = [];
        $this->withTrashedFlag  = false;
        $this->onlyTrashedFlag  = false;

        // Re-apply global config defaults for per-query flags
        $this->softDeleteEnabled = $this->config->softDelete  ?? false;
        $this->timestampsEnabled = $this->config->timestamps  ?? false;

        return $this;
    }
}
