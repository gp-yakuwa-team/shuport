<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesAsJst;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use SerializesDatesAsJst;
}
