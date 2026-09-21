<?php

use App\Support\Themes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('theme', Themes::DARK)->update(['theme' => Themes::ARGENTINA_DARK]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('theme', 20)->default(Themes::ARGENTINA_DARK)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme', 20)->default(Themes::DARK)->change();
        });
    }
};