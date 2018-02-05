<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-18
 * Time: 14:59
 */

namespace FCToernooi\Pdf;

/**
 * @package ZendExt
 */
abstract class Page extends \Zend_Pdf_Page
{
    protected $m_oParent;
    protected $m_oTextColor;
    protected $m_oFillColor;
    protected $m_oFillColorTmp;
    protected $m_nLineWidth;
    protected $m_nPadding;
    protected $m_arrImages;

    CONST ALIGNLEFT = 1;
    CONST ALIGNCENTER = 2;
    CONST ALIGNRIGHT = 3;

    public function __construct( $param1, $param2 = null, $param3 = null )
    {
        parent::__construct( $param1, $param2, $param3 );
        $this->setFillColor( new \Zend_Pdf_Color_Html( "white" ) );
        $this->m_oTextColor = new \Zend_Pdf_Color_Html( "black" );
        $this->setLineWidth( 1 );
        $this->m_nPadding = 1;
        $this->m_arrImages = array();
    }

    public abstract function getHeaderHeight();
    public abstract function getPageMargin();

    public function getParent()
    {
        return $this->m_oParent;
    }

    public function putParent( $oParent )
    {
        $this->m_oParent = $oParent;
    }

    public function getFillColor()
    {
        return $this->m_oFillColor;
    }

    public function setFillColor( \Zend_Pdf_Color $color )
    {
        parent::setFillColor( $color );
        $this->m_oFillColor = $color;
    }

    public function getLineWidth()
    {
        return $this->m_nLineWidth;
    }

    public function setLineWidth( $nLineWidth )
    {
        parent::setLineWidth( $nLineWidth );
        $this->m_nLineWidth = $nLineWidth;
    }

    public function getPadding()
    {
        return $this->m_nPadding;
    }

    public function setPadding( $nPadding )
    {
        $this->m_nPadding = $nPadding;
    }

    public function drawCell( $sText, $nXPos, $nYPos, $nWidth, $nHeight, $nAlign = Page::ALIGNLEFT, $vtLineColors = null )
    {
        $nStyle = \Zend_Pdf_Page::SHAPE_DRAW_FILL_AND_STROKE;

        $arrLineColors = $this->getLineColorsFromInput( $vtLineColors );

        if ( $arrLineColors !== null and is_array( $arrLineColors ) )
        {
            $nLineWidth = $this->getLineWidth();
            $this->setLineColor ( $this->getFillColor() );

            $this->drawRectangle( $nXPos + $nLineWidth, $nYPos - ( $nHeight - $nLineWidth ), $nXPos + ( $nWidth - $nLineWidth ), $nYPos - $nLineWidth, $nStyle );

            if ( array_key_exists( "b", $arrLineColors ) === true )
            {
                $this->setLineColor ( $arrLineColors["b"] );
                $this->drawLine( $nXPos + $nWidth, $nYPos - $nHeight, $nXPos, $nYPos - $nHeight, $nStyle ); // leftwards
            }
            if ( array_key_exists( "t", $arrLineColors ) === true )
            {
                $this->setLineColor ( $arrLineColors["t"] );
                $this->drawLine( $nXPos, $nYPos, $nXPos + $nWidth, $nYPos, $nStyle );	// rightwards
            }

            if ( array_key_exists( "l", $arrLineColors ) === true )
            {
                $this->setLineColor ( $arrLineColors["l"] );
                $this->drawLine( $nXPos, $nYPos - $nHeight, $nXPos, $nYPos, $nStyle );  // upwards
            }
            if ( array_key_exists( "r", $arrLineColors ) === true )
            {
                $this->setLineColor ( $arrLineColors["r"] );
                $this->drawLine( $nXPos + $nWidth, $nYPos, $nXPos + $nWidth, $nYPos - $nHeight, $nStyle ); // downwards
            }
        }
        else
        {
            $this->setLineColor ( $this->getFillColor() );
            $this->drawRectangle( $nXPos, $nYPos - $nHeight, $nXPos + $nWidth, $nYPos, $nStyle );
        }

        $nFontSize = $this->getFontSize();
        $nTextHeight = $nYPos - ( ( ( $nHeight / 2 ) + ( $nFontSize / 2 ) ) - 1 );

        $nRetVal = $this->drawString( $sText, $nXPos, $nTextHeight, $nWidth, $nAlign );

        return $nXPos + $nWidth;
    }

    protected function getLineColorsFromInput( $vtLineColors )
    {
        if ( $vtLineColors !== null  )
        {
            if ( is_array( $vtLineColors ) )
            {
                foreach( $vtLineColors as $sIndex => $vtLineColor )
                {
                    if ( is_string( $vtLineColor ) )
                        $vtLineColor = new \Zend_Pdf_Color_Html( $vtLineColor );
                    $vtLineColors[ $sIndex ] = $vtLineColor;
                }
            }
            else
            {
                if ( is_string( $vtLineColors ) )
                {
                    $oColor = new \Zend_Pdf_Color_Html( $vtLineColors );
                }
                else if ( $vtLineColors instanceof \Zend_Pdf_Color )
                {
                    $oColor = $vtLineColors;
                }
                $vtLineColors = array( "l" => $oColor, "t" => $oColor, "r" => $oColor, "b" => $oColor );
            }
        }
        return $vtLineColors;
    }



    /**	 *
     * Enter description here ...
     *
     * @param unknown_type $sText
     * @param unknown_type $nXPos
     * @param unknown_type $nYPos
     * @param unknown_type $nMaxWidth
     */
    public function drawString( $sText, $nXPos, $nYPos, $nMaxWidth = null, $nAlign = Page::ALIGNLEFT, $nRotationDegree = 0 )
    {
        $font = $this->getFont();
        $nFontUnitsPerEM = $font->getUnitsPerEm();
        $nFontSize = $this->getFontSize();

        if ( $nRotationDegree > 0 )
        {
            $nRotationAngle = M_PI / 6; //standaard M_PI/6 = 30 graden
            if( $nRotationDegree == 45 ) $nRotationAngle = M_PI / 4;
            if( $nRotationDegree == 90 ) $nRotationAngle = M_PI / 2;

            $this->rotate( $nXPos, $nYPos, $nRotationAngle );
            // $nYTextBase -= $nFontSize - $nPadding; //Y richting is nu horizontaal (bij hoek 90 graden)
            //$nXTextBase -= round( ($nHeight )/2 ) - 2*$nPadding	 ; //MOET NOG ANDERS!!  //round( ($nWidth - $nTextWidth)/2 ) centreert hem nu verticaal

            $nRetVal = $this->drawString( $sText, $nXPos, $nYPos, $nMaxWidth, $nAlign );

            $this->rotate( $nXPos, $nYPos, -$nRotationAngle );

            return $nRetVal;
        }

        $oFillColorTmp = $this->getFillColor();
        $this->setFillColor( $this->m_oTextColor );

        $nNewXPos = $this->getTextStartPosition( $nXPos, $sText, $nAlign, $nMaxWidth );

        $nDotDotWidth = $font->widthForGlyph( $font->glyphNumberForCharacter( ord ( "." ) ) );
        $nDotDotWidth = $nDotDotWidth / $nFontUnitsPerEM * $nFontSize;
        $nDotDotWidth *= 2;

        $nCharPosition = 0;
        // $unicodeString = 'aÄ…bcÄ�deÄ™Ã«Å‚';
        $chrArray = preg_split('//u',$sText, -1, PREG_SPLIT_NO_EMPTY);
        for ( $nCharIndex = 0 ; $nCharIndex < count( $chrArray ) ; $nCharIndex++ )
        {
            $nTmp = $this->uniord ( $chrArray[$nCharIndex] );

            $nCharWidth = $font->widthForGlyph( $font->glyphNumberForCharacter( $nTmp ) );
            $nCharWidth = $nCharWidth / $nFontUnitsPerEM * $nFontSize;

            if ( $nMaxWidth !== null and ( $nCharPosition + $nCharWidth + $nDotDotWidth ) > $nMaxWidth and $nCharIndex < ( count( $chrArray ) - 2 ) )
            {
                $this->drawText( "..", $nNewXPos + $nCharPosition, $nYPos, 'UTF-8' );
                break;
            }
            $this->drawText( $chrArray[$nCharIndex], $nNewXPos + $nCharPosition, $nYPos, 'UTF-8' );

            $nCharPosition += $nCharWidth;
        }
        $nNewXPos += $nCharPosition;
        if ( $nMaxWidth !== null )
            $nNewXPos = $nXPos + $nMaxWidth;

        $this->setFillColor( $oFillColorTmp );

        return $nNewXPos;
    }

    protected function uniord( $sChar )
    {
        $sUCS2Char = mb_convert_encoding( $sChar, 'UCS-2LE', 'UTF-8');
        $nCharCode1 = ord( substr( $sUCS2Char, 0, 1) );
        $nCharCode2 = ord( substr( $sUCS2Char, 1, 1) );
        return $nCharCode2 * 256 + $nCharCode1;
    }

    protected function getTextWidth( $sText )
    {
        $nCharPosition = 0;
        $font = $this->getFont();
        $nFontUnitsPerEM = $font->getUnitsPerEm();
        $nFontSize = $this->getFontSize();

        // $unicodeString = 'aÄ…bcÄ�deÄ™Ã«Å‚';
        $chrArray = preg_split('//u',$sText, -1, PREG_SPLIT_NO_EMPTY);
        for ( $nCharIndex = 0 ; $nCharIndex < count( $chrArray ) ; $nCharIndex++ )
        {
            $nTmp = $this->uniord ( $chrArray[$nCharIndex] );

            $nCharWidth = $font->widthForGlyph( $font->glyphNumberForCharacter( $nTmp ) );
            $nCharWidth = $nCharWidth / $nFontUnitsPerEM * $nFontSize;

            $nCharPosition += $nCharWidth;
        }
        return $nCharPosition;
    }

    private function getTextStartPosition( $nXPos, $sText, $nAlign, $nWidth )
    {
        $nMaxWidth = $nWidth;
        $nTextWidth = $this->getTextWidth( $sText );

        $nXPosText = $nXPos;
        {
            if( $nAlign === Page::ALIGNLEFT )
            {
                $nXPosText += $this->getPadding();
            }
            else if( $nAlign === Page::ALIGNCENTER )
            {
                if ( $nTextWidth > $nMaxWidth)
                    $nTextWidth = $nMaxWidth;

                $nXPosText = ( $nXPos + ( $nWidth / 2 ) ) - ( $nTextWidth / 2 );
            }
            else if ( $nAlign === Page::ALIGNRIGHT )
            {
                if ( $nTextWidth > $nMaxWidth)
                    $nTextWidth = $nMaxWidth;

                $nXPosText = ( ( $nXPos + $nWidth ) - ( $this->getPadding() + 1 ) ) - $nTextWidth;
            }
        }
        return $nXPosText;
    }

    public function drawTableHeader( $sText, $nXPos, $nYPos, $nWidth, $nHeight, $nAlign = Page::ALIGNCENTER, $vtLineColor = "black" )
    {
        $arrLines = explode( "<br>", $sText );
        $nNrOfLines = count( $arrLines );

        $nRetVal = $this->drawCell( "", $nXPos, $nYPos, $nWidth, $nHeight, $nAlign, $vtLineColor );

        $nLineHeight = ( $nHeight / $nNrOfLines );
        $nYDelta = 0;
        $nNrOfLines = count( $arrLines );
        if ( $nNrOfLines === 1 )
            $this->drawCell( $sText, $nXPos, $nYPos - $nYDelta, $nWidth, $nLineHeight, $nAlign, $vtLineColor );
        else
        {
            $oFillColor = $this->getFillColor();
            $arrTopLineColors = array(); $arrMiddleLineColors = array(); $arrBottomLineColors = array();
            if ( is_string( $vtLineColor ) )
            {
                $arrTopLineColors = array( "b" => $oFillColor, "t" => $sLineColor, "l" => $sLineColor, "r" => $sLineColor );
                $arrMiddleLineColors = array( "b" => $oFillColor, "t" => $oFillColor, "l" => $sLineColor, "r" => $sLineColor );
                $arrBottomLineColors = array( "b" => $sLineColor, "t" => $oFillColor, "l" => $sLineColor, "r" => $sLineColor );
            }
            else
            {
                if ( array_key_exists( "b", $vtLineColor ) === true )
                {
                    $arrTopLineColors["b"] = $oFillColor;
                    $arrMiddleLineColors["b"] = $oFillColor;
                    $arrBottomLineColors["b"] = $vtLineColor["b"];
                }
                if ( array_key_exists( "t", $vtLineColor ) === true )
                {
                    $arrTopLineColors["t"] = $vtLineColor["t"];
                    $arrMiddleLineColors["t"] = $oFillColor;
                    $arrBottomLineColors["t"] = $oFillColor;
                }
                if ( array_key_exists( "l", $vtLineColor ) === true )
                {
                    $arrTopLineColors["l"] = $vtLineColor["l"];
                    $arrMiddleLineColors["l"] = $vtLineColor["l"];
                    $arrBottomLineColors["l"] = $vtLineColor["l"];
                }
                if ( array_key_exists( "r", $vtLineColor ) === true )
                {
                    $arrTopLineColors["r"] = $vtLineColor["r"];
                    $arrMiddleLineColors["r"] = $vtLineColor["r"];
                    $arrBottomLineColors["r"] = $vtLineColor["r"];
                }
            }

            $bTop = true; $nLineNr = 0;
            foreach( $arrLines as $sLine )
            {
                $arrLineColors = $arrTopLineColors;
                if ( $bTop === false )
                    $arrLineColors = $arrMiddleLineColors;
                else if ( ++$nLineNr === $nNrOfLines )
                    $arrLineColors = $arrBottomLineColors;

                $this->drawCell( $sLine, $nXPos, $nYPos - $nYDelta, $nWidth, $nLineHeight, $nAlign, $arrLineColors );
                $nYDelta += $nLineHeight;

                $bTop = false;
            }
        }

        return $nRetVal;
    }
}