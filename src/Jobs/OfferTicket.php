<?php
/*
 * Moloni, lda
 *
 * Aviso de propriedade e confidencialidade
 * A distribuição ou reprodução deste documento (codigo) para além dos fins
 * a que o mesmo se destina só é permitida com o consentimento por escrito
 * por parte da Moloni.
 *
 * PHP version 5
 *
 * @category  BO-CATEGORIA
 * @package   BackOffice
 * @author    Nuno Almeida <nuno@moloni.com>
 * @copyright 2020 Moloni, lda
 * @license   Moloni, lda
 * @link      https://www.moloni.pt
 */

namespace MoloniPrint\Jobs;

use MoloniPrint\Job;
use MoloniPrint\Table\Table;

/**
 * Class OfferTicket
 *
 * @package MoloniPrint\Jobs
 */
class OfferTicket extends Document
{

    /**
     * @var array schema
     */
    protected $offerTicketSchema = [
        'image',
        'header',
        'documentDetails',
        'entity',
        'linebreak',
        'products',
        'linebreak',
        'notes',
        'linebreak',
        'documentFooter',
        'linebreak',
        'processedBy',
        'poweredBy',
        'linebreak',
    ];

    /**
     * Document constructor.
     *
     * @param Job $job current job
     */
    public function __construct(Job $job)
    {
        parent::__construct($job);
    }

    /**
     * Start by setting class variables and parsing entities and products
     * Array with products to be printed in the document
     * Format: $product => [product_id => 5, qty => 1]
     *
     * @param array $document document
     * @param array $products products
     *
     * @return array json format of print job
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
     *
     * @return void
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
     *
     * @return void
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
     *
     * @return void
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
     *
     * @return void
     */
    public function documentDate()
    {
        try {
            $closingHours = new \DateTime($this->document['lastmodified']);
            $closingDate = new \DateTime($this->document['date']);

            $dateFormatted = $closingDate->format('d-m-Y') . ' ' . $closingHours->format('H:i:s');
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

    /**
     * Add info about entities both customers or suppliers
     *
     * @return void
     */
    public function entity()
    {
        $this->entityName();
        $this->entityVat();
        $this->entityAddress();
    }

    /**
     * Entity Name
     *
     * @return void
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
     *
     * @return void
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
     *
     * @return void
     */
    public function entityAddress()
    {
        if ($this->hasAddress && !$this->isFinalConsumer) {
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

    /**
     * Add info about products in a document
     *
     * @return void
     */
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
            $this->drawProduct($table, $productIndex, $product);
        }

        $table->addLineSplit();
        $table->drawTable();
        $this->linebreak();
    }

    /**
     * Draws a single product
     *
     * @param Table $table table
     * @param int $productIndex current index on products
     * @param array $product array with product information
     *
     * @return void
     */
    public function drawProduct(Table $table, $productIndex, $product)
    {
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

    /**
     * Draws document footer
     *
     * @return void
     */
    public function documentFooter()
    {
        if (isset($this->terminal['footer_gift']) && !empty($this->terminal['footer_gift'])) {
            $this->builder->text($this->terminal['footer_gift']);
            $this->linebreak();
        }
    }

    /**
     * Draws notes
     *
     * @return void
     */
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

    /**
     * Draws processed by section
     *
     * @return void
     */
    public function processedBy()
    {
        $this->builder->textFont('C');
        $this->builder->textDouble();
        $this->builder->textStyle(false, false, true);
        $this->builder->textAlign('CENTER');
        $this->builder->text($this->labels->created_by_v2);
        $this->linebreak();
    }

}