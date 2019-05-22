<?php

namespace MoloniPrint\Table;

class Column
{
    public $autoSized = false;
    public $startedWithPercentage = false;
    public $charWidth;
    public $percentWidth;

    /**
     * Starts a new column with the given options.
     * $options can be
     *      null - auto sized
     *      chars - number of chars of the column
     *      percent - percentage of row space this column will have
     * @param null|array(chars|percent) $options
     */
    public function __construct($options = [])
    {
        if ($options && (isset($options['chars']) || isset($options['percent']))) {
            if (isset($options['chars'])) {
                $this->charWidth = (int)$options['chars'];
                $this->startedWithPercentage = false;
            } else {
                $this->percentWidth = (float)$options['percent'];
                $this->startedWithPercentage = true;
            }
        } else {
            $this->autoSized = true;
        }
    }

    /**
     * Get the width this column
     * @param int $fullCondensedWidth
     * @return float|int
     */
    public function getWidth($fullCondensedWidth)
    {
        if ($this->autoSized) {
            return -1;
        }

        if ($this->startedWithPercentage) {
            return round($fullCondensedWidth * $this->percentWidth);
        }

        return $this->charWidth;
    }

    /**
     * Set the width of the column
     * @param int $charWidth
     */
    public function setWidth($charWidth)
    {
        $this->charWidth = $charWidth;
        $this->startedWithPercentage = false;
        $this->autoSized = false;
    }

    /**
     * Check if this column is auto sized
     * @return bool
     */
    public function isAutoSized()
    {
        return $this->autoSized;
    }
}