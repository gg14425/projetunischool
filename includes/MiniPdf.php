<?php
/**
 * MiniPdf — petit générateur PDF sans dépendance (PDF 1.4).
 * Polices standard Helvetica / Helvetica-Bold (encodage WinAnsi = accents français OK).
 * Limite : les caractères hors Windows-1252 (ex. alphabet arabe) sont remplacés par « ? ».
 */
declare(strict_types=1);

final class MiniPdf
{
    public const W = 595.28;   // A4 portrait, en points
    public const H = 841.89;

    private const HELV = [
        278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,
        556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,
        1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,
        667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,
        333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,
        556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,
    ];
    private const HELV_BOLD = [
        278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,
        556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,
        975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,
        667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,
        333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,
        611,611,389,556,333,611,556,778,556,556,500,389,280,389,584,
    ];

    /** @var string[] contenu (flux) de chaque page */
    private array $pages = [];
    private int $cur = -1;
    private string $title;

    public function __construct(string $title = 'Document')
    {
        $this->title = $title;
        $this->addPage();
    }

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->cur = count($this->pages) - 1;
    }

    /** Convertit UTF-8 → Windows-1252 puis échappe pour une chaîne PDF. */
    private function enc(string $s): string
    {
        $s = str_replace(["\u{2019}", "\u{2018}", "\u{2013}", "\u{2014}", "\u{00A0}", "\u{202F}"], ["'", "'", '-', '-', ' ', ' '], $s);
        $c = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        $c = $c === false ? '?' : $c;
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $c);
    }

    public function textWidth(string $s, float $size, bool $bold = false): float
    {
        $tbl = $bold ? self::HELV_BOLD : self::HELV;
        $c   = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        $c   = $c === false ? $s : $c;
        $w   = 0;
        for ($i = 0, $n = strlen($c); $i < $n; $i++) {
            $o  = ord($c[$i]);
            $w += ($o >= 32 && $o <= 126) ? $tbl[$o - 32] : 556;
        }
        return $w * $size / 1000;
    }

    private function color(array $rgb): string
    {
        return sprintf('%.3F %.3F %.3F', $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
    }

    /** Texte à la position (x, y) — y mesuré depuis le HAUT de la page. */
    public function text(float $x, float $y, string $s, float $size = 11, bool $bold = false, array $rgb = [0, 0, 0]): void
    {
        $this->pages[$this->cur] .= sprintf(
            "BT /%s %.2F Tf %s rg %.2F %.2F Td (%s) Tj ET\n",
            $bold ? 'F2' : 'F1', $size, $this->color($rgb), $x, self::H - $y, $this->enc($s)
        );
    }

    public function textCentered(float $y, string $s, float $size = 11, bool $bold = false, array $rgb = [0, 0, 0]): void
    {
        $this->text((self::W - $this->textWidth($s, $size, $bold)) / 2, $y, $s, $size, $bold, $rgb);
    }

    public function textRight(float $xRight, float $y, string $s, float $size = 11, bool $bold = false): void
    {
        $this->text($xRight - $this->textWidth($s, $size, $bold), $y, $s, $size, $bold);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 0.8, array $rgb = [0, 0, 0]): void
    {
        $this->pages[$this->cur] .= sprintf(
            "%.2F w %s RG %.2F %.2F m %.2F %.2F l S\n",
            $width, $this->color($rgb), $x1, self::H - $y1, $x2, self::H - $y2
        );
    }

    public function rect(float $x, float $y, float $w, float $h, array $fill, ?array $stroke = null): void
    {
        $op = $stroke ? 'B' : 'f';
        $this->pages[$this->cur] .= sprintf(
            "%s rg %s RG 0.5 w %.2F %.2F %.2F %.2F re %s\n",
            $this->color($fill), $this->color($stroke ?? $fill), $x, self::H - $y - $h, $w, $h, $op
        );
    }

    /**
     * Paragraphe avec retour à la ligne automatique. Retourne le nouveau y.
     */
    public function paragraph(float $x, float $y, float $width, string $s, float $size = 11, bool $bold = false, float $leading = 0): float
    {
        $leading = $leading ?: $size * 1.5;
        foreach (explode("\n", $s) as $para) {
            $line = '';
            foreach (preg_split('/\s+/', trim($para)) as $word) {
                $try = $line === '' ? $word : $line . ' ' . $word;
                if ($line !== '' && $this->textWidth($try, $size, $bold) > $width) {
                    $this->text($x, $y, $line, $size, $bold);
                    $y   += $leading;
                    $line = $word;
                } else {
                    $line = $try;
                }
            }
            if ($line !== '') {
                $this->text($x, $y, $line, $size, $bold);
            }
            $y += $leading;
        }
        return $y;
    }

    /** Tronque un texte pour qu'il tienne dans $maxWidth. */
    public function fit(string $s, float $maxWidth, float $size, bool $bold = false): string
    {
        if ($this->textWidth($s, $size, $bold) <= $maxWidth) {
            return $s;
        }
        while ($s !== '' && $this->textWidth($s . '…', $size, $bold) > $maxWidth) {
            $s = mb_substr($s, 0, -1);
        }
        return $s . '…';
    }

    /** Génère le fichier PDF (octets). */
    public function output(): string
    {
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $n    = count($this->pages);
        for ($i = 0; $i < $n; $i++) {
            $kids[] = (5 + $i * 2) . ' 0 R';
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        foreach ($this->pages as $i => $content) {
            $pageNo = 5 + $i * 2;
            $objs[$pageNo] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::W, self::H, $pageNo + 1
            );
            $objs[$pageNo + 1] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
        }
        $infoNo = 5 + $n * 2;
        $objs[$infoNo] = '<< /Title (' . $this->enc($this->title) . ') /Producer (My EPI App) /CreationDate (D:' . date('YmdHis') . ') >>';

        $out  = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offs = [];
        ksort($objs);
        foreach ($objs as $no => $body) {
            $offs[$no] = strlen($out);
            $out .= "$no 0 obj\n$body\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 " . ($infoNo + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $infoNo; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offs[$i]);
        }
        $out .= "trailer\n<< /Size " . ($infoNo + 1) . " /Root 1 0 R /Info $infoNo 0 R >>\nstartxref\n$xref\n%%EOF";
        return $out;
    }
}
