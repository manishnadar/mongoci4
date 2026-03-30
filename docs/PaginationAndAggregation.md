# Pagination & Aggregation

## Pagination

MongoCI4 creates advanced pagination with full metadata returned cleanly as an associative array.

```php
// Via Service
$result = service('mongo')
    ->where('status', 1)
    ->orderBy('created_at', 'DESC')
    ->paginate('users', perPage: 15, page: 2);

// Via Model
$result = $model->paginate(page: 2, perPage: 15, conditions: ['status' => 1]);

// Via Global Helper
$result = mongo_paginate('users', page: 2, perPage: 15, conditions: ['status' => 1]);
```

The output contains standard pagination metadata required for front-end libraries:
```php
[
    'data'         => [...],      // Array of 15 user documents
    'total'        => 256,        // Total count matching queries
    'per_page'     => 15,
    'current_page' => 2,
    'last_page'    => 18,
    'from'         => 16,
    'to'           => 30,
]
```

## Aggregation Pipelines

A major advantage of MongoDB is the Aggregation Framework. MongoBuilder simplifies complex aggregation pipelines.

### Raw Pipeline

```php
$stats = mongo()->aggregate('orders', [
    ['$match' => ['status' => 'paid']],
    ['$group' => [
        '_id'       => '$category',
        'revenue'   => ['$sum' => '$amount'],
        'count'     => ['$sum' => 1],
    ]],
    ['$sort' => ['revenue' => -1]],
    ['$limit' => 5],
]);
```

### Fluent Pipeline Helpers

If you prefer building chains dynamically instead of passing nested arrays, you can use the fluent stage methods:

```php
$summary = mongo()
    ->where(['status' => 'paid'])       // Compiles to: $match
    ->join('users', 'user_id', '_id', 'customer') // Compiles to: $lookup
    ->unwind('customer')                // Compiles to: $unwind
    ->groupBy('category', [             // Compiles to: $group
        'revenue' => ['$sum' => '$amount'],
        'count'   => ['$sum' => 1],
    ])
    ->orderBy('revenue', 'DESC')       // Compiles to: $sort
    ->limit(5)
    ->aggregate('orders');
```

## Relational Joins ($lookup)

MongoDB uses `$lookup` to join relative collections. MongoCI4 provides a `join()` helper method modeling SQL `LEFT JOIN`.

```php
$userOrders = mongo()
    ->join(
        'orders',      // Foreign collection
        '_id',         // Local Field  (users._id)
        'user_id',     // Foreign Field (orders.user_id)
        'all_orders'   // Rejection Array name
    )
    ->aggregate('users');
```

To flatten a joined array into relative objects like an `INNER JOIN`, chain an `unwind()`.

```php
$flattened = mongo()
    ->join('orders', '_id', 'user_id', 'order_doc')
    ->unwind('order_doc', false)      // False = Drop items with no orders
    ->project([
        'name' => 1, 
        'email' => 1, 
        'order_doc.amount' => 1
    ])
    ->aggregate('users');
```
