<?php

namespace MoloniPrint\Utils;

use MoloniPrint\Settings\Printer;

class Builder
{

    /**
     * Text alignment
     * @var array
     */
    public $align = [
        'LEFT' => 0,
        'CENTER' => 1,
        'RIGHT' => 2,
    ];

    /**
     * Text font style
     * @var array
     */
    public $font = [
        'A' => ['emphasized' => false, 'underlined' => false],
        'B' => [],
        'C' => []
    ];

    /**
     * Pulses
     * @var array
     */
    public $pulse = [
        '100' => 50,
        '200' => 100,
        '300' => 150,
        '400' => 200,
        '500' => 250,
    ];

    /**
     * Cut types
     * @var array
     */
    public $cuts = [
        'CUT_NO_FEED' => 49,
        'CUT_FEED' => 66
    ];

    /**
     * Store last used styles
     */
    public $lastStyle = [];
    public $lastAlignment = 0;
    public $lastCondensed = null;
    public $lastDouble = [];

    /**
     * Array with the current print job
     */
    protected $printJob = [];

    /**
     * Add the printer settings based on a printer settings
     * @param Printer $printer
     */
    public function addSettings(Printer $printer)
    {
        $this->printJob[] = [
            'op' => 'settings',
            'data' => [
                'normalLineWidth' => $printer->normalWidth,
                'condensed' => $printer->normalWidth,
                'condensedLineWidth' => $printer->condensedWidth,
                'dotWidth' => $printer->dotWidth,
                'hasCutter' => $printer->hasCutter,
                'hasDrawer' => $printer->hasDrawer,
                'lowDensity' => $printer->lowDensity,
                'imagePrintMode' => $printer->imagePrintMode,
                'alternativeCharset' => $printer->alternativeCharset,
                'replaceAccentedChars' => $printer->replaceAccentedChars,
            ]
        ];
    }

    public function image($url, $maxWidth = 576)
    {
        try {
            $image = new \Imagick($url);
            $image->setImageFormat("png");

            $imageWidth = $maxWidth;
            $imageHeight = ceil($maxWidth * $image->getImageHeight() / $image->getImageWidth());

            if ($imageHeight > ($imageWidth / 3)) {
                $newImageHeight = ceil($imageWidth / 3);
                $imageWidth = floor($imageWidth * ($newImageHeight / $imageHeight));
                $imageHeight = $newImageHeight;
            }

            $image->resizeImage($imageWidth, $imageHeight, \Imagick::FILTER_BOX, 0);

            $base64 = base64_encode($image->getimageblob());

            $this->printJob[] = [
                'op' => 'image',
                'data' => 'data:image/png;base64,' . $base64
            ];
        } catch (\Exception $exception) {

        }
    }

    /***************************************************
     * Methods for adding text and modifying text styles
     * add, font, style, doubles and aligns
     ***************************************************/

    /**
     * @param $text string
     */
    public function addTittle($text)
    {
        $this->textFont('C');
        $this->textStyle();
        $this->textDouble(true, true);
        $this->text($text);
    }

    /**
     * Add text to the builder
     * @param string $string
     */
    public function text($string = '')
    {
        $this->printJob[] = [
            'op' => 'text',
            'data' => $string
        ];
    }

    /**
     * Set next text with the selected font
     * @param $font string (A, B, C)
     */
    public function textFont($font = 'A')
    {
        if ($this->lastCondensed !== $font) {
            $this->lastCondensed = $font;
            $this->printJob[] = [
                'op' => 'condensed',
                'data' => ($font == 'A' ? false : true)
            ];
        }
    }

    /**
     * @param bool $reverse
     * @param bool $underlined
     * @param bool $emphasized
     * @param bool $color
     */
    public function textStyle($reverse = false, $underlined = false, $emphasized = false, $color = false)
    {
        $style = [
            'op' => 'style',
            'data' => [
                'underlined' => $underlined,
                'emphasized' => $emphasized
            ]
        ];

        if ($this->lastStyle !== $style) {
            $this->lastStyle = $style;
            $this->printJob[] = $style;
        }
    }

    /**
     * @param bool $doubleWidth
     * @param bool $doubleHeight
     */
    public function textDouble($doubleWidth = false, $doubleHeight = false)
    {
        $option = [
            'op' => 'double',
            'data' => [
                'width' => $doubleWidth,
                'height' => $doubleHeight
            ]
        ];

        if ($this->lastDouble !== $option) {
            $this->lastDouble = $option;
            $this->printJob[] = $option;
        }
    }

    /**
     * @param string $align
     */
    public function textAlign($align = 'LEFT')
    {
        if ($this->lastAlignment !== $this->align[$align]) {
            $this->lastAlignment = $this->align[$align];
            $this->printJob[] = [
                'op' => 'alignment',
                'data' => (isset($this->align[$align]) ? $this->align[$align] : $this->align['LEFT'])
            ];
        }
    }

    /**
     * @todo Missing methods for printer pulse
     */

    /**
     * Method to send the command CUT
     */
    public function cut()
    {
        $this->printJob[] = [
            'op' => 'cut',
            'data' => 'feed'
        ];
    }

    /**
     * @param string $format
     * @return array|string
     */
    public function getPrintJob($format = 'array')
    {
        switch ($format) {
            case 'json':
                return json_encode($this->printJob);
                break;
            case 'array':
            default:
                return $this->printJob;
                break;
        }
    }
}