<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\AccountType; // استدعاء موديل أنواع الحسابات
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletPaymentTest extends TestCase
{
    use RefreshDatabase;

    private $accountType;

    /**
     * دالة التجهيز: تعمل تلقائياً قبل كل تيست لتهيئة البيانات الأساسية
     */
    protected function setUp(): void
    {
        parent::setUp();

        // إنشاء نوع حساب افتراضي في قاعدة بيانات الاختبار لتجنب خطأ الـ Foreign Key
        $this->accountType = AccountType::create([
            'name' => 'client' // أو أي اسم افتراضي يعتمده تطبيقك
        ]);
    }

    /**
     * 1. اختبار بدء عملية الشحن بنجاح وتحولها لحالة معلقة
     */
    public function test_user_can_initiate_sham_cash_charge()
    {
        // إنشاء مستخدم وربطه بنوع الحساب المنشأ في الـ setUp
        $user = User::factory()->create([
            'account_type_id' => $this->accountType->id
        ]);

        $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 0]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/wallet/charge', [
            'amount' => 5000,
            'payment_method' => 'sham_cash',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'status', 'transaction'])
                 ->assertJson(['status' => 'pending']);

        $this->assertEquals(0, $wallet->fresh()->balance);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => 5000,
            'status' => 'pending',
            'payment_provider' => 'sham_cash',
        ]);
    }

    /**
     * 2. اختبار استقبال الـ Callback من سيرفر شام كاش بنجاح وزيادة الرصيد
     */
    public function test_sham_cash_callback_updates_wallet_and_transaction_on_success()
    {
        $user = User::factory()->create([
            'account_type_id' => $this->accountType->id
        ]);

        $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 0]);

        $fakeProviderTxId = 'SHAM-123456';
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => 'charge',
            'amount' => 7500,
            'status' => 'pending',
            'payment_provider' => 'sham_cash',
            'provider_transaction_id' => $fakeProviderTxId,
        ]);

        $response = $this->postJson('/api/wallet/shamcash/callback', [
            'provider_transaction_id' => $fakeProviderTxId,
            'status' => 'SUCCESS',
        ]);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'تم تحديث المحفظة وإتمام العملية بنجاح.']);

        $this->assertEquals(7500, $wallet->fresh()->balance);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'completed',
        ]);
    }

    /**
     * 3. اختبار حالة فشل الدفع عند طرف شام كاش وعدم زيادة الرصيد
     */
    public function test_sham_cash_callback_marks_transaction_as_failed_on_failure()
    {
        $user = User::factory()->create([
            'account_type_id' => $this->accountType->id
        ]);

        $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 0]);

        $fakeProviderTxId = 'SHAM-777777';
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => 'charge',
            'amount' => 3000,
            'status' => 'pending',
            'payment_provider' => 'sham_cash',
            'provider_transaction_id' => $fakeProviderTxId,
        ]);

        $response = $this->postJson('/api/wallet/shamcash/callback', [
            'provider_transaction_id' => $fakeProviderTxId,
            'status' => 'FAILED'
        ]);

        $response->assertStatus(200);

        $this->assertEquals(0, $wallet->fresh()->balance);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'failed',
        ]);
    }
}
