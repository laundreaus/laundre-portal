<?php
namespace App\Http\Controllers;
use App\Models\{Location, User, Document, Ticket, Franchise};
class PortalController extends Controller {
    public function index() {
        $u = auth()->user();
        if ($u->isAccessLocked()) return $this->serve('laundre-locked', false);
        if ($u->needsNda())       return $this->serve('laundre-nda', false);
        if ($u->isOnboarding())   return $this->serve('laundre-onboard', false);
        return $this->serve('laundre-portal', true);
    }
    public function tool(string $page) {
        $u = auth()->user();
        if ($u->isAccessLocked() || $u->needsNda()) return redirect('/');
        if ($u->isOnboarding() && !in_array($page, ['laundre-onboard','laundre-nda','laundre-doc-viewer'])) {
            return redirect('/');
        }
        if ($u->role === 'user' && $page !== 'laundre-portal' && !in_array($page, (array)$u->sections)) {
            return redirect('/');
        }
        return $this->serve($page, false);
    }
    // Serves the large AU postcode dataset as JSON. Kept out of the tool HTML so
    // that page stays well under the server's ~1MB response-body limit; JSON
    // responses are not subject to that limit.
    public function postcodeData() {
        $path = public_path('legacy/region-postcode-data.json');
        abort_unless(is_file($path), 404);
        return response(file_get_contents($path), 200)
            ->header('Content-Type', 'application/json');
    }
    private function serve(string $page, bool $isHome = false) {
        $path = public_path('legacy/'.basename($page).'.html');
        abort_unless(is_file($path), 404);
        $html = file_get_contents($path);
        $html = preg_replace('/(["\'])laundre-portal\.html\1/', '$1/$1', $html);
        $html = preg_replace('/(["\'])([A-Za-z0-9_\-]+)\.html\1/', '$1/$2$1', $html);
        $u = auth()->user();
        $sessionJson = json_encode(['role'=>$u->role,'locationId'=>$u->location_id,'name'=>$u->name,'email'=>$u->email,'sections'=>$u->sections ?? []], JSON_UNESCAPED_SLASHES);
        $bridge = '<style>#login{display:none!important}#app{display:block!important}</style><script>try{localStorage.setItem("laundre_auth","1");localStorage.setItem("laundre_session",'.json_encode($sessionJson).');}catch(e){}window.LAUNDRE_CSRF='.json_encode(csrf_token()).';</script>';
        $html = str_ireplace('<head>', '<head>'.$bridge, $html);
        $logout = '<form id="__llogout" method="POST" action="/logout" style="display:none">'.csrf_field().'</form><script>document.addEventListener("DOMContentLoaded",function(){var b=document.getElementById("logoutBtn");if(b){b.onclick=function(e){e.preventDefault();document.getElementById("__llogout").submit();};}});</script>';
        $html = str_ireplace('</body>', $logout.'</body>', $html);
        if ($isHome && $u->role === 'admin') {
            $counts = [
                'laundre_sites_v1' => Location::count(),
                'laundre_users_v1' => User::where('role', '!=', 'admin')->count(),
                'laundre_documents_v1' => Document::count(),
                'laundre_tickets_v1' => Ticket::count(),
                'laundre_crm_v1' => 0,
                'laundre_franchises_v1' => Franchise::count(),
            ];
            $stats = '<script>(function(){var C='.json_encode($counts).';window.count=function(k){return C.hasOwnProperty(k)?C[k]:0;};function r(){try{renderStats();}catch(e){}}r();setTimeout(r,250);})();</script>';
            $html = str_ireplace('</body>', $stats.'</body>', $html);
        }
        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
