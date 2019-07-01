<?php

namespace MoloniPrint\Jobs\Cashflows;

use MoloniPrint\Job;
use MoloniPrint\Jobs\Cashflow;
use MoloniPrint\Table\Table;
use MoloniPrint\Utils\Tools;

class Detailed extends Cashflow
{

    public $drawingSchema = [
        'image',
        'header',
        'details',
        'linebreak',
        'resume',
        'linebreak',
        'movements',
        'linebreak',
        'documents',
        'linebreak',
        'sales',
        'linebreak',
        'expenses',
        'linebreak',
        'createdAt',
        'linebreak',
        'processedBy',
        'poweredBy',
        'linebreak',
    ];

    private $resume = [];
    private $incoming = [];
    private $outgoing = [];
    private $movements = [];
    private $documents = [];

    private $totalIncoming = 0;
    private $totalOutgoing = 0;

    private $totalDocuments = 0;
    private $totalMovements = 0;

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
        $this->builder->textStyle();
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

        $table->addCell($this->labels->balance, ['condensed' => true, 'emphasized' => true]);
        $table->addCell(Tools::priceFormat(($this->cashflow['value'] + $this->totalIncoming - $this->totalOutgoing), $this->currency), ['condensed' => true, 'alignment' => 'RIGHT']);

        $table->drawTable();
        $this->linebreak();
    }

    public function movements()
    {
        if (!empty($this->movements) && is_array($this->movements)) {
            $this->builder->addTittle($this->labels->cashflow);
            $this->linebreak();

            $table = new Table($this->builder, $this->printer);
            $table->addColumns([null, 20]);

            $table->addLineSplit();

            foreach ($this->movements as $movement) {
                $table->addCell($movement['name'], ['condensed' => true, 'emphasized' => true]);
                $table->addCell(Tools::priceFormat($movement['value'], $this->currency), ['alignment' => 'RIGHT', 'condensed' => true]);
                $table->newRow();
                $table->addCell(Tools::dateFormat($movement['date'], 'd-m-Y H:i:s'), ['condensed' => true]);
                $table->newRow();
            }

            $table->addLineSplit();

            $table->addCell($this->labels->total, ['condensed' => true, 'emphasized' => true]);
            $table->addCell(Tools::priceFormat($this->totalMovements), ['condensed' => true, 'emphasized' => true, 'alignment' => 'RIGHT']);

            $table->drawTable();
            $this->linebreak();
        }
    }

    public function documents()
    {
        if (!empty($this->documents) && is_array($this->documents)) {
            $this->builder->addTittle($this->labels->documents);
            $this->linebreak();

            $table = new Table($this->builder, $this->printer);
            $table->addColumns([null, 20]);

            $table->addLineSplit();

            foreach ($this->documents as $document) {
                $table->addCell($document['name'], ['condensed' => true, 'emphasized' => true]);
                $table->addCell(Tools::priceFormat($document['value'], $this->currency), ['alignment' => 'RIGHT', 'condensed' => true]);
                $table->newRow();
                $table->addCell(Tools::dateFormat($document['date'], 'd-m-Y H:i:s'), ['condensed' => true]);
                $table->newRow();
            }

            $table->addLineSplit();

            $table->addCell($this->labels->total, ['condensed' => true, 'emphasized' => true]);
            $table->addCell(Tools::priceFormat($this->totalDocuments), ['condensed' => true, 'emphasized' => true, 'alignment' => 'RIGHT']);

            $table->drawTable();
            $this->linebreak();
        }
    }

    public function sales()
    {
        if (!empty($this->incoming) && is_array($this->incoming)) {
            $this->builder->addTittle($this->labels->sales);
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
            $this->builder->addTittle($this->labels->outflow . '/' . $this->labels->expenses);
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
        $this->movements[] = $this->parseMovement($this->cashflow);
        if (!empty($this->cashflow['associated_movements']) && is_array($this->cashflow['associated_movements'])) {
            foreach ($this->cashflow['associated_movements'] as $movement) {

                $parseForDirectionTotal = $movement['type_id'] == 2 ? 'totalIncoming' : 'totalOutgoing';
                $parseForDirection = $movement['type_id'] == 2 ? 'incoming' : 'outgoing';

                if ($movement['document_id'] > 0 && !empty($movement['document'])) {
                    $this->documents[] = $this->parseDocument($movement);
                } else {
                    $this->movements[] = $this->parseMovement($movement);
                }

                $this->{$parseForDirectionTotal} += $movement['value'];
                $totalValue = $movement['value'];
                $totalAssociatedValue = 0;

                if (isset($movement['payments']) && is_array($movement['payments'])) {
                    foreach ($movement['payments'] as $payment) {
                        $this->{$parseForDirection}[$payment['payment_method_id']]['name'] = $payment['payment_method']['name'];
                        $this->{$parseForDirection}[$payment['payment_method_id']]['value'] += $payment['value'];

                        $totalAssociatedValue += $payment['value'];
                    }
                }

                if ($totalValue > $totalAssociatedValue) {
                    $this->{$parseForDirection}[0]['name'] = $this->labels->undifferentiated;
                    $this->{$parseForDirection}[0]['value'] += $totalValue - $totalAssociatedValue;
                }

            }
        }
    }

    private function parseDocument($movement)
    {
        $document = [];
        $document['name'] =
            $this->labels->document_types[$movement['document']['document_type_id']] . ' ' .
            $movement['document']['document_set_name'] . "/" .
            $movement['document']['number'];
        $document['date'] = $movement['date'];
        $document['value'] = $movement['value'];

        if ($movement['type_id'] == 1 || $movement['type_id'] == 2) {
            $this->totalDocuments += $movement['value'];
        } else if ($movement['type_id'] == 3) {
            $this->totalDocuments -= $movement['value'];
        }

        return $document;
    }

    private function parseMovement($movement)
    {
        $document = [];

        $document['name'] = $this->getCashflowTypeString($movement['type_id']);
        $document['date'] = $movement['date'];
        $document['value'] = $movement['value'];

        if ($movement['type_id'] == 1 || $movement['type_id'] == 2) {
            $this->totalMovements += $movement['value'];
        } else if ($movement['type_id'] == 3) {
            $this->totalMovements -= $movement['value'];
        }

        return $document;
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