<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('offboardings', function (Blueprint $table) {
            $table->enum('cancellation_status', ['pending', 'in_progress', 'completed', 'not_required'])
                  ->nullable()
                  ->after('visa_sponsorship');
            $table->date('cancellation_date')->nullable()->after('cancellation_status');
            $table->string('cancellation_reference')->nullable()->after('cancellation_date');
            $table->string('cancellation_document')->nullable()->after('cancellation_reference');
            $table->text('cancellation_remarks')->nullable()->after('cancellation_document');
        });
    }

    public function down(): void
    {
        Schema::table('offboardings', function (Blueprint $table) {
            $table->dropColumn([
                'cancellation_status',
                'cancellation_date',
                'cancellation_reference',
                'cancellation_document',
                'cancellation_remarks',
            ]);
        });
    }
};
