<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MembershipController extends Controller {
    private const TAGLINE = 'The smarter way to launder';

    // Colours per theme (used by the pkpass; the web card uses its own CSS).
    private function colors(string $theme): array {
        return [
            'investor'   => ['bg'=>'rgb(17,17,17)',   'fg'=>'rgb(255,255,255)', 'label'=>'rgb(190,190,190)'],
            'franchisee' => ['bg'=>'rgb(51,71,61)',   'fg'=>'rgb(244,239,230)', 'label'=>'rgb(200,214,204)'],
            'user'       => ['bg'=>'rgb(244,239,230)', 'fg'=>'rgb(67,94,83)',    'label'=>'rgb(120,140,130)'],
        ][$theme];
    }

    private function appleConfigured(): bool {
        $dir = storage_path('app/wallet/apple');
        return is_file($dir.'/pass.p12') && is_file($dir.'/wwdr.pem')
            && is_file($dir.'/images/icon.png') && is_file($dir.'/images/logo.png')
            && env('APPLE_PASS_TYPE_ID') && env('APPLE_TEAM_ID');
    }
    private function googleConfigured(): bool {
        return is_file(storage_path('app/wallet/google/service-account.json'))
            && env('GOOGLE_WALLET_ISSUER_ID') && env('GOOGLE_WALLET_CLASS_ID');
    }

    // ---- Card data for the on-account view ----
    public function show(Request $r) {
        $u = $r->user();
        abort_unless($u->hasCard(), 403);
        if (!$u->member_no) $u->assignMemberNo();
        return response()->json([
            'name'      => $u->name,
            'email'     => $u->email,
            'tier'      => $u->cardTier(),
            'theme'     => $u->cardTheme(),
            'member_no' => $u->member_no,
            'tagline'   => self::TAGLINE,
            'since'     => optional($u->created_at)->format('M Y'),
            'apple_ready'  => $this->appleConfigured(),
            'google_ready' => $this->googleConfigured(),
        ]);
    }

    // ---- Apple Wallet (.pkpass) ----
    public function applePass(Request $r) {
        $u = $r->user();
        abort_unless($u->hasCard(), 403);
        if (!$this->appleConfigured()) {
            return response()->json(['ok'=>false,'message'=>'Apple Wallet isn’t set up yet. Add the Pass Type certificate to enable it.'], 503);
        }
        if (!$u->member_no) $u->assignMemberNo();

        $dir = storage_path('app/wallet/apple');
        $c = $this->colors($u->cardTheme());
        $pass = [
            'formatVersion' => 1,
            'passTypeIdentifier' => env('APPLE_PASS_TYPE_ID'),
            'teamIdentifier' => env('APPLE_TEAM_ID'),
            'organizationName' => 'Laundré',
            'description' => 'Laundré Membership Card',
            'serialNumber' => $u->member_no.'-'.$u->id,
            'backgroundColor' => $c['bg'], 'foregroundColor' => $c['fg'], 'labelColor' => $c['label'],
            'logoText' => 'Laundré',
            'generic' => [
                'primaryFields'   => [['key'=>'name','label'=>'MEMBER','value'=>$u->name]],
                'secondaryFields' => [
                    ['key'=>'tier','label'=>'TIER','value'=>$u->cardTier()],
                    ['key'=>'no','label'=>'MEMBER NO','value'=>$u->member_no],
                ],
                'auxiliaryFields' => [['key'=>'tag','label'=>'','value'=>self::TAGLINE]],
                'backFields'      => [['key'=>'about','label'=>'Laundré','value'=>self::TAGLINE]],
            ],
            'barcodes' => [['format'=>'PKBarcodeFormatQR','message'=>$u->member_no,'messageEncoding'=>'iso-8859-1']],
        ];

        // Assemble the pass files
        $files = ['pass.json' => json_encode($pass, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)];
        foreach (['icon.png','icon@2x.png','logo.png','logo@2x.png'] as $img) {
            $p = $dir.'/images/'.$img;
            if (is_file($p)) $files[$img] = file_get_contents($p);
        }
        // manifest.json = sha1 of every file
        $manifest = [];
        foreach ($files as $name=>$data) $manifest[$name] = sha1($data);
        $files['manifest.json'] = json_encode((object)$manifest, JSON_UNESCAPED_SLASHES);

        // Sign the manifest (detached PKCS#7) with the pass certificate + WWDR
        $p12 = file_get_contents($dir.'/pass.p12');
        $certs = [];
        if (!openssl_pkcs12_read($p12, $certs, env('APPLE_PASS_P12_PASSWORD',''))) {
            return response()->json(['ok'=>false,'message'=>'Could not read the Apple pass certificate.'], 500);
        }
        $tmpManifest = tempnam(sys_get_temp_dir(),'man'); file_put_contents($tmpManifest, $files['manifest.json']);
        $tmpSig = tempnam(sys_get_temp_dir(),'sig');
        openssl_pkcs7_sign($tmpManifest, $tmpSig, $certs['cert'], [$certs['pkey'], env('APPLE_PASS_P12_PASSWORD','')],
            [], PKCS7_BINARY|PKCS7_DETACHED, $dir.'/wwdr.pem');
        // Convert the PEM PKCS7 output to raw DER signature
        $sigPem = file_get_contents($tmpSig);
        @unlink($tmpManifest); @unlink($tmpSig);
        $sigPem = preg_replace('/^.*?\r?\n\r?\n/s', '', $sigPem, 1); // strip MIME headers
        $sigPem = preg_replace('/\r?\n\r?\n.*$/s', '', $sigPem, 1);
        $der = base64_decode(preg_replace('/-----.*?-----|\s/s','',$sigPem));
        $files['signature'] = $der;

        // Zip → .pkpass
        $zipPath = tempnam(sys_get_temp_dir(),'pk').'.pkpass';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE|\ZipArchive::OVERWRITE);
        foreach ($files as $name=>$data) $zip->addFromString($name, $data);
        $zip->close();

        return response()->download($zipPath, 'laundre-membership.pkpass', [
            'Content-Type' => 'application/vnd.apple.pkpass',
        ])->deleteFileAfterSend(true);
    }

    // ---- Google Wallet (Save link) ----
    public function googleSave(Request $r) {
        $u = $r->user();
        abort_unless($u->hasCard(), 403);
        if (!$this->googleConfigured()) {
            return response()->json(['ok'=>false,'message'=>'Google Wallet isn’t set up yet. Add the service-account key to enable it.'], 503);
        }
        if (!$u->member_no) $u->assignMemberNo();

        $sa = json_decode(file_get_contents(storage_path('app/wallet/google/service-account.json')), true);
        $issuer = env('GOOGLE_WALLET_ISSUER_ID'); $classId = env('GOOGLE_WALLET_CLASS_ID');
        $objectId = $issuer.'.'.preg_replace('/[^A-Za-z0-9_]/','_', 'LDR'.$u->id);

        $object = [
            'id' => $objectId,
            'classId' => $classId,
            'state' => 'ACTIVE',
            'genericType' => 'GENERIC_TYPE_UNSPECIFIED',
            'cardTitle' => ['defaultValue'=>['language'=>'en','value'=>'Laundré']],
            'subheader' => ['defaultValue'=>['language'=>'en','value'=>$u->cardTier()]],
            'header'    => ['defaultValue'=>['language'=>'en','value'=>$u->name]],
            'textModulesData' => [
                ['id'=>'member_no','header'=>'MEMBER NO','body'=>$u->member_no],
                ['id'=>'tagline','header'=>'','body'=>self::TAGLINE],
            ],
            'barcode' => ['type'=>'QR_CODE','value'=>$u->member_no],
            'hexBackgroundColor' => $u->cardTheme()==='investor' ? '#111111' : ($u->cardTheme()==='franchisee' ? '#33473D' : '#F4EFE6'),
        ];

        $claims = [
            'iss' => $sa['client_email'],
            'aud' => 'google',
            'typ' => 'savetowallet',
            'iat' => time(),
            'payload' => ['genericObjects' => [$object]],
        ];
        $seg = fn($d) => rtrim(strtr(base64_encode(json_encode($d, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
        $signingInput = $seg(['alg'=>'RS256','typ'=>'JWT']).'.'.$seg($claims);
        $sig = '';
        openssl_sign($signingInput, $sig, $sa['private_key'], 'SHA256');
        $jwt = $signingInput.'.'.rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');

        return response()->json(['ok'=>true, 'save_url'=>'https://pay.google.com/gp/v/save/'.$jwt]);
    }
}
