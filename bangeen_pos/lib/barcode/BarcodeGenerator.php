<?php
/**
 * Professional Code128-B Barcode Generator
 * Clean output — scanner compatible
 * Simple number below bars only
 */
class BarcodeGenerator
{
    private static $patterns = [
        '11011001100','11001101100','11001100110','10010011000','10010001100',
        '10001001100','10011001000','10011000100','10001100100','11001001000',
        '11001000100','11000100100','10110011100','10011011100','10011001110',
        '10111001100','10011101100','10011100110','11001110010','11001011100',
        '11001001110','11011100100','11001110100','11101101110','11101001100',
        '11100101100','11100100110','11101100100','11100110100','11100110010',
        '11011011000','11011000110','11000110110','10100011000','10001011000',
        '10001000110','10110001000','10001101000','10001100010','11010001000',
        '11000101000','11000100010','10110111000','10110001110','10001101110',
        '10111011000','10111000110','10001110110','11101110110','11010001110',
        '11000101110','11011101000','11011100010','11011101110','11101011000',
        '11101000110','11100010110','11101101000','11101100010','11100011010',
        '11101111010','11001000010','11110001010','10100110000','10100001100',
        '10010110000','10010000110','10000101100','10000100110','10110010000',
        '10110000100','10011010000','10011000010','10000110100','10000110010',
        '11000010010','11001010000','11110111010','11000010100','10001111010',
        '10100111100','10010111100','10010011110','10111100100','10011110100',
        '10011110010','11110100100','11110010100','11110010010','11011011110',
        '11011110110','11110110110','10101111000','10100011110','10001011110',
        '10111101000','10111100010','11110101000','11110100010','10111011110',
        '10111101110','11101011110','11110101110','11010000100','11010010000',
        '11010011100','11000111010',
    ];

    /**
     * Generate barcode PNG — bars + number below
     * barHeight = height of bars in px
     * barWidth  = width of each module in px (use 3+ for thermal printers)
     */
    public static function generatePNG(
        string $text,
        string $filePath,
        int $barHeight = 80,
        int $barWidth  = 3
    ): bool {
        if (!function_exists('imagecreatetruecolor')) return false;

        // Only use simple ASCII — strip any non-printable chars
        $text = preg_replace('/[^\x20-\x7E]/', '', $text);
        if (!$text) return false;

        $symbols = self::encode($text);
        if (empty($symbols)) return false;

        // Build binary bar string
        $binary = '';
        foreach ($symbols as $s) {
            $binary .= self::$patterns[$s] ?? '00000000000';
        }
        $binary .= '11'; // termination bar

        $barCount  = strlen($binary);
        $quietZone = $barWidth * 10; // quiet zone = 10x bar width
        $topPad    = 8;
        $textGap   = 4;
        $fontSize  = 3;
        $charW     = imagefontwidth($fontSize);
        $charH     = imagefontheight($fontSize);
        $textW     = $charW * strlen($text);

        $imgW = $barCount * $barWidth + $quietZone * 2;
        $imgH = $topPad + $barHeight + $textGap + $charH + 8;

        // Make sure image is wide enough for text
        if ($imgW < $textW + 20) {
            $imgW = $textW + 20;
        }

        $img   = imagecreatetruecolor($imgW, $imgH);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);

        // White background
        imagefill($img, 0, 0, $white);

        // Draw bars left to right
        $x = $quietZone;
        for ($i = 0; $i < $barCount; $i++) {
            if ($binary[$i] === '1') {
                imagefilledrectangle(
                    $img,
                    $x,
                    $topPad,
                    $x + $barWidth - 1,
                    $topPad + $barHeight - 1,
                    $black
                );
            }
            $x += $barWidth;
        }

        // Draw number centered below bars — left to right, normal orientation
        $textX = (int)(($imgW - $textW) / 2);
        $textY = $topPad + $barHeight + $textGap;
        imagestring($img, $fontSize, $textX, $textY, $text, $black);

        // Save PNG
        $dir = dirname($filePath);
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $result = imagepng($img, $filePath);
        imagedestroy($img);
        return $result;
    }

    /**
     * Generate label: name on top + barcode + number
     */
    public static function generateLabel(
        string $text,
        string $filePath,
        string $productName = '',
        string $price       = '',
        int    $barHeight   = 80,
        int    $barWidth    = 3
    ): bool {
        if (!function_exists('imagecreatetruecolor')) return false;

        $text = preg_replace('/[^\x20-\x7E]/', '', $text);
        if (!$text) return false;

        $symbols = self::encode($text);
        if (empty($symbols)) return false;

        $binary = '';
        foreach ($symbols as $s) $binary .= self::$patterns[$s] ?? '00000000000';
        $binary .= '11';

        $barCount  = strlen($binary);
        $quietZone = $barWidth * 10;
        $barsW     = $barCount * $barWidth + $quietZone * 2;

        $fontSize3 = 3;
        $charW3    = imagefontwidth($fontSize3);
        $textW     = $charW3 * strlen($text);

        $fontSize4 = 4;
        $charW4    = imagefontwidth($fontSize4);

        // Clean product name — GD only supports ASCII
        $displayName = preg_replace('/[^\x20-\x7E]/', '', $productName);
        if (strlen(trim($displayName)) < 2) {
            $displayName = '';
        }
        $nameW = $displayName ? $charW4 * strlen($displayName) : 0;

        $labelW = max($barsW, $textW + 20, $nameW + 20, 250);
        $labelH = 10
                + ($displayName ? imagefontheight($fontSize4) + 6 : 0)
                + $barHeight
                + 4 + imagefontheight($fontSize3)
                + 10;

        $img   = imagecreatetruecolor($labelW, $labelH);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $white);

        // Thin border
        imagerectangle($img, 0, 0, $labelW-1, $labelH-1, $black);

        $y = 8;

        // Product name centered at top
        if ($displayName) {
            $nx = (int)(($labelW - $nameW) / 2);
            imagestring($img, $fontSize4, $nx, $y, $displayName, $black);
            $y += imagefontheight($fontSize4) + 6;
        }

        // Barcode bars centered
        $barStartX = (int)(($labelW - ($barCount * $barWidth + $quietZone * 2)) / 2);
        $x = $barStartX + $quietZone;
        for ($i = 0; $i < $barCount; $i++) {
            if ($binary[$i] === '1') {
                imagefilledrectangle($img, $x, $y, $x + $barWidth - 1, $y + $barHeight - 1, $black);
            }
            $x += $barWidth;
        }
        $y += $barHeight + 4;

        // Barcode number centered
        $tx = (int)(($labelW - $textW) / 2);
        imagestring($img, $fontSize3, $tx, $y, $text, $black);

        $dir = dirname($filePath);
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $result = imagepng($img, $filePath);
        imagedestroy($img);
        return $result;
    }

    private static function encode(string $text): array
    {
        $symbols  = [104]; // START B
        $checksum = 104;

        for ($i = 0; $i < strlen($text); $i++) {
            $ascii = ord($text[$i]);
            if ($ascii < 32 || $ascii > 126) $ascii = 32;
            $val       = $ascii - 32;
            $symbols[] = $val;
            $checksum += $val * ($i + 1);
        }

        $symbols[] = $checksum % 103; // check digit
        $symbols[] = 106;             // STOP

        return $symbols;
    }
}