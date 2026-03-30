# Query Builder API

The `MongoBuilder` uses fluent method chaining. Every state-modifying method (`where`, `orderBy`, etc.) returns the builder itself. Every terminal method (`get`, `insert`, `delete`, etc.) executes the query and **automatically resets** the builder state.

## Selectors & Projections

Specify which fields to return in your documents:

```php
// Return only specific fields
mongo()->select('name, email, age')->get('users');
mongo()->select(['name', 'email'])->get('users');

// Exclude specific fields (e.g., passwords)
mongo()->selectExclude('password, remember_token')->get('users');
```

## Where Clauses

```php
// Basic Equal
mongo()->where('status', 1);
mongo()->where(['status' => 1, 'role' => 'editor']); // AND

// Array Inclusion / Exclusion
mongo()->whereIn('role', ['admin', 'moderator']);
mongo()->whereNotIn('status', ['banned', 'deleted']);

// Comparisons
mongo()->whereGt('age', 18);     // Greater than (>)
mongo()->whereGte('score', 90);  // Greater than or equal (>=)
mongo()->whereLt('price', 100);  // Less than (<)
mongo()->whereLte('price', 50);  // Less than or equal (<=)
mongo()->whereBetween('age', 18, 65);

// Null checks
mongo()->whereNull('deleted_at');
mongo()->whereNotNull('email');
```

## OR conditions

```php
mongo()
    ->where('role', 'admin')
    ->orWhere('role', 'moderator')
    ->get('users');
```

## LIKE (Regex matching)

MongoDB doesn't have a SQL `LIKE` operator, so this translates to MongoDB `$regex`:

```php
mongo()->like('name', 'john');             // WHERE name LIKE '%john%'
mongo()->like('name', 'J', 'after');       // WHERE name LIKE 'J%'
mongo()->like('email', '.com', 'before');  // WHERE email LIKE '%.com'

// Case sensitive match
mongo()->like('name', 'John', 'both', true);
```

## Sorting and Limits

```php
mongo()
    ->orderBy('created_at', 'DESC')
    ->orderBy('name', 'ASC')
    ->limit(20)
    ->offset(40)  // Skip 40 records
    ->get('users');
```

## Terminal Methods (Read)

```php
// Get array of all matching documents
$docs = mongo()->where('status', 1)->get('users');

// Get ONLY the first matching document
$doc = mongo()->where('_id', $id)->first('users');

// Get total count
$count = mongo()->where('role', 'user')->count('users');

// Get distinct values for a field
$categories = mongo()->distinct('category', 'products');
```

## Terminal Methods (Write)

```php
// INSERT
$res = mongo()->insert('users', ['name' => 'John']);
$id  = $res['inserted_id'];

// INSERT BATCH
mongo()->insertBatch('logs', [
    ['msg' => 'Log 1'], 
    ['msg' => 'Log 2']
]);

// UPDATE ALL MATCHING
mongo()->where('status', 0)->update('users', ['status' => 1]);

// UPDATE ONE
mongo()->where('_id', $id)->updateOne('users', ['name' => 'Revised']);

// UPSERT (Update if matches, Insert if missing)
mongo()->where('email', 'a@b.com')->upsert('users', ['name' => 'A B', 'email' => 'a@b.com']);

// DELETE
mongo()->where('status', 'banned')->delete('users');
mongo()->where('_id', $id)->deleteOne('users');
```
