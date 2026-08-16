# تقرير مراجعة أخطاء المشروع

هذا الملف يشرح أهم الأخطاء والملاحظات التي ظهرت أثناء مراجعة مشروع `syria-motor-second`.

لم يتم تعديل كود التطبيق ضمن هذه المراجعة. هذا الملف توثيق فقط.

## 1. مسارات لوحة الإدارة تشير إلى دوال غير موجودة

**مكان الخطأ:**
- `routes/admin.php`
- `app/Http/Controllers/Admin/UserController.php`
- `app/Http/Controllers/Admin/AdController.php`
- `app/Http/Controllers/Admin/RentalAdController.php`
- `app/Http/Controllers/Admin/AuctionController.php`
- `app/Http/Controllers/Admin/TransactionController.php`
- `app/Http/Controllers/Admin/RatingController.php`

**شرح الخطأ:**
يوجد في ملف `routes/admin.php` مسارات `create` و `store` لعدة أقسام، لكن الكنترولرات المرتبطة لا تحتوي هذه الدوال.

أمثلة:
- المسار `admin/users/create` يستدعي `UserController@create`، لكن `UserController` لا يحتوي دالة `create`.
- المسار `admin/auctions/create` يستدعي `AuctionController@create`، لكن `AuctionController` لا يحتوي دالة `create`.
- المسار `admin/ratings/create` يستدعي `RatingController@create`، لكن `RatingController` لا يحتوي دالة `create`.

**الأثر:**
عند فتح هذه الصفحات أو الإرسال إليها سيظهر خطأ وقت التشغيل مثل:

```text
Method App\Http\Controllers\Admin\...Controller::create does not exist.
```

## 2. تسجيل المستخدم من الويب مكسور بسبب `account_type_id`

**مكان الخطأ:**
- `database/migrations/2014_10_12_000000_create_users_table.php`
- `app/Http/Controllers/Auth/RegisteredUserController.php`
- `resources/views/auth/register.blade.php`

**شرح الخطأ:**
جدول `users` يتطلب وجود `account_type_id`، لكن فورم التسجيل في الويب لا يطلب نوع الحساب، و `RegisteredUserController` لا يضيف قيمة لهذا الحقل عند إنشاء المستخدم.

**الأثر:**
المستخدم الجديد لا يستطيع التسجيل من واجهة الويب. قاعدة البيانات ترفض إنشاء المستخدم ويظهر خطأ:

```text
Field 'account_type_id' doesn't have a default value
```

## 3. `UserFactory` لا ينشئ مستخدمين صالحين للاختبار

**مكان الخطأ:**
- `database/factories/UserFactory.php`

**شرح الخطأ:**
ملف `UserFactory` لا يضيف قيمة `account_type_id` عند إنشاء مستخدمين وهميين، مع أن هذا الحقل مطلوب في قاعدة البيانات.

**الأثر:**
معظم اختبارات Laravel تفشل لأنها تحاول إنشاء مستخدم بدون `account_type_id`.

الخطأ الذي ظهر عند تشغيل الاختبارات:

```text
SQLSTATE[HY000]: General error: 1364 Field 'account_type_id' doesn't have a default value
```

## 4. Migration الخاص بربط نوع الحساب مكتوب بشكل معكوس

**مكان الخطأ:**
- `database/migrations/2025_07_10_105310_add_account_type_foreign_key_to_users_table.php`

**شرح الخطأ:**
دالة `up()` فارغة، بينما دالة `down()` تضيف foreign key.

المفروض العكس:
- `up()` تضيف التعديل.
- `down()` تلغي التعديل.

**الأثر:**
الـ foreign key بين `users.account_type_id` و `account_types.id` لا يتم إنشاؤه عند تشغيل migrations بشكل طبيعي. وكذلك rollback غير صحيح لأنه يضيف constraint بدل أن يحذفه.

## 5. صفحة تعديل التقييمات غير متوافقة مع الموديل وجدول قاعدة البيانات

**مكان الخطأ:**
- `resources/views/admin/ratings/edit.blade.php`
- `app/Http/Controllers/Admin/RatingController.php`
- `app/Models/Rating.php`
- `database/migrations/2025_07_10_100225_create_ratings_table.php`

**شرح الخطأ:**
صفحة تعديل التقييمات تستخدم أسماء حقول وعلاقات غير موجودة أو غير متطابقة:

- تستخدم `$rating->rater`، بينما العلاقة الموجودة في الموديل اسمها `user()`.
- تستخدم حقل `rating` في الفورم، بينما العمود في قاعدة البيانات اسمه `stars`.
- تستخدم حقل `status`، بينما جدول `ratings` لا يحتوي عمود `status`.

**الأثر:**
تعديل التقييم قد يفشل في validation، أو قد لا يحفظ البيانات المطلوبة بشكل صحيح.

## 6. صفحات المعاملات تستخدم أعمدة غير موجودة في قاعدة البيانات

**مكان الخطأ:**
- `resources/views/admin/transactions/edit.blade.php`
- `resources/views/admin/transactions/index.blade.php`
- `app/Http/Controllers/Admin/TransactionController.php`
- `database/migrations/2025_07_10_100215_create_transactions_table.php`

**شرح الخطأ:**
واجهة تعديل المعاملات والكنترولر يستخدمان حقولًا غير موجودة في جدول `transactions`:

- `payment_method`
- `transaction_date`

بينما جدول `transactions` يحتوي فقط على:

- `user_id`
- `type`
- `amount`
- `status`
- `description`
- `transactionable_id`
- `transactionable_type`
- `created_at`
- `updated_at`

**الأثر:**
صفحة تعديل المعاملة قد تفشل عند الحفظ، وقائمة المعاملات قد تعرض التاريخ بشكل غير صحيح لأنها تبحث عن `transaction_date` بدل استخدام `created_at` أو إضافة العمود فعليًا.

## 7. أنواع المعاملات غير موحّدة بين API ولوحة الإدارة

**مكان الخطأ:**
- `app/Http/Controllers/Admin/TransactionController.php`
- `resources/views/admin/transactions/edit.blade.php`
- `app/Http/Controllers/Api/WalletController.php`
- `database/migrations/2025_07_10_100215_create_transactions_table.php`

**شرح الخطأ:**
كود المحفظة في API ينشئ معاملات بأنواع مثل:

- `charge`
- `withdrawal_request`
- `commission`

لكن صفحة تعديل المعاملات في لوحة الإدارة تقبل فقط:

- `deposit`
- `withdrawal`
- `payment`
- `refund`

**الأثر:**
المعاملات التي ينشئها النظام من API قد لا يمكن تعديلها من لوحة الإدارة، لأن validation سيرفض نوع المعاملة.

## 8. API المزادات يكتب في عمود غير موجود اسمه `published_at`

**مكان الخطأ:**
- `app/Http/Controllers/Api/AuctionController.php`
- `database/migrations/2025_07_10_100120_create_auctions_table.php`

**شرح الخطأ:**
عند تفعيل المزاد، يوجد كود يكتب:

```php
$auction->published_at = now();
```

لكن جدول `auctions` لا يحتوي عمود `published_at`.

**الأثر:**
تفعيل المزاد من API قد يؤدي إلى خطأ عند حفظ الموديل في قاعدة البيانات.

## 9. API يفترض أن كل مستخدم لديه نوع حساب

**مكان الخطأ:**
- `app/Http/Controllers/Api/CarController.php`
- `app/Http/Controllers/Api/AuctionController.php`
- `app/Http/Controllers/Api/RentalAdController.php`
- `app/Http/Controllers/Api/SubscriptionController.php`

**شرح الخطأ:**
يوجد في عدة أماكن استخدام مباشر لهذا الشكل:

```php
$user->accountType->name
```

بدون التأكد أن `accountType` موجود فعلًا.

**الأثر:**
إذا وُجد مستخدم بدون `account_type_id` صحيح، قد يظهر خطأ:

```text
Attempt to read property "name" on null
```

وهذا مرتبط أيضًا بمشكلة تسجيل الويب و `UserFactory`.

## 10. الاختبارات تفشل حاليًا

**الأمر الذي تم تشغيله:**

```bash
php artisan test
```

**النتيجة:**

```text
20 failed, 5 passed
```

**السبب الأساسي:**
معظم الأخطاء بسبب أن `UserFactory` ينشئ مستخدمين بدون `account_type_id`، مع أن قاعدة البيانات تتطلب هذا الحقل.

**الأثر:**
حاليًا لا يمكن الاعتماد على الاختبارات كشبكة أمان قبل إصلاح إعداد المستخدمين وأنواع الحسابات.

## ملاحظات إضافية

الأوامر التالية نجحت:

```bash
php artisan route:list
php artisan view:cache
```

لكن نجاح `route:list` لا يعني أن كل route يعمل فعلًا؛ لأنه يعرض المسارات المسجلة فقط ولا يتأكد أن كل دالة داخل الكنترولر موجودة.

