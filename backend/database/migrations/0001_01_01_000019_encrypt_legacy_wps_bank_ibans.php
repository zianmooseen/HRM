<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->encryptColumn('employees', 'bank_iban');
        $this->encryptColumn('wps_payroll_batch_items', 'bank_iban');
    }

    public function down(): void
    {
        // Encryption of sensitive payroll data must not be reversed.
    }

    private function encryptColumn(string $table, string $column): void
    {
        DB::table($table)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($table, $column): void {
                foreach ($rows as $row) {
                    $value = $row->{$column};

                    try {
                        Crypt::decryptString($value);

                        continue;
                    } catch (DecryptException) {
                        // Legacy rows were stored as plaintext before encrypted casts were introduced.
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update([$column => Crypt::encryptString($value)]);
                }
            });
    }
};
