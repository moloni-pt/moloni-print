<?php

namespace MoloniPrint\Jobs\Cashflows;

use MoloniPrint\Job;
use MoloniPrint\Jobs\Cashflow;
use MoloniPrint\Table\Table;
use MoloniPrint\Utils\Tools;

class Resume extends Cashflow
{

    public $drawingSchema = [
        'image',
        'header',
        'details',
        'linebreak',
        'resume',
        'linebreak',
        'sales',
        'linebreak',
        'expenses',
        'linebreak',
        'createdAt',
        'linebreak',
        'processedBy',
        'poweredBy'
    ];

    private $resume = [];
    private $incoming = [];
    private $outgoing = [];

    private $totalIncoming = 0;
    private $totalOutgoing = 0;

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
        $this->parseMovements();
        $this->parsePayments();

        $this->drawFromScheme($this->drawingSchema);
        $this->finish();

        return $this->builder->getPrintJob('json');
    }

    public function details()
    {
        $this->cashflowType();
        $this->cashflowTerminal();
        $this->cashflowDate();
    }

    public function cashflowType()
    {
        $this->builder->textFont('C');
        $this->builder->textAlign('RIGHT');
        $this->builder->textDouble(true, true);
        $this->builder->textStyle(false, false, true);
        $this->builder->text($this->labels->cashflow_resume);
        $this->linebreak();
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
        $this->builder->text($this->labels->period . ': ' . Tools::dateFormat($this->cashflow['date']) . ' - ' . Tools::dateFormat(date('d-m-Y H:i')));
        $this->linebreak();
    }

    public function resume()
    {
        $this->builder->textFont('C');
        $this->builder->textAlign('');
        $this->builder->textDouble(true, true);
        $this->builder->textStyle(false, false, true);
        $this->builder->text($this->labels->resume);
        $this->linebreak();

        $table = new Table($this->builder, $this->printer);
        $table->addColumns([null, 20]);

        $table->addLineSplit();

        $table->addCell($this->labels->initial, ['condensed' => true, 'emphasized' => true]);
        $table->addCell(Tools::priceFormat($this->cashflow['value'], $this->currency), ['condensed' => true, 'alignment' => 'RIGHT']);

        $table->addCell($this->labels->total_sales, ['condensed' => true, 'emphasized' => true]);
        $table->addCell(Tools::priceFormat($this->totalIncoming, $this->currency), ['condensed' => true, 'alignment' => 'RIGHT']);

        $table->addCell($this->labels->total_expenses, ['condensed' => true, 'emphasized' => true]);
        $table->addCell(Tools::priceFormat($this->totalOutgoing, $this->currency), ['condensed' => true, 'alignment' => 'RIGHT']);

        $table->addLineSplit();

        $table->addCell($this->labels->difference, ['condensed' => true, 'emphasized' => true]);
        $table->addCell(Tools::priceFormat(($this->cashflow['value'] + $this->totalIncoming - $this->totalOutgoing), $this->currency), ['condensed' => true, 'alignment' => 'RIGHT']);

        $table->drawTable();
        $this->linebreak();
    }

    public function sales()
    {
        if (!empty($this->incoming) && is_array($this->incoming)) {
            $this->builder->textFont('C');
            $this->builder->textAlign('');
            $this->builder->textDouble(true, true);
            $this->builder->textStyle(false, false, true);
            $this->builder->text($this->labels->sales);
            $this->linebreak();

            $table = new Table($this->builder, $this->printer);
            $table->addColumns([null, 20]);

            $table->addLineSplit();

            foreach ($this->incoming as $paymentMethod) {
                $table->addCell($paymentMethod['name'], ['condensed' => true]);
                $table->addCell(Tools::priceFormat($paymentMethod['value'], $this->currency), ['alignment' => 'RIGHT', 'condensed' => true]);
            }

            $table->addLineSplit();

            $table->addCell($this->labels->total, ['condensed' => true, 'emphasized' => true]);
            $table->addCell(Tools::priceFormat($this->totalIncoming), ['condensed' => true, 'emphasized' => true, 'alignment' => 'RIGHT']);

            $table->drawTable();
            $this->linebreak();
        }
    }

    public function expenses()
    {
        if (!empty($this->outgoing) && is_array($this->outgoing)) {
            $this->builder->textFont('C');
            $this->builder->textAlign('');
            $this->builder->textDouble(true, true);
            $this->builder->textStyle(false, false, true);
            $this->builder->text($this->labels->outflow . '/' . $this->labels->expenses);
            $this->linebreak();

            $table = new Table($this->builder, $this->printer);
            $table->addColumns([null, 20]);

            $table->addLineSplit();

            foreach ($this->outgoing as $payment) {
                $table->addCell($payment['name'], ['condensed' => true]);
                $table->addCell(Tools::priceFormat($payment['value'], $this->currency), ['condensed' => true, 'alignment' => 'RIGHT']);
            }

            $table->addLineSplit();

            $table->addCell($this->labels->total, ['condensed' => true, 'emphasized' => true]);
            $table->addCell(Tools::priceFormat($this->totalOutgoing, $this->currency), ['condensed' => true, 'emphasized' => true, 'alignment' => 'RIGHT']);

            $table->drawTable();
            $this->linebreak();
        }
    }

    private function parseMovements()
    {
        foreach ($this->cashflow['associated_movements'] as $movement) {
            if ($movement['type_id'] == 2) {
                // For sales
                $this->totalIncoming += $movement['value'];
                $totalValue = $movement['value'];
                $totalAssociatedValue = 0;

                if (!empty($movement['payments']) && is_array($movement['payments'])) {
                    foreach ($movement['payments'] as $payment) {
                        $this->incoming[$payment['payment_method_id']]['name'] = $payment['payment_method']['name'];
                        $this->incoming[$payment['payment_method_id']]['value'] += $payment['value'];
                        $totalAssociatedValue += $payment['value'];
                    }
                }

                if ($totalValue > $totalAssociatedValue) {
                    $this->incoming[0]['name'] = $this->labels->undifferentiated;
                    $this->incoming[0]['value'] += $totalValue - $totalAssociatedValue;
                }

            } else {
                // For expenses
                $this->totalOutgoing += $movement['value'];
                $totalValue = $movement['value'];
                $totalAssociatedValue = 0;

                if (isset($movement['payments']) && is_array($movement['payments'])) {
                    foreach ($movement['payments'] as $payment) {
                        $this->outgoing[$payment['payment_method_id']]['name'] = $payment['payment_method']['name'];
                        $this->outgoing[$payment['payment_method_id']]['value'] += $payment['value'];

                        $totalAssociatedValue += $payment['value'];
                    }
                }

                if ($totalValue > $totalAssociatedValue) {
                    $this->outgoing[0]['name'] = $this->labels->undifferentiated;
                    $this->outgoing[0]['value'] += $totalValue - $totalAssociatedValue;
                }
            }
        }
    }

    private function parsePayments()
    {
        if (!empty($this->cashflow['payments']) && is_array($this->cashflow['payments'])) {
            foreach ($this->cashflow['payments'] as $payment) {
                $this->resume[$payment['payment_method_id']]['name'] = $payment['payment_method']['name'];
                $this->resume[$payment['payment_method_id']]['value'] += $payment['value'];
            }
        }
    }
}