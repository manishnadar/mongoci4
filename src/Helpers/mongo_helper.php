<?php

/**
 * MongoCI4 Helper Functions
 *
 * These functions are auto-loaded when you require the package via Composer.
 * They provide convenient shortcuts to the MongoBuilder service.
 *
 * Usage (anywhere in your CI4 app after installing the package):
 *
 *   $users = mongo()->where(['active' => 1])->limit(10)->get('users');
 *   $oid   = object_id('507f1f77bcf86cd799439011');
 */

use MongoCI4\Libraries\MongoBuilder;

if (!function_exists('mongo')) {
    /**
     * Get the shared MongoBuilder service instance.
     *
     * Usage:
     *   mongo()->insert('logs', ['message' => 'hello']);
     *   mongo()->where(['user_id' => $id])->get('orders');
     */
    function mongo(): MongoBuilder
    {
        return service('mongo');
    }
}

if (!function_exists('object_id')) {
    /**
     * Convert a string to a MongoDB ObjectId.
     *
     * Usage:
     *   $oid = object_id('507f1f77bcf86cd799439011');
     *   mongo()->where('_id', $oid)->first('users');
     *
     * Note: MongoBuilder automatically converts raw _id strings,
     * so calling this manually is optional.
     */
    function object_id(string $id): \MongoDB\BSON\ObjectId
    {
        return service('mongo')->toObjectId($id);
    }
}

if (!function_exists('is_object_id')) {
    /**
     * Check if a string is a valid MongoDB ObjectId.
     *
     * Usage:
     *   if (is_object_id($id)) { ... }
     */
    function is_object_id(string $id): bool
    {
        return service('mongo')->isValidObjectId($id);
    }
}

if (!function_exists('mongo_paginate')) {
    /**
     * Paginate a MongoDB collection with optional filters.
     *
     * Usage:
     *   $result = mongo_paginate('users', page: 1, perPage: 15, conditions: ['active' => 1]);
     *
     * Returns:
     *   [
     *     'data'         => [...],
     *     'total'        => 100,
     *     'per_page'     => 15,
     *     'current_page' => 1,
     *     'last_page'    => 7,
     *     'from'         => 1,
     *     'to'           => 15,
     *   ]
     */
    function mongo_paginate(
        string $collection,
        int    $page       = 1,
        int    $perPage    = 10,
        array  $conditions = []
    ): array {
        $builder = service('mongo');

        if (!empty($conditions)) {
            $builder->where($conditions);
        }

        return $builder->paginate($collection, $perPage, $page);
    }
}

if (!function_exists('mongo_raw')) {
    /**
     * Get the raw MongoDB\Collection object for advanced operations.
     *
     * Usage:
     *   $col = mongo_raw('users');
     *   $col->watch([], ['fullDocument' => 'updateLookup']); // change streams
     */
    function mongo_raw(string $collection): \MongoDB\Collection
    {
        return service('mongo')->raw($collection);
    }
}
