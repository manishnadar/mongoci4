<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\OrderModel;
use CodeIgniter\RESTful\ResourceController;

/**
 * ExampleController
 *
 * Demonstrates how to use the MongoCI4 library in a CodeIgniter 4 controller.
 * All examples use both the service() helper and the MongoModel approach.
 */
class ExampleController extends ResourceController
{
    // =========================================================================
    // 1. BASIC CRUD — Using service('mongo') directly
    // =========================================================================

    /**
     * CREATE — Insert a document
     *
     * POST /example/create
     */
    public function create(): void
    {
        $mongo = service('mongo');

        // ---- Simple insert ----
        $result = $mongo->insert('users', [
            'name'   => 'John Doe',
            'email'  => 'john@example.com',
            'role'   => 'user',
            'status' => 1,
        ]);

        // $result = ['success' => true, 'inserted_id' => '507f...', 'inserted_count' => 1]
        echo json_encode($result);

        // ---- Batch insert ----
        $batchResult = $mongo->insertBatch('users', [
            ['name' => 'Alice', 'email' => 'alice@example.com', 'role' => 'admin'],
            ['name' => 'Bob',   'email' => 'bob@example.com',   'role' => 'user'],
            ['name' => 'Carol', 'email' => 'carol@example.com', 'role' => 'user'],
        ]);
    }

    /**
     * READ — Find documents
     *
     * GET /example/read
     */
    public function read(): void
    {
        $mongo = service('mongo');

        // ---- Get all users ----
        $allUsers = $mongo->get('users');

        // ---- Get with conditions ----
        $activeUsers = $mongo->where(['status' => 1, 'role' => 'user'])->get('users');

        // ---- Get with select (projection) ----
        $names = $mongo
            ->select('name, email')
            ->where(['status' => 1])
            ->orderBy('name', 'ASC')
            ->limit(10)
            ->get('users');

        // ---- Get single document ----
        $user = $mongo->where('_id', '507f1f77bcf86cd799439011')->first('users');
        // _id string is auto-converted to ObjectId — no manual casting needed!

        // ---- Count ----
        $count = $mongo->where(['role' => 'admin'])->count('users');

        // ---- Distinct values ----
        $roles = $mongo->distinct('role', 'users');
        // Returns: ['admin', 'user', 'guest']

        echo json_encode(compact('allUsers', 'activeUsers', 'names', 'user', 'count', 'roles'));
    }

    /**
     * UPDATE — Modify documents
     *
     * PUT /example/update
     */
    public function update(mixed $id = null): void
    {
        $mongo = service('mongo');

        // ---- Update a single document by ID ----
        $result = $mongo
            ->where('_id', '507f1f77bcf86cd799439011')
            ->updateOne('users', ['name' => 'John Updated', 'status' => 2]);

        // ---- Update ALL matching documents ----
        $result = $mongo
            ->where(['role' => 'user'])
            ->update('users', ['verified' => true]);

        // ---- Using raw MongoDB operators ----
        $mongo
            ->where('_id', '507f1f77bcf86cd799439011')
            ->updateOne('users', [
                '$inc'  => ['login_count' => 1],
                '$set'  => ['last_login'  => date('Y-m-d H:i:s')],
                '$push' => ['activity_log' => ['action' => 'login', 'at' => date('Y-m-d H:i:s')]],
            ]);

        // ---- Upsert (insert if not exists) ----
        $mongo
            ->where(['email' => 'neuser@example.com'])
            ->upsert('users', ['name' => 'New User', 'email' => 'newuser@example.com']);

        echo json_encode($result);
    }

    /**
     * DELETE — Remove documents
     *
     * DELETE /example/delete
     */
    public function delete(mixed $id = null): void
    {
        $mongo = service('mongo');

        // ---- Delete one document by ID ----
        $result = $mongo
            ->where('_id', '507f1f77bcf86cd799439011')
            ->deleteOne('users');

        // ---- Delete all matching ----
        $result = $mongo
            ->where(['status' => 0])
            ->delete('users');

        echo json_encode($result);
    }

    // =========================================================================
    // 2. ADVANCED WHERE CONDITIONS
    // =========================================================================

    public function advancedWhere(): void
    {
        $mongo = service('mongo');

        // WHERE IN
        $users = $mongo->whereIn('role', ['admin', 'moderator'])->get('users');

        // WHERE NOT IN
        $users = $mongo->whereNotIn('status', [0, 3])->get('users');

        // WHERE BETWEEN
        $adults = $mongo->whereBetween('age', 18, 65)->get('users');

        // WHERE NULL / NOT NULL
        $unverified = $mongo->whereNull('verified_at')->get('users');
        $verified   = $mongo->whereNotNull('verified_at')->get('users');

        // Greater / Less than
        $highEarners = $mongo->whereGt('salary', 50000)->get('employees');
        $juniors     = $mongo->whereLte('experience_years', 2)->get('employees');

        // OR conditions
        $results = $mongo
            ->where('role', 'admin')
            ->orWhere('role', 'moderator')
            ->orWhere('is_superuser', true)
            ->get('users');

        // LIKE (regex)
        $johns = $mongo->like('name', 'john')->get('users');              // contains john
        $jStart = $mongo->like('name', 'J', 'after')->get('users');       // starts with J
        $dotCom = $mongo->like('email', '.com', 'before')->get('users');  // ends with .com

        // Raw MongoDB filter
        $results = $mongo
            ->whereRaw([
                '$expr' => [
                    '$gt' => ['$amount_paid', '$amount_due'],
                ],
            ])
            ->get('invoices');

        echo json_encode(compact('users', 'adults'));
    }

    // =========================================================================
    // 3. AGGREGATION PIPELINE
    // =========================================================================

    public function aggregationExamples(): void
    {
        $mongo = service('mongo');

        // ---- Count by role ----
        $roleCounts = $mongo->aggregate('users', [
            ['$group' => [
                '_id'   => '$role',
                'count' => ['$sum' => 1],
            ]],
            ['$sort' => ['count' => -1]],
        ]);
        // Returns: [['_id' => 'user', 'count' => 120], ['_id' => 'admin', 'count' => 5]]

        // ---- Revenue by month ----
        $monthlyRevenue = $mongo->aggregate('orders', [
            ['$match' => ['status' => 'paid']],
            ['$group' => [
                '_id'     => ['$month' => ['$dateFromString' => ['dateString' => '$created_at']]],
                'revenue' => ['$sum' => '$amount'],
                'count'   => ['$sum' => 1],
            ]],
            ['$sort'  => ['_id' => 1]],
        ]);

        // ---- Fluent aggregation building ----
        $summary = $mongo
            ->where(['status' => 'paid'])                        // becomes $match
            ->groupBy('category', [                              // becomes $group
                'total'    => ['$sum' => '$amount'],
                'count'    => ['$sum' => 1],
                'avg_sale' => ['$avg' => '$amount'],
            ])
            ->addStage(['$sort' => ['total' => -1]])             // raw stage
            ->addStage(['$limit' => 5])
            ->aggregate('orders');

        echo json_encode(compact('roleCounts', 'monthlyRevenue', 'summary'));
    }

    // =========================================================================
    // 4. JOIN ($lookup) — SQL-like Joins
    // =========================================================================

    public function joinExamples(): void
    {
        $mongo = service('mongo');

        // ---- Simple join (like LEFT JOIN in SQL) ----
        // Get all users with their orders embedded
        $usersWithOrders = $mongo
            ->join(
                'orders',        // join FROM this collection
                '_id',           // local field  (users._id)
                'user_id',       // foreign field (orders.user_id)
                'orders'         // result field name
            )
            ->aggregate('users');
        // Each user doc will have an 'orders' array embedded

        // ---- Join + unwind (one row per order, like INNER JOIN) ----
        $userOrders = $mongo
            ->join('orders', '_id', 'user_id', 'order')
            ->unwind('order', false)         // flatten the array
            ->project([
                'name'           => 1,
                'email'          => 1,
                'order._id'      => 1,
                'order.amount'   => 1,
                'order.status'   => 1,
            ])
            ->aggregate('users');

        // ---- Multi-level join ----
        // Users → Orders → Products
        $detailed = $mongo
            ->join('orders',   '_id',       'user_id',    'orders')
            ->unwind('orders')
            ->join('products', 'orders.product_id', '_id', 'product')
            ->unwind('product', true)
            ->aggregate('users');

        // ---- Raw $lookup with pipeline (advanced) ----
        $advanced = $mongo->lookup([
            'from'     => 'orders',
            'let'      => ['userId' => '$_id'],
            'pipeline' => [
                ['$match' => ['$expr' => ['$and' => [
                    ['$eq'  => ['$user_id', '$$userId']],
                    ['$gte' => ['$amount', 100]],
                ]]]],
                ['$sort'  => ['created_at' => -1]],
                ['$limit' => 5],
            ],
            'as' => 'recent_big_orders',
        ])->aggregate('users');

        echo json_encode(['count' => count($usersWithOrders)]);
    }

    // =========================================================================
    // 5. PAGINATION
    // =========================================================================

    public function paginationExample(): void
    {
        $mongo = service('mongo');

        $page    = (int) ($this->request->getGet('page')     ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 15);

        // ---- Basic pagination ----
        $result = $mongo
            ->where(['status' => 1])
            ->orderBy('created_at', 'DESC')
            ->paginate('users', $perPage, $page);

        /*
         * $result = [
         *   'data'         => [...],      // array of documents
         *   'total'        => 256,
         *   'per_page'     => 15,
         *   'current_page' => 1,
         *   'last_page'    => 18,
         *   'from'         => 1,
         *   'to'           => 15,
         * ]
         */

        // ---- Using the helper function ----
        $result2 = mongo_paginate('users', $page, $perPage, ['active' => 1]);

        echo json_encode($result);
    }

    // =========================================================================
    // 6. SOFT DELETE
    // =========================================================================

    public function softDeleteExamples(): void
    {
        $mongo = service('mongo');

        // ---- Soft delete (sets deleted_at, does NOT remove) ----
        $mongo->where('_id', '507f1f77bcf86cd799439011')
              ->withSoftDelete()
              ->deleteOne('users');

        // ---- Normal get() EXCLUDES soft-deleted records ----
        $activeUsers = $mongo->withSoftDelete()->get('users');

        // ---- Include soft-deleted records ----
        $allUsers = $mongo->withSoftDelete()->withTrashed()->get('users');

        // ---- ONLY soft-deleted records ----
        $deletedUsers = $mongo->onlyTrashed()->get('users');

        // ---- Restore a soft-deleted record ----
        $mongo->where('_id', '507f1f77bcf86cd799439011')->restore('users');

        echo json_encode(['active' => count($activeUsers), 'deleted' => count($deletedUsers)]);
    }

    // =========================================================================
    // 7. TRANSACTIONS (requires replica set)
    // =========================================================================

    public function transactionExample(): void
    {
        $mongo = service('mongo');

        $mongo->startTransaction();

        try {
            // Deduct from sender
            $mongo
                ->where('_id', 'sender_id_here')
                ->updateOne('accounts', ['$inc' => ['balance' => -500]]);

            // Add to receiver
            $mongo
                ->where('_id', 'receiver_id_here')
                ->updateOne('accounts', ['$inc' => ['balance' => 500]]);

            // Record transaction
            $mongo->insert('transactions', [
                'from'   => 'sender_id_here',
                'to'     => 'receiver_id_here',
                'amount' => 500,
                'type'   => 'transfer',
            ]);

            $mongo->commitTransaction();
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            $mongo->abortTransaction();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } finally {
            $mongo->endSession();
        }
    }

    // =========================================================================
    // 8. INDEX MANAGEMENT
    // =========================================================================

    public function indexExamples(): void
    {
        $mongo = service('mongo');

        // Single field index
        $mongo->createIndex('users', ['email' => 1], ['unique' => true]);

        // Compound index
        $mongo->createIndex('orders', ['user_id' => 1, 'created_at' => -1]);

        // Text index for full-text search
        $mongo->createIndex('posts', ['title' => 'text', 'body' => 'text']);

        // TTL index — auto-expire documents after 7 days
        $mongo->createIndex('sessions', ['created_at' => 1], ['expireAfterSeconds' => 604800]);

        // Geospatial index
        $mongo->createIndex('locations', ['coordinates' => '2dsphere']);

        // Sparse index (only indexes documents where field exists)
        $mongo->createIndex('users', ['phone' => 1], ['sparse' => true, 'unique' => true]);

        // Create multiple indexes at once
        $mongo->createIndexes('users', [
            ['key' => ['email'      => 1 ], 'unique' => true],
            ['key' => ['created_at' => -1]],
            ['key' => ['role'       => 1, 'status' => 1]],
        ]);

        // List all indexes
        $indexes = $mongo->listIndexes('users');

        // Drop an index
        $mongo->dropIndex('users', 'email_1');

        echo json_encode($indexes);
    }

    // =========================================================================
    // 9. USING MongoModel
    // =========================================================================

    public function modelExamples(): void
    {
        $model = new UserModel();

        // Find by ID
        $user = $model->find('507f1f77bcf86cd799439011');

        // Find all
        $users = $model->findAll(['active' => 1]);

        // Paginate
        $page   = (int) ($this->request->getGet('page') ?? 1);
        $result = $model->paginate($page, 20, ['active' => 1]);

        // Save (insert)
        $newId = $model->save(['name' => 'New User', 'email' => 'new@example.com']);

        // Update
        $model->update('507f...', ['name' => 'Updated Name']);

        // Delete (soft if $softDelete = true in model)
        $model->delete('507f...');

        // Restore
        $model->restore('507f...');

        // Fluent query on model
        $admins = $model->where(['role' => 'admin'])->orderBy('name')->get('users');

        // Aggregate
        $stats = $model->aggregate([
            ['$group' => ['_id' => '$role', 'count' => ['$sum' => 1]]],
        ]);

        echo json_encode(compact('user', 'users', 'result', 'newId', 'stats'));
    }

    // =========================================================================
    // 10. USING GLOBAL HELPER FUNCTIONS
    // =========================================================================

    public function helperExamples(): void
    {
        // mongo() helper — global shortcut to service('mongo')
        $users = mongo()->where(['active' => 1])->limit(5)->get('users');

        // object_id() — convert string to ObjectId
        $oid  = object_id('507f1f77bcf86cd799439011');
        $user = mongo()->where('_id', $oid)->first('users');

        // is_object_id() — validate
        $valid = is_object_id('507f1f77bcf86cd799439011'); // true
        $bad   = is_object_id('not-an-id');                 // false

        // mongo_paginate() — quick pagination
        $result = mongo_paginate('products', 2, 12, ['in_stock' => true]);

        // mongo_raw() — raw collection for advanced operations
        $col = mongo_raw('events');

        echo json_encode(compact('users', 'valid', 'result'));
    }
}
