<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campuses', function (Blueprint $table) {
            $table->id();

            // Short code used throughout the register: MIIT, MIAT, RCMP, UIO, TBD...
            $table->string('code', 20)->unique();
            $table->string('name');

            // UIO and TBD are not teaching institutes; flag them so reporting can
            // exclude or group them separately from the 12 real campuses.
            $table->boolean('is_institute')->default(true);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campuses');
    }
};
