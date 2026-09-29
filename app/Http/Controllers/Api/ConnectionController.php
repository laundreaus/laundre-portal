<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Setting;
use App\Models\XeroConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Connected Systems status + the Xero demo connection.
 * Real Xero OAuth will replace xeroDemo() later (each laundromat = its own Xero org).
 */
class ConnectionController extends Controller {
    public function status(Request $r) {
        $loc = (int) $r->query('loc');
        $conn = XeroConnection::where('location_id', $loc)->first();
        return response()->json([
            'xero' => (bool) Setting::get('xero_connected_' . $loc, false),
            'xero_mode' => Setting::get('xero_mode_' . $loc, null), // 'demo' | 'live' | null
            'xero_connection' => $conn ? ['org_name'=>$conn->org_name, 'status'=>$conn->status, 'last_sync_at'=>$conn->last_sync_at] : null,
        ]);
    }

    // Base per-laundromat Xero connection (each laundromat = its own Xero org).
    // Records the organisation + marks it pending; the OAuth handshake is wired later.
    public function xeroSetup(Request $r) {
        abort_unless($r->user()->isAdmin(), 403);
        $d = $r->validate(['location_id'=>'required|exists:locations,id', 'org_name'=>'nullable|string']);
        $conn = XeroConnection::updateOrCreate(
            ['location_id'=>(int) $d['location_id']],
            ['org_name'=>$d['org_name'] ?? null, 'status'=>'pending']
        );
        return response()->json(['ok'=>true, 'connection'=>['org_name'=>$conn->org_name, 'status'=>$conn->status]]);
    }

    // Admin: "connect" Xero in demo mode and seed demo expenses so Profit has data.
    public function xeroDemo(Request $r) {
        abort_unless($r->user()->isAdmin(), 403);
        $loc = (int) $r->validate(['location_id'=>'required|exists:locations,id'])['location_id'];
        Setting::put('xero_connected_' . $loc, true);
        Setting::put('xero_mode_' . $loc, 'demo');
        $this->seedDemoExpenses($loc);
        return response()->json(['ok'=>true, 'xero'=>true, 'xero_mode'=>'demo']);
    }

    public function xeroDisconnect(Request $r) {
        abort_unless($r->user()->isAdmin(), 403);
        $loc = (int) $r->validate(['location_id'=>'required|exists:locations,id'])['location_id'];
        Setting::put('xero_connected_' . $loc, false);
        Setting::put('xero_mode_' . $loc, null);
        // Leave demo expenses in place; admin can delete them from the Profit view.
        return response()->json(['ok'=>true, 'xero'=>false]);
    }

    private function seedDemoExpenses(int $loc): void {
        // Representative monthly cost lines (ex GST) for a laundromat.
        $lines = [
            ['category'=>'Rent',        'amount'=>3200],
            ['category'=>'Utilities',   'amount'=>1450],
            ['category'=>'Wages',       'amount'=>1800],
            ['category'=>'Supplies',    'amount'=>640],
            ['category'=>'Insurance',   'amount'=>210],
            ['category'=>'Maintenance', 'amount'=>380],
        ];
        $tz = 'Australia/Brisbane';
        for ($i = 0; $i < 6; $i++) {
            $month = Carbon::now($tz)->subMonthsNoOverflow($i)->format('Y-m');
            foreach ($lines as $j => $l) {
                // small deterministic variation per month so charts look natural
                $wobble = 1 + ((($i * 7 + $j * 13) % 15) - 7) / 100.0; // +/-7%
                Expense::updateOrCreate(
                    ['location_id'=>$loc, 'month'=>$month, 'category'=>$l['category'], 'source'=>'xero_demo'],
                    ['amount'=>round($l['amount'] * $wobble, 2)]
                );
            }
        }
    }
}
