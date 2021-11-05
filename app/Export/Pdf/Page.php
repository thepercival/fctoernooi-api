<?php
declare(strict_types=1);

namespace App\Export\Pdf;

use Zend_Pdf_Color;
use Zend_Pdf_Color_Html;
use Zend_Pdf_Exception;
use Zend_Pdf_Image;
use Zend_Pdf_Page;
use Zend_Pdf_Resource_ImageFactory;

abstract class Page extends Zend_Pdf_Page
{
    protected Zend_Pdf_Color $textColor;
    protected Zend_Pdf_Color $fillColor;
    protected Zend_Pdf_Color|null $fillColorTmp = null;
    protected float $lineWidth;
    protected float $padding;

    public function __construct(protected Document $parent, mixed $param1, mixed $param2 = null, mixed $param3 = null)
    {
        parent::__construct($param1, $param2, $param3);
        $this->setFillColor(new Zend_Pdf_Color_Html('white'));
        $this->textColor = new Zend_Pdf_Color_Html('black');
        $this->setLineWidth(1);
        $this->padding = 1.0;
        $this->_fontSize = 0.0;
        $this->_safeGS = true;
        $this->_attached = false;
        $this->lineWidth = 1.0;
    }

    abstract public function getHeaderHeight(): float;

    abstract public function getPageMargin(): float;

    public function getParent(): Document
    {
        return $this->parent;
    }

    public function getFillColor(): Zend_Pdf_Color
    {
        return $this->fillColor;
    }

    final public function setFillColor(Zend_Pdf_Color $color): self
    {
        parent::setFillColor($color);
        $this->fillColor = $color;
        return $this;
    }

    public function getLineWidth(): float
    {
        return $this->lineWidth;
    }

    /**
     * @param float $width
     * @return self
     */
    public function setLineWidth($width): self
    {
        parent::setLineWidth($width);
        $this->lineWidth = $width;
        return $this;
    }

    public function getPadding(): float
    {
        return $this->padding;
    }

    public function setPadding(float $padding): void
    {
        $this->padding = $padding;
    }

    public function drawHeader(string $subTitle = null, float $y = null): float
    {
        if ($y === null) {
            $y = $this->getHeight() - $this->getPageMargin();
        }
        $this->setFont($this->getParent()->getFont(), $this->getParent()->getFontHeight());
        $title = 'FCToernooi';
        $subTitle = $subTitle === null ? '' : $subTitle;

        $displayWidth = $this->getDisplayWidth();
        $margin = $displayWidth / 25;
        $nRowHeight = 20;
        $imgSize = $nRowHeight;
        $widthLeft = $imgSize + $this->getTextWidth($title);
        $xLeft = $this->getPageMargin();
        $xCenter = $xLeft + $widthLeft + $margin;
        $widthRight = strlen($subTitle) > 0 ? $this->getTextWidth($subTitle) : 0;
        $xRight = strlen($subTitle) > 0 ? $this->getWidth() - ($this->getPageMargin() + $widthRight) : 0;
        $widthCenter = $displayWidth - ($widthLeft + $margin);
        if (strlen($subTitle) > 0) {
            $widthCenter -= ($margin + $widthRight);
        }
        /** @var Zend_Pdf_Image $img */
        $img = Zend_Pdf_Resource_ImageFactory::factory(__DIR__ . '/../logo.jpg');
        $this->drawImage($img, $xLeft, $y - $imgSize, $xLeft + $imgSize, $y);

        $arrLineColors = array('b' => 'black');
        $this->drawCell(
            'FCToernooi',
            $xLeft + $imgSize,
            $y,
            $widthLeft - $imgSize,
            $nRowHeight,
            Align::Left,
            $arrLineColors
        );


        $name = $this->getParent()->getTournament()->getCompetition()->getLeague()->getName();
        $this->drawCell($name, $xCenter, $y, $widthCenter, $nRowHeight, Align::Left, $arrLineColors);

        if (strlen($subTitle) > 0) {
            $this->drawCell($subTitle, $xRight, $y, $widthRight, $nRowHeight, Align::Right, $arrLineColors);
        }

        return $y - (2 * $nRowHeight);
    }

    public function drawSubHeader(string $subHeader, float $y): float
    {
        $fontHeightSubHeader = $this->getParent()->getFontHeightSubHeader();
        $this->setFont($this->getParent()->getFont(true), $this->getParent()->getFontHeightSubHeader());
        $x = $this->getPageMargin();
        $displayWidth = $this->getDisplayWidth();
        $this->drawCell($subHeader, $x, $y, $displayWidth, $fontHeightSubHeader, Align::Center);
        return $y - (2 * $fontHeightSubHeader);
    }

    public function getDisplayWidth(): float
    {
        return $this->getWidth() - (2 * $this->getPageMargin());
    }

    /**
     * @param string $sText
     * @param float $xPos
     * @param float $yPos
     * @param float $nWidth
     * @param float $nHeight
     * @param int $nAlign
     * @param array<string, Zend_Pdf_Color | string>| string | null $vtLineColors
     * @param int|null $degrees
     * @return float
     * @throws Zend_Pdf_Exception
     */
    public function drawCell(
        string $sText,
        float $xPos,
        float $yPos,
        float $nWidth,
        float $nHeight,
        int $nAlign = Align::Left,
        array|string|null $vtLineColors = null,
        int $degrees = null
    ): float {
        $nStyle = Zend_Pdf_Page::SHAPE_DRAW_FILL_AND_STROKE;

        $arrLineColors = $this->getLineColorsFromInput($vtLineColors);

        if ($arrLineColors !== null) {
            $nLineWidth = $this->getLineWidth();
            $this->setLineColor($this->getFillColor());

            $this->drawRectangle(
                $xPos + $nLineWidth,
                $yPos - ($nHeight - $nLineWidth),
                $xPos + ($nWidth - $nLineWidth),
                $yPos - $nLineWidth,
                $nStyle
            );

            if (array_key_exists('b', $arrLineColors) === true) {
                $this->setLineColor($arrLineColors['b']);
                $this->drawLine($xPos + $nWidth, $yPos - $nHeight, $xPos, $yPos - $nHeight); // leftwards
            }
            if (array_key_exists('t', $arrLineColors) === true) {
                $this->setLineColor($arrLineColors['t']);
                $this->drawLine($xPos, $yPos, $xPos + $nWidth, $yPos);    // rightwards
            }

            if (array_key_exists('l', $arrLineColors) === true) {
                $this->setLineColor($arrLineColors['l']);
                $this->drawLine($xPos, $yPos - $nHeight, $xPos, $yPos);  // upwards
            }
            if (array_key_exists('r', $arrLineColors) === true) {
                $this->setLineColor($arrLineColors['r']);
                $this->drawLine($xPos + $nWidth, $yPos, $xPos + $nWidth, $yPos - $nHeight); // downwards
            }
        } else {
            $this->setLineColor($this->getFillColor());
            $this->drawRectangle($xPos, $yPos - $nHeight, $xPos + $nWidth, $yPos, $nStyle);
        }

        $nFontSize = $this->getFontSize();
        $nTextY = (int)($yPos - ((($nHeight / 2) + ($nFontSize / 2)) - 1.5));

        $maxLength = $nWidth;
        $stringXPos = $xPos;
        if ($degrees === null) {
            $degrees = 0;
        } elseif ($degrees > 45) {
            $maxLength = $nHeight;
            $stringXPos -= ($nHeight - $nWidth) / 2;
        }
        $this->drawString($sText, $stringXPos, $nTextY, $maxLength, $nAlign, $degrees);

        return $xPos + $nWidth;
    }

    /**
     * @param array<string, Zend_Pdf_Color | string>| Zend_Pdf_Color | string | null $vtLineColors
     * @return array<string, Zend_Pdf_Color>| null
     * @throws Zend_Pdf_Exception
     */
    protected function getLineColorsFromInput(array | Zend_Pdf_Color | string | null $vtLineColors): array | null
    {
        if ($vtLineColors === null) {
            return null;
        }
        if (is_array($vtLineColors)) {
            $retVal = [];
            foreach ($vtLineColors as $sIndex => $vtLineColor) {
                if (is_string($vtLineColor)) {
                    $vtLineColor = new Zend_Pdf_Color_Html($vtLineColor);
                }
                $retVal[$sIndex] = $vtLineColor;
            }
            return $retVal;
        }

        if (is_string($vtLineColors)) {
            $oColor = new Zend_Pdf_Color_Html($vtLineColors);
        } else { // if ($vtLineColors instanceof Zend_Pdf_Color) {
            $oColor = $vtLineColors;
        }
        return array('l' => $oColor, 't' => $oColor, 'r' => $oColor, 'b' => $oColor);
    }

    public function drawString(
        string|null $sText,
        float $xPos,
        float $yPos,
        float $nMaxWidth = null,
        int $nAlign = Align::Left,
        int $nRotationDegree = 0
    ): float {
        $font = $this->getFont();
        $nFontUnitsPerEM = $font->getUnitsPerEm();
        $nFontSize = $this->getFontSize();
        $sText = $sText === null ? '' : $sText;

        if ($nRotationDegree > 0) {
            $nRotationAngle = M_PI / 6; //standaard M_PI/6 = 30 graden
            if ($nRotationDegree == 45) {
                $nRotationAngle = M_PI / 4;
            }
            if ($nRotationDegree == 90) {
                $nRotationAngle = M_PI / 2;
            }

            $xMiddle = $xPos;
            if ($nMaxWidth !== null) {
                $xMiddle += ($nMaxWidth / 2);
            }
            $yMiddle = $yPos + ($nFontSize / 2);
            $this->rotate($xMiddle, $yMiddle, $nRotationAngle);
            // $yTextBase -= $nFontSize - $nPadding; //Y richting is nu horizontaal (bij hoek 90 graden)
            //$xTextBase -= round( ($nHeight )/2 ) - 2*$nPadding	 ; //MOET NOG ANDERS!!  //round( ($nWidth - $nTextWidth)/2 ) centreert hem nu verticaal
            $nRetVal = $this->drawString($sText, $xPos, $yPos, $nMaxWidth, $nAlign);

            $this->rotate($xMiddle, $yMiddle, -$nRotationAngle);

            return $nRetVal;
        }

        $oFillColorTmp = $this->getFillColor();
        $this->setFillColor($this->textColor);

        $widthForStartPosition = 0;
        if ($nMaxWidth !== null) {
            $widthForStartPosition = $nMaxWidth;
        }
        $nNewXPos = $this->getTextStartPosition($xPos, $sText, $nAlign, $widthForStartPosition);

        $nDotDotWidth = $font->widthForGlyph($font->glyphNumberForCharacter(ord('.')));
        $nDotDotWidth = $nDotDotWidth / $nFontUnitsPerEM * $nFontSize;
        $nDotDotWidth *= 2;

        $nCharPosition = 0;
        // $unicodeString = 'aÄ…bcÄ�deÄ™Ã«Å‚';
        $chrArray = preg_split('//u', $sText, -1, PREG_SPLIT_NO_EMPTY);
        if ($chrArray === false) {
            return $nNewXPos;
        }
        for ($nCharIndex = 0; $nCharIndex < count($chrArray); $nCharIndex++) {
            $nTmp = $this->uniord($chrArray[$nCharIndex]);

            $nCharWidth = $font->widthForGlyph($font->glyphNumberForCharacter($nTmp));
            $nCharWidth = $nCharWidth / $nFontUnitsPerEM * $nFontSize;

            if ($nMaxWidth !== null and ($nCharPosition + $nCharWidth + $nDotDotWidth) > $nMaxWidth
                and $nCharIndex < (count($chrArray) - 2)
            ) {
                $this->drawText('..', $nNewXPos + $nCharPosition, $yPos, 'UTF-8');
                break;
            }
            $this->drawText($chrArray[$nCharIndex], $nNewXPos + $nCharPosition, $yPos, 'UTF-8');

            $nCharPosition += $nCharWidth;
        }
        $nNewXPos += $nCharPosition;
        if ($nMaxWidth !== null) {
            $nNewXPos = $xPos + $nMaxWidth;
        }

        $this->setFillColor($oFillColorTmp);

        return $nNewXPos;
    }

    protected function uniord(string $char):int
    {
        // $sUCS2Char = mb_convert_encoding( $sChar, 'UCS-2LE', 'UTF-8');
        $charCode1 = ord(substr($char, 0, 1));
        $charCode2 = ord(substr($char, 1, 1));
        return $charCode2 * 256 + $charCode1;
    }

    protected function getTextWidth(string $sText = null, int $nFontSize = null): float
    {
        $nCharPosition = 0;
        $font = $this->getFont();
        $nFontUnitsPerEM = $font->getUnitsPerEm();
        if ($nFontSize === null) {
            $nFontSize = $this->getFontSize();
        }
        if ($sText === null) {
            $sText = '';
        }
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

    private function getTextStartPosition(float $xPos, string $sText, int $nAlign, float $nWidth): float
    {
        $nMaxWidth = $nWidth;
        $nTextWidth = $this->getTextWidth($sText);

        $xPosText = $xPos;
        {
            if ($nAlign === Align::Left) {
                $xPosText += $this->getPadding();
            } elseif ($nAlign === Align::Center) {
                if ($nTextWidth > $nMaxWidth) {
                    $nTextWidth = $nMaxWidth;
                }

                $xPosText = ($xPos + ($nWidth / 2)) - ($nTextWidth / 2);
            } elseif ($nAlign === Align::Right) {
                if ($nTextWidth > $nMaxWidth) {
                    $nTextWidth = $nMaxWidth;
                }

                $xPosText = (($xPos + $nWidth) - ($this->getPadding() + 1)) - $nTextWidth;
            }
        }
        return $xPosText;
    }

    /**
     * @param string $sText
     * @param float $xPos
     * @param float $yPos
     * @param float $nWidth
     * @param float $nHeight
     * @param int $nAlign
     * @param array<string, Zend_Pdf_Color | string>| string | null $vtLineColor
     * @return float
     * @throws Zend_Pdf_Exception
     */
    public function drawTableHeader(
        string $sText,
        float $xPos,
        float $yPos,
        float $nWidth,
        float $nHeight,
        int $nAlign = Align::Center,
        array|string|null $vtLineColor = 'black'
    ) {
        $arrLines = explode('<br>', $sText);
        $nNrOfLines = count($arrLines);

        $nRetVal = $this->drawCell('', $xPos, $yPos, $nWidth, $nHeight, $nAlign, $vtLineColor);

        $nLineHeight = ($nHeight / $nNrOfLines);
        $yDelta = 0;
        $nNrOfLines = count($arrLines);
        if ($nNrOfLines === 1) {
            $this->drawCell($sText, $xPos, $yPos - $yDelta, $nWidth, $nLineHeight, $nAlign, $vtLineColor);
        } else {
            $oFillColor = $this->getFillColor();
            $arrTopLineColors = [];
            $arrMiddleLineColors = [];
            $arrBottomLineColors = [];
            if (is_string($vtLineColor)) {
                $arrTopLineColors = [
                    'b' => $oFillColor,
                    't' => $vtLineColor,
                    'l' => $vtLineColor,
                    'r' => $vtLineColor
                ];
                $arrMiddleLineColors = [
                    'b' => $oFillColor,
                    't' => $oFillColor,
                    'l' => $vtLineColor,
                    'r' => $vtLineColor
                ];
                $arrBottomLineColors = [
                    'b' => $vtLineColor,
                    't' => $oFillColor,
                    'l' => $vtLineColor,
                    'r' => $vtLineColor
                ];
            } elseif (is_array($vtLineColor)) {
                if (array_key_exists('b', $vtLineColor) === true) {
                    $arrTopLineColors['b'] = $oFillColor;
                    $arrMiddleLineColors['b'] = $oFillColor;
                    $arrBottomLineColors['b'] = $vtLineColor['b'];
                }
                if (array_key_exists('t', $vtLineColor) === true) {
                    $arrTopLineColors['t'] = $vtLineColor['t'];
                    $arrMiddleLineColors['t'] = $oFillColor;
                    $arrBottomLineColors['t'] = $oFillColor;
                }
                if (array_key_exists('l', $vtLineColor) === true) {
                    $arrTopLineColors['l'] = $vtLineColor['l'];
                    $arrMiddleLineColors['l'] = $vtLineColor['l'];
                    $arrBottomLineColors['l'] = $vtLineColor['l'];
                }
                if (array_key_exists('r', $vtLineColor) === true) {
                    $arrTopLineColors['r'] = $vtLineColor['r'];
                    $arrMiddleLineColors['r'] = $vtLineColor['r'];
                    $arrBottomLineColors['r'] = $vtLineColor['r'];
                }
            }

            $bTop = true;
            $nLineNr = 0;
            foreach ($arrLines as $sLine) {
                $arrLineColors = $arrTopLineColors;
                if ($bTop === false) {
                    $arrLineColors = $arrMiddleLineColors;
                } elseif (++$nLineNr === $nNrOfLines) {
                    $arrLineColors = $arrBottomLineColors;
                }

                $this->drawCell($sLine, $xPos, $yPos - $yDelta, $nWidth, $nLineHeight, $nAlign, $arrLineColors);
                $yDelta += $nLineHeight;

                $bTop = false;
            }
        }

        return $nRetVal;
    }
}
