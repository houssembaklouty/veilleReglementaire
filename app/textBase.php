<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class textBase extends Model
{

	protected $table = 'text_bases';

	protected $appends = ['from_date_exigence'];

	public function getFromDateExigenceAttribute()
	{
	    return \Carbon\Carbon::parse($this->attributes['date_exigence'])->format('d-m-Y');
	}

	public function systeme()
	{
	    return $this->belongsTo('App\System', 'systeme_id');
	}

	public function theme()
	{
	    return $this->belongsTo('App\Theme', 'theme_id');
	}

	public function type()
	{
	    return $this->belongsTo('App\Type', 'type_id');
	}
}
