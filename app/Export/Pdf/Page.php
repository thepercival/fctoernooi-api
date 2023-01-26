<?php

declare(strict_types=1);

namespace App\Export\Pdf;

use App\Export\Pdf\Drawers\Helper;
use App\Export\Pdf\Page\Traits\HeaderDrawer;
use App\Export\Pdf\Page\Traits\TitleDrawer;
use Sports\Structure\NameService as StructureNameService;
use Zend_Pdf_Color;
use Zend_Pdf_Color_Html;
use Zend_Pdf_Exception;
use Zend_Pdf_Page;
use Zend_Pdf_Resource_Image;

/**
 * @template T
 */
abstract class Page extends Zend_Pdf_Page
{
    use HeaderDrawer;
    use TitleDrawer;

    public const A4_PORTRET_WIDTH = 595;
    public const A4_PORTRET_HEIGHT = 842;
    public const PAGEMARGIN = 20;
    public const CELL_PADDING_X = 1;
    public const DEFAULT_TEXT_COLOR = 'black';
    protected Helper $helper;

    protected Zend_Pdf_Color $textColor;
    protected Zend_Pdf_Color $fillColor;
    protected Zend_Pdf_Color|null $fillColorTmp = null;
    protected float $lineWidth;
    /**
     * @var T
     */
    protected mixed $parent;

    /**
     * @param mixed $parent
     * @param mixed $param1
     * @param mixed|null $param2
     * @param mixed|null $param3
     * @throws Zend_Pdf_Exception
     */
    public function __construct(mixed $parent, mixed $param1, mixed $param2 = null, mixed $param3 = null)
    {
        parent::__construct($param1, $param2, $param3);
        $this->parent = $parent;
        $this->setFillColor(new Zend_Pdf_Color_Html('white'));
        $this->textColor = new Zend_Pdf_Color_Html('black');
        $this->setLineWidth(1);
        $this->_fontSize = 0.0;
        $this->_safeGS = true;
        $this->_attached = false;
        $this->lineWidth = 1.0;
        $this->helper = new Helper();
    }

    public function getStructureNameService(): StructureNameService
    {
        return $this->parent->getStructureNameService();
    }

    public function getFillColor(): Zend_Pdf_Color
    {
        return $this->fillColor;
    }

    final public function setTextColor(Zend_Pdf_Color $color): void
    {
        $this->textColor = $color;
    }

    final public function resetTextColor(): void
    {
        $this->textColor = new Zend_Pdf_Color_Html(self::DEFAULT_TEXT_COLOR);
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

    public function getDisplayWidth(): float
    {
        return $this->getWidth() - (2 * self::PAGEMARGIN);
    }

    /**
     * @param string $sText
     * @param Rectangle $rectangle
     * @param Align $nAlign
     * @param array<string, Zend_Pdf_Color | string>| string $vtLineColors
     * @param array<int>|null $cornerRadius
     * @throws Zend_Pdf_Exception
     */
    public function drawCell(
        string $sText,
        Rectangle $rectangle,
        Align $nAlign = Align::Left,
        array|string $vtLineColors = null,
        array|null $cornerRadius = null,
    ): void {
        $this->drawCellHelper($sText, $rectangle, $nAlign, $vtLineColors, $cornerRadius);
    }

    /**
     * @param string $sText
     * @param Rectangle $rectangle
     * @param Align $nAlign
     * @param array<string, Zend_Pdf_Color | string>| string | null $vtLineColors
     * @param int|null $degrees
     * @throws Zend_Pdf_Exception
     */
    public function drawAngledCell(
        string $sText,
        Rectangle $rectangle,
        Align $nAlign = Align::Left,
        array|string|null $vtLineColors = null,
        int $degrees = null
    ): void {
        $this->drawCellHelper($sText, $rectangle, $nAlign, $vtLineColors, null, $degrees);
    }

    /**
     * @param string $sText
     * @param Rectangle $rectangle
     * @param Align $nAlign
     * @param array<string, Zend_Pdf_Color | string>| string | null $vtLineColors
     * @param array<int> | null $cornerRadius
     * @param int|null $degrees
     * @throws Zend_Pdf_Exception
     */
    private function drawCellHelper(
        string $sText,
        Rectangle $rectangle,
        Align $nAlign = Align::Left,
        array|string|null $vtLineColors = null,
        array|null $cornerRadius = null,
        int $degrees = null
    ): void {
        $nStyle = Zend_Pdf_Page::SHAPE_DRAW_FILL_AND_STROKE;

        $xPos = $rectangle->getLeft()->getX();
        $yPos = $rectangle->getTop()->getY();
        $arrLineColors = $this->getLineColorsFromInput($vtLineColors);
        if ($arrLineColors !== null) {
            if (is_array($arrLineColors)) {
                $nStyle = Zend_Pdf_Page::SHAPE_DRAW_FILL;
                $this->drawRectangle(
                    $xPos,
                    $yPos - $rectangle->getHeight(),
                    $xPos + $rectangle->getWidth(),
                    $yPos,
                    $nStyle
                );
                if (array_key_exists('b', $arrLineColors) === true) {
                    $this->setLineColor($arrLineColors['b']);
                    $this->drawLine(
                        $xPos + $rectangle->getWidth(),
                        $yPos - $rectangle->getHeight(),
                        $xPos,
                        $yPos - $rectangle->getHeight()
                    ); // leftwards
                }
                if (array_key_exists('t', $arrLineColors) === true) {
                    $this->setLineColor($arrLineColors['t']);
                    $this->drawLine($xPos, $yPos, $xPos + $rectangle->getWidth(), $yPos);    // rightwards
                }

                if (array_key_exists('l', $arrLineColors) === true) {
                    $this->setLineColor($arrLineColors['l']);
                    $this->drawLine($xPos, $yPos - $rectangle->getHeight(), $xPos, $yPos);  // upwards
                }
                if (array_key_exists('r', $arrLineColors) === true) {
                    $this->setLineColor($arrLineColors['r']);
                    $this->drawLine(
                        $xPos + $rectangle->getWidth(),
                        $yPos,
                        $xPos + $rectangle->getWidth(),
                        $yPos - $rectangle->getHeight()
                    ); // downwards
                }
            } else /* string */ {
                $this->setLineColor($arrLineColors);
                if ($cornerRadius !== null) {
                    $this->drawRoundedRectangle(
                        $xPos,
                        $yPos - $rectangle->getHeight(),
                        $xPos + $rectangle->getWidth(),
                        $yPos,
                        $cornerRadius,
                        $nStyle
                    );
                } else {
                    $this->drawRectangle(
                        $xPos,
                        $yPos - $rectangle->getHeight(),
                        $xPos + $rectangle->getWidth(),
                        $yPos,
                        $nStyle
                    );
                }
            }
        }

        $nFontSize = $this->getFontSize();
        $nTextY = (int)($yPos - ((($rectangle->getHeight() / 2) + ($nFontSize / 2)) - 1.5));

        $maxLength = $rectangle->getWidth();
        $stringXPos = $xPos;
        if ($degrees === null) {
            $degrees = 0;
        } elseif ($degrees > 45) {
            $maxLength = $rectangle->getHeight();
            $stringXPos -= ($rectangle->getHeight() - $rectangle->getWidth()) / 2;
        }
        if ($nAlign === Align::Left) {
            $stringXPos += self::CELL_PADDING_X;
        }
        $this->drawString($sText, new Point($stringXPos, $nTextY), $maxLength, $nAlign, $degrees);
    }

    public function drawImageExt(Zend_Pdf_Resource_Image $image, Rectangle $rectangle): void
    {
        $bottomLeft = $rectangle->getLeft()->getStart();
        $upperRight = $rectangle->getRight()->getEnd();
        $this->drawImage(
            $image,
            $bottomLeft->getX(),
            $bottomLeft->getY(),
            $upperRight->getX(),
            $upperRight->getY()
        );
    }

    public function drawRectangleExt(
        Rectangle $rectangle/*x1, $y1, $x2, $y2*/,
        int $fillType = Zend_Pdf_Page::SHAPE_DRAW_FILL_AND_STROKE
    ): void {
    }

    /**
     * @param array<string, Zend_Pdf_Color | string>| Zend_Pdf_Color | string | null $vtLineColors
     * @return array<string, Zend_Pdf_Color>| Zend_Pdf_Color | null
     * @throws Zend_Pdf_Exception
     */
    protected function getLineColorsFromInput(array|Zend_Pdf_Color|string|null $vtLineColors): array|Zend_Pdf_Color|null
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
            return new Zend_Pdf_Color_Html($vtLineColors);
        }
        return $vtLineColors;
    }

    public function drawString(
        string|null $sText,
        Point $start,
        float $nMaxWidth = null,
        Align $nAlign = Align::Left,
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

            $xMiddle = $start->getX();
            if ($nMaxWidth !== null) {
                $xMiddle += ($nMaxWidth / 2);
            }
            $yMiddle = $start->getY() + ($nFontSize / 2);
            $this->rotate($xMiddle, $yMiddle, $nRotationAngle);
            // $yTextBase -= $nFontSize - $nPadding; //Y richting is nu horizontaal (bij hoek 90 graden)
            //$xTextBase -= round( ($nHeight )/2 ) - 2*$nPadding	 ; //MOET NOG ANDERS!!  //round( ($nWidth - $nTextWidth)/2 ) centreert hem nu verticaal
            $nRetVal = $this->drawString($sText, $start, $nMaxWidth, $nAlign);

            $this->rotate($xMiddle, $yMiddle, -$nRotationAngle);

            return $nRetVal;
        }

        $oFillColorTmp = $this->getFillColor();
        $this->setFillColor($this->textColor);

        $widthForStartPosition = 0;
        if ($nMaxWidth !== null) {
            $widthForStartPosition = $nMaxWidth;
        }
        $nNewXPos = $this->getTextStartPosition($start->getX(), $sText, $nAlign, $widthForStartPosition, $nFontSize);

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
            $nTmp = $this->helper->uniord($chrArray[$nCharIndex]);

            $nCharWidth = $font->widthForGlyph($font->glyphNumberForCharacter($nTmp));
            $nCharWidth = $nCharWidth / $nFontUnitsPerEM * $nFontSize;

            if ($nMaxWidth !== null and ($nCharPosition + $nCharWidth + $nDotDotWidth) > $nMaxWidth
                and $nCharIndex < (count($chrArray) - 2)
            ) {
                $this->drawText('..', $nNewXPos + $nCharPosition, $start->getY(), 'UTF-8');
                break;
            }
            $this->drawText($chrArray[$nCharIndex], $nNewXPos + $nCharPosition, $start->getY(), 'UTF-8');

            $nCharPosition += $nCharWidth;
        }
        $nNewXPos += $nCharPosition;
        if ($nMaxWidth !== null) {
            $nNewXPos = $start->getX() + $nMaxWidth;
        }

        $this->setFillColor($oFillColorTmp);

        return $nNewXPos;
    }

    protected function getTextWidth(string $sText = null, float $fontSize, bool $withCellPaddingX = true): float
    {
        $cellPaddingX = $withCellPaddingX ? self::CELL_PADDING_X : 0;
        return $cellPaddingX + $this->helper->getTextWidth($sText ?? '', $this->getFont(), $fontSize) + $cellPaddingX;
    }

    private function getTextStartPosition(
        float $xPos,
        string $text,
        Align $nAlign,
        float $width,
        float $fontSize
    ): float {
        $maxWidth = $width;
        $textWidth = $this->getTextWidth($text, $fontSize);

        $xPosText = $xPos;
        {
           if ($nAlign === Align::Center) {
               if ($textWidth > $maxWidth) {
                   $textWidth = $maxWidth;
               }

               $xPosText = ($xPos + ($width / 2)) - ($textWidth / 2);
           } elseif ($nAlign === Align::Right) {
               if ($textWidth > $maxWidth) {
                   $textWidth = $maxWidth;
               }

               $xPosText = (($xPos + $width) - 1) - $textWidth;
           }
        }
        return $xPosText;
    }

    /**
     * @param string $text
     * @param Rectangle $rectangle
     * @param Align $align
     * @param array<string, Zend_Pdf_Color | string>| string | null $vtLineColor
     * @throws Zend_Pdf_Exception
     */
    public function drawTableHeader(
        string $text,
        Rectangle $rectangle,
        Align $align = Align::Center,
        array|string|null $vtLineColor = 'black'
    ): void {
        $arrLines = explode('<br>', $text);
        $nNrOfLines = count($arrLines);

        $this->drawCell('', $rectangle, $align, $vtLineColor);

        $nLineHeight = ($rectangle->getHeight() / $nNrOfLines);
        $yDelta = 0;
        $nNrOfLines = count($arrLines);
        if ($nNrOfLines === 1) {
            $rectangle = new Rectangle(
                $rectangle->getTop(),
                $rectangle->getHeight() - ($nLineHeight + $yDelta)
            );
            $this->drawCell($text, $rectangle, $align, $vtLineColor);
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
//                $rectangle = new Rectangle(
//                    $rectangle->getStart()->addY(- $yDelta),
//                    new Point($rectangle->getWidth(), $nLineHeight)
//                );
                $this->drawCell($sLine, $rectangle, $align, $arrLineColors);
                $yDelta += $nLineHeight;

                $bTop = false;
            }
        }
    }
}
