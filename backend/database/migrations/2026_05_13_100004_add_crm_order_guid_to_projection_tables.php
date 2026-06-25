<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add crm_order_guid to local projection tables so every row can be
 * traced back to the order_request that created it.
 *
 * Also add crm_*_id columns where missing to support idempotent upserts
 * by CRM primary key.
 */
return new class extends Migration
{
    public function up(): void
    {
        // contracts
        if (!Schema::hasColumn('contracts', 'crm_order_guid')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->uuid('crm_order_guid')->nullable()->after('contractsID');
                $table->index('crm_order_guid', 'idx_contracts_crm_order_guid');
            });
        }

        // userspaymentsschedules
        if (!Schema::hasColumn('userspaymentsschedules', 'crm_order_guid')) {
            Schema::table('userspaymentsschedules', function (Blueprint $table) {
                $table->uuid('crm_order_guid')->nullable()->after('usersPaymentsSchedulesID');
                $table->index('crm_order_guid', 'idx_ups_crm_order_guid');
            });
        }

        // payments
        if (!Schema::hasColumn('payments', 'crm_order_guid')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->uuid('crm_order_guid')->nullable()->after('paymentsID');
                $table->index('crm_order_guid', 'idx_payments_crm_order_guid');
            });
        }

        // paymentsitems
        if (!Schema::hasColumn('paymentsitems', 'crm_order_guid')) {
            Schema::table('paymentsitems', function (Blueprint $table) {
                $table->uuid('crm_order_guid')->nullable()->after('paymentsItemsID');
                $table->index('crm_order_guid', 'idx_paymentsitems_crm_order_guid');
            });
        }
    }

    public function down(): void
    {
        foreach (['contracts', 'userspaymentsschedules', 'payments', 'paymentsitems'] as $tbl) {
            if (Schema::hasColumn($tbl, 'crm_order_guid')) {
                Schema::table($tbl, fn (Blueprint $t) => $t->dropColumn('crm_order_guid'));
            }
        }
    }
};