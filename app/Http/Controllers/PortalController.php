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
        if ($u->isOnboarding() && !in_array($page, ['laundre-onboard','laundre-nda','laundre-doc-viewer','laundre-card'])) {
            return redirect('/');
        }
        if ($u->isInvestor() && !in_array($page, ['laundre-portal','laundre-investor-dashboard','laundre-card','laundre-doc-viewer'])) {
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
        $favicon = '<link rel="icon" href="/favicon.ico" sizes="any"><link rel="icon" type="image/gif" href="/favicon.gif"><link rel="apple-touch-icon" href="/favicon-32.png">';
        $bridge = $favicon.'<style>#login{display:none!important}#app{display:block!important}</style><script>try{localStorage.setItem("laundre_auth","1");localStorage.setItem("laundre_session",'.json_encode($sessionJson).');}catch(e){}window.LAUNDRE_CSRF='.json_encode(csrf_token()).';</script>';
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
        // When an admin is previewing "as" another user, show a persistent exit banner.
        if (session()->has('impersonate_id')) {
            $labels = ['potential_franchisee'=>'Potential Franchisee','investor'=>'Investor','potential_investor'=>'Potential Investor',
                'franchisee'=>'Franchisee','cleaner'=>'Cleaner','maintenance'=>'Maintenance','user'=>'Staff'];
            $roleLabel = $labels[$u->role] ?? ucfirst($u->role);
            $store = $u->location_id ? optional(Location::find($u->location_id))->name : null;
            $desc = e($u->name).' · '.$roleLabel.($store ? ' ('.e($store).')' : '');
            $banner = '<div style="position:fixed;left:50%;transform:translateX(-50%);bottom:16px;z-index:99999;background:#33473D;color:#EAF0EC;padding:9px 18px;border-radius:24px;box-shadow:0 10px 30px rgba(0,0,0,.35);font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:13px;font-weight:600;display:flex;gap:12px;align-items:center;white-space:nowrap">'
                .'<span>👁️ Admin preview — viewing as <b style="color:#fff">'.$desc.'</b></span>'
                .'<a href="/stop-impersonate" style="color:#F4EFE6;background:#C4703F;padding:4px 12px;border-radius:16px;text-decoration:none;font-weight:800">Exit preview</a></div>';
            $html = str_ireplace('</body>', $banner.'</body>', $html);
        }
        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
