<?php

/**
 * MongoCI4 — Services Registration Snippet
 *
 * =========================================================================
 * INSTALLATION INSTRUCTIONS
 * =========================================================================
 * Copy the `mongo()` method below into YOUR project's app/Config/Services.php
 * inside the Services class body. Do NOT replace your entire Services.php.
 *
 * Alternatively, if you prefer a dedicated file, create:
 *   app/Config/MongoServices.php  (with the content below, adjusted)
 * and CI4 will auto-discover it.
 * =========================================================================
 *
 * Usage after registration:
 *
 *   $mongo = service('mongo');
 *   $users = $mongo->where(['active' => 1])->limit(10)->get('users');
 */

namespace Config;

use CodeIgniter\Config\BaseService;
use MongoCI4\Config\Mongo;
use MongoCI4\Libraries\MongoBuilder;

class Services extends BaseService
{
    /**
     * Returns a shared MongoBuilder instance.
     *
     * Usage:
     *   service('mongo')
     *   \Config\Services::mongo()
     *
     * @param bool $getShared Return the shared instance (recommended)
     */
    public static function mongo(bool $getShared = true): MongoBuilder
    {
        if ($getShared) {
            return static::getSharedInstance('mongo');
        }

        /** @var Mongo $config */
        $config = config(Mongo::class);

        return new MongoBuilder($config);
    }
}
