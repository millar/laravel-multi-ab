<?php namespace Millar\AB\Models;

use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Variant extends Eloquent {

    public $timestamps = false;

    protected $fillable = ['experiment', 'name', 'visitors', 'engagement', 'experiment_variant'];

    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        // Set the connection based on the config.
        $this->connection = Config::get('ab::connection');
    }

    public function goals()
    {
        return $this->hasMany('Millar\AB\Models\Goal', 'variant', 'name')->where('experiment', $this->experiment);
    }

    public function scopeActive($query)
    {
        if ($experiments = Config::get('ab::experiments'))
        {
            $experiment_variants = [];
            foreach (Config::get('ab::experiments') as $experiment => $variants){
                foreach ($variants as $variant){
                    array_push($experiment_variants, "$experiment.$variant");
                }
            }
            
            return $query->whereIn('experiment_variant', $experiment_variants);
        }

        return $query;
    }

}
