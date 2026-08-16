<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountType extends Model
{
    use HasFactory;

    protected $fillable = [
     // admin, individual, company
        'name',
        //مثلا بقول admin 
        'description',
    ];


        // علاقة مع المستخدمين
    public function users()
    //يعني نوع الحساب ممكن ان ينتمي له عدة اشخاص 
    {
        return $this->hasMany(User::class);
    }
}
