# MongoCI4 — MongoDB Query Builder for CodeIgniter 4

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue)](https://php.net)
[![CodeIgniter](https://img.shields.io/badge/CodeIgniter-4.x-orange)](https://codeigniter.com)
[![MongoDB PHP Library](https://img.shields.io/badge/mongodb%2Fmongodb-1.15%2B-green)](https://github.com/mongodb/mongo-php-library)
[![License](https://img.shields.io/badge/license-MIT-brightgreen)](LICENSE)

> Use MongoDB in CodeIgniter 4 **exactly like MySQL** — same familiar Query Builder syntax, zero learning curve.

---

## Features

| Feature | Details |
|---|---|
| 🔗 **Fluent chaining** | `select → where → orderBy → limit → get` |
| 📦 **Full CRUD** | `insert`, `insertBatch`, `update`, `updateOne`, `upsert`, `delete`, `deleteOne` |
| 🔍 **Rich filtering** | `where`, `orWhere`, `whereIn`, `whereNotIn`, `whereBetween`, `whereNull`, `whereGt/Gte/Lt/Lte`, `like`, `notLike`, `whereRaw` |
| 📄 **Pagination** | Built-in with full metadata (`total`, `last_page`, `from`, `to`) |
| 🔗 **JOIN support** | `join()` / `lookup()` via MongoDB `$lookup` aggregation |
| 📊 **Aggregation** | Full pipeline support + fluent helpers (`groupBy`, `project`, `unwind`, `match`, `addStage`) |
| 🗑️ **Soft delete** | Per-query or global; `withTrashed()`, `onlyTrashed()`, `restore()` |
| ⏱️ **Timestamps** | Auto `created_at` / `updated_at` management |
| 💳 **Transactions** | Session-based with `startTransaction()` / `commitTransaction()` / `abortTransaction()` |
| 🗂️ **Index management** | `createIndex`, `createIndexes`, `dropIndex`, `listIndexes` |
| 🏗️ **Model base class** | `MongoModel` with `fillable`, `hidden`, CRUD shortcuts |
| 🔧 **Helper functions** | `mongo()`, `object_id()`, `mongo_paginate()`, `mongo_raw()` |
| 🐛 **Debug logging** | Logs all queries to CI4 logger when debug mode is on |

---

## Requirements

- PHP 8.0 or higher
- CodeIgniter 4.x
- MongoDB PHP extension (`ext-mongodb`)
- MongoDB server 4.4+ (or MongoDB Atlas)

---

## Installation

### Step 1 — Install PHP MongoDB Extension

```bash
# Ubuntu / Debian
sudo pecl install mongodb
echo "extension=mongodb.so" >> /etc/php/8.x/cli/php.ini
echo "extension=mongodb.so" >> /etc/php/8.x/apache2/php.ini

# Or using apt (Ubuntu 22.04+)
sudo apt install php8.x-mongodb

# macOS (Homebrew)
pecl install mongodb

# Verify installation
php -m | grep mongodb
```

### Step 2 — Install via Composer

```bash
composer require mongoci4/mongoci4
```

### Step 3 — Register the Service

Open `app/Config/Services.php` in your CI4 project and add the `mongo()` method inside the `Services` class:

```php
<?php

namespace Config;

use CodeIgniter\Config\BaseService;
use MongoCI4\Config\Mongo;
use MongoCI4\Libraries\MongoBuilder;

class Services extends BaseService
{
    // ... your existing services ...

    public static function mongo(bool $getShared = true): MongoBuilder
    {
        if ($getShared) {
            return static::getSharedInstance('mongo');
        }

        return new MongoBuilder(config(Mongo::class));
    }
}
```

### Step 4 — Configure MongoDB Connection

Add to your `.env` file:

```env
mongodb.uri      = "mongodb://localhost:27017"
mongodb.database = "myapp"
mongodb.debug    = false
mongodb.timestamps   = true
mongodb.softDelete   = false
mongodb.throwExceptions = true
```

**MongoDB Atlas:**

```env
mongodb.uri = "mongodb+srv://username:password@cluster0.abcde.mongodb.net"
mongodb.database = "myapp"
```

**With credentials (not in URI):**

```env
mongodb.uri       = "mongodb://localhost:27017"
mongodb.username  = "myuser"
mongodb.password  = "mypassword"
mongodb.authSource = "admin"
mongodb.database  = "myapp"
```

### Step 5 — Register Namespace (Optional but Recommended)

In `app/Config/Autoload.php`, add the package namespace so CI4 can discover the Mongo config:

```php
public $psr4 = [
    APP_NAMESPACE => APPPATH,
    'MongoCI4'    => ROOTPATH . 'vendor/mongoci4/mongoci4/src',
];
```

---

## Quick Start

```php
// Get the service
$mongo = service('mongo');

// Or use the global helper
$mongo = mongo();
```

---

## Usage Examples

### Basic SELECT

```php
// Get all documents
$users = mongo()->get('users');

// With conditions
$activeUsers = mongo()
    ->where(['status' => 1, 'role' => 'user'])
    ->get('users');

// Select specific fields
$names = mongo()
    ->select('name, email, role')
    ->where(['status' => 1])
    ->orderBy('name', 'ASC')
    ->limit(10)
    ->offset(20)
    ->get('users');

// Get single document
$user = mongo()->where('_id', '507f1f77bcf86cd799439011')->first('users');
// ↑ _id string is automatically converted to ObjectId — no manual casting!

// Count
$total = mongo()->where(['role' => 'admin'])->count('users');

// Distinct values
$roles = mongo()->distinct('role', 'users');
// Returns: ['admin', 'user', 'guest']
```

### INSERT

```php
// Insert one
$result = mongo()->insert('users', [
    'name'  => 'John Doe',
    'email' => 'john@example.com',
    'role'  => 'user',
]);
// $result['inserted_id'] contains the new document ID

// Insert many
$result = mongo()->insertBatch('users', [
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob',   'email' => 'bob@example.com'],
]);
// $result['inserted_count'] = 2
```

### UPDATE

```php
// Update all matching
mongo()->where(['role' => 'user'])->update('users', ['verified' => true]);

// Update one
mongo()->where('_id', $id)->updateOne('users', ['name' => 'Updated Name']);

// Using MongoDB operators
mongo()->where('_id', $id)->updateOne('users', [
    '$inc'  => ['login_count' => 1],
    '$set'  => ['last_login'  => date('Y-m-d H:i:s')],
    '$push' => ['tags' => 'premium'],
]);

// Upsert (insert if not exists)
mongo()->where(['email' => 'new@example.com'])->upsert('users', [
    'name'  => 'New User',
    'email' => 'new@example.com',
]);
```

### DELETE

```php
// Delete one
mongo()->where('_id', $id)->deleteOne('users');

// Delete all matching
mongo()->where(['status' => 0])->delete('users');
```

---

## WHERE Conditions

```php
// Basic
->where('status', 1)
->where(['status' => 1, 'role' => 'admin'])

// OR conditions
->where('role', 'admin')->orWhere('role', 'moderator')

// IN / NOT IN
->whereIn('status', [1, 2, 3])
->whereNotIn('role', ['banned', 'deleted'])

// Comparison
->whereGt('age', 18)
->whereGte('score', 90)
->whereLt('price', 100)
->whereLte('distance', 50)
->whereBetween('age', 18, 65)

// NULL checks
->whereNull('deleted_at')
->whereNotNull('email')

// LIKE (regex)
->like('name', 'john')              // contains 'john' (case-insensitive)
->like('name', 'J', 'after')        // starts with 'J'
->like('email', '.com', 'before')   // ends with '.com'
->notLike('email', 'spam')

// Raw MongoDB filter
->whereRaw(['$expr' => ['$gt' => ['$price', '$cost']]])
```

---

## Pagination

```php
$result = mongo()
    ->where(['active' => 1])
    ->orderBy('created_at', 'DESC')
    ->paginate('users', perPage: 15, page: 2);

/*
 * Returns:
 * [
 *   'data'         => [...],    // 15 user documents
 *   'total'        => 256,
 *   'per_page'     => 15,
 *   'current_page' => 2,
 *   'last_page'    => 18,
 *   'from'         => 16,
 *   'to'           => 30,
 * ]
 */

// Using helper function
$result = mongo_paginate('users', page: 1, perPage: 10, conditions: ['active' => 1]);
```

---

## JOIN / $lookup

MongoDB doesn't have SQL JOINs — MongoCI4 uses the `$lookup` aggregation stage under the hood:

```php
// Users with their orders embedded
$usersWithOrders = mongo()
    ->join('orders', '_id', 'user_id', 'orders')  // join(from, localField, foreignField, as)
    ->aggregate('users');

// Flatten (like INNER JOIN)
$userOrders = mongo()
    ->join('orders', '_id', 'user_id', 'order')
    ->unwind('order')
    ->project(['name' => 1, 'email' => 1, 'order.amount' => 1, 'order.status' => 1])
    ->aggregate('users');

// Multi-level join (Users → Orders → Products)
$detailed = mongo()
    ->join('orders',   '_id',              'user_id',    'orders')
    ->unwind('orders')
    ->join('products', 'orders.product_id', '_id',        'product')
    ->unwind('product', preserveNull: true)
    ->aggregate('users');

// Advanced $lookup with pipeline
$result = mongo()->lookup([
    'from'     => 'orders',
    'let'      => ['userId' => '$_id'],
    'pipeline' => [
        ['$match' => ['$expr' => ['$and' => [
            ['$eq'  => ['$user_id', '$$userId']],
            ['$gte' => ['$amount', 100]],
        ]]]],
        ['$limit' => 5],
    ],
    'as' => 'big_orders',
])->aggregate('users');
```

---

## Aggregation

```php
// Full pipeline
$stats = mongo()->aggregate('orders', [
    ['$match' => ['status' => 'paid']],
    ['$group' => [
        '_id'     => '$category',
        'total'   => ['$sum' => '$amount'],
        'count'   => ['$sum' => 1],
        'average' => ['$avg' => '$amount'],
    ]],
    ['$sort'  => ['total' => -1]],
    ['$limit' => 10],
]);

// Fluent aggregation building
$summary = mongo()
    ->where(['status' => 'paid'])           // becomes $match
    ->groupBy('category', [                  // becomes $group
        'revenue' => ['$sum' => '$amount'],
        'count'   => ['$sum' => 1],
    ])
    ->orderBy('revenue', 'DESC')             // becomes $sort in pipeline
    ->limit(10)
    ->aggregate('orders');
```

---

## Soft Delete

Enable at config level (global) or per-query:

```env
# .env
mongodb.softDelete = true
```

```php
// Soft delete (sets deleted_at timestamp)
mongo()->where('_id', $id)->withSoftDelete()->deleteOne('users');

// Normal get() automatically excludes soft-deleted records
$active = mongo()->withSoftDelete()->get('users');

// Include deleted
$all = mongo()->withSoftDelete()->withTrashed()->get('users');

// Only deleted
$deleted = mongo()->onlyTrashed()->get('users');

// Restore
mongo()->where('_id', $id)->restore('users');
```

---

## Timestamps

When `mongodb.timestamps = true`:

- `insert()` auto-adds `created_at` and `updated_at`
- `update()` / `updateOne()` auto-updates `updated_at`

```php
mongo()->insert('users', ['name' => 'John']);
// Stored as: { name: "John", created_at: "2026-01-01 12:00:00", updated_at: "2026-01-01 12:00:00" }
```

---

## Transactions

> ⚠️ Requires MongoDB **replica set** or **sharded cluster**. Not available on standalone deployments.

```php
$mongo = service('mongo');
$mongo->startTransaction();

try {
    $mongo->where('_id', $senderId)->updateOne('accounts', ['$inc' => ['balance' => -500]]);
    $mongo->where('_id', $receiverId)->updateOne('accounts', ['$inc' => ['balance' => 500]]);
    $mongo->insert('transactions', ['from' => $senderId, 'to' => $receiverId, 'amount' => 500]);

    $mongo->commitTransaction();
} catch (Exception $e) {
    $mongo->abortTransaction();
} finally {
    $mongo->endSession();
}
```

---

## Index Management

```php
// Unique index
mongo()->createIndex('users', ['email' => 1], ['unique' => true]);

// Compound index
mongo()->createIndex('orders', ['user_id' => 1, 'created_at' => -1]);

// Text index (full-text search)
mongo()->createIndex('posts', ['title' => 'text', 'body' => 'text']);

// TTL — auto-expire documents
mongo()->createIndex('sessions', ['created_at' => 1], ['expireAfterSeconds' => 3600]);

// List all indexes
$indexes = mongo()->listIndexes('users');

// Drop
mongo()->dropIndex('users', 'email_1');
```

---

## MongoModel — Model-based Approach

```php
// app/Models/UserModel.php
namespace App\Models;

use MongoCI4\Libraries\MongoModel;

class UserModel extends MongoModel
{
    protected string $collection = 'users';
    protected bool   $softDelete = true;
    protected bool   $timestamps = true;

    protected array $fillable = ['name', 'email', 'role', 'status'];
    protected array $hidden   = ['password', '__v'];
}
```

```php
// In your controller:
$model = new UserModel();

$user  = $model->find('507f1f77bcf86cd799439011');       // by ID
$users = $model->findAll(['role' => 'admin']);            // filtered
$id    = $model->save(['name' => 'Alice']);               // insert → returns inserted ID
         $model->update('507f...', ['name' => 'Alice']); // update by ID
         $model->delete('507f...');                       // soft-delete (if enabled)
         $model->restore('507f...');                      // restore

// Paginate
$result = $model->paginate(page: 1, perPage: 15);

// Fluent on model
$admins = $model->where(['role' => 'admin'])->orderBy('name')->get('users');
```

---

## Helper Functions

```php
// Access the MongoBuilder service
mongo()

// Convert string to ObjectId (usually automatic for _id)
object_id('507f1f77bcf86cd799439011')

// Check if valid ObjectId
is_object_id($id)   // true / false

// Quick pagination
mongo_paginate('users', page: 1, perPage: 10, conditions: ['active' => 1])

// Raw MongoDB\Collection (change streams, watch, etc.)
mongo_raw('events')
```

---

## Method Reference

### Query Building (Chainable)

| Method | Description |
|---|---|
| `select($fields)` | Specify fields to return (comma-string or array) |
| `selectExclude($fields)` | Exclude specific fields from results |
| `where($key, $val)` | AND condition |
| `orWhere($key, $val)` | OR condition |
| `whereIn($key, $vals)` | `$in` operator |
| `whereNotIn($key, $vals)` | `$nin` operator |
| `whereBetween($key, $min, $max)` | `$gte + $lte` |
| `whereNull($key)` | Field is null/missing |
| `whereNotNull($key)` | Field exists and is not null |
| `whereGt/Gte/Lt/Lte($key, $val)` | Comparison operators |
| `whereRaw($filter)` | Raw MongoDB filter document |
| `like($key, $val, $position)` | Regex match |
| `notLike($key, $val)` | Inverted regex |
| `orderBy($field, $dir)` | Sort |
| `limit($n)` | Result limit |
| `offset($n)` | Result offset (skip) |

### Read Operations (Terminal)

| Method | Returns |
|---|---|
| `get($collection)` | `array` of documents |
| `first($collection)` | `array\|null` — first match |
| `count($collection)` | `int` |
| `distinct($field, $collection)` | `array` of unique values |
| `paginate($collection, $perPage, $page)` | `array` with data + metadata |
| `aggregate($collection, $pipeline)` | `array` of result documents |

### Write Operations (Terminal)

| Method | Returns |
|---|---|
| `insert($collection, $data)` | `['success', 'inserted_id', 'inserted_count']` |
| `insertBatch($collection, $rows)` | `['success', 'inserted_count', 'inserted_ids']` |
| `update($collection, $data)` | `['success', 'matched_count', 'modified_count']` |
| `updateOne($collection, $data)` | `['success', 'matched_count', 'modified_count']` |
| `upsert($collection, $data)` | `['success', 'matched_count', 'modified_count', 'upserted_id']` |
| `delete($collection)` | `['success', 'deleted_count']` |
| `deleteOne($collection)` | `['success', 'deleted_count']` |
| `restore($collection)` | Same as update |

### Aggregation Pipeline Builders (Chainable)

| Method | MongoDB Stage |
|---|---|
| `join($from, $local, $foreign, $as)` | `$lookup` |
| `lookup($config)` | `$lookup` (raw) |
| `unwind($field, $preserveNull)` | `$unwind` |
| `groupBy($id, $accumulators)` | `$group` |
| `project($fields)` | `$project` |
| `match($filter)` | `$match` |
| `addStage($stage)` | Any raw stage |

---

## File Structure

```
your-ci4-project/
├── vendor/
│   └── mongoci4/mongoci4/
│       ├── composer.json
│       ├── src/
│       │   ├── Config/
│       │   │   ├── Mongo.php           ← Connection config
│       │   │   └── Services.php        ← Service snippet
│       │   ├── Libraries/
│       │   │   ├── MongoBuilder.php    ← Core query builder
│       │   │   └── MongoModel.php      ← Base model
│       │   └── Helpers/
│       │       └── mongo_helper.php    ← Global functions
│       └── examples/
│           ├── ExampleController.php
│           └── ExampleModel.php
└── app/
    └── Config/
        └── Services.php                ← Add mongo() method here
```

---

## Design Decisions

### Why method chaining & auto-reset?

Every terminal operation (`get`, `insert`, `update`, `delete`, `aggregate`) automatically resets the builder state. This means you get a clean slate for each query without needing to call `reset()` manually — just like CI4's own Query Builder.

### Why auto-cast `_id` strings to ObjectId?

MongoDB requires `_id` to be an `ObjectId` type, not a plain string. MongoCI4 automatically detects when a 24-character hex string is passed as an `_id` value and converts it — eliminating a very common source of bugs for developers migrating from MySQL.

### Why return arrays instead of objects?

Plain PHP arrays are simpler to work with in CI4 views and APIs. No casting needed, and they serialise directly to JSON. The `documentToArray()` method recursively converts all BSON types (ObjectId, UTCDateTime, Regex, nested BSONDocument/BSONArray) to native PHP types.

### Why support both raw operators and simple arrays in `update()`?

Developers who want simple `['key' => 'value']` updates don't need to know about MongoDB's `$set` operator. But power users who need `$inc`, `$push`, `$unset`, etc. can pass them directly. MongoCI4 auto-detects which mode you're using.

---

## License

MIT License — free for personal and commercial use.
