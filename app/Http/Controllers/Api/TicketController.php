<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
class TicketController extends Controller {
    public function index(Request $r) {
        $u = $r->user();
        $q = Ticket::with('messages')->orderByDesc('updated_at');
        if (!$u->isAdmin()) { $q->where(fn($w)=>$w->where('location_id',$u->location_id)->orWhere('user_id',$u->id)); }
        return $q->get();
    }
    public function store(Request $r) {
        $u = $r->user();
        $data = $r->validate(['type'=>'required|in:Incident,Question','subject'=>'required|string','body'=>'required|string']);
        return Ticket::create($data + ['location_id'=>$u->location_id,'user_id'=>$u->id,'user_name'=>$u->name,'user_email'=>$u->email,'status'=>'Open']);
    }
    public function reply(Request $r, Ticket $ticket) {
        $u = $r->user();
        $this->authorizeTicket($u, $ticket);
        $data = $r->validate(['text'=>'required|string']);
        $ticket->messages()->create(['from'=>$u->name,'role'=>$u->role,'text'=>$data['text']]);
        $ticket->touch();
        return $ticket->load('messages');
    }
    public function status(Request $r, Ticket $ticket) {
        abort_unless($r->user()->isAdmin(), 403);
        $ticket->update(['status'=>$r->validate(['status'=>'required|in:Open,Closed'])['status']]);
        return $ticket;
    }
    private function authorizeTicket($u, Ticket $t): void {
        if (!$u->isAdmin() && $t->location_id !== $u->location_id && $t->user_id !== $u->id) abort(403);
    }
}
