<?php

namespace MoloniPrint\Settings;

class Printer
{
    /**
     * Width of a line with normal text
     * @var int
     */
    public $normalWidth = 48;

    /**
     * Width of a line with condensed text
     * @var int
     */
    public $condensedWidth = 64;

    /**
     * Printer paper width
     * @var int
     */
    public $dotWidth = 576;

    /**
     * Terminal has drawer
     * @var boolean
     */
    public $hasDrawer = true;

    /**
     * Terminal has paper cutter
     * @var boolean
     */
    public $hasCutter = true;

    /**
     * Print in low density mode
     * @var boolean
     */
    public $lowDensity = false;

    /**
     * Image printing mode
     * @var int
     */
    public $imagePrintMode = 0;

    /**
     * Use alternative charset
     * @var boolean
     */
    public $alternativeCharset = false;

    /**
     * Replace special chars in strings
     * @var int
     */
    public $replaceAccentedChars = false;

    /**
     * Line split character for line draw
     * @var string
     */
    public $tableSplitChar = '─';

    /**
     * Printers constructor.
     * @param $printer array
     */
    public function __construct($printer)
    {
        $this->setPrinterSettings($printer);
    }

    /**
     * @param array $printer
     */
    private function setPrinterSettings($printer)
    {
        if (!empty($printer) && is_array($printer)) {
            if (isset($printer['normalWidth']) && $printer['normalWidth'] > 0 && $printer['normalWidth'] < 1000) {
                $this->normalWidth = (int)$printer['normalWidth'];
            }

            if (isset($printer['condensedWidth']) && $printer['condensedWidth'] > 0 && $printer['condensedWidth'] < 1000) {
                $this->condensedWidth = (int)$printer['condensedWidth'];
            }

            if (isset($printer['dotWidth']) && $printer['dotWidth'] > 0 && $printer['dotWidth'] < 1000) {
                $this->dotWidth = (int)$printer['dotWidth'];
            }

            if (isset($printer['hasDrawer'])) {
                $this->hasDrawer = ($printer['hasDrawer'] || $printer['hasDrawer'] == 'true' ? true : false);
            }

            if (isset($printer['hasCutter'])) {
                $this->hasCutter = ($printer['hasCutter'] || $printer['hasCutter'] == 'true' ? true : false);
            }

            if (isset($printer['lowDensity']) || isset($printer['imagePrintMode'])) {
                if (isset($printer['lowDensity'])) {
                    $this->lowDensity = ($printer['lowDensity'] || $printer['lowDensity'] == 'true' ? true : false);
                }

                if (isset($printer['imagePrintMode'])) {
                    $this->imagePrintMode = $printer['imagePrintMode'];
                }
            } elseif (isset($printer['printMode'])) {
                $this->lowDensity = ($printer['printMode'] > 3) ? true : false;
                $this->imagePrintMode = (int)(($printer['printMode'] > 3) ? ($printer['printMode'] - 4) : $printer['printMode']);
            }

            if (isset($printer['replaceAccentedChars'])) {
                $this->replaceAccentedChars = ($printer['replaceAccentedChars'] || $printer['replaceAccentedChars'] == 'true' ? true : false);
            }
            if (isset($printer['alternativeCharset'])) {
                $this->alternativeCharset = ($printer['alternativeCharset'] || $printer['alternativeCharset'] == 'true' ? true : false);
            }

            if (isset($printer['tableSplitChar'])) {
                $this->tableSplitChar = $printer['tableSplitChar'];
            }
        }
    }
}