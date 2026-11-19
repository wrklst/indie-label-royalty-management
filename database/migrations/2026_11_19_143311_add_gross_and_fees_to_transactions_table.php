<?php

use App\Support\Decimal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What customers paid before anyone took a cut, and what the
     * distributor and the payment provider kept, in the currency of the
     * transaction. Null where the statement does not say.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('gross_total', 30, 15)->nullable()->after('total');
            $table->decimal('distribution_fee', 30, 15)->nullable()->after('gross_total');
            $table->decimal('payment_fee', 30, 15)->nullable()->after('distribution_fee');
        });

        // Rightz Audio lines kept their statement line, which names the agency's commission.
        DB::table('transactions')->where('importer_source', 'rightz-audio')->whereNotNull('raw_data')->orderBy('id')
            ->each(function (object $transaction): void {
                $commission = str_replace(',', '.', (string) (json_decode((string) $transaction->raw_data, true)['Total RightzAudio [€]'] ?? ''));

                if (is_numeric($commission)) {
                    DB::table('transactions')->where('id', $transaction->id)->update([
                        'gross_total' => Decimal::add((string) $transaction->total, $commission),
                        'distribution_fee' => $commission,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['gross_total', 'distribution_fee', 'payment_fee']);
        });
    }
};
