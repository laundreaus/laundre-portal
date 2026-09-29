<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
// Import prospective sites from Rex Commercial (Zeyad Hijazi) + the Jimboomba inspection. Deduped by name.
return new class extends Migration {
    public function up(): void {
        $agent = ['agent_name'=>'Zeyad Hijazi','agent_email'=>'zeyad@rexcommercial.com.au','agent_phone'=>'0494 154 189'];
        $rows = [
            ['name'=>'Jimboomba — 133 Brisbane St (Unit 3)', 'amount'=>null, 'lat'=>-27.831000,'lng'=>153.033000,
             'notes'=>'Unit 3, 133 Brisbane St, Jimboomba. Inspected. Agent: Rex Commercial (Zeyad).', 'agent'=>true],
            ['name'=>'Salisbury Central', 'amount'=>'$62,500 pa + GST + Outs', 'lat'=>-27.560000,'lng'=>153.035000,
             'notes'=>'655 Toohey Rd, Salisbury QLD 4107. 125 sqm retail next to the barber shop.', 'agent'=>true],
            ['name'=>'Rochedale Village', 'amount'=>'$117,280 pa (146 sqm) / $74,610 pa (82 sqm cafe)', 'lat'=>-27.583000,'lng'=>153.130000,
             'notes'=>'549 Underwood Rd, Rochedale. Multiple options. Tenancy near post shop (square) 146 sqm @ $117,280 pa negotiable; cafe spot near Drakes (can be defitted) 82 sqm @ $74,610 pa negotiable.', 'agent'=>true],
            ['name'=>'Logan City Centre', 'amount'=>'$97,200 pa + GST (121 sqm)', 'lat'=>-27.640000,'lng'=>153.109000,
             'notes'=>'2 Wembley Rd, Logan Central QLD 4114. Best tenancy = 66, external to the centre near the newsagency, 121 sqm.', 'agent'=>true],
            ['name'=>'Yarrabilba HQ', 'amount'=>'$59,700 pa + GST + Outs (190 sqm)', 'lat'=>-27.792000,'lng'=>153.115000,
             'notes'=>'Unit B1, 190 sqm. IM on file. Construction completes end of this year; ready for occupation January. ', 'agent'=>true],
        ];
        $existing = DB::table('site_prospects')->pluck('name')->map(fn ($n) => mb_strtolower(trim($n)))->all();
        $pos = (int) DB::table('site_prospects')->max('position');
        $now = now();
        foreach ($rows as $r) {
            if (in_array(mb_strtolower($r['name']), $existing)) continue;
            $row = [
                'name'=>$r['name'], 'stage'=>'prospect', 'position'=>++$pos,
                'amount'=>$r['amount'], 'notes'=>$r['notes'], 'lat'=>$r['lat'], 'lng'=>$r['lng'],
                'created_at'=>$now, 'updated_at'=>$now,
            ];
            if (!empty($r['agent'])) { $row['agent_name']=$agent['agent_name']; $row['agent_email']=$agent['agent_email']; $row['agent_phone']=$agent['agent_phone']; }
            DB::table('site_prospects')->insert($row);
        }
    }
    public function down(): void {}
};
