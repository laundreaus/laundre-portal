<?php
namespace App\Support;

/**
 * Minimal, dependency-free PDF writer.
 *
 * Supports: A4 pages, the two standard Helvetica core fonts (regular + bold,
 * WinAnsi encoding, no embedding required), word-wrapped paragraphs and
 * headings with automatic page breaks, and embedding JPEG images (DCTDecode).
 *
 * Built specifically to render executed Laundré NDA copies on the server
 * without needing a Composer package such as dompdf/FPDF.
 */
class SimplePdf
{
    /** @var string[] finished page content streams */
    private array $pages = [];
    private string $buf = '';

    /** @var array<int,array{w:int,h:int,data:string}> embedded JPEG images */
    private array $images = [];

    private float $pw = 595.28;   // A4 width  (pt)
    private float $ph = 841.89;   // A4 height (pt)
    private float $ml = 56.0;     // left margin
    private float $mr = 56.0;     // right margin
    private float $mt = 60.0;     // top margin
    private float $mb = 56.0;     // bottom margin
    private float $y;

    /** @var array<int,int> Helvetica character widths (per 1000 em) */
    private static array $cw = [];

    public function __construct()
    {
        if (!self::$cw) self::buildWidths();
        $this->y = $this->ph - $this->mt;
    }

    public function addPage(): void
    {
        if ($this->buf !== '') { $this->pages[] = $this->buf; $this->buf = ''; }
        $this->y = $this->ph - $this->mt;
    }

    /** Section heading (bold). */
    public function heading(string $text, float $size = 12.5): void
    {
        $this->y -= 4;
        $this->emitLine($this->enc($text), $size, true, 0);
        $this->y -= 3;
    }

    /** Large document title (bold, centred). */
    public function title(string $text, float $size = 17): void
    {
        $lh = $size * 1.4;
        $this->ensure($lh);
        $this->y -= $lh;
        $t = $this->enc($text);
        $x = ($this->pw - $this->w($t, $size)) / 2;
        $this->buf .= "BT /F2 " . $this->n($size) . " Tf " . $this->n($x) . " " . $this->n($this->y) . " Td (" . $this->esc($t) . ") Tj ET\n";
    }

    /** Body paragraph. Newlines force line breaks; text is word-wrapped. */
    public function paragraph(string $text, float $size = 10.2, bool $bold = false, float $indent = 0, float $gap = 6): void
    {
        foreach (explode("\n", $text) as $para) {
            $this->writeWrapped($this->enc($para), $size, $bold, $indent);
        }
        $this->y -= $gap;
    }

    /** Bold label + regular value on one line. */
    public function field(string $label, string $value, float $size = 10.5): void
    {
        $lh = $size * 1.6;
        $this->ensure($lh);
        $this->y -= $lh;
        $l = $this->enc($label);
        $v = $this->enc($value);
        $x = $this->ml;
        $this->buf .= "BT /F2 " . $this->n($size) . " Tf " . $this->n($x) . " " . $this->n($this->y) . " Td (" . $this->esc($l) . ") Tj ET\n";
        $lw = $this->w($l, $size) * 1.08 + 11;
        $this->buf .= "BT /F1 " . $this->n($size) . " Tf " . $this->n($x + $lw) . " " . $this->n($this->y) . " Td (" . $this->esc($v) . ") Tj ET\n";
    }

    public function spacer(float $pts = 8): void { $this->y -= $pts; }

    /** Horizontal rule across the text column. */
    public function rule(): void
    {
        $this->ensure(10);
        $this->y -= 8;
        $this->buf .= "0.75 w 0.8 0.8 0.8 RG " . $this->n($this->ml) . " " . $this->n($this->y) . " m " . $this->n($this->pw - $this->mr) . " " . $this->n($this->y) . " l S 0 0 0 RG\n";
        $this->y -= 4;
    }

    /**
     * Embed a JPEG image (raw JPEG bytes) at display width $dispW (pt),
     * left-aligned. Height scales to preserve aspect ratio.
     */
    public function image(string $jpeg, int $imgW, int $imgH, float $dispW): void
    {
        if ($imgW <= 0 || $imgH <= 0) return;
        $dispH = $dispW * $imgH / $imgW;
        $this->ensure($dispH + 4);
        $this->y -= $dispH;
        $this->images[] = ['w' => $imgW, 'h' => $imgH, 'data' => $jpeg];
        $n = count($this->images);
        $this->buf .= "q " . $this->n($dispW) . " 0 0 " . $this->n($dispH) . " " . $this->n($this->ml) . " " . $this->n($this->y) . " cm /Img{$n} Do Q\n";
        $this->y -= 4;
    }

    // ---- internals ----------------------------------------------------------

    private function writeWrapped(string $s, float $size, bool $bold, float $indent): void
    {
        $maxw = $this->pw - $this->ml - $this->mr - $indent;
        $s = trim($s);
        if ($s === '') { $this->y -= $size * 1.45; return; }
        $words = preg_split('/ +/', $s);
        $lineWords = [];
        $lineW = 0.0;
        $spaceW = $this->w(' ', $size);
        foreach ($words as $word) {
            $ww = $this->w($word, $size);
            if ($lineWords && ($lineW + $spaceW + $ww) > $maxw) {
                $this->emitLine(implode(' ', $lineWords), $size, $bold, $indent);
                $lineWords = [$word];
                $lineW = $ww;
            } else {
                $lineW = $lineWords ? ($lineW + $spaceW + $ww) : $ww;
                $lineWords[] = $word;
            }
        }
        if ($lineWords) $this->emitLine(implode(' ', $lineWords), $size, $bold, $indent);
    }

    private function emitLine(string $text, float $size, bool $bold, float $indent): void
    {
        $lh = $size * 1.45;
        $this->ensure($lh);
        $this->y -= $lh;
        $f = $bold ? 'F2' : 'F1';
        $x = $this->ml + $indent;
        $this->buf .= "BT /{$f} " . $this->n($size) . " Tf " . $this->n($x) . " " . $this->n($this->y) . " Td (" . $this->esc($text) . ") Tj ET\n";
    }

    /** Break to a new page if $need pt won't fit above the bottom margin. */
    private function ensure(float $need): void
    {
        if ($this->y - $need < $this->mb) {
            $this->pages[] = $this->buf;
            $this->buf = '';
            $this->y = $this->ph - $this->mt;
        }
    }

    private function esc(string $s): string
    {
        return str_replace(["\\", "(", ")", "\r", "\n"], ["\\\\", "\\(", "\\)", "", ""], $s);
    }

    /** UTF-8 -> Windows-1252 so accented chars / curly quotes render with core fonts. */
    private function enc(string $s): string
    {
        if (function_exists('iconv')) {
            $r = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
            if ($r !== false) return $r;
        }
        if (function_exists('mb_convert_encoding')) {
            $r = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
            if ($r !== false) return $r;
        }
        return $s;
    }

    private function w(string $str, float $size): float
    {
        $wd = 0;
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $wd += self::$cw[ord($str[$i])] ?? 556;
        }
        return $wd * $size / 1000;
    }

    private function n(float $v): string
    {
        return rtrim(rtrim(sprintf('%.2F', $v), '0'), '.');
    }

    public function output(): string
    {
        if ($this->buf !== '') { $this->pages[] = $this->buf; $this->buf = ''; }
        if (!$this->pages) $this->pages[] = '';

        // Fixed object numbering:
        //   1 = Catalog, 2 = Pages, 3 = Font F1, 4 = Font F2
        //   images: 5 .. 4+I   |   pages+contents follow
        $I = count($this->images);
        $firstPageObj = 5 + $I;
        $P = count($this->pages);

        $obj = []; // objnum => body

        $obj[1] = "<< /Type /Catalog /Pages 2 0 R >>";

        $kids = [];
        for ($k = 0; $k < $P; $k++) $kids[] = ($firstPageObj + $k * 2) . " 0 R";
        $obj[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count {$P} >>";

        $obj[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $obj[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        $xobjRefs = '';
        for ($i = 0; $i < $I; $i++) {
            $num = 5 + $i;
            $im = $this->images[$i];
            $obj[$num] = "<< /Type /XObject /Subtype /Image /Width {$im['w']} /Height {$im['h']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($im['data']) . " >>\nstream\n" . $im['data'] . "\nendstream";
            $xobjRefs .= "/Img" . ($i + 1) . " {$num} 0 R ";
        }

        $resources = "<< /Font << /F1 3 0 R /F2 4 0 R >>"
            . ($I ? " /XObject << " . trim($xobjRefs) . " >>" : "")
            . " >>";

        for ($k = 0; $k < $P; $k++) {
            $pageObj = $firstPageObj + $k * 2;
            $contentObj = $pageObj + 1;
            $stream = $this->pages[$k];
            $obj[$pageObj] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . $this->n($this->pw) . " " . $this->n($this->ph) . "] /Resources {$resources} /Contents {$contentObj} 0 R >>";
            $obj[$contentObj] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        }

        ksort($obj);
        $N = max(array_keys($obj));

        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        for ($i = 1; $i <= $N; $i++) {
            $offsets[$i] = strlen($out);
            $out .= "{$i} 0 obj\n" . ($obj[$i] ?? "<< >>") . "\nendobj\n";
        }
        $xrefPos = strlen($out);
        $out .= "xref\n0 " . ($N + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $N; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $out .= "trailer\n<< /Size " . ($N + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";
        return $out;
    }

    private static function buildWidths(): void
    {
        // Helvetica AFM advance widths (units per 1000 em), ASCII 32-126.
        $w = [
            32=>278,33=>278,34=>355,35=>556,36=>556,37=>889,38=>667,39=>191,40=>333,41=>333,
            42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,48=>556,49=>556,50=>556,51=>556,
            52=>556,53=>556,54=>556,55=>556,56=>556,57=>556,58=>278,59=>278,60=>584,61=>584,
            62=>584,63=>556,64=>1015,65=>667,66=>667,67=>722,68=>722,69=>667,70=>611,71=>778,
            72=>722,73=>278,74=>500,75=>667,76=>556,77=>833,78=>722,79=>778,80=>667,81=>778,
            82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,89=>667,90=>611,91=>278,
            92=>278,93=>278,94=>469,95=>556,96=>333,97=>556,98=>556,99=>500,100=>556,101=>556,
            102=>278,103=>556,104=>556,105=>222,106=>222,107=>500,108=>222,109=>833,110=>556,
            111=>556,112=>556,113=>556,114=>333,115=>500,116=>278,117=>556,118=>500,119=>722,
            120=>500,121=>500,122=>500,123=>334,124=>260,125=>334,126=>584,
            // Common Windows-1252 high range used in the NDA (quotes, dashes, accents).
            145=>222,146=>222,147=>333,148=>333,150=>556,151=>1000,169=>737,233=>556,
        ];
        $full = [];
        for ($i = 0; $i < 256; $i++) $full[$i] = $w[$i] ?? 556;
        self::$cw = $full;
    }
}
