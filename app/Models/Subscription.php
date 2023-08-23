<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Subscription extends Model
{

    use HasUuids;

    protected $table = "subscriptions";

    protected $fillable = ['user_id', 'type', 'expires_at', 'status'];

}
