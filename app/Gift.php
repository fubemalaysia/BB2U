<?php 

namespace App;

use Illuminate\Database\Eloquent\Model;

class Gift extends Model {

	protected $fillable = [
		'name',
        'file',
        'price'
    ];

}
