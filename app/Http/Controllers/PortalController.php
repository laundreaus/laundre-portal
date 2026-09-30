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
        if ($u->isInvestor() && !in_array($page, ['laundre-portal','laundre-investor-dashboard','laundre-card','laundre-doc-viewer','laundre-support'])) {
            return redirect('/');
        }
        // Keep cleaners & maintenance inside their own tools (so a "view as" preview lands
        // on the role's real section — never the admin version of the page).
        $roleAllow = [
            'cleaner'     => ['laundre-portal','laundre-cleaning','laundre-doc-viewer'],
            'maintenance' => ['laundre-portal','laundre-maintenance','laundre-maintenance-docs','laundre-doc-viewer'],
        ];
        if (isset($roleAllow[$u->role]) && !in_array($page, $roleAllow[$u->role])) {
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
    /** slug => [icon, label] for building a staff member's sidebar from their assigned sections. */
    private function moduleMeta(): array {
        return [
            'laundre-tasks'=>['🗒️','Task List'],'laundre-franchise-contacts'=>['📥','Contacts'],
            'laundre-crm'=>['👥','Franchise CRM'],'laundre-onboarding'=>['🚀','Onboarding Tracker'],
            'laundre-investors'=>['💼','Investors'],'laundre-site-acquisition'=>['🧭','Site Acquisition'],
            'laundre-calculators'=>['🧮','Cost Calculator'],'laundre-project-delivery'=>['🏗️','Project Delivery'],
            'laundre-milestones'=>['🗓️','Build Schedule'],'laundre-live-laundromats'=>['🧺','Live Laundromats'],
            'laundre-maintenance'=>['🛠️','Maintenance'],'laundre-cleaning'=>['🧹','Cleaning'],
            'laundre-reporting'=>['📈','Reporting'],'laundre-bookkeeping'=>['📒','Bookkeeping'],
            'laundre-support'=>['🆘','Support Queue'],'laundre-maintenance-docs'=>['📘','Maintenance Docs'],
            'laundre-users'=>['👤','User Management'],'laundre-ai-search'=>['🤖','Ask your data'],
            'laundre-onboarding-content'=>['🎬','Onboarding Content'],'laundre-documents'=>['📄','Document Management'],
            'laundre-notifications'=>['✉️','Notifications'],'laundre-activity'=>['🗂️','Activity Log'],
            'laundre-nda-admin'=>['✍️','NDA'],'laundre-road-to-100'=>['🏁','Road to 100'],
            'laundre-cashflow'=>['💰','Cashflow Projections'],'laundre-layout'=>['📐','Layout Tool'],
            'laundre-suppliers'=>['🔧','Suppliers & Contacts'],'laundre-about'=>['ℹ️','About this store'],
            'laundre-insurance'=>['🛡️','Insurance'],'laundre-cctv'=>['📹','CCTV'],
            'laundre-contacts'=>['👥','Customers'],'laundre-checklist'=>['✅','Franchise Checklist'],
            'laundre-card'=>['💳','Membership Card'],
        ];
    }
    /** The left-sidebar navigation for a given user, tailored to their role. */
    private function roleSidebar($u): array {
        $role = $u->role;
        if ($role === 'admin') {
            return [
                ['items'=>[['i'=>'🗒️','n'=>'Task List','h'=>'laundre-tasks']]],
                ['g'=>'Growth','items'=>[
                    ['i'=>'📥','n'=>'Contacts','h'=>'laundre-franchise-contacts'],
                    ['i'=>'👥','n'=>'Franchise CRM','h'=>'laundre-crm'],
                    ['i'=>'🚀','n'=>'Onboarding Tracker','h'=>'laundre-onboarding'],
                    ['i'=>'💼','n'=>'Investors','h'=>'laundre-investors'],
                ]],
                ['g'=>'Sites Acquisition','items'=>[
                    ['i'=>'🧭','n'=>'Site Acquisition','h'=>'laundre-site-acquisition'],
                    ['i'=>'🧮','n'=>'Cost Calculator','h'=>'laundre-calculators','sub'=>true],
                ]],
                ['g'=>'Delivery','items'=>[
                    ['i'=>'🏗️','n'=>'Project Delivery','h'=>'laundre-project-delivery'],
                    ['i'=>'🗓️','n'=>'Build Schedule','h'=>'laundre-milestones','sub'=>true],
                ]],
                ['g'=>'Operations','items'=>[
                    ['i'=>'🧺','n'=>'Live Laundromats','h'=>'laundre-live-laundromats'],
                ]],
                ['g'=>'Servicing','items'=>[
                    ['i'=>'🛠️','n'=>'Maintenance','h'=>'laundre-maintenance'],
                    ['i'=>'🧹','n'=>'Cleaning','h'=>'laundre-cleaning'],
                    ['i'=>'📈','n'=>'Reporting','h'=>'laundre-reporting'],
                    ['i'=>'📒','n'=>'Bookkeeping','h'=>'laundre-bookkeeping'],
                    ['i'=>'🆘','n'=>'Support Queue','h'=>'laundre-support'],
                    ['i'=>'📘','n'=>'Maintenance Docs','h'=>'laundre-maintenance-docs'],
                ]],
                ['g'=>'System Settings','items'=>[
                    ['i'=>'👤','n'=>'User Management','h'=>'laundre-users'],
                    ['i'=>'🤖','n'=>'Ask your data','h'=>'laundre-ai-search'],
                    ['i'=>'🎬','n'=>'Onboarding Content','h'=>'laundre-onboarding-content'],
                    ['i'=>'📄','n'=>'Document Management','h'=>'laundre-documents'],
                    ['i'=>'✉️','n'=>'Notifications','h'=>'laundre-notifications'],
                    ['i'=>'🗂️','n'=>'Activity Log','h'=>'laundre-activity'],
                    ['i'=>'✍️','n'=>'NDA','h'=>'laundre-nda-admin'],
                    ['i'=>'🏁','n'=>'Road to 100','h'=>'laundre-road-to-100'],
                    ['i'=>'💰','n'=>'Cashflow Projections','h'=>'laundre-cashflow'],
                ]],
                ['g'=>'More Tools','items'=>[
                    ['i'=>'📐','n'=>'Layout Tool','h'=>'laundre-layout'],
                    ['i'=>'🔧','n'=>'Suppliers & Contacts','h'=>'laundre-suppliers'],
                ]],
            ];
        }
        if ($role === 'franchisee') {
            return [
                ['g'=>'My Store','items'=>[
                    ['i'=>'ℹ️','n'=>'About this store','h'=>'laundre-about'],
                    ['i'=>'📄','n'=>'Documents','h'=>'laundre-documents'],
                    ['i'=>'🛡️','n'=>'Insurance','h'=>'laundre-insurance'],
                    ['i'=>'🗓️','n'=>'Build schedule','h'=>'laundre-milestones'],
                    ['i'=>'📹','n'=>'CCTV','h'=>'laundre-cctv'],
                    ['i'=>'👥','n'=>'Customers','h'=>'laundre-contacts'],
                ]],
                ['g'=>'Operations','items'=>[
                    ['i'=>'✅','n'=>'Franchise Checklist','h'=>'laundre-checklist'],
                    ['i'=>'📒','n'=>'Bookkeeping','h'=>'laundre-bookkeeping'],
                    ['i'=>'🆘','n'=>'Submit / Ask','h'=>'laundre-support'],
                    ['i'=>'🤖','n'=>'Ask your data','h'=>'laundre-ai-search'],
                ]],
                ['g'=>'Account','items'=>[
                    ['i'=>'💳','n'=>'Membership Card','h'=>'laundre-card'],
                ]],
            ];
        }
        if ($role === 'investor') {
            return [ ['items'=>[
                ['i'=>'🆘','n'=>'Submit / Ask','h'=>'laundre-support'],
                ['i'=>'💳','n'=>'Membership Card','h'=>'laundre-card'],
            ]] ];
        }
        if ($role === 'cleaner') {
            return [ ['items'=>[['i'=>'🧹','n'=>'Daily Cleaning','h'=>'laundre-cleaning']]] ];
        }
        if ($role === 'maintenance') {
            return [ ['items'=>[
                ['i'=>'🛠️','n'=>'Maintenance','h'=>'laundre-maintenance'],
                ['i'=>'📘','n'=>'Maintenance Docs','h'=>'laundre-maintenance-docs'],
            ]] ];
        }
        if ($role === 'user') {
            $meta = $this->moduleMeta();
            $items = [];
            foreach ((array)($u->sections ?? []) as $slug) {
                if (isset($meta[$slug])) $items[] = ['i'=>$meta[$slug][0],'n'=>$meta[$slug][1],'h'=>$slug];
            }
            return $items ? [['items'=>$items]] : [];
        }
        return [];
    }
    private function serve(string $page, bool $isHome = false) {
        $path = public_path('legacy/'.basename($page).'.html');
        abort_unless(is_file($path), 404);
        $html = file_get_contents($path);
        $html = preg_replace('/(["\'])laundre-portal\.html\1/', '$1/$1', $html);
        $html = preg_replace('/(["\'])([A-Za-z0-9_\-]+)\.html\1/', '$1/$2$1', $html);
        $u = auth()->user();
        // Embedded pages (e.g. the store dashboard shown inside the Live Laundromat iframe)
        // must NOT get the injected uniform header / nav / sidebar — those would override the
        // page's own "hide header" embed CSS and re-show its title bar inside the frame.
        $isEmbed = request()->query('embed') === '1';
        // Investors are scoped to their investor-assigned laundromats; everyone else to their own store(s).
        $locIds = $u->isInvestor() ? $u->investorLocationIds() : $u->locationIds();
        $primaryLoc = $u->location_id ?: ($locIds[0] ?? null);
        $locList = $locIds ? Location::whereIn('id', $locIds)->orderBy('name')->get(['id','name'])->map(fn($l)=>['id'=>$l->id,'name'=>$l->name])->values()->all() : [];
        $sessionJson = json_encode(['role'=>$u->role,'locationId'=>$primaryLoc,'locationName'=>($primaryLoc?optional(Location::find($primaryLoc))->name:null),'locationIds'=>$locIds,'locations'=>$locList,'name'=>$u->name,'email'=>$u->email,'sections'=>$u->sections ?? []], JSON_UNESCAPED_SLASHES);
        $favicon = '<link rel="icon" href="/favicon.ico" sizes="any"><link rel="icon" type="image/gif" href="/favicon.gif"><link rel="apple-touch-icon" href="/favicon-32.png">';
        $bridge = $favicon.'<style>#login{display:none!important}#app{display:block!important}</style><script>try{localStorage.setItem("laundre_auth","1");localStorage.setItem("laundre_session",'.json_encode($sessionJson).');}catch(e){}window.LAUNDRE_CSRF='.json_encode(csrf_token()).';</script>';
        $html = str_ireplace('<head>', '<head>'.$bridge, $html);
        $logout = '<form id="__llogout" method="POST" action="/logout" style="display:none">'.csrf_field().'</form><script>document.addEventListener("DOMContentLoaded",function(){var b=document.getElementById("logoutBtn");if(b){b.onclick=function(e){e.preventDefault();document.getElementById("__llogout").submit();};}});</script>';
        $html = str_ireplace('</body>', $logout.'</body>', $html);
        // Page → header emoji. Old pages show a "·" (or nothing) before the title; we swap in the
        // matching emoji so every page header reads "🧹 Cleaning" like the newer sections do.
        $emojiMap = [
            'laundre-tasks'=>'🗒️','laundre-franchise-contacts'=>'📥','laundre-crm'=>'👥',
            'laundre-onboarding'=>'🚀','laundre-investors'=>'💼','laundre-site-acquisition'=>'🧭',
            'laundre-calculators'=>'🧮','laundre-project-delivery'=>'🏗️','laundre-milestones'=>'🗓️',
            'laundre-live-laundromats'=>'🧺','laundre-maintenance'=>'🛠️','laundre-cleaning'=>'🧹',
            'laundre-reporting'=>'📈','laundre-bookkeeping'=>'📒','laundre-support'=>'🆘',
            'laundre-maintenance-docs'=>'📘','laundre-users'=>'👤','laundre-ai-search'=>'🤖',
            'laundre-onboarding-content'=>'🎬','laundre-documents'=>'📄','laundre-notifications'=>'✉️',
            'laundre-activity'=>'🗂️','laundre-nda-admin'=>'✍️','laundre-road-to-100'=>'🏁',
            'laundre-cashflow'=>'💰','laundre-layout'=>'📐','laundre-suppliers'=>'🔧',
            'laundre-insurance'=>'🛡️','laundre-quotes'=>'🧾','laundre-profit'=>'💵','laundre-machines'=>'⚙️',
            'laundre-about'=>'ℹ️','laundre-connected'=>'🔗','laundre-files'=>'📁','laundre-contacts'=>'📇',
            'laundre-admin'=>'🏬','laundre-cost-analysis'=>'📊','laundre-checklist'=>'✅',
            'laundre-site-analyzer'=>'🔍','laundre-cctv'=>'📹','laundre-investor-dashboard'=>'📊',
            'laundre-site-acquisition'=>'🧭','laundre-portal'=>'🏠',
        ];
        $emoji = $emojiMap[$page] ?? '';
        // Put the page's emoji at the front of the header title on EVERY page (home too),
        // whether or not the title uses a ".ttl" class — old pages use a plain span with a
        // leading "·". Runs standalone so it also covers pages the nav block skips.
        if (!$isEmbed && $emoji !== '') {
            $emojiJs = '<script>document.addEventListener("DOMContentLoaded",function(){if(window.self!==window.top)return;try{'
                .'var EMO='.json_encode($emoji).';var hdr=document.querySelector("header");if(!hdr)return;'
                .'var ttl=hdr.querySelector(".ttl");'
                .'if(!ttl){var ns=hdr.querySelectorAll("*");for(var i=0;i<ns.length;i++){var el=ns[i];'
                .'if(el.children.length)continue;if(el.closest("a,button"))continue;'
                .'if(el.tagName==="IMG"||el.tagName==="svg"||el.tagName==="SVG")continue;'
                .'var tx=(el.textContent||"").trim();if(!tx)continue;'
                .'if(el.className&&/logo|wm|spacer/i.test(el.className))continue;'
                .'ttl=el;break;}}'
                .'if(!ttl)return;var t=ttl.textContent.replace(/^[·•]\s*/,"");'
                .'if(t.indexOf(EMO)===0){if(t!==ttl.textContent)ttl.textContent=t;return;}'
                .'var cp=t.codePointAt(0)||0;ttl.textContent=(cp>0x2100?t:EMO+" "+t);'
                .'}catch(e){}});</script>';
            $html = str_ireplace('</body>', $emojiJs.'</body>', $html);
        }
        // Uniform header + "back to previous page" across every tool page (skips the portal home & embeds).
        if (!$isHome && !$isEmbed) {
            // Pages with an intentionally dark header (pre-portal states) keep their own styling.
            $darkHeader = in_array($page, ['laundre-locked','laundre-nda','laundre-onboard','laundre-doc-viewer']);
            $headerStyle = $darkHeader ? '' : '<style>'
                .'header{background:#F4EFE6!important;border-bottom:1px solid #E4DBCB!important;display:flex!important;align-items:center!important;gap:14px!important;padding:14px 26px!important;position:fixed!important;top:0!important;left:0!important;right:0!important;width:100%!important;z-index:10001!important;box-sizing:border-box!important;}'
                .'header .logo.lheadlogo{display:flex!important;align-items:center!important;}'
                .'header .logo.lheadlogo img{height:28px!important;width:auto!important;display:block!important;}'
                .'</style>';
            $logoJs = $darkHeader ? '' :
                'if(!hdr.querySelector(".logo")&&!hdr.querySelector(".lheadlogo")&&!hdr.querySelector("svg")){var lg=document.createElement("span");lg.className="logo lheadlogo";var im=document.createElement("img");im.src="/laundre-logo.svg";im.alt="Laundré";lg.appendChild(im);hdr.insertBefore(lg,hdr.firstChild);}'
                .'var exlogo=hdr.querySelector(".logo:not(.lheadlogo)");if(exlogo){exlogo.classList.add("lheadlogo");var ei=exlogo.querySelector("img,svg");if(ei){ei.style.height="28px";}}';
            $nav = $headerStyle
                .'<script>document.addEventListener("DOMContentLoaded",function(){'
                .'if(window.self!==window.top)return;'
                .'try{var hdr=document.querySelector("header");if(!hdr)return;'
                .$logoJs
                .'var back=hdr.querySelector("a[href=\'/\']");'
                .'if(back){if(/portal|home|⌂|←/i.test(back.textContent)){back.textContent="← Back";}'
                .'back.addEventListener("click",function(e){if(history.length>1){e.preventDefault();history.back();}});}'
                .'}catch(e){}});</script>';
            $html = str_ireplace('</body>', $nav.'</body>', $html);
        }
        // Persistent collapsible left sidebar on every tool page — role-appropriate items.
        $sidebarData = (!$isHome && !$isEmbed) ? $this->roleSidebar($u) : [];
        if (!empty($sidebarData)) {
            $navJson = json_encode($sidebarData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $sb = '<style>'
                .'body{transition:margin-left .18s ease;}'
                .'body{transition:padding-left .18s ease;}'
                .'body.lsb-open{padding-left:230px;}'
                .'#lsbNav{position:fixed;top:61px;left:0;width:230px;height:calc(100vh - 61px);overflow-y:auto;background:#33473D;color:#EAF0EC;z-index:9998;transform:translateX(-100%);transition:transform .18s ease;padding-bottom:24px;box-shadow:2px 0 18px rgba(0,0,0,.18);font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;box-sizing:border-box;}'
                .'body.lsb-open #lsbNav{transform:translateX(0);}'
                .'#lsbNav *{box-sizing:border-box;}'
                .'.lsb-top{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid rgba(255,255,255,.08);position:sticky;top:0;background:#33473D;}'
                .'.lsb-logo{height:22px;width:auto;filter:brightness(0) invert(1);}'
                .'.lsb-x{background:transparent;border:none;color:#cdd9d1;font-size:22px;cursor:pointer;line-height:1;padding:0 4px;}'
                .'.lsb-g{font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#8fa89a;font-weight:800;padding:14px 18px 5px;cursor:pointer;display:flex;align-items:center;justify-content:space-between;user-select:none;}'
                .'.lsb-g:hover{color:#c5d5cb;}.lsb-g .lsb-car{font-size:9px;transition:transform .15s;opacity:.7;}.lsb-g.collapsed .lsb-car{transform:rotate(-90deg);}'
                .'.lsb-items.collapsed{display:none;}'
                .'#lsbNav a.lsb-a{display:flex;align-items:center;gap:10px;padding:8px 18px;color:#d7e2db;text-decoration:none;font-size:13px;font-weight:600;border-left:3px solid transparent;}'
                .'#lsbNav a.lsb-a:hover{background:rgba(255,255,255,.06);color:#fff;}'
                .'#lsbNav a.lsb-a.on{background:rgba(196,112,63,.16);color:#fff;border-left-color:#C4703F;}'
                .'#lsbNav a.lsb-a.sub{padding-left:32px;font-size:12px;color:#b9c8bf;}'
                .'#lsbNav a.lsb-a .si{width:18px;text-align:center;flex:0 0 18px;font-size:14px;}'
                .'#lsbToggle{position:fixed;top:12px;left:12px;z-index:9999;width:38px;height:36px;border:1px solid rgba(0,0,0,.12);background:#fff;color:#33473D;border-radius:8px;font-size:16px;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.12);display:none;align-items:center;justify-content:center;}'
                .'@media(max-width:820px){body.lsb-open{padding-left:0;}#lsbNav{z-index:10000;}}'
                .'</style>'
                .'<script>document.addEventListener("DOMContentLoaded",function(){if(window.self!==window.top)return;try{'
                .'var NAV='.$navJson.';var path=location.pathname.replace(/\/$/,"");'
                .'var nav=document.createElement("nav");nav.id="lsbNav";'
                .'var html="<a class=\'lsb-a"+(path===""||path==="/"?" on":"")+"\' href=\'/\' style=\'margin-top:8px\'><span class=\'si\'>🏠</span> Dashboard</a>";'
                .'var gst={};try{gst=JSON.parse(localStorage.getItem("laundre_sb_groups")||"{}")||{};}catch(e){}'
                .'NAV.forEach(function(sec,gi){var inner="";sec.items.forEach(function(it){var on=(path===("/"+it.h));inner+="<a class=\'lsb-a"+(it.sub?" sub":"")+(on?" on":"")+"\' href=\'/"+it.h+"\'><span class=\'si\'>"+it.i+"</span> "+it.n+"</a>";});'
                .'if(sec.g){var col=(gst[sec.g]===false)?"":" collapsed";html+="<div class=\'lsb-g"+col+"\' data-gi=\'"+gi+"\'>"+sec.g+"<span class=\'lsb-car\'>▾</span></div><div class=\'lsb-items"+col+"\' data-items=\'"+gi+"\'>"+inner+"</div>";}else{html+=inner;}});'
                .'nav.innerHTML=html;document.body.appendChild(nav);'
                .'nav.querySelectorAll(".lsb-g").forEach(function(h){h.addEventListener("click",function(){var gi=h.getAttribute("data-gi");var items=nav.querySelector(".lsb-items[data-items=\'"+gi+"\']");var now=h.classList.toggle("collapsed");if(items)items.classList.toggle("collapsed");var st={};try{st=JSON.parse(localStorage.getItem("laundre_sb_groups")||"{}")||{};}catch(e){}st[h.textContent.replace("▾","").trim()]=now;try{localStorage.setItem("laundre_sb_groups",JSON.stringify(st));}catch(e){}});});'
                .'function set(open){document.body.classList.toggle("lsb-open",open);try{localStorage.setItem("laundre_sb_collapsed",open?"0":"1");}catch(e){}}'
                .'var col=null;try{col=localStorage.getItem("laundre_sb_collapsed");}catch(e){}'
                .'set(col===null?(window.innerWidth>820):(col!=="1"));'
                .'var hdr=document.querySelector("header");var t;'
                .'if(hdr){t=document.createElement("button");t.type="button";t.textContent="☰";t.title="Menu";t.style.cssText="background:transparent;border:none;font-size:18px;cursor:pointer;color:inherit;margin-right:8px;line-height:1;";hdr.insertBefore(t,hdr.firstChild);}'
                .'else{t=document.getElementById("lsbToggle")||document.createElement("button");t.id="lsbToggle";t.textContent="☰";t.style.display="flex";document.body.appendChild(t);}'
                .'t.addEventListener("click",function(){set(!document.body.classList.contains("lsb-open"));});'
                .'function offset(){var h=(hdr&&hdr.offsetHeight)?hdr.offsetHeight:61;document.body.style.paddingTop=h+"px";nav.style.top=h+"px";nav.style.height="calc(100vh - "+h+"px)";}'
                .'offset();setTimeout(offset,120);window.addEventListener("resize",offset);'
                .'}catch(e){}});</script>';
            $html = str_ireplace('</body>', $sb.'</body>', $html);
        }
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
