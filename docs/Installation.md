# Installation & Configuration

## Requirements
To use MongoCI4, you need:
- PHP 8.0 or higher (Tested on PHP 7.4+ as well)
- CodeIgniter 4.x
- MongoDB PHP extension (`ext-mongodb`)
- MongoDB Server 4.4+ or MongoDB Atlas

---

## 1. Install the PHP Extension

You need the native MongoDB C-driver wrapper for PHP.

**Ubuntu / Debian**
```bash
sudo apt update
sudo apt install php-mongodb
```
*Or using PECL:*
```bash
sudo pecl install mongodb
echo "extension=mongodb.so" >> /etc/php/8.x/cli/php.ini
echo "extension=mongodb.so" >> /etc/php/8.x/apache2/php.ini
```

**macOS (Homebrew)**
```bash
pecl install mongodb
```

**Verify installation:**
```bash
php -m | grep mongodb
```

---

## 2. Install the Package

Inside your CodeIgniter 4 project root:
```bash
composer require manishnadar/mongoci4
```

---

## 3. Register the CI4 Service

To make `service('mongo')` available globally, open your `app/Config/Services.php` and add:

```php
<?php

namespace Config;

use CodeIgniter\Config\BaseService;
use MongoCI4\Config\Mongo;
use MongoCI4\Libraries\MongoBuilder;

class Services extends BaseService
{
    // ... existing services ...

    public static function mongo(bool $getShared = true): MongoBuilder
    {
        if ($getShared) {
            return static::getSharedInstance('mongo');
        }

        return new MongoBuilder(config(Mongo::class));
    }
}
```

---

## 4. Configuration (.env)

Add the MongoDB connection details to your project's `.env` file:

```env
# Local Database
mongodb.uri      = "mongodb://localhost:27017"
mongodb.database = "my_ci4_app"
mongodb.debug    = false

# Feature Flags
mongodb.timestamps = true
mongodb.softDelete = false
```

### Advanced Connections

**MongoDB Atlas (Cloud):**
```env
mongodb.uri = "mongodb+srv://<username>:<password>@cluster0.abcde.mongodb.net"
mongodb.database = "myapp_production"
```

**Local with Auth Source:**
```env
mongodb.uri        = "mongodb://localhost:27017"
mongodb.username   = "db_user"
mongodb.password   = "secret123"
mongodb.authSource = "admin"
mongodb.database   = "my_ci4_app"
```

---

## You're Done!

You can now start querying MongoDB exactly like MySQL:

```php
$users = service('mongo')->where(['active' => 1])->get('users');
```
