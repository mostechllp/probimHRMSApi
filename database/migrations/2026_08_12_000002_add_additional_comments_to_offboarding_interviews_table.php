<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('offboarding_interviews', 'additional_comments')) {
            Schema::table('offboarding_interviews', function (Blueprint $table) {
                $table->text('additional_comments')->nullable()->after('would_recommend');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('offboarding_interviews', 'additional_comments')) {
            Schema::table('offboarding_interviews', function (Blueprint $table) {
                $table->dropColumn('additional_comments');
            });
        }
    }
};
