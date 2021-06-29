<?php

namespace App\Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

class LevelModel extends Model {


  protected $table = "member_levels";
  protected $fillable = ['level_name', 'point', 'description','level_number'];

  const YES = 'yes';
  const NO = 'no';

  const CANDY_PAYMENT = 'candy';
  const VIDEO_PAYMENT = 'video';
  const IMAGE_PAYMENT = 'image';

  const CREATED_AT = 'createdAt';
  const UPDATED_AT = 'updatedAt';

}
