<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Volunteer extends Model
{
  protected $table = 'volunteers';

  public function committee(){
      return $this->belongsTo(Committee::class);
  }
  public function comment(){
      return $this->hasMany(Comment::class);
  }
  public function post(){
      return $this->hasMany(Post::class);
  }
  public function task(){
      return $this->hasMany(Task::class);
  }
}
