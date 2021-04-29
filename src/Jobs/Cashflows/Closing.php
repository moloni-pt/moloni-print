<?php

namespace MoloniPrint\Jobs\Cashflows;

use MoloniPrint\Job;
use MoloniPrint\Jobs\Cashflow;
use MoloniPrint\Table\Table;
use MoloniPrint\Utils\Tools;

class Closing extends Cashflow
{

    private $incoming = [];
    private $outgoing = [];

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

        $this->drawFromScheme($this->cashflowClosingSchema);
        $this->finish();

        return $this->builder->getPrintJob('json');
    }

    public function details()
    {
        $this->cashflowType();
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
        $this->builder->text($this->labels->period . ': ' . Tools::dateFormat($this->cashflow['parent']['date']) . ' - ' . Tools::dateFormat($this->cashflow['date']));
        $this->linebreak();
    }

    public function resume()
    {
        $this->builder->textFont('C');
        $this->builder->textAlign('');
        $this->builder->textDouble(true, true);
        $this->builder->textStyle(false, false, true);
        $this->builder->text($this->labels->cashflow_starting_amount);
        $this->linebreak();

        $table = new Table($this->builder, $this->printer);
        $table->addColumns([null, 20]);

        $table->addLineSplit();

        $table->addCell($this->labels->initial, ['condensed' => true, 'emphasized' => true]);
        $table->addCell(Tools::priceFormat($this->cashflow['parent']['value'], $this->currency), ['condensed' => true, 'alignment' => 'RIGHT']);

        $table->addCell($this->labels->final, ['condensed' => true, 'emphasized' => true]);
        $table->addCell(Tools::priceFormat($this->cashflow['value'], $this->currency), ['condensed' => true, 'alignment' => 'RIGHT']);

        $table->addLineSplit();

        $table->addCell($this->labels->difference, ['condensed' => true, 'emphasized' => true]);
        $table->addCell(Tools::priceFormat(($this->cashflow['value'] - $this->cashflow['parent']['value']), $this->currency), ['condensed' => true, 'alignment' => 'RIGHT']);

        $table->drawTable();
        $this->linebreak();
    }

    public function sales()
    {
        if (!empty($this->incoming) && is_array($this->incoming)) {
            $totalDeclared = 0;
            $totalInvoiced = 0;

            $this->builder->textFont('C');
            $this->builder->textAlign('');
            $this->builder->textDouble(true, true);
            $this->builder->textStyle(false, false, true);
            $this->builder->text($this->labels->sales);
            $this->linebreak();

            $table = new Table($this->builder, $this->printer);
            $table->addColumns([null, 12, 12, 12]);

            $table->addCells(['', $this->labels->declared, $this->labels->invoiced, $this->labels->difference], ['condensed' => true, 'emphasized' => true, 'alignment' => 'RIGHT']);
            $table->addLineSplit();

            foreach ($this->incoming as $paymentMethod) {
                $totalDeclared += $paymentMethod['declared'];
                $totalInvoiced += $paymentMethod['invoiced'];

                $table->addCell($paymentMethod['name'], ['condensed' => true]);
                $table->addCell(Tools::priceFormat($paymentMethod['declared'], $this->currency), ['alignment' => 'RIGHT', 'condensed' => true]);
                $table->addCell(Tools::priceFormat($paymentMethod['invoiced'], $this->currency), ['alignment' => 'RIGHT', 'condensed' => true]);
                $table->addCell(Tools::priceFormat(($paymentMethod['declared'] - $paymentMethod['invoiced']), $this->currency), ['alignment' => 'RIGHT', 'condensed' => true]);
            }

            $table->addLineSplit();

            $table->addCell($this->labels->total, ['condensed' => true, 'emphasized' => true]);
            $table->addCell(Tools::priceFormat($totalDeclared, $this->currency), ['condensed' => true, 'emphasized' => true, 'alignment' => 'RIGHT']);
            $table->addCell(Tools::priceFormat($totalInvoiced, $this->currency), ['condensed' => true, 'emphasized' => true, 'alignment' => 'RIGHT']);
            $table->addCell(Tools::priceFormat($totalDeclared - $totalInvoiced, $this->currency), ['condensed' => true, 'emphasized' => true, 'alignment' => 'RIGHT']);

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
                $table->addCell($payment['name'], ['condensed' => true, 'emphasized' => true]);
                $table->addCell($payment['value'], ['condensed' => true, 'alignment' => 'RIGHT']);
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
                $totalValue = $movement['value'];
                $totalInvoicedLeft = $movement['value'];
                $totalAssociatedValue = 0;

                if (!empty($movement['payments']) && is_array($movement['payments'])) {
                    foreach ($movement['payments'] as $payment) {
                        $paymentValue = $payment['value'];
                        if ($paymentValue > $totalInvoicedLeft) {
                            $paymentValue = $totalInvoicedLeft;
                        }

                        $this->incoming[$payment['payment_method_id']]['name'] = $payment['payment_method']['name'];
                        $this->incoming[$payment['payment_method_id']]['invoiced'] += $paymentValue;
                        $this->incoming[$payment['payment_method_id']]['declared'] += 0;

                        $totalInvoicedLeft -= $paymentValue;
                        $totalAssociatedValue += $paymentValue;
                    }
                }

                if ($totalValue > $totalAssociatedValue) {
                    $this->incoming[0]['name'] = $this->labels->undifferentiated;
                    $this->incoming[0]['invoiced'] += $totalValue - $totalAssociatedValue;
                    $this->incoming[0]['declared'] += 0;
                }

            } else {
                // For expenses
                $this->totalOutgoing += $movement['value'];
                $totalValue = $movement['value'];
                $totalAssociatedValue = 0;

                if (isset($movement['payments']) && is_array($movement['payments'])) {
                    foreach ($movement['payments'] as $payment) {
                        $this->outgoing[] = [
                            'name' => $payment['payment_method']['name'],
                            'value' => Tools::priceFormat($payment['value'], $this->currency)
                        ];

                        $totalAssociatedValue += $payment['value'];
                    }
                }

                if ($totalValue > $totalAssociatedValue) {
                    $this->outgoing[] = [
                        'name' => $this->labels->undifferentiated,
                        'value' => Tools::priceFormat(($totalValue - $totalAssociatedValue), $this->currency)
                    ];
                }
            }
        }
    }

    private function parsePayments()
    {
        if (!empty($this->cashflow['payments']) && is_array($this->cashflow['payments'])) {
            foreach ($this->cashflow['payments'] as $payment) {
                $this->incoming[$payment['payment_method_id']]['name'] = $payment['payment_method']['name'];
                $this->incoming[$payment['payment_method_id']]['invoiced'] += 0;
                $this->incoming[$payment['payment_method_id']]['declared'] += $payment['value'];
            }
        }
    }
}