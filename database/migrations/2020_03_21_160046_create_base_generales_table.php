<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBaseGeneralesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('base_generales', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('type_id');
            $table->foreign('type_id')->references('id')->on('types');

            $table->unsignedBigInteger('theme_id');
            $table->foreign('theme_id')->references('id')->on('themes');
            
            $table->unsignedBigInteger('systeme_id');
            $table->foreign('systeme_id')->references('id')->on('systems');

            $table->text('title');
            $table->longText('description')->nullable();
            $table->string('pdf')->nullable();
            $table->date('date_exigence')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('base_generales');
    }
}
