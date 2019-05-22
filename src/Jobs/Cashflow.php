<?php

namespace MoloniPrint\Jobs;

use MoloniPrint\Job;
use MoloniPrint\Table\Table;
use MoloniPrint\Utils\Tools;

class Cashflow extends Common
{

    protected $cashflow = [];
    protected $payments = [];

    /**
     * Document constructor.
     * @param Job $job
     */
    public function __construct(Job &$job)
    {
        parent::__construct($job);
    }

    public function cashflowType()
    {
        $this->builder->textFont('C');
        $this->builder->textAlign('RIGHT');
        $this->builder->textDouble(true, true);
        $this->builder->textStyle(false, false, true);
        $this->builder->text($this->getCashflowTypeString($this->cashflow['type_id']));
        $this->linebreak();
    }

    /**
     * Info about the terminal
     * Terminal Name
     * Operator Name
     * Salesman Name
     */
    public function cashflowTerminal()
    {
        $this->builder->textFont('C');
        $this->builder->textAlign('RIGHT');
        $this->builder->textDouble();
        $this->builder->textStyle();
        $this->builder->text($this->labels->terminal . ': ' . $this->terminal['name']);
        $this->linebreak();
        $this->builder->text($this->labels->operator . ': ' . $this->cashflow['lastmodifiedby']);
        $this->linebreak();
    }

    public function signature()
    {
        $this->builder->textFont('A');
        $this->builder->textDouble();
        $this->builder->textAlign();
        $this->builder->textStyle(false, false, true);
        $this->builder->text($this->labels->signature);
        $this->linebreak();
        $this->drawLine();
        $this->builder->textFont('C');
        $this->builder->textStyle();
        $this->builder->text('(' . $this->cashflow['lastmodifiedby'] . ')');
        $this->linebreak();
    }

    public function createdAt()
    {

        $this->builder->textFont('C');
        $this->builder->textDouble();
        $this->builder->textAlign();
        $this->builder->textStyle();

        $this->builder->text($this->labels->document_created_at . ' ' . date('Y-m-d H:i'));
        $this->linebreak();
    }

    public function processedBy()
    {
        $this->builder->textFont('C');
        $this->builder->textDouble();
        $this->builder->textStyle(false, false, true);
        $this->builder->textAlign('CENTER');
        $this->builder->text($this->labels->processed_by);
        $this->linebreak();
    }

    protected function getCashflowTypeString($documentType = '')
    {
        switch ($documentType) {
            case 1:
                return $this->labels->cashflow_open;
                break;

            case 2:
                return $this->labels->cashflow_in;
                break;

            case 3:
                return $this->labels->cashflow_out;
                break;

            case 4:
                return $this->labels->cashflow_close;
                break;

            default:
                return $this->labels->cashflow_starting_amount;
                break;
        }
    }
}