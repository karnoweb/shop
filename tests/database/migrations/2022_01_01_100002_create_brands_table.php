<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->text('languages')->nullable();
            $table->string('slug')->unique()->index();
            $table->unsignedInteger('ordering')->default(1);
            $table->boolean('published')->default(true);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->json('extra_attributes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
