<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('price_lists')
            ->where('code', 'retail_gel')
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return;
        }

        $now = now();

        DB::table('price_lists')->insert([
            'code' => 'retail_gel',
            'name' => 'Retail GEL',
            'currency_code' => 'GEL',
            'status' => 'active',
            'is_default' => true,
            'priority' => 0,
            'prices_include_tax' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('price_lists')
            ->where('code', 'retail_gel')
            ->where('name', 'Retail GEL')
            ->whereNull('deleted_at')
            ->delete();
    }
};
