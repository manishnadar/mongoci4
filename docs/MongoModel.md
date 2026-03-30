# MongoModel

If you prefer building object-oriented architectures, `MongoCI4\Libraries\MongoModel` provides a clean base class for CodeIgniter 4 models using MongoDB instead of MySQL.

## Creating a Model

Extend `MongoModel` rather than `CodeIgniter\Model`.

```php
namespace App\Models;

use MongoCI4\Libraries\MongoModel;

class UserModel extends MongoModel
{
    // The equivalent of CI4 Model's $table
    protected string $collection = 'users';

    // Auto-manage 'created_at' and 'updated_at'
    protected bool $timestamps = true;

    // Enable soft-deletes (sets 'deleted_at' instead of executing a hard delete)
    protected bool $softDelete = true;

    // Mass-assignment protection (Only these fields can be inserted/updated directly)
    protected array $fillable = [
        'name',
        'email',
        'role',
        'status',
        'avatar'
    ];

    // Fields that are NEVER returned in an array output (e.g. API responses)
    protected array $hidden = [
        'password',
        'remember_token',
        '__v'
    ];
}
```

## Finding Documents

```php
$model = new UserModel();

// Find by ID string (automatically converts to ObjectId!)
$user = $model->find('507f1f77bcf86cd799439011');

// Find all with conditions
$admins = $model->findAll(['role' => 'admin', 'status' => 1]);

// Find exactly one
$moderator = $model->findOne(['role' => 'moderator']);
```

## Modifying Documents

```php
$model = new UserModel();

// CREATE
// Saves a new document and returns the _id string
$newId = $model->save([
    'name'  => 'New User',
    'email' => 'new@domain.com',
    'role'  => 'user'
]);

// UPDATE
// Providing ID and array of fields
$model->update($newId, ['name' => 'Revised Name']);
// Or providing an array of conditions
$model->update(['status' => 0], ['status' => 1]);

// DELETE
$model->delete($newId);                     // Hard delete or Soft delete (if softDelete=true)
$model->delete(['status' => 0]);            // Delete many

// RESTORE
$model->restore($newId);                    // Restores a soft-deleted record
```

## Advanced Querying on Models

You can utilize the fluent Query Builder directly via `query()` or proxy method calls directly to the model.

```php
// Finding records via direct query builder proxying:
$users = $model
    ->where(['active' => 1])
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get('users');

// Or using query():
$result = $model->query()->distinct('role', 'users');
```
