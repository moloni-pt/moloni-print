<?php

namespace MoloniPrint\Jobs;

use DateTime;
use MoloniPrint\Job;
use MoloniPrint\Table\Table;

class OrderTicket extends Document
{
    protected $order;
    protected $offerTicketSchema = [
        'orderHeader',
        'linebreak',
        'orderedProducts',
        'linebreak',
        'orderFooter',
        'linebreak',
        'processedBy',
        'linebreak',
        'linebreak',
        'linebreak'
    ];

    /**
     * Document constructor.
     * @param Job $job
     */
    public function __construct(Job $job)
    {
        parent::__construct($job);
    }

    public function create(Array $order)
    {
        $this->order = $order;

        $this->drawFromScheme($this->offerTicketSchema);
        $this->finish();

        return $this->builder->getPrintJob('json');
    }

    public function orderHeader()
    {
        $text= '';

        if ($this->printer->alias !== '') {
            $this->builder->textDouble();
            $this->builder->textStyle();
            $this->builder->textAlign('CENTER');
            $this->linebreak();
            $this->builder->text($this->printer->alias);
            $this->linebreak();
        }

        $this->builder->textFont();
        $this->builder->textDouble(true, true);
        $this->builder->textAlign('CENTER');
        $this->builder->textStyle(false, false, true);
        $this->builder->text($this->labels->order_detail);
        $this->linebreak();
        $this->linebreak();

        $this->builder->textDouble(true, true);
        $this->builder->textAlign('CENTER');
        $this->builder->textStyle(false, false, true);
        $this->builder->text((string)$this->order['number']);
        $this->linebreak();
        $this->linebreak();

        $this->builder->textDouble();
        $this->builder->textStyle();
        $this->builder->textAlign();

        $date = new DateTime($this->order['date_begin']);
        $dateFormatted = $date->format('d-m-Y H:i:s');

        $this->builder->text($this->labels->date . ': ' . $dateFormatted);
        $this->linebreak();

        switch ((int)$this->order['type_id']) {
            case 1:
                $text = $this->labels->restaurant;
                break;
            case 2:
                $text = $this->labels->take_away;
                break;
            case 3:
                $text = $this->labels->home_delivery;
                break;
        }

        $this->builder->text($this->labels->type . ': ' . $text);
        $this->linebreak();

        if (!empty($this->order['area']['name'])) {
            $this->builder->text($this->labels->area . ': ' . $this->order['area']['name'] );
            $this->linebreak();
        }

        if (!empty($this->order['table']['name'])) {
            $this->builder->text($this->labels->table . ': ' . $this->order['table']['name'] . ' #' . $this->order['table']['reference']);
            $this->linebreak();
        }


        $this->linebreak();

        if (!empty($this->order['terminal']['name'])) {
            $this->builder->text($this->labels->terminal . ': ' . $this->order['terminal']['name']);
            $this->linebreak();
        }

        if (!empty($this->order['operator']['name'])) {
            $this->builder->text($this->labels->operator . ': ' . $this->order['operator']['name']);
            $this->linebreak();
        }


        switch ((int)$this->order['status']) {
            case 0:
                $text = $this->labels->waiting;
                break;
            case 1:
                $text = $this->labels->in_preparation;
                break;
            case 2:
                $text = $this->labels->ready;
                break;
            case 3:
                $text = $this->labels->closed;
                break;
            case 4:
                $text = $this->labels->cancelled;
                break;
        }

        $this->builder->text($this->labels->status . ': ' . $text);
        $this->linebreak();

        if ($this->order['isPaid'] === true) {
            $text = $this->labels->yes;
        } else {
            $text = $this->labels->no;
        }

        $this->builder->text($this->labels->order_paid . ': ' . $text);
    }

    public function orderedProducts()
    {
        $lastCategory = 0;
        $sizeColumnQty = 6;
        $sizeColumnX = 6;

        if ($this->order['products'] && count($this->order['products']) > 0) {
            $table = new Table($this->builder, $this->printer);

            foreach ($this->order['products'] as $product) {
                $auxLength = strlen($product['qty']);

                if ($auxLength > $sizeColumnQty) {
                    $sizeColumnQty = $auxLength + 4;
                    $sizeColumnX = $sizeColumnQty - $auxLength;
                    $sizeColumnX = $sizeColumnX > 0 ? $sizeColumnX : 6;
                }
            }

            $table->addColumn($sizeColumnQty);
            $table->addColumn($sizeColumnX);
            $table->addColumn();

            if (!$this->order['groupByCategory']) {
                $table->addCell('');
                $table->newRow();
                $table->addCell($this->labels->products, ['emphasized' => true]);
                $table->addLineSplit();
            }

            foreach ($this->order['products'] as $product) {
                if ($this->order['groupByCategory'] && (int)$product['product']['category_id'] !== $lastCategory) {
                    $table->addCell('');
                    $table->newRow();
                    $table->addCell($product['product']['category']['name'], ['emphasized' => true]);
                    $table->addLineSplit();

                    $lastCategory = (int)$product['product']['category_id'];
                }

                $table->addCell($product['qty']);
                $table->addCell('x');
                $table->addCell($product['product']['name']);

                if (!empty($product['notes'])) {
                    $table->addCell('');
                    $table->addCell('obs:', ['emphasized' => true]);
                    $table->addCell($product['notes']);
                }
            }

            $table->drawTable();
        }
    }

    public function orderFooter()
    {
        if (in_array((int)$this->order['type_id'], [2,3])) {
            $text = '';
            $hasData = false;

            $this->builder->textStyle(false, false, true);
            $this->builder->textFont();
            $this->builder->textDouble();

            switch ((int)$this->order['type_id']) {
                case 2:
                    $text = $this->labels->take_away;
                    break;
                case 3:
                    $text = $this->labels->home_delivery;
                    break;
            }

            $this->builder->text($text);
            $this->linebreak();
            $this->drawLine();

            $this->builder->textFont();
            $this->builder->textStyle(false, false, false);

            if (!empty($this->order['customer_name'])) {
                $this->builder->text($this->labels->name . ': ' . $this->order['customer_name']);
                $this->linebreak();

                $hasData = true;
            }

            if (!empty($this->order['customer_phone'])) {
                $this->builder->text($this->labels->contact . ': ' . $this->order['customer_phone']);
                $this->linebreak();

                $hasData = true;
            }

            if (!empty($this->order['date_delivery'])) {
                $date = new DateTime($this->order['date_delivery']);
                $dateFormatted = $date->format('d-m-Y H:i:s');

                $this->builder->text($this->labels->delivery_time . ': ' . $dateFormatted);
                $this->linebreak();

                $hasData = true;
            }


            if ((int)$this->order['type_id'] === 3 && !empty($this->order['customer_address'])) {
                $this->builder->text($this->labels->address . ': ' . $this->order['customer_address']);
                $this->linebreak();

                $hasData = true;
            }

            if (!empty($this->order['notes'])) {
                $this->builder->text($this->labels->obs . ': ' . $this->order['notes']);
                $this->linebreak();

                $hasData = true;
            }

            if ($hasData === false) {
                $this->builder->text($this->labels->missing_information);
                $this->linebreak();
            }
        }
    }

    public function processedBy()
    {
        $this->builder->textDouble();
        $this->builder->textStyle(false, false, true);
        $this->builder->textAlign('CENTER');
        $this->builder->text($this->labels->created_by_moloni);
    }

}