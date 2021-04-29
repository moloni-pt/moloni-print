<?php

namespace MoloniPrint\Utils;

use Exception;
use MoloniPrint\Settings\Printer;

class Builder
{

    protected $imageUrl = "https://moloni.pt/_imagens/";

    private $store = [];

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
        $settings = [
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
                'continuousPrint' => $printer->continuousPrint,
                'copies' => $printer->copies
            ]
        ];

        array_unshift($this->printJob, $settings);
    }

    public function image($path, $maxWidth = 576, $imageType = 'default')
    {
        try {
            switch ($imageType) {
                case 'path':
                    $this->printJob[] = [
                        'op' => 'image',
                        'data' => $path
                    ];
                    break;
                case 'qr':
                    $this->printJob[] = [
                        'op' => 'image',
                        'data' => 'data:image/png;base64,' . $this->getImageBase64($path, $maxWidth > 260 ? 260 : $maxWidth, true)
                    ];

                    break;
                case 'default':
                case 'base64':
                default:
                    $url = $this->imageUrl . '?macro=imgWebPOSCompanyLogoPrinterRaw&img=' . $path;
                    $this->printJob[] = [
                        'op' => 'image',
                        'data' => 'data:image/png;base64,' . $this->getImageBase64($url, $maxWidth)
                    ];

                    break;
            }
        } catch (Exception $exception) {

        }
    }

    /**
     * Obter um base64 de uma URL ou path
     *
     * @param string $url
     * @param int $maxWidth
     * @param bool $avoidResize
     * @return mixed|string
     * @throws \ImagickException
     */
    protected function getImageBase64($url, $maxWidth = 576, $avoidResize = false)
    {
        $storeIndex = $url . $maxWidth;
        if (isset($this->store[$storeIndex])) {
            return $this->store[$url . $maxWidth];
        }

        $maxPaperWidth = $maxWidth;
        $image = new \Imagick($url);

        $image->setImageFormat("png");

        $imageWidth = $maxWidth;
        $imageHeight = ceil($maxWidth * $image->getImageHeight() / $image->getImageWidth());

        if ($imageHeight > ($imageWidth / 3) && !$avoidResize) {
            $newImageHeight = ceil($imageWidth / 3);
            $imageWidth = floor($imageWidth * ($newImageHeight / $imageHeight));
            $imageHeight = $newImageHeight;
            $image->resizeImage($imageWidth, $imageHeight, \Imagick::FILTER_BOX, 0);
            $image->extentImage($maxPaperWidth, $imageHeight, 0, 0);
        } else {
            $image->resizeImage($imageWidth, $imageHeight, \Imagick::FILTER_BOX, 0);
        }

        $base64 = base64_encode($image->getimageblob());
        $this->store[$storeIndex] = $base64;
        return $base64;
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

    public function resetStyle()
    {
        $this->textFont();
        $this->textAlign();
        $this->textDouble();
        $this->textStyle();
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
     * Add variable to the builder
     * @param string $string
     */
    public function variable($string = '')
    {
        $this->printJob[] = [
            'op' => 'variable',
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
        if ($this->lastAlignment !== $align) {
            $this->lastAlignment = $align;
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

    public function openDrawer()
    {
        $this->printJob[] = [
            'op' => 'pulse',
            'data' => [
                'drawer' => 0,
                'pulse' => 50
            ]
        ];
    }

    public function pause()
    {
        $this->printJob[] = [
            'op' => 'pause',
            'data' => 'pause'
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