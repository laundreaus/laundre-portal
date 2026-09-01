<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
class TicketController extends Controller {
    public function index(Request $r) {
        $u = $r->user();
        $q = Ticket::with('messages')->orderByDesc('updated_at');
        if (!$u->isAdmin()) { $ids=$u->locationIds(); if($r->filled('loc')&&in_array((int)$r->query('loc'),$ids))$ids=[(int)$r->query('loc')]; $q->where(fn($w)=>$w->whereIn('location_id',$ids)->orWhere('user_id',$u->id)); }
        return $q->get();
    }
    public function store(Request $r) {
        $u = $r->user();
        $data = $r->validate(['type'=>'required|in:Incident,Question','subject'=>'required|string','body'=>'required|string','location_id'=>'nullable|integer']);
        $loc=(int)($data['location_id']??0); if(!$loc||!in_array($loc,$u->locationIds()))$loc=(int)$u->location_id; unset($data['location_id']);
        return Ticket::create($data + ['location_id'=>$loc,'user_id'=>$u->id,'user_name'=>$u->name,'user_email'=>$u->email,'status'=>'Open']);
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
        if (!$u->isAdmin() && !in_array($t->location_id, $u->locationIds()) && $t->user_id !== $u->id) abort(403);
    }
}
