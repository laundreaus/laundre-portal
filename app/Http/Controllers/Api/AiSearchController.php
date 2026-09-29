<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;

/**
 * Ask-your-data search. Admins can ask across all laundromats; franchisees/investors
 * are scoped to their assigned laundromats. Answers use revenue + weather.
 * Scaffolded: works the moment AI_API_KEY is set in .env.
 */
class AiSearchController extends Controller {
    public function ask(Request $r) {
        $u = $r->user();
        $d = $r->validate(['q'=>'required|string', 'loc'=>'nullable|integer']);

        // Resolve scope
        $ids = null; // null = all (admin)
        if ($r->filled('loc')) {
            $loc = (int) $d['loc'];
            abort_unless($u->isAdmin() || in_array($loc, $u->locationIds()), 403);
            $ids = [$loc];
        } elseif (!$u->isAdmin()) {
            $ids = $u->locationIds();
        }

        $context = $this->buildContext($ids);

        $cfg = config('laundre_ai');
        if (empty($cfg['key'])) {
            return response()->json([
                'configured' => false,
                'answer' => "AI search isn't switched on yet — an API key needs to be added. Here's the data it would use:",
                'data' => $context,
            ]);
        }

        try {
            $answer = $this->callLlm($cfg, $d['q'], $context, $u->isAdmin());
            return response()->json(['configured'=>true, 'answer'=>$answer]);
        } catch (\Throwable $e) {
            return response()->json(['configured'=>true, 'answer'=>'Sorry — the AI service returned an error. '.$e->getMessage()], 200);
        }
    }

    private function buildContext(?array $ids): array {
        $tz = 'Australia/Brisbane';
        $to = Carbon::now($tz); $from = $to->copy()->subDays(30);
        $q = Sale::whereBetween('date', [$from->toDateString(), $to->toDateString()]);
        if ($ids !== null) $q->whereIn('location_id', $ids);
        $sales = $q->get();

        $names = Location::pluck('name', 'id');
        $byLocDay = [];
        $totals = [];
        foreach ($sales as $s) {
            $dt = substr((string) $s->date, 0, 10);
            $byLocDay[$s->location_id][$dt] = ($byLocDay[$s->location_id][$dt] ?? 0) + (float) $s->revenue;
            $totals[$s->location_id] = ($totals[$s->location_id] ?? 0) + (float) $s->revenue;
        }
        $locs = [];
        foreach ($totals as $id => $t) {
            $locs[] = ['laundromat'=>$names[$id] ?? ('#'.$id), 'revenue_30d'=>round($t, 2), 'daily'=>$byLocDay[$id] ?? []];
        }

        // Weather for a single-location scope (with coords)
        $weather = [];
        if ($ids !== null && count($ids) === 1) {
            $loc = Location::find($ids[0]);
            if ($loc && $loc->lat !== null) {
                try {
                    $resp = Http::timeout(10)->get('https://api.open-meteo.com/v1/forecast', [
                        'latitude'=>$loc->lat, 'longitude'=>$loc->lng,
                        'daily'=>'temperature_2m_max,precipitation_sum', 'timezone'=>'auto', 'past_days'=>30, 'forecast_days'=>1,
                    ]);
                    $wd = $resp->json('daily') ?? [];
                    foreach (($wd['time'] ?? []) as $i => $date) {
                        $weather[$date] = ['tmax'=>$wd['temperature_2m_max'][$i] ?? null, 'precip'=>$wd['precipitation_sum'][$i] ?? null];
                    }
                } catch (\Throwable $e) {}
            }
        }

        return ['period'=>['from'=>$from->toDateString(), 'to'=>$to->toDateString()], 'laundromats'=>$locs, 'weather'=>$weather];
    }

    private function callLlm(array $cfg, string $question, array $context, bool $isAdmin): string {
        $sys = "You are a concise data analyst for Laundré, a laundromat franchise. "
            . "Answer the user's question using ONLY the JSON data provided (revenue in AUD, GST-inclusive; weather from Open-Meteo). "
            . ($isAdmin ? "The user is an admin and may compare all laundromats." : "The user only sees their own laundromat(s).")
            . " If the data can't answer, say so briefly. Keep answers short and specific with numbers.";
        $userMsg = "Question: {$question}\n\nData:\n" . json_encode($context);

        if (($cfg['provider'] ?? 'anthropic') === 'openai') {
            $resp = Http::withToken($cfg['key'])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $cfg['model'] ?? 'gpt-4o-mini',
                'messages' => [['role'=>'system','content'=>$sys], ['role'=>'user','content'=>$userMsg]],
                'max_tokens' => 500,
            ]);
            return $resp->json('choices.0.message.content') ?? 'No answer.';
        }
        // Anthropic (default)
        $resp = Http::withHeaders(['x-api-key'=>$cfg['key'], 'anthropic-version'=>'2023-06-01'])
            ->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model' => $cfg['model'] ?? 'claude-3-5-haiku-latest',
                'max_tokens' => 500,
                'system' => $sys,
                'messages' => [['role'=>'user','content'=>$userMsg]],
            ]);
        return $resp->json('content.0.text') ?? 'No answer.';
    }
}
