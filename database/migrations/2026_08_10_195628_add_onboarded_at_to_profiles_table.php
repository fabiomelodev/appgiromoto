<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            // Null while the account hasn't picked motoboy/restaurante yet.
            $table->timestamp('onboarded_at')->nullable()->after('role');
        });

        // Accounts that already existed (seeded/demo/real) were already using
        // the app; only new signups from now on go through onboarding.
        DB::table('profiles')->update(['onboarded_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('onboarded_at');
        });
    }
};
