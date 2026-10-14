<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lakes (seeded per region) and the Environment Agency's designated
 * bathing waters we track: beaches, lake swimming sites and the river
 * sites that sit on our spots. Samples and classifications build up
 * history the bathing water list itself does not keep.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->json('bathing_water_districts')->nullable()->after('overflow_feeds')->comment('Local authority districts whose coastal bathing waters belong to this region');
        });

        Schema::create('lakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
            $table->string('gauge_reference')->nullable()->comment('EA flood monitoring station reference for the lake level');
            $table->foreignId('gauge_station_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['region_id', 'slug']);
        });

        Schema::create('bathing_waters', function (Blueprint $table) {
            $table->id();
            $table->string('eubwid')->unique()->comment('EA bathing water identifier (eubwidNotation)');
            $table->string('sampling_point')->comment('EA sampling point notation');
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lake_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('spot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 16)->comment('coastal, estuary, lake or river');
            $table->string('name');
            $table->string('slug');
            $table->string('district')->nullable();
            $table->string('sewerage_undertaker')->nullable();
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
            $table->text('description')->nullable();
            $table->string('classification', 16)->nullable();
            $table->unsignedSmallInteger('classification_year')->nullable();
            $table->string('risk_level', 16)->nullable()->comment('Latest pollution risk forecast: normal or increased');
            $table->timestamp('risk_expires_at')->nullable();
            $table->boolean('affected_by_heavy_rain')->default(false);
            $table->date('season_starts_on')->nullable();
            $table->date('season_ends_on')->nullable();
            $table->timestamp('history_fetched_at')->nullable();
            $table->timestamps();

            $table->index(['region_id', 'kind']);
        });

        Schema::create('bathing_water_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bathing_water_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sampled_at');
            $table->unsignedInteger('e_coli')->nullable()->comment('Escherichia coli per 100 ml');
            $table->string('e_coli_qualifier', 2)->nullable()->comment('= exact, < below or > above the reported count');
            $table->unsignedInteger('enterococci')->nullable()->comment('Intestinal enterococci per 100 ml');
            $table->string('enterococci_qualifier', 2)->nullable();
            $table->timestamps();

            $table->unique(['bathing_water_id', 'sampled_at']);
        });

        Schema::create('bathing_water_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bathing_water_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('classification', 16);
            $table->timestamps();

            $table->unique(['bathing_water_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bathing_water_classifications');
        Schema::dropIfExists('bathing_water_samples');
        Schema::dropIfExists('bathing_waters');
        Schema::dropIfExists('lakes');

        Schema::table('regions', function (Blueprint $table) {
            $table->dropColumn('bathing_water_districts');
        });
    }
};
