<?php

use App\Enums\RateCardStatus;
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
        Schema::table('rate_cards', function (Blueprint $table) {
            $table->string('status')->default(RateCardStatus::PENDING->value)->after('rate');
            $table->foreignId('approved_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            $table->index(['component_id', 'status']);
        });

        // Set any existing records to approved so existing operations remain uninterrupted
        DB::table('rate_cards')->update([
            'status' => RateCardStatus::APPROVED->value,
            'approved_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rate_cards', function (Blueprint $table) {
            $table->dropIndex(['component_id', 'status']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['status', 'approved_by', 'approved_at']);
        });
    }
};
