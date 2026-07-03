<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Program wsparcia Fundacji Świat Tańca (moduł SUP z planu Etapu I).
 * Subskrypcja 5 zł/mies. per użytkownik + historia wpłat. Kolumny crm_id
 * i statusy sync przygotowane pod przyszłą integrację z CRM (outbox pattern);
 * realne obciążenia cykliczne wejdą wraz z integracją operatora płatności.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('users_id')->index();
            // active | paused | cancelled
            $table->string('status', 20)->default('active')->index();
            $table->decimal('monthly_amount', 8, 2);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->date('next_payment_at')->nullable();
            // np. 'paynow', 'tpay' — na razie informacyjnie.
            $table->string('payment_method', 40)->nullable();
            $table->unsignedBigInteger('crm_id')->nullable();
            $table->timestamps();

            $table->unique('users_id', 'support_subscription_unique_user');
        });

        Schema::create('support_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('support_subscription_id')->index();
            $table->unsignedInteger('users_id')->index();
            $table->decimal('amount', 8, 2);
            // first | recurring
            $table->string('type', 20)->default('recurring');
            // paid | pending | failed
            $table->string('status', 20)->default('paid')->index();
            // Okres rozliczeniowy, którego dotyczy wpłata — idempotencja
            // naliczeń support:process-renewals (jedna wpłata per okres).
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('crm_id')->nullable();
            $table->timestamps();

            $table->unique(
                ['support_subscription_id', 'due_date'],
                'support_payment_unique_period'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_payments');
        Schema::dropIfExists('support_subscriptions');
    }
};
