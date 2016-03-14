<?php namespace Millar\AB\Models;

use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Experiment extends Eloquent {

    protected $table = 'ab_experiments';

    protected $primaryKey = 'name';

    public $timestamps = false;

    protected $fillable = ['name'];

    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        // Set the connection based on the config.
        $this->connection = Config::get('ab::connection');
    }

    public function variants()
    {
        return $this->hasMany('Millar\AB\Models\Variant', 'experiment');
    }

    public function scopeActive($query)
    {
        if ($experiments = Config::get('ab::experiments'))
        {
            return $query->whereIn('name', Config::get('ab::experiments'));
        }

        return $query;
    }

}
