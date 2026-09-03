<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            // translation: title like color or size
            $table->text('languages')->nullable();
            $table->string('type')->default('text');
            $table->boolean('filterable')->default(true);
            $table->boolean('comparable')->default(true);
            $table->boolean('special')->default(false);
            $table->unsignedInteger('order')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
