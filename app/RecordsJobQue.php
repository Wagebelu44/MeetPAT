<?php

namespace MeetPAT;

use Illuminate\Database\Eloquent\Model;

class RecordsJobQue extends Model
{
    //
    protected $fillable = ['audience_file_id', 'user_id', 'status', 'records', 'records_completed'];

    public function audience_file()
    {
        return $this->belongsTo('\MeetPAT\AudienceFile', 'audience_file_id');
    }
}
