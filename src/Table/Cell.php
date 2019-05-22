<?php

namespace MoloniPrint\Table;

class Cell
{
    public $text;
    public $columnSpan = 1;
    public $condensed = false;
    public $emphasized = false;
    public $underlined = false;
    public $doubleSize = false;
    public $alignment = 0;
    public $filler = ' ';

    /**
     * Add a new cell to the table.
     * @param string $text
     * @param array $options
     */
    public function __construct($text, $options = [])
    {
        $this->text = $text;
        if (is_array($options) && !empty($options)) {
            foreach ($options as $option => $value) {
                $this->{$option} = $value;
            }
        }
    }

}