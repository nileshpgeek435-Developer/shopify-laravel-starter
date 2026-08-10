<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Osiset\ShopifyApp\Contracts\ShopModel as IShopModel;
use Osiset\ShopifyApp\Traits\ShopModel;

class User extends Authenticatable implements IShopModel
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;
    use ShopModel;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'shopify_grandfathered',
        'shopify_namespace',
        'shopify_freemium',
        'plan_id',
        'theme_support_level',
        'password_updated_at',
        'shopify_offline_refresh_token',
        'shopify_offline_access_token_expires_at',
        'shopify_offline_refresh_token_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'shopify_offline_refresh_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * Do not cast password as hashed — Shopify stores the offline access token there.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_updated_at' => 'datetime',
            'shopify_grandfathered' => 'boolean',
            'shopify_freemium' => 'boolean',
        ];
    }
}
