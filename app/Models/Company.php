<?php

namespace App\Models;

use App\Models\Concerns\FormatsDatesForApi;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use FormatsDatesForApi;
}
