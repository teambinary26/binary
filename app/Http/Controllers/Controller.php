<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * @mixin AuthorizesRequests
 */
abstract class Controller
{
    use AuthorizesRequests;
}
