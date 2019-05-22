<?php

namespace MoloniPrint\Jobs;

use MoloniPrint\Job;
use MoloniPrint\Table\Table;

class OfferTicket extends Document
{

    /**
     * Document constructor.
     * @param Job $job
     */
    public function __construct(Job &$job)
    {
        parent::__construct($job);
    }

    /**
     * Start by setting class variables and parsing entities and products
     * @param array $document
     * @param array $products
     * Array with products to be printed in the document
     * Format: $product => [product_id => 5, qty => 1]
     * @return array|string
     */
    public function create(Array $document, $products = [])
    {
        $this->document = $document;
        $this->productsToPrint = is_array($products) ? $products : [];

        $this->parseEntities();
        $this->parseProducts();

        $this->drawFromScheme($this->offerTicketSchema);
        $this->finish();

        return $this->builder->getPrintJob('json');
    }

    /**
     * Draw block of document details and document identification
     */
    public function documentDetails()
    {
        $this->documentIdentification();
        $this->documentTerminal();
        $this->documentDate();
    }

    /**
     * Document identifications
     * Document Type
     * Document Set Name / Document Number
     */
    public function documentIdentification()
    {
        $this->builder->textFont('C');
        $this->builder->textAlign('RIGHT');
        $this->builder->textDouble(true, true);
        $this->builder->textStyle(false, false, true);
        $this->builder->text($this->labels->offer_ticket . ' ' . $this->document['document_set_name'] . '/' . $this->document['number']);
        $this->linebreak();
    }

    /**
     * Info about the terminal
     * Terminal Name
     * Operator Name
     * Salesman Name
     */
    public function documentTerminal()
    {
        $this->builder->textFont('A');
        $this->builder->textAlign('RIGHT');
        $this->builder->textDouble();
        $this->builder->textStyle();
        $this->builder->text($this->labels->terminal . ': ' . $this->terminal['name']);
        $this->linebreak();
        $this->builder->text($this->labels->operator . ': ' . $this->document['lastmodifiedby']);
        $this->linebreak();

        if (isset($this->document['salesman_id']) && $this->document['salesman_id'] > 0) {
            $this->builder->text($this->labels->salesman . ': ' . $this->document['salesman']['name']);
            $this->linebreak();
        }
    }

    /**
     * Show document last modified date
     */
    public function documentDate()
    {
        try {
            $date = new \DateTime($this->document['lastmodified']);
            $dateFormatted = $date->format("d-m-Y H:i");
        } catch (\Exception $exception) {
            $dateFormatted = $this->document['lastmodified'];
        }

        $this->builder->textFont('A');
        $this->builder->textAlign('RIGHT');
        $this->builder->textDouble();
        $this->builder->textStyle(false, false, true);
        $this->builder->text($this->labels->date . ': ' . $dateFormatted);
        $this->linebreak();
    }


    /*****************************************************
     * Add info about entities both customers or suppliers
     *****************************************************/
    public function entity()
    {
        $this->entityName();
        $this->entityVat();
        $this->entityAddress();
    }

    /**
     * Entity Name
     */
    public function entityName()
    {
        $this->builder->textFont('A');
        $this->builder->textAlign('LEFT');
        $this->builder->textStyle(false, false, true);
        $this->builder->textDouble();
        if (isset($this->document['entity_name']) && !empty($this->document['entity_name'])) {
            $this->builder->text($this->isFinalConsumer ? 'Cliente' : $this->document['entity_name']);
            $this->linebreak();
        }
    }

    /**
     * Entity VAT number
     */
    public function entityVat()
    {
        if (isset($this->document['entity_vat']) && !empty($this->document['entity_vat'])) {
            $this->builder->textFont('A');
            $this->builder->textAlign('LEFT');
            $this->builder->textStyle(false, false, true);
            $this->builder->textDouble();
            $this->builder->text($this->labels->vat . ': ');
            $this->builder->text($this->isFinalConsumer ? 'Consumidor Final' : $this->document['entity_vat']);
            $this->linebreak();
        }
    }

    /**
     * Entity address from document
     * Address
     * Zip Code
     * City
     * Country
     */
    public function entityAddress()
    {
        if ($this->hasAddress) {
            $this->builder->textFont('A');
            $this->builder->textAlign('LEFT');
            $this->builder->textStyle();
            $this->builder->textDouble();

            if (isset($this->document['entity_address']) && !empty($this->document['entity_address'])) {
                $this->builder->text($this->document['entity_address']);
                $this->linebreak();
            }

            if (isset($this->document['entity_zip_code']) && !empty($this->document['entity_zip_code'])) {
                $this->builder->text($this->document['entity_zip_code']);

                if (isset($this->document['entity_address']) && !empty($this->document['entity_address'])) {
                    $this->builder->text(' ' . $this->document['entity_city']);
                }

                $this->linebreak();
            }

            if (isset($this->document['entity_country']) && !empty($this->document['entity_country'])) {
                $this->builder->text($this->document['entity_country']);
                $this->linebreak();
            }
        }

    }

    /***************************************
     * Add info about products in a document
     ***************************************/

    public function products()
    {
        $this->builder->addTittle($this->labels->products);
        $this->linebreak();

        $table = new Table($this->builder, $this->printer);

        $table->addColumns(['', 10]);
        $table->addCell($this->labels->designation, ['emphasized' => true, 'condensed' => true]);
        $table->addCell($this->labels->qty, ['emphasized' => true, 'condensed' => true, 'alignment' => 'RIGHT']);
        $table->addLineSplit();

        foreach ($this->products as $productIndex => $product) {
            $table->addCell($this->labels->reference_short . ' ' . $product['reference'], ['condensed' => true]);
            $table->addCell($product['quantity'], ['alignment' => 'RIGHT']);
            $table->newRow();
            $table->addCell($product['name']);
            $table->newRow();

            if (!empty($product['summary'])) {
                $table->addCell($product['summary'], ['condensed' => true]);
                $table->newRow();
            }

            if ($productIndex < count($this->products) - 1) {
                $table->addCells(['', '']);
                $table->newRow();
            }
        }
        $table->addLineSplit();
        $table->drawTable();
        $this->linebreak();
    }

    public function notes()
    {
        $this->builder->textFont('C');
        $this->builder->textDouble();
        $this->builder->textAlign();
        $this->builder->textStyle();

        $this->builder->text($this->labels->document_created_at . ' ' . date('Y-m-d H:i'));
        $this->linebreak();

        if (!empty($this->document['notes'])) {
            $this->builder->textStyle();
            $this->builder->text($this->document['notes']);
            $this->linebreak();
        }
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

}