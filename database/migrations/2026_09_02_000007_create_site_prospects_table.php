<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Site acquisition pipeline (prospective centres) shown on the Site Analyzer:
// map with pins + a CRM board (prospect → offer received → counter offer → passed → accepted → in design).
return new class extends Migration {
    public function up(): void {
        Schema::create('site_prospects', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('stage', 40)->default('prospect');
            $t->integer('position')->default(0);
            $t->string('target')->nullable();   // fuzzy timing, e.g. "Feb 27", "NOW"
            $t->string('amount')->nullable();    // fuzzy $, e.g. "$300…"
            $t->text('notes')->nullable();
            // agent contact
            $t->string('agent_name')->nullable();
            $t->string('agent_email')->nullable();
            $t->string('agent_phone')->nullable();
            // centre / landlord contact
            $t->string('centre_name')->nullable();
            $t->string('centre_email')->nullable();
            $t->string('centre_phone')->nullable();
            $t->decimal('lat', 10, 6)->nullable();
            $t->decimal('lng', 10, 6)->nullable();
            $t->timestamps();
            $t->index(['stage', 'position']);
        });

        // Seed the initial prospect list (all into "prospect" — the team drags them from there).
        $rows = [
            ['Hervey Bay',            'Design · target 1st Nov',                    -25.290000, 152.840000],
            ['Miami',                 '1st Nov · $300…',                            -28.070000, 153.440000],
            ['Trinity Beach',         'Labelled "Rance"? · 1st March · $300…',      -16.790000, 145.700000],
            ['Hervey Bay #2',         'Late 27',                                    -25.310000, 152.860000],
            ['Salamander Bay',        'XX SPLIT??',                                 -32.720000, 152.070000],
            ['Victoria Point',        '??? ADAM',                                   -27.580000, 153.300000],
            ['Gympie',                'Feb 27',                                     -26.190000, 152.660000],
            ['Narangba',              'Feb / Mar / mid 27',                         -27.200000, 152.960000],
            ['Jimboomba',             'NOW',                                        -27.830000, 153.030000],
            ['Coolum Village',        '',                                           -26.530000, 153.090000],
            ['Belmont North',         '',                                           -32.990000, 151.660000],
            ['Tamworth Sth',          '',                                           -31.100000, 150.930000],
            ['Anderson Grove',        'Location to confirm',                        null,       null],
            ['Busselton',             '',                                           -33.650000, 115.350000],
            ['Wodonga',               '',                                           -36.120000, 146.890000],
            ['Orange',                '',                                           -33.280000, 149.100000],
            ['Thurgoona Village',     '',                                           -36.030000, 146.980000],
            ['Mountain View (Evolve)','Location to confirm',                        null,       null],
            ['Sth Australia',         'State only — pin to confirm',                null,       null],
            ['Cabarita',              '',                                           -28.340000, 153.570000],
        ];
        $now = now();
        $pos = 1;
        $insert = [];
        foreach ($rows as $r) {
            $insert[] = [
                'name' => $r[0], 'stage' => 'prospect', 'position' => $pos++,
                'notes' => $r[1] ?: null, 'lat' => $r[2], 'lng' => $r[3],
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        DB::table('site_prospects')->insert($insert);
    }
    public function down(): void { Schema::dropIfExists('site_prospects'); }
};
