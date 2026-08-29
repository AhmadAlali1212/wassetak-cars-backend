<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        //الشركة المصنعة kia
        'make',
        // موديل السيارة
        'model',
//سنة الصنع
        'year',
        //جديدة او مستعملة
        'condition',
        //وصف
        'description',
//وين مكان السيارة
        'location',
        'latitude',
        'longitude',
        'images',
        'approval_status',
        'approval_reason',
        'approved_at',
        // ... الحقول القديمة ...
    'is_inspected',
    'inspection_report_path',
    'inspected_at',
    'bulk_upload_batch'
    ];

    protected $casts = [
        //سيعامل الصور على انها مصفوفة لسهولة العرض
        'images' => 'array',
        'approved_at' => 'datetime',
    ];

    // علاقة مع المستخدم (المالك)
    public function user()
    {
        //كل سيارة تنتمي إلى مستخدم واحد

        return $this->belongsTo(User::class);
    }

    // علاقة مع إعلانات البيع
    public function ad()
    {
        // كل سيارة لها اعلان بيع واحد

        return $this->hasOne(Ad::class);
    }

    // علاقة مع إعلانات التأجير
    public function rentalAd()
    {
        return $this->hasOne(RentalAd::class);
    }

    // علاقة مع المزاد
    public function auction()
    {
        return $this->hasOne(Auction::class);
    }


    /** جميع إعلانات البيع المرتبطة بالسيارة. */
    public function saleAds()
    {
        return $this->hasMany(Ad::class);
    }

    /** آخر إعلان بيع للسيارة، بما في ذلك الحالات الداخلية للمالك. */
    public function latestSaleAd()
    {
        return $this->hasOne(Ad::class)->latestOfMany();
    }

    /** جميع إعلانات التأجير المرتبطة بالسيارة. */
    public function rentalAds()
    {
        return $this->hasMany(RentalAd::class);
    }

    /** سجلات المفضلة (من أضاف هذه السيارة لمفضلته). */
    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    /** المستخدمون الذين أضافوا هذه السيارة لمفضلتهم. */
    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    /** آخر إعلان تأجير للسيارة، بما في ذلك الحالات الداخلية للمالك. */
    public function latestRentalAd()
    {
        return $this->hasOne(RentalAd::class)->latestOfMany();
    }

    /** جميع المزادات المرتبطة بالسيارة. */
    public function auctions()
    {
        return $this->hasMany(Auction::class);
    }

    /** آخر مزاد مرتبط بالسيارة. */
    public function latestAuction()
    {
        return $this->hasOne(Auction::class)->latestOfMany();
    }
}
