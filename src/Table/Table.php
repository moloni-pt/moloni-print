<?php

namespace MoloniPrint\Table;

use MoloniPrint\Settings\Printer;
use MoloniPrint\Utils\Builder;
use MoloniPrint\Utils\Tools;

class Table
{
    public $builder;
    public $printer;
    public $normalLineWidth;
    public $condensedLineWidth;
    public $tableWidth;

    public $currentColumn = 0;
    public $columns = [];
    public $cells = [];

    /**
     * Start by setting the paper dimensions.
     * @param Builder $builder
     * @param Printer $printer
     */
    public function __construct(Builder &$builder, Printer $printer)
    {
        $this->printer = $printer;
        $this->builder = $builder;
        $this->normalLineWidth = $this->printer->normalWidth;
        $this->condensedLineWidth = $this->printer->condensedWidth;
        $this->tableWidth = $this->printer->condensedWidth;
    }

    /**
     * The next text will be in a new table row
     */
    public function newRow()
    {
        $this->currentColumn = 0;
    }

    /**
     * Add a line with the printer tableSplitChar
     */
    public function addLineSplit()
    {
        $lineStyle = [
            'filler' => $this->printer->tableSplitChar,
            'condensed' => true
        ];

        $this->newRow();
        $this->addCell('', $lineStyle);
        $this->newRow();
    }

    /**
     * Add multiple addColumn from a given array
     * @param array $columns
     */
    public function addColumns($columns)
    {
        foreach ($columns as $column) {
            $this->addColumn($column);
        }
    }

    /**
     * Add multiple cells from array with a set of options for all cells
     * @param array $cells
     * @param array $options
     */
    public function addCells($cells = [], $options = [])
    {
        foreach ($cells as $cell) {
            $this->addCell($cell, $options);
        }
    }

    /**
     * Add a new column
     * This supports both int or array
     * If the $options is int we set the column with with $options size
     * If the $options is array, it should have 'chars' or 'percent'
     * @param null|int|array $options
     */
    public function addColumn($options = null)
    {
        if (is_int($options)) {
            $options = ['chars' => $options];
        }

        $this->columns[] = new Column($options);
    }

    /**
     * Add a new cell with a set of options
     * @param string $text
     * @param array $options
     * @return Cell
     */
    public function addCell($text = '', $options = [])
    {
        $cell = new Cell($text, $options);

        if ($this->currentColumn == 0) {
            $this->cells[] = [];
        }

        if (count($this->columns) > 0) {
            if (($this->currentColumn + $cell->columnSpan) >= count($this->columns)) {
                $this->currentColumn = 0;

                if (($this->currentColumn + $cell->columnSpan) > count($this->columns)) {
                    $cell->columnSpan = count($this->columns);
                }

            } else {
                $this->currentColumn += $cell->columnSpan;
            }

            $this->cells[count($this->cells) - 1][] = $cell;
        }

        return $cell;
    }

    /**
     * This methods calculates the size of rows and draws the columns and cells
     * In order to print a table, this method is REQUIRED whenever you want to draw table
     */
    public function drawTable()
    {
        $this->calculateAutoSizedColumns();

        foreach ($this->cells as $rowColumns) {
            $cellWidths = $this->getCellWidths($rowColumns);
            $maxLinesInRow = $this->getMaxLinesInRow($cellWidths, $rowColumns);

            for ($i = 0; $i < $maxLinesInRow; $i++) {
                $totalCount = 0;
                $countDeviation = 0;

                foreach ($rowColumns as $cellIndex => $cell) {

                    $this->builder->textFont($cell->condensed ? 'C' : 'A');
                    $this->builder->textDouble($cell->doubleSize, false);
                    $this->builder->textStyle(false, $cell->underlined, $cell->emphasized);

                    $realCellWidth = $cellWidths[$cellIndex];
                    $totalCount += $realCellWidth;

                    if (!$cell->condensed) {
                        $exactCellWidth = $realCellWidth * ($this->normalLineWidth / $this->condensedLineWidth);
                        $realCellWidth = floor($realCellWidth * ($this->normalLineWidth / $this->condensedLineWidth));
                        $countDeviation += $exactCellWidth - $realCellWidth;
                    }

                    if ($cell->doubleSize) {
                        $realCellWidth = floor($realCellWidth / 2);
                    }

                    $text = $this->getLineFromText($realCellWidth, $cell->text, $i);

                    switch ($cell->alignment) {
                        case 'LEFT':
                        default:
                            $text = Tools::mb_str_pad($text, $realCellWidth, $cell->filler, STR_PAD_RIGHT);
                            break;
                        case 'CENTER':
                            $text = Tools::mb_str_pad($text, $realCellWidth, $cell->filler, STR_PAD_BOTH);
                            break;
                        case 'RIGHT':
                            $text = Tools::mb_str_pad($text, $realCellWidth, $cell->filler, STR_PAD_LEFT);
                            break;
                    }

                    if ($cellIndex == (count($rowColumns) - 1) && (floor($totalCount - $countDeviation) < $this->condensedLineWidth)) {
                        $text .= "\n";
                    }

                    $this->builder->text($text);
                }
            }
        }
    }

    /**
     * Auto calculates the size of each auto sized column
     */
    private function calculateAutoSizedColumns()
    {
        $numAutoSizedColumns = 0;
        $availableSpace = $this->tableWidth;

        foreach ($this->columns as $column) {
            if ($column->isAutoSized()) {
                $numAutoSizedColumns++;
            } else {
                $availableSpace -= $column->getWidth($this->tableWidth);
            }
        }

        if (($availableSpace > 0) && ($numAutoSizedColumns > 0)) {
            foreach ($this->columns as $column) {
                if ($column->isAutoSized()) {
                    $column->setWidth(floor($availableSpace / $numAutoSizedColumns));
                }
            }
        }
    }

    /**
     * Calculates the real width of each cell in a table
     * @param array $rowCells
     * @return array
     */
    private function getCellWidths($rowCells)
    {
        $widths = [];
        $currentCol = 0;

        foreach ($rowCells as $cellIndex => $cell) {
            $cellWidth = 0;

            if ($cellIndex == count($rowCells) - 1) {
                if (count($widths) + $cell->columnSpan !== count($this->columns)) {
                    $cell->columnSpan = count($this->columns) - $currentCol;
                }
            }

            $cellSpan = $cell->columnSpan;
            for ($i = $currentCol; $i < ($currentCol + $cellSpan); $i++) {
                $cellWidth += $this->columns[$i]->getWidth($this->tableWidth);
            }

            $currentCol += $cellSpan;
            $widths[] = $cellWidth;
        }

        return $widths;
    }

    /**
     * Define how many rows a cell will have
     * This is done by splitting a string into multiple ones
     * Each string cannot be bigger than the maximum width
     * @param array $cellWidths
     * @param array $rowCells
     * @return int
     */
    private function getMaxLinesInRow($cellWidths, $rowCells)
    {
        $maxLines = 1;

        foreach ($rowCells as $cellIndex => $cell) {
            $realCellWidth = $cellWidths[$cellIndex];

            if (!$cell->condensed) {
                $realCellWidth = floor($realCellWidth * ($this->normalLineWidth / $this->condensedLineWidth));
            }

            if ($cell->doubleSize) {
                $realCellWidth = floor($realCellWidth / 2);
            }

            $wrapped = Tools::wrapText($cell->text, $realCellWidth);
            $maxLines = max($maxLines, count(explode("\n", $wrapped)));
        }

        return $maxLines;
    }

    /**
     * Split a string into multiple ones and return the string on index $line
     * @param int $cellWidth
     * @param string $cellText
     * @param int $line
     * @return string
     */
    private function getLineFromText($cellWidth, $cellText, $line)
    {
        $wrappedText = Tools::wrapText($cellText, $cellWidth);

        $textLines = explode("\n", $wrappedText);

        if ($line < count($textLines)) {
            return $textLines[$line];
        }

        return '';
    }


}