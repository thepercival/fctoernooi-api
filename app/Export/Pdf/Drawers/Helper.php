<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers;

use Zend_Pdf_Font;
use Zend_Pdf_Resource_Font;

class Helper
{
    public function __construct(
    ) {
    }

    public function getTimesFont(bool $bBold = false, bool $bItalic = false): Zend_Pdf_Resource_Font
    {
        $suffix = 'times.ttf';
        if ($bBold === true and $bItalic === false) {
            $suffix = 'timesbd.ttf';
        } elseif ($bBold === false and $bItalic === true) {
            $suffix = 'timesi.ttf';
        } elseif ($bBold === true and $bItalic === true) {
            $suffix = 'timesbi.ttf';
        }
        $sFontDir = __DIR__ . '/../../../../fonts/';
        return Zend_Pdf_Font::fontWithPath($sFontDir . $suffix);
    }

    public function getTextWidth(
        string $sText,
        Zend_Pdf_Resource_Font $font,
        float $nFontSize
    ): float {
        $nCharPosition = 0;
        $nFontUnitsPerEM = $font->getUnitsPerEm();
        // $unicodeString = 'aÄ…bcÄ�deÄ™Ã«Å‚';
        $chrArray = preg_split('//u', $sText, -1, PREG_SPLIT_NO_EMPTY);
        if ($chrArray === false) {
            return $nCharPosition;
        }
        for ($nCharIndex = 0; $nCharIndex < count($chrArray); $nCharIndex++) {
            $nTmp = $this->uniord($chrArray[$nCharIndex]);

            $nCharWidth = $font->widthForGlyph($font->glyphNumberForCharacter($nTmp));
            $nCharWidth = $nCharWidth / $nFontUnitsPerEM * $nFontSize;

            $nCharPosition += $nCharWidth;
        }
        return $nCharPosition;
    }

    public function uniord(string $char): int
    {
        // $sUCS2Char = mb_convert_encoding( $sChar, 'UCS-2LE', 'UTF-8');
        $charCode1 = ord(substr($char, 0, 1));
        $charCode2 = ord(substr($char, 1, 1));
        return $charCode2 * 256 + $charCode1;
    }
}
