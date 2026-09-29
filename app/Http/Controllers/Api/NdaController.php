<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Onboarding;
use App\Models\User;
use App\Support\SimplePdf;
use Illuminate\Http\Request;

class NdaController extends Controller
{
    private const TZ = 'Australia/Brisbane';

    private function roleLabel(string $role): string
    {
        return [
            'potential_franchisee' => 'Potential Franchisee',
            'investor'             => 'Investor',
            'franchisee'           => 'Franchisee',
            'cleaner'              => 'Cleaner',
            'maintenance'          => 'Maintenance',
            'user'                 => 'Team Member',
            'admin'                => 'Administrator',
        ][$role] ?? ucfirst($role);
    }

    /** Admin: every signed NDA, newest first. */
    public function list(Request $r)
    {
        abort_unless($r->user()->isAdmin(), 403);
        $obs = Onboarding::whereNotNull('nda_signed_at')->get()->keyBy('user_id');
        // withTrashed so signed NDAs of deleted users are still listed (NDAs are retained after deletion).
        $users = User::withTrashed()->whereNotNull('nda_signed_at')->orderByDesc('nda_signed_at')->get();
        return $users->map(function (User $u) use ($obs) {
            $o = $obs->get($u->id);
            return [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $o->nda_email ?? $u->email,
                'phone'      => $o->nda_phone ?? $u->phone,
                'role'       => $u->role,
                'role_label' => $this->roleLabel($u->role),
                'member_no'  => $u->member_no,
                'address'    => $u->nda_address ?: ($o->nda_address ?? null),
                'signed_name'=> $u->nda_signer_name ?: ($o->nda_typed_name ?? null),
                'ip'         => $o->nda_ip ?? null,
                'signed_at'  => optional($u->nda_signed_at)->toISOString(),
                'pdf_url'    => url('/nda-admin/' . $u->id . '/pdf'),
            ];
        })->values();
    }

    /** Admin: download the executed NDA for one signer as a PDF. */
    public function pdf(Request $r, $user)
    {
        abort_unless($r->user()->isAdmin(), 403);
        // withTrashed so a deleted signer's NDA PDF is still downloadable.
        $user = User::withTrashed()->findOrFail($user);
        abort_unless($user->nda_signed_at, 404, 'This user has not signed an NDA.');

        $o = Onboarding::where('user_id', $user->id)->first();

        $signedName = $user->nda_signer_name ?: ($o->nda_typed_name ?? $user->name);
        $email      = $o->nda_email ?? $user->email;
        $phone      = $o->nda_phone ?? $user->phone;
        $address    = $user->nda_address ?: ($o->nda_address ?? '');
        $ip         = $o->nda_ip ?? null;
        $signedAt   = $user->nda_signed_at->copy()->setTimezone(self::TZ);

        $pdf = new SimplePdf();
        $pdf->title('Confidentiality & Non-Disclosure Agreement');
        $pdf->spacer(2);
        $pdf->paragraph('Executed copy — Laundré Franchising Pty Ltd', 10, false, 0, 4);
        // centre-ish subline handled as a normal paragraph; keep it plain.
        $pdf->rule();
        $pdf->spacer(4);

        foreach ($this->ndaBlocks() as $b) {
            if (isset($b['h'])) $pdf->heading($b['h']);
            else                $pdf->paragraph($b['p'], 10.2, false, isset($b['indent']) ? $b['indent'] : 0);
        }

        // ---- Execution page ----
        $pdf->addPage();
        $pdf->heading('Execution', 13.5);
        $pdf->paragraph('This agreement was signed electronically by the Recipient. The details recorded at the time of signing are set out below.', 10.2, false, 0, 8);

        $pdf->field('Recipient (full name):', $user->name ?: '—');
        $pdf->field('Role:', $this->roleLabel($user->role));
        if ($user->member_no) $pdf->field('Member number:', $user->member_no);
        $pdf->field('Email:', $email ?: '—');
        if ($phone) $pdf->field('Phone:', $phone);
        $pdf->field('Address:', $address ?: '—');
        $pdf->field('Discloser:', 'Laundré Franchising Pty Ltd');
        $pdf->field('Signed (electronically):', $signedAt->format('j F Y, g:i a') . ' AEST');
        $pdf->field('IP address:', $ip ?: 'Not recorded');
        $pdf->field('Typed name:', $signedName ?: '—');

        $pdf->spacer(10);
        $pdf->paragraph('Signature:', 10.5, true, 0, 4);
        $w = 0; $h = 0;
        $jpeg = $this->signatureJpeg($user->nda_signature, $w, $h);
        if ($jpeg) {
            $pdf->image($jpeg, $w, $h, 230);
        } else {
            $pdf->paragraph('[Signature captured on file — image could not be rendered on this server]', 9.5, false, 0, 4);
        }
        $pdf->spacer(6);
        $pdf->rule();
        $pdf->paragraph('Signed electronically via the Laundré onboarding portal. The Recipient confirmed they had read and agreed to this agreement and consented to signing it electronically as a legally binding document.', 8.6, false, 0, 2);

        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', trim(($user->member_no ? $user->member_no . '-' : '') . ($user->name ?: 'signer')));
        $slug = trim($slug, '-') ?: 'signer';
        $fname = 'NDA-' . $slug . '-' . $signedAt->format('Ymd') . '.pdf';

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $fname . '"')
            ->header('Cache-Control', 'no-store, private');
    }

    /** Flatten the stored signature PNG (data URL) onto white and return JPEG bytes. */
    private function signatureJpeg(?string $dataUrl, int &$w, int &$h): ?string
    {
        if (!$dataUrl || !function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) return null;
        $b64 = $dataUrl;
        if (($c = strpos($b64, ',')) !== false && str_starts_with($b64, 'data:')) {
            $b64 = substr($b64, $c + 1);
        }
        $raw = base64_decode($b64, true);
        if ($raw === false || $raw === '') return null;
        $src = @imagecreatefromstring($raw);
        if (!$src) return null;
        $sw = imagesx($src);
        $sh = imagesy($src);
        $dst = imagecreatetruecolor($sw, $sh);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $sw, $sh, $white);
        imagealphablending($dst, true);
        imagecopy($dst, $src, 0, 0, 0, 0, $sw, $sh);
        ob_start();
        imagejpeg($dst, null, 92);
        $jpeg = ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);
        if (!$jpeg) return null;
        $w = $sw;
        $h = $sh;
        return $jpeg;
    }

    /** The NDA body, as heading/paragraph blocks (mirrors laundre-nda.html). */
    private function ndaBlocks(): array
    {
        return [
            ['h' => 'Non-Disclosure Agreement (NDA)'],
            ['p' => 'This agreement is made between Laundré Franchising Pty Ltd (the Discloser) and the individual signing below (the Recipient).'],

            ['h' => 'Recitals'],
            ['p' => 'The Discloser has agreed to disclose to the Recipient certain Confidential Information on the terms of this agreement. In consideration for this disclosure, the Recipient agrees to treat the Confidential Information as confidential on the terms and conditions set out below.'],

            ['h' => 'Confidentiality Obligations'],
            ['p' => 'In consideration for receiving Confidential Information, the Recipient must:'],
            ['p' => "(a) keep the Confidential Information secret and confidential and, except as permitted by this agreement, not disclose the Confidential Information;\n(b) ensure that the Confidential Information is only disclosed to those directors, employees and professional advisers of it (and its related bodies corporate) who have a specific need to access the Confidential Information for the Purpose (Additional Disclosees);\n(c) ensure that all Additional Disclosees comply with this agreement;\n(d) not use the Confidential Information for any purpose other than the Purpose (including for the Recipient’s own gain or in any manner which may cause loss to the Discloser);\n(e) take all steps reasonably necessary to safeguard the Discloser’s Confidential Information from unauthorised access, use or disclosure; and\n(f) immediately notify the Discloser of any potential, suspected or actual unauthorised disclosure or use of the Confidential Information or breach of the agreement.", 'indent' => 12],
            ['p' => 'The Recipient must immediately on request by the Discloser return or destroy all copies of the Confidential Information and ensure all Additional Disclosees return or destroy all copies of the Confidential Information.'],
            ['p' => 'The Recipient acknowledges that monetary compensation may not be a sufficient remedy for any breach of this agreement and that the Discloser may seek and obtain specific performance or injunctive relief as a remedy for any breach or threatened breach of this agreement, in addition to any other remedies available at law.'],
            ['p' => 'The obligations of confidentiality imposed by this agreement begin on the date when this agreement is signed by the last party and continue in force until all of the Confidential Information is readily available in the public domain or until agreement in writing by all parties.'],
            ['p' => 'The obligations in this agreement do not apply to any Confidential Information which the Recipient can prove:'],
            ['p' => "(a) is in, or comes into, the public domain other than by a breach of this agreement;\n(b) was lawfully in its possession prior to disclosure by the Discloser;\n(c) was received from a third party who is not under an obligation to the Discloser to maintain the Confidential Information in confidence and who legitimately obtained the Confidential Information; or\n(d) it is required to disclose in order to enforce this Agreement or under law or a binding order of a governmental agency or court, and provided that it informs the Discloser in advance of such disclosure being made and uses all reasonable efforts to obtain confidential treatment of such Confidential Information required to be disclosed.", 'indent' => 12],

            ['h' => 'Security and Data Privacy'],
            ['p' => 'The Recipient must, at its cost:'],
            ['p' => "(a) ensure Confidential Information held or handled by it in connection with this agreement is protected against misuse, interference and loss and against unauthorised access, use, modification or disclosure;\n(b) institute effective security measures to prevent the unauthorised access to or use of the Discloser’s Confidential Information;\n(c) keep the Discloser’s Confidential Information under its control and stored in a manner that only it and its authorised officers and employees may access it;\n(d) immediately take all steps, at its own expense, necessary to prevent any suspected or actual unauthorised disclosure of the Discloser’s Confidential Information by any of its officers, employees, agents or contractors;\n(e) immediately notify the Discloser if: (i) it becomes aware or suspects there has been an unauthorised use, copying, or disclosure of, or other security breach in relation to Confidential Information held or handled by it in connection with this agreement, or (ii) it becomes aware that a disclosure of Confidential Information held by it in connection with this agreement is, or may be, required by Law;\n(f) not do anything with Confidential Information that will cause the Discloser to breach any laws, and\n(g) comply with any reasonable request, direction or inquiry made by the Discloser in relation to Confidential Information and any suspected or actual breach of this agreement; and\n(h) provide assistance to the Discloser as it may reasonably request in relation to any action taken by the other party to prevent any suspected or actual unauthorised use, copying or disclosure of the other party’s Confidential Information.", 'indent' => 12],

            ['h' => 'Indemnity'],
            ['p' => 'To the maximum extent permitted by law, the Recipient is liable for and indemnifies the Discloser in respect of any claim, action, damage, loss, liability, cost, charge, expense, outgoing or payment which the Discloser suffers or incurs or is liable for in respect of a breach of this agreement, including by or from any Additional Disclosees, or any infringement of the Discloser’s rights in respect of the Confidential Information by the Recipient, including in respect of consequential or economic loss or damage suffered by the Discloser (including loss of profits or opportunities). Nothing in this agreement is intended to limit the operation of the Competition and Consumer Act 2010 (Cth).'],

            ['h' => 'Acknowledgements and Disclaimers'],
            ['p' => 'The Recipient acknowledges that the Discloser owns the Confidential Information and all rights (including intellectual property rights) in it. Nothing in this agreement may be construed as granting or conferring on the Recipient any proprietary rights, licences or other rights in any of either Discloser’s Confidential Information, other than the rights expressly granted under this agreement.'],

            ['h' => 'General'],
            ['p' => 'The laws specified in Queensland, Australia govern this agreement. Each party submits to the non-exclusive jurisdiction of the courts of that place and the courts of appeal from them. This agreement constitutes the entire agreement between the parties about its subject matter and supersedes any previous understanding, agreement, representation or warranty relating to Confidential Information. This agreement may only be varied by written agreement of the parties. This agreement may be executed in any number of counterparts. All counterparts together will be taken to constitute one instrument.'],

            ['h' => 'Definitions'],
            ['p' => 'Confidential Information means this agreement and all information of the Discloser which is disclosed to or otherwise comes to be known by the Recipient, whether before or after the date of this agreement, which is in fact or which is reasonably regarded by the Discloser as confidential to the Discloser. This includes but is not limited to information relating to financial information, data including on the Discloser’s systems, computer software, marketing strategies, technology, processes, products, specifications, inventions or designs used or developed by the Discloser, trade secrets and know-how and information of a commercially sensitive nature, including any of the above that are developed in connection with the business of the Discloser.'],
            ['p' => 'Purpose means the purpose of enabling the Discloser and the Recipient to engage in commercial discussions regarding the Confidential Information.'],
        ];
    }
}
