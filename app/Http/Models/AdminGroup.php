<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes AS SoftDeletes;

class AdminGroup extends Base {

    use SoftDeletes;
    public $table = 'admin_group';
    public $timestamps = true;
	public $primaryKey = 'admin_group_id';
    protected $dates = ['deleted_at'];
	
	public function __construct()
	{
		// set tables and keys
        $this->__table = $this->table;
		$this->primaryKey = $this->__table . '_id';
		$this->__keyParam = $this->primaryKey.'-';
		$this->hidden = array();
		
        // set fields
        $this->__fields   = array($this->primaryKey, 'name', 'created_at', 'updated_at', 'deleted_at');
	}

}