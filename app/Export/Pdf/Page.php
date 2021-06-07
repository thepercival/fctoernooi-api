<?php
declare(strict_types=1);

namespace App\Export\Pdf;

use Zend_Pdf_Color;

abstract class Page extends \Zend_Pdf_Page
{
    protected Zend_Pdf_Color $textColor;
    protected Zend_Pdf_Color $fillColor;
    protected $fillColorTmp;
    protected float $lineWidth;
    protected float $padding;
    protected array $images;

    const ALIGNLEFT = 1;
    const ALIGNCENTER = 2;
    const ALIGNRIGHT = 3;

    public function __construct(protected Document $parent, mixed $param1, mixed $param2 = null, mixed $param3 = null)
    {
        parent::__construct($param1, $param2, $param3);
        $this->setFillColor(new \Zend_Pdf_Color_Html("white"));
        $this->textColor = new \Zend_Pdf_Color_Html("black");
        $this->setLineWidth(1);
        $this->padding = 1.0;
        $this->images = array();
        $this->_fontSize = 0.0;
        $this->_safeGS = true;
        $this->_attached = true;
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

    final public function setFillColor(\Zend_Pdf_Color $color): self
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
     * @param float $lineWidth
     * @return $this|\Zend_Pdf_Canvas_Interface
     */
    public function setLineWidth($lineWidth)
    {
        parent::setLineWidth($lineWidth);
        $this->lineWidth = $lineWidth;
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

    public function drawHeader(string $subTitle = null, float $nY = null)
    {
        if ($nY === null) {
            $nY = $this->getHeight() - $this->getPageMargin();
        }
        $this->setFont($this->getParent()->getFont(), $this->getParent()->getFontHeight());
        $title = "FCToernooi";

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

        $img = \Zend_Pdf_Resource_ImageFactory::factory(__DIR__ . '/../logo.jpg');
        $this->drawImage($img, $xLeft, $nY - $imgSize, $xLeft + $imgSize, $nY);

        $arrLineColors = array("b" => "black");
        $this->drawCell(
            "FCToernooi",
            $xLeft + $imgSize,
            $nY,
            $widthLeft - $imgSize,
            $nRowHeight,
            Page::ALIGNLEFT,
            $arrLineColors
        );


        $name = $this->getParent()->getTournament()->getCompetition()->getLeague()->getName();
        $this->drawCell($name, $xCenter, $nY, $widthCenter, $nRowHeight, Page::ALIGNLEFT, $arrLineColors);

        if (strlen($subTitle) > 0) {
            $this->drawCell($subTitle, $xRight, $nY, $widthRight, $nRowHeight, Page::ALIGNRIGHT, $arrLineColors);
        }

        return $nY - (2 * $nRowHeight);
    }

    public function drawSubHeader($subHeader, $nY)
    {
        $fontHeightSubHeader = $this->getParent()->getFontHeightSubHeader();
        $this->setFont($this->getParent()->getFont(true), $this->getParent()->getFontHeightSubHeader());
        $nX = $this->getPageMargin();
        $displayWidth = $this->getDisplayWidth();
        $this->drawCell($subHeader, $nX, $nY, $displayWidth, $fontHeightSubHeader, Page::ALIGNCENTER);
        return $nY - (2 * $fontHeightSubHeader);
    }

    protected function getDisplayWidth()
    {
        return $this->getWidth() - (2 * $this->getPageMargin());
    }

    /**
     * @param string $sText
     * @param float $nXPos
     * @param float $nYPos
     * @param float $nWidth
     * @param float $nHeight
     * @param int $nAlign
     * @param array<string, Zend_Pdf_Color | string>| string | null $vtLineColors
     * @param int|null $degrees
     * @return float
     * @throws \Zend_Pdf_Exception
     */
    public function drawCell(
        string $sText,
        float $nXPos,
        float $nYPos,
        float $nWidth,
        float $nHeight,
        int $nAlign = Page::ALIGNLEFT,
        array|string|null $vtLineColors = null,
        int $degrees = null
    ) {
        $nStyle = \Zend_Pdf_Page::SHAPE_DRAW_FILL_AND_STROKE;

        $arrLineColors = $this->getLineColorsFromInput($vtLineColors);

        if ($arrLineColors !== null and is_array($arrLineColors)) {
            $nLineWidth = $this->getLineWidth();
            $this->setLineColor($this->getFillColor());

            $this->drawRectangle(
                $nXPos + $nLineWidth,
                $nYPos - ($nHeight - $nLineWidth),
                $nXPos + ($nWidth - $nLineWidth),
                $nYPos - $nLineWidth,
                $nStyle
            );

            if (array_key_exists("b", $arrLineColors) === true) {
                $this->setLineColor($arrLineColors["b"]);
                $this->drawLine($nXPos + $nWidth, $nYPos - $nHeight, $nXPos, $nYPos - $nHeight); // leftwards
            }
            if (array_key_exists("t", $arrLineColors) === true) {
                $this->setLineColor($arrLineColors["t"]);
                $this->drawLine($nXPos, $nYPos, $nXPos + $nWidth, $nYPos);    // rightwards
            }

            if (array_key_exists("l", $arrLineColors) === true) {
                $this->setLineColor($arrLineColors["l"]);
                $this->drawLine($nXPos, $nYPos - $nHeight, $nXPos, $nYPos);  // upwards
            }
            if (array_key_exists("r", $arrLineColors) === true) {
                $this->setLineColor($arrLineColors["r"]);
                $this->drawLine($nXPos + $nWidth, $nYPos, $nXPos + $nWidth, $nYPos - $nHeight); // downwards
            }
        } else {
            $this->setLineColor($this->getFillColor());
            $this->drawRectangle($nXPos, $nYPos - $nHeight, $nXPos + $nWidth, $nYPos, $nStyle);
        }

        $nFontSize = $this->getFontSize();
        $nTextY = (int)($nYPos - ((($nHeight / 2) + ($nFontSize / 2)) - 1.5));

        $maxLength = $nWidth;
        $stringXPos = $nXPos;
        if ($degrees > 45) {
            $maxLength = $nHeight;
            $stringXPos -= ($nHeight - $nWidth) / 2;
        }
        $nRetVal = $this->drawString($sText, $stringXPos, $nTextY, $maxLength, $nAlign, $degrees);

        return $nXPos + $nWidth;
    }

    /**
     * @param array<string, Zend_Pdf_Color | string>| string | null $vtLineColors
     * @return array<string, Zend_Pdf_Color>| null
     * @throws \Zend_Pdf_Exception
     */
    protected function getLineColorsFromInput(array | null $vtLineColors): array | null
    {
        if ($vtLineColors !== null) {
            if (is_array($vtLineColors)) {
                foreach ($vtLineColors as $sIndex => $vtLineColor) {
                    if (is_string($vtLineColor)) {
                        $vtLineColor = new \Zend_Pdf_Color_Html($vtLineColor);
                    }
                    $vtLineColors[$sIndex] = $vtLineColor;
                }
            } else {
                $oColor = null;
                if (is_string($vtLineColors)) {
                    $oColor = new \Zend_Pdf_Color_Html($vtLineColors);
                } elseif ($vtLineColors instanceof \Zend_Pdf_Color) {
                    $oColor = $vtLineColors;
                }
                $vtLineColors = array("l" => $oColor, "t" => $oColor, "r" => $oColor, "b" => $oColor);
            }
        }
        return $vtLineColors;
    }


    /**
     * @param string|null $sText
     * @param int $nXPos
     * @param int $nYPos
     * @param int|null $nMaxWidth
     * @param int $nAlign
     * @param int $nRotationDegree
     * @return float|int|null
     * @throws \Zend_Pdf_Exception
     */
    public function drawString(
        $sText,
        $nXPos,
        $nYPos,
        $nMaxWidth = null,
        $nAlign = Page::ALIGNLEFT,
        $nRotationDegree = 0
    ) {
        $font = $this->getFont();
        $nFontUnitsPerEM = $font->getUnitsPerEm();
        $nFontSize = $this->getFontSize();

        if ($nRotationDegree > 0) {
            $nRotationAngle = M_PI / 6; //standaard M_PI/6 = 30 graden
            if ($nRotationDegree == 45) {
                $nRotationAngle = M_PI / 4;
            }
            if ($nRotationDegree == 90) {
                $nRotationAngle = M_PI / 2;
            }

            $nXMiddle = $nXPos;
            if ($nMaxWidth !== null) {
                $nXMiddle += ($nMaxWidth / 2);
            }
            $nYMiddle = $nYPos + ($nFontSize / 2);
            $this->rotate($nXMiddle, $nYMiddle, $nRotationAngle);
            // $nYTextBase -= $nFontSize - $nPadding; //Y richting is nu horizontaal (bij hoek 90 graden)
            //$nXTextBase -= round( ($nHeight )/2 ) - 2*$nPadding	 ; //MOET NOG ANDERS!!  //round( ($nWidth - $nTextWidth)/2 ) centreert hem nu verticaal
            $nRetVal = $this->drawString($sText, $nXPos, $nYPos, $nMaxWidth, $nAlign);

            $this->rotate($nXMiddle, $nYMiddle, -$nRotationAngle);

            return $nRetVal;
        }

        $oFillColorTmp = $this->getFillColor();
        $this->setFillColor($this->m_oTextColor);

        $nNewXPos = $this->getTextStartPosition($nXPos, $sText, $nAlign, $nMaxWidth);

        $nDotDotWidth = $font->widthForGlyph($font->glyphNumberForCharacter(ord(".")));
        $nDotDotWidth = $nDotDotWidth / $nFontUnitsPerEM * $nFontSize;
        $nDotDotWidth *= 2;

        $nCharPosition = 0;
        // $unicodeString = 'aÄ…bcÄ�deÄ™Ã«Å‚';
        $chrArray = preg_split('//u', $sText, -1, PREG_SPLIT_NO_EMPTY);
        for ($nCharIndex = 0; $nCharIndex < count($chrArray); $nCharIndex++) {
            $nTmp = $this->uniord($chrArray[$nCharIndex]);

            $nCharWidth = $font->widthForGlyph($font->glyphNumberForCharacter($nTmp));
            $nCharWidth = $nCharWidth / $nFontUnitsPerEM * $nFontSize;

            if ($nMaxWidth !== null and ($nCharPosition + $nCharWidth + $nDotDotWidth) > $nMaxWidth and $nCharIndex < (count(
                $chrArray
            ) - 2)) {
                $this->drawText("..", $nNewXPos + $nCharPosition, $nYPos, 'UTF-8');
                break;
            }
            $this->drawText($chrArray[$nCharIndex], $nNewXPos + $nCharPosition, $nYPos, 'UTF-8');

            $nCharPosition += $nCharWidth;
        }
        $nNewXPos += $nCharPosition;
        if ($nMaxWidth !== null) {
            $nNewXPos = $nXPos + $nMaxWidth;
        }

        $this->setFillColor($oFillColorTmp);

        return $nNewXPos;
    }

    protected function uniord($sChar)
    {
        // $sUCS2Char = mb_convert_encoding( $sChar, 'UCS-2LE', 'UTF-8');
        $nCharCode1 = ord(substr($sChar, 0, 1));
        $nCharCode2 = ord(substr($sChar, 1, 1));
        return $nCharCode2 * 256 + $nCharCode1;
    }

    protected function getTextWidth(string $sText = null, int $nFontSize = null)
    {
        $nCharPosition = 0;
        $font = $this->getFont();
        $nFontUnitsPerEM = $font->getUnitsPerEm();
        if ($nFontSize === null) {
            $nFontSize = $this->getFontSize();
        }

        // $unicodeString = 'aÄ…bcÄ�deÄ™Ã«Å‚';
        $chrArray = preg_split('//u', $sText, -1, PREG_SPLIT_NO_EMPTY);
        for ($nCharIndex = 0; $nCharIndex < count($chrArray); $nCharIndex++) {
            $nTmp = $this->uniord($chrArray[$nCharIndex]);

            $nCharWidth = $font->widthForGlyph($font->glyphNumberForCharacter($nTmp));
            $nCharWidth = $nCharWidth / $nFontUnitsPerEM * $nFontSize;

            $nCharPosition += $nCharWidth;
        }
        return $nCharPosition;
    }

    private function getTextStartPosition($nXPos, $sText, $nAlign, $nWidth)
    {
        $nMaxWidth = $nWidth;
        $nTextWidth = $this->getTextWidth($sText);

        $nXPosText = $nXPos;
        {
            if ($nAlign === Page::ALIGNLEFT) {
                $nXPosText += $this->getPadding();
            } elseif ($nAlign === Page::ALIGNCENTER) {
                if ($nTextWidth > $nMaxWidth) {
                    $nTextWidth = $nMaxWidth;
                }

                $nXPosText = ($nXPos + ($nWidth / 2)) - ($nTextWidth / 2);
            } elseif ($nAlign === Page::ALIGNRIGHT) {
                if ($nTextWidth > $nMaxWidth) {
                    $nTextWidth = $nMaxWidth;
                }

                $nXPosText = (($nXPos + $nWidth) - ($this->getPadding() + 1)) - $nTextWidth;
            }
        }
        return $nXPosText;
    }

    public function drawTableHeader(
        $sText,
        $nXPos,
        $nYPos,
        $nWidth,
        $nHeight,
        $nAlign = Page::ALIGNCENTER,
        $vtLineColor = "black"
    ) {
        $arrLines = explode("<br>", $sText);
        $nNrOfLines = count($arrLines);

        $nRetVal = $this->drawCell("", $nXPos, $nYPos, $nWidth, $nHeight, $nAlign, $vtLineColor);

        $nLineHeight = ($nHeight / $nNrOfLines);
        $nYDelta = 0;
        $nNrOfLines = count($arrLines);
        if ($nNrOfLines === 1) {
            $this->drawCell($sText, $nXPos, $nYPos - $nYDelta, $nWidth, $nLineHeight, $nAlign, $vtLineColor);
        } else {
            $oFillColor = $this->getFillColor();
            $arrTopLineColors = array();
            $arrMiddleLineColors = array();
            $arrBottomLineColors = array();
            if (is_string($vtLineColor)) {
                $arrTopLineColors = array(
                    "b" => $oFillColor,
                    "t" => $vtLineColor,
                    "l" => $vtLineColor,
                    "r" => $vtLineColor
                );
                $arrMiddleLineColors = array(
                    "b" => $oFillColor,
                    "t" => $oFillColor,
                    "l" => $vtLineColor,
                    "r" => $vtLineColor
                );
                $arrBottomLineColors = array(
                    "b" => $vtLineColor,
                    "t" => $oFillColor,
                    "l" => $vtLineColor,
                    "r" => $vtLineColor
                );
            } else {
                if (array_key_exists("b", $vtLineColor) === true) {
                    $arrTopLineColors["b"] = $oFillColor;
                    $arrMiddleLineColors["b"] = $oFillColor;
                    $arrBottomLineColors["b"] = $vtLineColor["b"];
                }
                if (array_key_exists("t", $vtLineColor) === true) {
                    $arrTopLineColors["t"] = $vtLineColor["t"];
                    $arrMiddleLineColors["t"] = $oFillColor;
                    $arrBottomLineColors["t"] = $oFillColor;
                }
                if (array_key_exists("l", $vtLineColor) === true) {
                    $arrTopLineColors["l"] = $vtLineColor["l"];
                    $arrMiddleLineColors["l"] = $vtLineColor["l"];
                    $arrBottomLineColors["l"] = $vtLineColor["l"];
                }
                if (array_key_exists("r", $vtLineColor) === true) {
                    $arrTopLineColors["r"] = $vtLineColor["r"];
                    $arrMiddleLineColors["r"] = $vtLineColor["r"];
                    $arrBottomLineColors["r"] = $vtLineColor["r"];
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

                $this->drawCell($sLine, $nXPos, $nYPos - $nYDelta, $nWidth, $nLineHeight, $nAlign, $arrLineColors);
                $nYDelta += $nLineHeight;

                $bTop = false;
            }
        }

        return $nRetVal;
    }
}
