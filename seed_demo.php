<?php
/**
 * One-off demo seeder for STAGING review. Creates ~5 records in each new section at
 * varied stages. Safe to re-run: it clears its own demo rows first (tagged demo=1 / DEMO).
 * Delete this file after running.
 */
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Enquiry;
use App\Models\Location;
use App\Models\PipelineCard;
use App\Models\Setting;

function d($n){ return now()->addDays($n)->toDateString(); }
$out = [];

/* ---------------- 1) Contacts (enquiries) ---------------- */
Enquiry::where('source','demo')->delete();
$enq = [
  ['name'=>'Priya Nair','email'=>'priya.nair@example.com','phone'=>'+61400111222','city'=>'Sunshine Coast','type'=>'franchisee','status'=>'new','message'=>'Interested in a franchise on the Sunshine Coast. What are the next steps?'],
  ['name'=>'Tom Beckett','email'=>'tom.beckett@example.com','phone'=>'+61400333444','city'=>'Newcastle','type'=>'investor','status'=>'new','message'=>'Looking to invest passively across 2-3 sites. Please send info.'],
  ['name'=>'Sarah Lim','email'=>'sarah.lim@example.com','phone'=>'+61400555666','city'=>'Gold Coast','type'=>'franchisee','status'=>'approved','message'=>'Have run retail before, keen to own and operate. Budget ready.'],
  ['name'=>'Marcus Webb','email'=>'marcus.webb@example.com','phone'=>'+61400777888','city'=>'Brisbane','type'=>'general','status'=>'new','message'=>'General question about the online course and pricing.'],
  ['name'=>'Ava Thompson','email'=>'ava.thompson@example.com','phone'=>'+61400999000','city'=>'Toowoomba','type'=>'franchisee','status'=>'dismissed','message'=>'Enquired but timing not right this year.'],
];
foreach ($enq as $e) { $e['source']='demo'; Enquiry::create($e); }
$out[] = 'Enquiries: '.count($enq);

/* ---------------- 2) Live Laundromats (locations) ---------------- */
Location::where('notes','like','DEMO%')->delete();
$locs = [
  // trading (has opening_date in the past)
  ['name'=>'Laundré Maroochydore','address'=>'12 Ocean St, Maroochydore QLD 4558','lat'=>-26.6560,'lng'=>153.0920,'opening_date'=>d(-120),'keys_date'=>d(-200)],
  ['name'=>'Laundré Ipswich','address'=>'88 Brisbane St, Ipswich QLD 4305','lat'=>-27.6146,'lng'=>152.7600,'opening_date'=>d(-45),'keys_date'=>d(-110)],
  // in-build (opening_date in the future)
  ['name'=>'Laundré Robina','address'=>'5 Robina Town Centre Dr, Robina QLD 4226','lat'=>-28.0770,'lng'=>153.3930,'opening_date'=>d(40),'keys_date'=>d(-10)],
  ['name'=>'Laundré Redcliffe','address'=>'201 Oxley Ave, Redcliffe QLD 4020','lat'=>-27.2300,'lng'=>153.1100,'opening_date'=>d(75),'keys_date'=>d(15)],
  ['name'=>'Laundré Ballina','address'=>'44 River St, Ballina NSW 2478','lat'=>-28.8650,'lng'=>153.5650,'opening_date'=>d(110),'keys_date'=>d(55)],
];
foreach ($locs as $i=>$l) {
  Location::create(array_merge($l, [
    'radius'=>3,'unit'=>'km','status'=>'active','date_approved'=>d(-220 + $i*10),
    'notes'=>'DEMO seed laundromat','about'=>['demo'=>true],'modules'=>[],
  ]));
}
$out[] = 'Locations: '.count($locs);
$newLocs = Location::where('notes','like','DEMO%')->get();

/* ---------------- 3) Franchise CRM pipeline ---------------- */
// Ensure custom columns exist (in addition to locked leads / nda_sent / reviewing_documents)
Setting::put('pipeline_columns', [
  ['id'=>'leads','name'=>'Leads','color'=>'#C4703F','locked'=>true],
  ['id'=>'nda_sent','name'=>'NDA sent','color'=>'#8a9790','locked'=>true],
  ['id'=>'reviewing_documents','name'=>'Reviewing Documents','color'=>'#6b8e9e','locked'=>true],
  ['id'=>'meeting','name'=>'Meeting booked','color'=>'#6E8B7B','locked'=>false],
  ['id'=>'approved','name'=>'Approved','color'=>'#2f6b46','locked'=>false],
  ['id'=>'nos','name'=>"No's",'color'=>'#B4472F','locked'=>false],
]);
PipelineCard::where('source','demo')->delete();
$cards = [
  ['name'=>'Priya Nair','email'=>'priya.nair@example.com','phone'=>'+61400111222','city'=>'Sunshine Coast','stage'=>'leads','type'=>'franchisee','notes'=>'Web enquiry, to qualify by phone.'],
  ['name'=>'Sarah Lim','email'=>'sarah.lim@example.com','phone'=>'+61400555666','city'=>'Gold Coast','stage'=>'nda_sent','type'=>'franchisee','notes'=>'NDA sent, awaiting signature.'],
  ['name'=>'Daniel Cho','email'=>'daniel.cho@example.com','phone'=>'+61401222333','city'=>'Brisbane','stage'=>'reviewing_documents','type'=>'franchisee','notes'=>'Signed NDA, reviewing FDD.'],
  ['name'=>'Rebecca Stone','email'=>'rebecca.stone@example.com','phone'=>'+61401444555','city'=>'Cairns','stage'=>'meeting','type'=>'investor','notes'=>'Meeting booked with Luke next week.'],
  ['name'=>'Owen Price','email'=>'owen.price@example.com','phone'=>'+61401666777','city'=>'Townsville','stage'=>'nos','type'=>'general','notes'=>'Not proceeding — budget.'],
];
foreach ($cards as $c) {
  $c['source']='demo'; $c['contact']=$c['name'];
  $c['position']=(int)(PipelineCard::where('stage',$c['stage'])->max('position')+1);
  $c['stage_changed_at']=now()->subDays(rand(1,20));
  PipelineCard::create($c);
}
$out[] = 'Pipeline cards: '.count($cards);

/* ---------------- 4) Project Delivery ---------------- */
function milestones($keys,$fitout,$open,$doneUpTo){
  $ms=[
    ['t'=>'Keys handover','d'=>$keys,'done'=>$doneUpTo>=1],
    ['t'=>'Fit-out start','d'=>$fitout,'done'=>$doneUpTo>=2],
    ['t'=>'Services & rough-in','d'=>date('Y-m-d',strtotime($fitout.' +21 days')),'done'=>$doneUpTo>=3],
    ['t'=>'Machine install','d'=>date('Y-m-d',strtotime($open.' -14 days')),'done'=>$doneUpTo>=4],
    ['t'=>'Commissioning & test','d'=>date('Y-m-d',strtotime($open.' -3 days')),'done'=>$doneUpTo>=5],
    ['t'=>'Open for trade','d'=>$open,'done'=>$doneUpTo>=6],
  ];
  return $ms;
}
$projects=[];
$pd = [
  ['name'=>'Laundré Robina','keys'=>d(-10),'fitout'=>d(0),'open'=>d(40),'done'=>2],
  ['name'=>'Laundré Redcliffe','keys'=>d(15),'fitout'=>d(25),'open'=>d(75),'done'=>0],
  ['name'=>'Laundré Ballina','keys'=>d(55),'fitout'=>d(65),'open'=>d(110),'done'=>0],
  ['name'=>'Laundré Ipswich','keys'=>d(-110),'fitout'=>d(-95),'open'=>d(-45),'done'=>6],
  ['name'=>'Laundré Maroochydore','keys'=>d(-200),'fitout'=>d(-185),'open'=>d(-120),'done'=>6],
];
foreach ($pd as $i=>$p) {
  $loc = $newLocs->firstWhere('name',$p['name']);
  $projects[] = [
    'id'=>'p'.substr(md5($p['name']),0,8),
    'location_id'=>$loc?->id,
    'name'=>$p['name'],
    'keys_date'=>$p['keys'],'fitout_date'=>$p['fitout'],'opening_date'=>$p['open'],
    'created'=>now()->toIso8601String(),
    'milestones'=>milestones($p['keys'],$p['fitout'],$p['open'],$p['done']),
  ];
}
Setting::put('project_delivery_v1', ['projects'=>$projects]);
$out[] = 'Delivery projects: '.count($projects);

/* ---------------- 5) Sites Acquisition ---------------- */
function sid($s){ return 's'.substr(md5($s),0,10); }
$sites = [
  ['name'=>'Laundré Gympie','stage'=>'location','f'=>['suburb'=>'Gympie','state'=>'QLD','address'=>'Mary St, Gympie','lat'=>-26.190,'lng'=>152.665,'radius'=>3,'territory_notes'=>'Regional hub, limited competition.']],
  ['name'=>'Laundré Nambour','stage'=>'terms','f'=>['suburb'=>'Nambour','state'=>'QLD','address'=>'Currie St, Nambour','lat'=>-26.626,'lng'=>152.959,'radius'=>3,'terms_theirs'=>'$55k/yr, 5+5, 3 months incentive','terms_suggested'=>'$48k/yr, 6 months incentive']],
  ['name'=>'Laundré Caloundra','stage'=>'price','f'=>['suburb'=>'Caloundra','state'=>'QLD','address'=>'Bulcock St, Caloundra','lat'=>-26.803,'lng'=>153.128,'radius'=>3,'turnkey_price'=>420000,'keys_date'=>d(30),'fitout_date'=>d(40),'opening_date'=>d(95)]],
  ['name'=>'Laundré Lismore','stage'=>'package','f'=>['suburb'=>'Lismore','state'=>'NSW','address'=>'Molesworth St, Lismore','lat'=>-28.813,'lng'=>153.277,'radius'=>3,'turnkey_price'=>465000,'assigned_name'=>'Sarah Lim','assigned_email'=>'sarah.lim@example.com']],
  ['name'=>'Laundré Coffs Harbour','stage'=>'intake','f'=>['suburb'=>'Coffs Harbour','state'=>'NSW','address'=>'Harbour Dr','lat'=>-30.296,'lng'=>153.114,'radius'=>3,'contact_name'=>'Agent — Ray White','contact_phone'=>'+61266000000','intake_notes'=>'Fresh lead from agent.']],
];
$sa=[];
foreach ($sites as $s){ $s['id']=sid($s['name']); $s['created']=now()->toIso8601String(); $s['shareToken']=sid($s['name'].'tok').sid($s['name']); $sa[]=$s; }
Setting::put('site_acq_v1', ['sites'=>$sa]);
$out[] = 'Sites acquisition: '.count($sa);

/* ---------------- 6) Cashflow projections ---------------- */
Setting::put('cashflow_v1', ['opening'=>50000, 'rows'=>[
  ['date'=>d(-60),'desc'=>'Franchise fee — Maroochydore','in'=>60000,'out'=>''],
  ['date'=>d(-50),'desc'=>'Fit-out — Robina (deposit)','in'=>'','out'=>85000],
  ['date'=>d(-30),'desc'=>'Franchise fee — Ipswich','in'=>60000,'out'=>''],
  ['date'=>d(-20),'desc'=>'Equipment order — Redcliffe','in'=>'','out'=>120000],
  ['date'=>d(-10),'desc'=>'Royalties (monthly)','in'=>18000,'out'=>''],
  ['date'=>d(5),'desc'=>'Rent & overheads','in'=>'','out'=>22000],
  ['date'=>d(20),'desc'=>'Franchise fee — Robina','in'=>60000,'out'=>''],
]]);
$out[] = 'Cashflow rows seeded';

echo "DEMO SEED COMPLETE\n - ".implode("\n - ", $out)."\n";
