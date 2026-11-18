<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_type_id');
            $table->unsignedBigInteger('user_id');
            $table->string('subject', 191);
            $table->string('slug', 191);
            $table->string('email', 191);
            $table->text('description');
            $table->enum('status', ['open', 'in_progress', 'reopen', 'pending_review', 'resolved', 'closed'])->default('open');
            $table->timestamps();
            $table->unique('slug', 'support_tickets_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
