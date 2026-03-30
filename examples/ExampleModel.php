<?php

namespace App\Models;

use MongoCI4\Libraries\MongoModel;

/**
 * UserModel — Example model using MongoCI4
 *
 * Extend MongoModel instead of CodeIgniter's Model.
 */
class UserModel extends MongoModel
{
    /** MongoDB collection name (equivalent to $table in CI4 Model) */
    protected string $collection = 'users';

    /** Enable soft deletes */
    protected bool $softDelete = true;

    /** Auto-manage created_at / updated_at */
    protected bool $timestamps = true;

    /** Only these fields can be mass-assigned via save() / update() */
    protected array $fillable = [
        'name',
        'email',
        'role',
        'status',
        'phone',
        'avatar',
        'bio',
        'verified_at',
    ];

    /** These fields are NEVER returned in results */
    protected array $hidden = [
        'password',
        'remember_token',
        '__v',
    ];

    // -------------------------------------------------------------------------
    // Custom methods (add your business logic here)
    // -------------------------------------------------------------------------

    /**
     * Find a user by email address.
     */
    public function findByEmail(string $email): ?array
    {
        return $this->findOne(['email' => $email]);
    }

    /**
     * Get all admin users.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdmins(): array
    {
        return $this->findAll(['role' => 'admin', 'status' => 1]);
    }

    /**
     * Get users that registered in the last N days.
     */
    public function getRecentUsers(int $days = 7): array
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $this->builder
            ->whereGte('created_at', $since)
            ->orderBy('created_at', 'DESC')
            ->get($this->collection);
    }

    /**
     * Count users by role.
     *
     * @return array<string, int>
     */
    public function countByRole(): array
    {
        $results = $this->aggregate([
            ['$group' => [
                '_id'   => '$role',
                'count' => ['$sum' => 1],
            ]],
        ]);

        $map = [];
        foreach ($results as $row) {
            $map[$row['_id']] = $row['count'];
        }

        return $map;
    }
}


/**
 * OrderModel — Example model for orders collection
 */
class OrderModel extends MongoModel
{
    protected string $collection = 'orders';
    protected bool   $timestamps = true;
    protected bool   $softDelete = false;

    protected array $fillable = [
        'user_id',
        'items',
        'total',
        'status',
        'shipping_address',
        'payment_method',
        'paid_at',
    ];

    /**
     * Get orders for a specific user, newest first.
     */
    public function getForUser(string $userId): array
    {
        return $this->builder
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->get($this->collection);
    }

    /**
     * Get revenue summary grouped by month.
     */
    public function monthlyRevenue(): array
    {
        return $this->aggregate([
            ['$match' => ['status' => 'paid']],
            ['$group' => [
                '_id'     => [
                    'year'  => ['$year'  => ['$dateFromString' => ['dateString' => '$created_at']]],
                    'month' => ['$month' => ['$dateFromString' => ['dateString' => '$created_at']]],
                ],
                'revenue' => ['$sum' => '$total'],
                'count'   => ['$sum' => 1],
            ]],
            ['$sort' => ['_id.year' => -1, '_id.month' => -1]],
        ]);
    }

    /**
     * Get orders with user and product details joined.
     */
    public function getWithDetails(): array
    {
        return $this->builder
            ->join('users',    'user_id',           '_id', 'user')
            ->unwind('user',   true)
            ->join('products', 'items.product_id',  '_id', 'product_details')
            ->aggregate($this->collection);
    }
}
