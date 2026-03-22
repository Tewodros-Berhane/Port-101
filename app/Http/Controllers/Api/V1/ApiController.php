<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;

abstract class ApiController extends Controller
{
    use InteractsWithApiResponses;
}
