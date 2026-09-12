<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    // Laravel 12's skeleton ships a bare base controller. We put authorisation
    // back on it because every controller in this application authorises —
    // there is no endpoint here that should be reachable by anyone at all.
    use AuthorizesRequests, ValidatesRequests;
}
