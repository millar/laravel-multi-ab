<?php namespace Millar\AB\Models;

use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Goal extends Eloquent {

    protected $table = 'ab_goals';

    protected $primaryKey = 'name';

    public $timestamps = false;

    protected $fillable = ['name', 'experiment', 'variant', 'count'];

    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        // Set the connection based on the config.
        $this->connection = Config::get('ab::connection');
    }

    public function scopeActive($query)
    {
        if ($experiments = Config::get('ab::experiments'))
        {
            return $query->whereIn('experiment', array_keys(Config::get('ab::experiments')));
        }

        return $query;
    }

}
