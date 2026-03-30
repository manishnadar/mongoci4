<?php

namespace MongoCI4\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * MongoDB Configuration for MongoCI4
 *
 * Copy this file to your app/Config/ directory or reference it through
 * the MongoCI4 namespace. All values can be overridden in your .env file.
 *
 * .env keys:
 *   mongodb.uri
 *   mongodb.database
 *   mongodb.username
 *   mongodb.password
 *   mongodb.debug
 *   mongodb.softDelete
 *   mongodb.timestamps
 *   mongodb.throwExceptions
 */
class Mongo extends BaseConfig
{
    /**
     * MongoDB connection URI.
     *
     * Examples:
     *   mongodb://localhost:27017
     *   mongodb://user:pass@localhost:27017
     *   mongodb+srv://user:pass@cluster0.example.net
     */
    public string $uri = 'mongodb://localhost:27017';

    /**
     * Default database name.
     */
    public string $database = 'myapp';

    /**
     * Username (used if not embedded in URI).
     */
    public string $username = '';

    /**
     * Password (used if not embedded in URI).
     */
    public string $password = '';

    /**
     * Authentication database (usually 'admin').
     */
    public string $authSource = 'admin';

    /**
     * Additional URI options passed to MongoDB\Client.
     *
     * @see https://www.mongodb.com/docs/php-library/current/reference/method/MongoDBClient/
     */
    public array $uriOptions = [];

    /**
     * Driver-level options passed to MongoDB\Client.
     */
    public array $driverOptions = [];

    /**
     * Enable debug mode.
     * When true, all queries are logged via CodeIgniter's log_message('debug', ...).
     */
    public bool $debug = false;

    /**
     * Enable global soft delete support.
     * When true, delete() sets 'deleted_at' instead of removing the document.
     * Records with deleted_at != null are excluded from all find() queries.
     */
    public bool $softDelete = false;

    /**
     * Auto-manage timestamps.
     * When true:
     *   - insert() automatically adds 'created_at' and 'updated_at'
     *   - update() automatically updates 'updated_at'
     */
    public bool $timestamps = true;

    /**
     * When true, exceptions bubble up to the caller.
     * When false, exceptions are only logged (silent mode).
     */
    public bool $throwExceptions = true;

    // -------------------------------------------------------------------------
    // Constructor — maps .env values
    // -------------------------------------------------------------------------

    public function __construct()
    {
        parent::__construct();

        // Allow .env overrides without changing this file
        $this->uri             = env('mongodb.uri',              $this->uri);
        $this->database        = env('mongodb.database',         $this->database);
        $this->username        = env('mongodb.username',         $this->username);
        $this->password        = env('mongodb.password',         $this->password);
        $this->authSource      = env('mongodb.authSource',       $this->authSource);
        $this->debug           = (bool) env('mongodb.debug',     $this->debug);
        $this->softDelete      = (bool) env('mongodb.softDelete',$this->softDelete);
        $this->timestamps      = (bool) env('mongodb.timestamps',$this->timestamps);
        $this->throwExceptions = (bool) env('mongodb.throwExceptions', $this->throwExceptions);

        // Build auth into uriOptions if credentials provided separately
        if ($this->username !== '' && !str_contains($this->uri, '@')) {
            $this->uriOptions['username'] = $this->username;
            $this->uriOptions['password'] = $this->password;
            $this->uriOptions['authSource'] = $this->authSource;
        }
    }
}
