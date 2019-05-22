<?php

namespace MoloniPrint\Jobs\Cashflows;

use MoloniPrint\Job;
use MoloniPrint\Jobs\Cashflow;
use MoloniPrint\Table\Table;
use MoloniPrint\Utils\Tools;

class Regular extends Cashflow
{

    /**
     * Document constructor.
     * @param Job $job
     */
    public function __construct(Job &$job)
    {
        parent::__construct($job);
    }

    public function create(Array $cashflow)
    {
        $this->cashflow = $cashflow;
        $this->currency = $this->company['currency']['symbol'];
        $this->parsePayments();

        $this->drawFromScheme($this->cashflowRegularSchema);
        $this->finish();

        return $this->builder->getPrintJob('json');
    }

    public function details()
    {
        if ($this->cashflow['type_id'] == 1) {
            $this->cashflowType();
        }
        $this->cashflowTerminal();
        $this->cashflowDate();
    }

    /**
     * Show cashflow last modified date
     */
    public function cashflowDate()
    {
        $this->builder->textFont('C');
        $this->builder->textAlign('RIGHT');
        $this->builder->textDouble();
        $this->builder->textStyle(false, false, true);
        $this->builder->text($this->labels->date . ': ' . Tools::dateFormat($this->cashflow['date']));
        $this->linebreak();
    }

    public function payments()
    {
        $this->builder->textFont('C');
        $this->builder->textAlign();
        $this->builder->textDouble(true, true);
        $this->builder->textStyle(false, false, true);

        if ($this->cashflow['type_id'] == 4 || $this->cashflow['type_id'] == 3) {
            $this->builder->text($this->getCashflowTypeString($this->cashflow['type_id']));
        } else {
            $this->builder->text($this->getCashflowTypeString());
        }

        $this->linebreak();

        if (!empty($this->cashflow['notes'])) {
            $this->builder->textFont('C');
            $this->builder->textAlign();
            $this->builder->textDouble();
            $this->builder->textStyle();
            $this->builder->text($this->cashflow['notes']);
        }

        $this->linebreak();
        $this->drawLine();

        $table = new Table($this->builder, $this->printer);
        $table->addColumns(['', 20]);

        foreach ($this->payments as $paymentIndex => $payment) {
            $table->addCell($payment['name'], ['emphasized' => true]);
            $table->addCell($payment['value'], ['alignment' => 'RIGHT']);

            if ($paymentIndex == count($this->payments) - 1) {
                $table->addLineSplit();
            }
        }

        $table->addCell($this->labels->total, ['emphasized' => true]);
        $table->addCell(Tools::priceFormat(($this->cashflow['value']), $this->currency), ['alignment' => 'RIGHT']);

        $table->drawTable();
        $this->linebreak();
    }

    protected function parsePayments()
    {
        $totalValue = $this->cashflow['value'];
        $totalAssociatedValue = 0;

        if (isset($this->cashflow['payments']) && is_array($this->cashflow['payments'])) {
            foreach ($this->cashflow['payments'] as $payment) {
                $this->payments[] = [
                    'name' => $payment['payment_method']['name'],
                    'value' => Tools::priceFormat($payment['value'], $this->currency)
                ];

                $totalAssociatedValue += $payment['value'];
            }
        }

        if ($totalValue > $totalAssociatedValue) {
            $this->payments[] = [
                'name' => $this->labels->undifferentiated,
                'value' => Tools::priceFormat(($totalValue - $totalAssociatedValue), $this->currency)
            ];
        }
    }
}