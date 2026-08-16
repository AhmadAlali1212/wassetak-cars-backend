<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // إضافة 'draft' كحالة جديدة لإعلانات البيع الناتجة عن الرفع الجماعي
        // قبل أن يحدد صاحبها السعر الفعلي (بدل استخدام 'pending' بشكل مضلِّل).
        DB::statement("ALTER TABLE ads MODIFY COLUMN status ENUM('draft', 'pending', 'active', 'rejected', 'sold') DEFAULT 'pending'");
    }

    public function down(): void
    {
        // تحويل أي إعلانات draft متبقية إلى pending قبل التراجع، لتفادي فقدان بيانات
        DB::table('ads')->where('status', 'draft')->update(['status' => 'pending']);

        DB::statement("ALTER TABLE ads MODIFY COLUMN status ENUM('pending', 'active', 'rejected', 'sold') DEFAULT 'pending'");
    }
};
