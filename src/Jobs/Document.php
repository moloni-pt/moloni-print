<?php

namespace MoloniPrint\Jobs;

use DateTime;
use Exception;
use MoloniPrint\Job;
use MoloniPrint\Table\Table;
use MoloniPrint\Utils\Tools;

class Document extends Common
{

    protected $ultraSmallWidth = 34;
    protected $documentSchema = [
        'image',
        'header' => [
            'companyName',
            'companyAddress',
            'companyContacts',
            'companySocialCapital'
        ],
        'linebreak',
        'documentDetails',
        'linebreak',
        'entity',
        'products',
        'taxes',
        'mbReferences',
        'payments',
        'relatedDocuments',
        'shippingDetails',
        'notes',
        'footer',
        'documentFooter',
        'productsCounter',
        'productsWithQuantityCounter',
        'linebreak',
        'productsAvailabilityNote',
        'linebreak',
        'processedBy',
        'poweredBy',
        'linebreak',
    ];

    protected $taxes = [];
    protected $exemptions = [];
    protected $deductions = [];
    protected $products = [];
    protected $productsCount = 0;
    protected $productsWithQuantityCount = 0;
    protected $productsToPrint = [];

    protected $currentCopy = 1;

    /**
     * @var float
     */
    protected $exchangeRate = 1;

    /**
     * Document constructor.
     * @param Job $job
     */
    public function __construct(Job $job)
    {
        parent::__construct($job);
    }

    /**
     * Start by setting class variables and parsing entities and products
     * @param array $document
     * @return array|string
     */
    public function create(Array $document)
    {
        $this->document = $document;

        $this->currency = isset($this->document['exchange_currency']['symbol']) ?
            $this->document['exchange_currency']['symbol'] :
            $this->company['currency']['symbol'];

        if (isset($this->document['exchange_rate']) && !empty((float)$this->document['exchange_rate'])) {
            Tools::$currency = $this->currency;
            Tools::$exchangeRate = $this->document['exchange_rate'];
            Tools::$symbolRight = $this->document['exchange_currency']['symbol_right'];
        }


        $this->parseEntities();
        $this->parseProducts();

        $this->parseCopies();

        $this->drawFromScheme($this->documentSchema);
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
        $this->documentOurReference();
        $this->documentYourReference();
        $this->documentDate();
        $this->documentTableIdentification();
        $this->documentShippingCode();
    }

    /**
     * Document identifications
     * Original or segunda via
     * Documento Type
     * Document Set Name / Document Number
     */
    public function documentIdentification()
    {
        $this->builder->textStyle(false, false, false);
        $this->builder->textFont('C');

        /* if (isset($this->document['second_way']) && $this->document['second_way']) {
          $this->builder->text($this->labels->second_way);
          } else {
          $this->builder->text($this->labels->original);
          }a
         */

        if ($this->printer->copies > 1) {
            $this->builder->variable('#CopyNumber#');
        } else {
            $this->builder->text($this->labels->original);
        }

        $this->linebreak();

        $this->builder->textStyle(false, false, true);
        $this->builder->textFont('A');

        $documentName = $this->labels->document_types[$this->document['document_type_id']];
        $this->builder->text($documentName . ' ' . $this->document['document_set']['name'] . '/' . $this->document['number']);
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
        $this->builder->textStyle(false, false, false);
        if (isset($this->terminal['name'])) {
            $this->builder->text($this->labels->terminal . ': ' . $this->terminal['name']);
            $this->linebreak();
        }
        $this->builder->text($this->labels->operator . ': ' . $this->document['lastmodifiedby']);
        $this->linebreak();

        if (isset($this->document['salesman_id']) && $this->document['salesman_id'] > 0) {
            $this->builder->text($this->labels->salesman . ': ' . $this->document['salesman']['name']);
            $this->linebreak();
        }

        if (isset($this->document['exchange_currency']['iso4217'])) {
            $this->builder->text($this->labels->coin . ': ' . $this->document['exchange_currency']['iso4217']);
            $this->linebreak();
        }
    }

    /**
     * Show our reference
     */
    public function documentOurReference()
    {
        if (!empty($this->document['our_reference'])) {
            $this->builder->textStyle(false, false, false);
            $this->builder->text($this->labels->our_reference . ': ' . $this->document['our_reference']);
            $this->linebreak();
        }
    }

    /**
     * Show your reference
     */
    public function documentYourReference()
    {
        if (!empty($this->document['our_reference'])) {
            $this->builder->textStyle(false, false, false);
            $this->builder->text($this->labels->your_reference . ': ' . $this->document['your_reference']);
            $this->linebreak();
        }
    }

    /**
     * Show document last modified date
     */
    public function documentDate()
    {
        try {
            $closingHours = new DateTime($this->document['lastmodified']);
            $closingDate = new DateTime($this->document['date']);

            $dateFormatted = $closingDate->format('d-m-Y') . ' ' . $closingHours->format('H:i');
        } catch (Exception $exception) {
            $dateFormatted = $this->document['date'];
        }

        $this->builder->textStyle(false, false, true);
        $this->builder->text($this->labels->date . ': ' . $dateFormatted);
        $this->linebreak();
    }

    /**
     * Show document Shipping Code from AT
     */
    public function documentShippingCode()
    {
        if (isset($this->document['transport_code']) && !empty($this->document['transport_code'])) {
            $this->builder->textStyle(false, false, false);
            $this->builder->text($this->labels->at_code . ': ' . $this->document['transport_code']);
            $this->linebreak();
        }
    }

    /**
     * @todo Add table information from another table
     * Area Name
     * Table Name
     */
    public function documentTableIdentification()
    {

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
        $this->builder->textStyle(false, false, true);
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
        $this->builder->textStyle(false, false, true);
        if (isset($this->document['entity_vat']) && !empty($this->document['entity_vat'])) {
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
            $this->builder->textStyle(false, false, false);

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
        $this->drawProductsFull();
    }

    public function taxes()
    {
        if (!empty($this->taxes) || !empty($this->exemptions)) {
            $this->linebreak();
            $this->builder->addTittle($this->labels->taxes);
            $this->linebreak();

            $table = new Table($this->builder, $this->printer);
            $table->addColumns([null, 7, 12, 12]);

            $headerStyle = ['emphasized' => true, 'condensed' => true, 'alignment' => 'RIGHT'];
            $table->addCells(['', $this->labels->value, $this->labels->incidence, $this->labels->total], $headerStyle);

            $table->addLineSplit();

            foreach ($this->taxes as $name => $tax) {
                $table->newRow();
                $table->addCell($name, ['condensed' => true]);

                if ($this->printer->condensedWidth <= $this->ultraSmallWidth) {
                    $table->newRow();
                    $table->addCell('');
                }

                $table->addCell(Tools::priceFormat($tax['value'], '%'), ['condensed' => true, 'alignment' => 'RIGHT']);
                $table->addCell(Tools::priceFormat($tax['incidence'], $this->currency), ['condensed' => true, 'alignment' => 'RIGHT']);
                $table->addCell(Tools::priceFormat($tax['total'], $this->currency), ['condensed' => true, 'alignment' => 'RIGHT']);
            }

            foreach ($this->exemptions as $exemption) {
                $table->newRow();
                $table->addCell($exemption['description'], ['condensed' => true]);

                if ($this->printer->condensedWidth <= $this->ultraSmallWidth) {
                    $table->newRow();
                    $table->addCell('');
                }

                $table->addCell(Tools::priceFormat($exemption['total'], '%'), ['condensed' => true, 'alignment' => 'RIGHT']);
                $table->addCell(Tools::priceFormat($exemption['incidence'], $this->currency), ['condensed' => true, 'alignment' => 'RIGHT']);
                $table->addCell(Tools::priceFormat($exemption['total'], $this->currency), ['condensed' => true, 'alignment' => 'RIGHT']);
            }

            $table->drawTable();
            $this->linebreak();
        }
    }

    public function mbReferences()
    {
        if (!empty($this->document['mb_references'])) {
            $this->linebreak();
            $this->builder->addTittle($this->labels->mb_references);
            $this->linebreak();
            $table = new Table($this->builder, $this->printer);
            $table->addColumns([null, 14, 12]);

            $table->addCell($this->labels->entity_short, ['emphasized' => true, 'condensed' => true]);
            $table->addCell($this->labels->reference_short, ['emphasized' => true, 'condensed' => true]);
            $table->addCell($this->labels->value, ['emphasized' => true, 'condensed' => true, 'alignment' => 'RIGHT']);
            $table->addLineSplit();

            foreach ($this->document['mb_references'] as $reference) {
                $table->newRow();

                $fullReference = str_pad($reference['sub_entity'], 3, '0', STR_PAD_LEFT);
                $fullReference .= str_pad($reference['reference'], 4, '0', STR_PAD_LEFT);
                $fullReference .= str_pad($reference['check_digits'], 2, '0', STR_PAD_LEFT);
                $fullReference = chunk_split($fullReference, 3, ' ');
                $table->addCells([$reference['entity'], $fullReference], ['condensed' => true]);
                $table->addCell(Tools::priceFormat($reference['value']), ['condensed' => true, 'alignment' => 'RIGHT']);
            }

            $table->drawTable();
            $this->linebreak();
        }
    }

    public function payments()
    {
        if (!empty($this->document['payments'])) {
            $this->linebreak();
            $this->builder->addTittle($this->labels->payments);
            $this->linebreak();

            $table = new Table($this->builder, $this->printer);
            $table->addColumns([null, 15, 15]);

            $headerStyle = ['emphasized' => true, 'condensed' => true, 'alignment' => 'RIGHT'];
            $table->addCells(['', $this->labels->date, $this->labels->total], $headerStyle);
            $table->addLineSplit();

            foreach ($this->document['payments'] as $payment) {
                $table->newRow();
                $table->addCell($payment['payment_method_name'], ['condensed' => true]);

                if ($this->printer->condensedWidth <= $this->ultraSmallWidth) {
                    $table->newRow();
                    $table->addCell('');
                }

                $table->addCell(Tools::dateFormat($payment['date'], 'd-m-Y'), ['condensed' => true, 'alignment' => 'RIGHT']);
                $table->addCell(Tools::priceFormat($payment['value'], $this->currency), ['condensed' => true, 'alignment' => 'RIGHT']);
                if (!empty($payment['notes'])) {
                    $table->newRow();
                    $table->addCell($this->labels->obs_short . ': ' . $payment['notes'], ['condensed' => true]);
                }
            }

            $table->drawTable();
            $this->linebreak();
        }
    }

    public function relatedDocuments()
    {
        if (!empty($this->document['associated_documents']) && (int)$this->company['docs_show_related'] === 1) {
            $this->linebreak();
            $this->builder->addTittle($this->labels->associated_documents);
            $this->linebreak();

            $table = new Table($this->builder, $this->printer);
            $table->addColumns([null, 10, 11, 11]);

            $headerStyle = ['emphasized' => true, 'condensed' => true, 'alignment' => 'RIGHT'];
            $table->addCells(['', $this->labels->date, $this->labels->value, 'Conciliado'], $headerStyle);
            $table->addLineSplit();

            foreach ($this->document['associated_documents'] as $document) {
                try {
                    $date = new DateTime($document['associated_document']['date']);
                    $dateFormatted = $date->format('d-m-Y');
                } catch (Exception $exception) {
                    $dateFormatted = $document['associated_document']['date'];
                }

                $documentName = $this->labels->document_types[$document['associated_document']['document_type_id']];
                $table->addCell($documentName . ' ' . $document['associated_document']['document_set_name'] . '/' . $document['associated_document']['number'], ['condensed' => true]);

                if ($this->printer->condensedWidth < 60) {
                    $table->newRow();
                    $table->addCell('');
                }

                $table->addCell($dateFormatted, ['condensed' => true, 'alignment' => 'RIGHT']);

                /* Hide the value from a global guide */
                if((int)$document['associated_document']['global_guide'] === 1) {
                    $table->addCell('', ['condensed' => true, 'alignment' => 'RIGHT']);
                } else {
                    $table->addCell(Tools::priceFormat($document['associated_document']['net_value'], $this->currency), ['condensed' => true, 'alignment' => 'RIGHT']);
                }


                $table->addCell(Tools::priceFormat($document['value'], $this->currency), ['condensed' => true, 'alignment' => 'RIGHT']);
            }

            $table->drawTable();
            $this->linebreak();
        }
    }

    public function shippingDetails()
    {
        if (!empty($this->document['delivery_datetime']) ||
            !empty($this->document['delivery_method_name']) ||
            !empty($this->document['delivery_departure_address']) ||
            !empty($this->document['delivery_departure_city']) ||
            !empty($this->document['delivery_departure_zip_code']) ||
            !empty($this->document['delivery_departure_country']) ||
            !empty($this->document['delivery_destination_address']) ||
            !empty($this->document['delivery_destination_city']) ||
            !empty($this->document['delivery_destination_zip_code']) ||
            !empty($this->document['delivery_destination_country']) ||
            !empty($this->document['vehicle_name']) ||
            !empty($this->document['vehicle_number_plate'])
        ) {
            $this->linebreak();
            $this->builder->addTittle($this->labels->shipping);
            $this->linebreak();

            $this->builder->resetStyle();
            $this->builder->textFont('C');

            if (!empty($this->document['delivery_method_name'])) {
                $this->linebreak();
                $this->builder->textStyle(false, false, true);
                $this->builder->text($this->labels->shippingMethod . ': ');
                $this->builder->textStyle(false, false, false);
                $this->builder->text($this->document['delivery_method_name']);
            }

            if (!empty($this->document['delivery_datetime'])) {
                $this->linebreak();
                $this->builder->textStyle(false, false, true);
                $this->builder->text($this->labels->beginning . ': ');
                $this->builder->textStyle(false, false, false);
                $this->builder->text(Tools::dateFormat($this->document['delivery_datetime']));
            }

            if (!empty($this->document['delivery_departure_address']) ||
                !empty($this->document['delivery_departure_city']) ||
                !empty($this->document['delivery_departure_zip_code']) ||
                !empty($this->document['delivery_departure_country_details']['name'])) {
                $this->linebreak();
                $this->builder->textStyle(false, false, true);
                $this->builder->text($this->labels->departure_place . ':');
                $this->builder->textStyle(false, false, false);
                $this->builder->text(' ' . $this->document['delivery_departure_address']);
                $this->builder->text(', ' . $this->document['delivery_departure_zip_code']);
                $this->builder->text(' ' . $this->document['delivery_departure_city']);
                $this->builder->text(', ' . $this->document['delivery_departure_country_details']['name']);
            }

            if (!empty($this->document['delivery_destination_address']) ||
                !empty($this->document['delivery_destination_city']) ||
                !empty($this->document['delivery_destination_zip_code']) ||
                !empty($this->document['delivery_destination_country'])) {
                $this->linebreak();
                $this->builder->textStyle(false, false, true);
                $this->builder->text($this->labels->destination_place . ':');
                $this->builder->textStyle(false, false, false);
                $this->builder->text(' ' . $this->document['delivery_destination_address']);
                $this->builder->text(', ' . $this->document['delivery_destination_zip_code']);
                $this->builder->text(' ' . $this->document['delivery_destination_city']);
                $this->builder->text(', ' . $this->document['delivery_destination_country_details']['name']);
            }

            if (!empty($this->document['vehicle_name'])) {
                $this->linebreak();
                $this->builder->textStyle(false, false, true);
                $this->builder->text($this->labels->vehicle . ':');
                $this->builder->textStyle(false, false, false);
                $this->builder->text(' ' . $this->document['vehicle_name']);
                if (!empty($this->document['vehicle_name'])) {
                    $this->builder->text(' (' . $this->document['vehicle_number_plate'] . ')');
                }
            }

            $this->linebreak();
        }
    }

    public function productsCounter()
    {
        if ($this->terminal['print_qty_of_products_rows']) {
            $this->builder->textFont('C');
            $this->builder->textDouble();
            $this->builder->textStyle();
            $this->builder->text($this->labels->products_lines . ': ' . $this->productsCount);
            $this->linebreak();
        }
    }

    public function productsWithQuantityCounter()
    {
        if ($this->terminal['print_qty_of_products']) {
            $this->builder->textFont('C');
            $this->builder->textDouble();
            $this->builder->textStyle();
            $this->builder->text($this->labels->products_qty_short . ': ' . $this->productsWithQuantityCount);
            $this->linebreak();
        }
    }

    public function productsAvailabilityNote()
    {
        try {
            $date = new DateTime($this->document['date']);
            $dateFormatted = $date->format('d-m-Y');
        } catch (Exception $exception) {
            $dateFormatted = $this->document['lastmodified'];
        }

        $this->builder->textFont('C');
        $this->builder->textDouble();
        $this->builder->textStyle();
        $this->builder->text($this->labels->products_availability_note . ' ' . $dateFormatted);
        $this->linebreak();
    }

    public function processedBy()
    {
        $this->builder->textFont('C');
        $this->builder->textDouble();
        $this->builder->textStyle(false, false, true);
        $this->builder->textAlign('CENTER');
        if (!empty($this->document['rsa_hash'])) {
            $hash = $this->document['rsa_hash'][0];
            $hash .= $this->document['rsa_hash'][10];
            $hash .= $this->document['rsa_hash'][20];
            $hash .= $this->document['rsa_hash'][30];
            $this->builder->text($hash . ' - ');
        }
        $this->builder->text($this->labels->processed_by);
        $this->linebreak();
    }

    public function notes()
    {
        $this->linebreak();
        // Credit notes require a customer signature
        if ($this->document['document_type']['saft_code'] === 'NC') {
            $this->signature();
        }

        if (!empty($this->document['notes'])) {
            $this->builder->textFont('A');
            $this->builder->textDouble();
            $this->builder->textAlign();
            $this->builder->textStyle();

            $this->builder->text($this->document['notes']);
            $this->linebreak();
        }
    }

    public function footer()
    {
        if (isset($this->document['document_set']['template']['documents_footnote'])) {
            $footer = trim(strip_tags($this->document['document_set']['template']['documents_footnote']));
        } else {
            $footer = trim(strip_tags($this->company['docs_footer']));
        }


        if (!empty($footer)) {
            $this->builder->textFont('A');
            $this->builder->textDouble();
            $this->builder->textAlign();
            $this->builder->textStyle();

            $this->builder->text($footer);
            $this->linebreak();
        }
    }

    public function documentFooter()
    {
        // If the document is a FT, FS or FR print the terminal message
        if (!empty($this->terminal['document_settings']) && is_array($this->terminal['document_settings'])) {
            foreach ($this->terminal['document_settings'] as $documentSetting) {
                if ($documentSetting['document_type_id'] == $this->document['document_type_id'] && !empty($documentSetting['footer'])) {
                    $this->linebreak();
                    $this->builder->text($documentSetting['footer']);
                    $this->linebreak();
                }
            }
        }
    }

    /******************************************
     * Methods to draw a full list of products
     * If we want to add more kind of lists
     ******************************************/
    public function drawProductsFull()
    {
        if (is_array($this->products) && !empty($this->products)) {
            $this->linebreak();
            $this->builder->textFont('C');
            $this->builder->textDouble(true, true);
            $this->builder->text($this->labels->products);
            $this->linebreak();

            $table = new Table($this->builder, $this->printer);
            $this->drawProductsFullHeader($table);

            $table->addLineSplit();
            foreach ($this->products as $product) {
                $this->drawProductsFullLine($table, $product);
            }
            $table->addLineSplit();

            $table->drawTable();
            $this->drawProductsFullResume();
        } else {
            $this->builder->textFont('C');
            $this->builder->textDouble(true, true);
            $this->builder->textAlign('RIGHT');
            $this->builder->text($this->labels->total . ': ' . Tools::priceFormat($this->document['net_value']));
            $this->builder->textDouble();
            $this->builder->textAlign();
            $this->linebreak();
        }
    }

    private function drawProductsFullHeader(Table &$table)
    {
        $table->addColumn();
        $table->addColumn(7);

        if ($this->printer->condensedWidth > $this->ultraSmallWidth) {
            $table->addColumn(14);
        }

        if ($this->printer->condensedWidth > 48 && $this->document['comercial_discount_value'] > 0) {
            $table->addColumn(8);
        }

        if ($this->printer->condensedWidth > 48) {
            $table->addColumn(10);
        } else {
            $table->addColumn(6);
        }

        $table->addColumn(14);

        $headerStyle = [
            'emphasized' => true,
            'condensed' => true,
            'alignment' => 'RIGHT'
        ];

        $table->addCell('', $headerStyle);
        $table->addCell($this->labels->qty, $headerStyle);
        if ($this->printer->condensedWidth > $this->ultraSmallWidth) {
            $table->addCell($this->labels->pvp_unit_short, $headerStyle);
        }

        if ($this->printer->condensedWidth > 48 && $this->document['comercial_discount_value'] > 0) {
            $table->addCell($this->labels->discount_short, $headerStyle);
        }

        $table->addCell($this->labels->iva, $headerStyle);
        $table->addCell($this->labels->total, $headerStyle);
    }

    private function drawProductsFullLine(Table $table, $product)
    {
        $description = $this->terminal['print_products_reference'] ? $product['reference'] . ' ' : '';
        $description .= $product['name'];
        $table->addCell($description);
        $table->newRow();

        if (
            !empty($product['summary']) && (
                (!isset($this->terminal['print_products_summary']) && $this->company['docs_show_products_summary']) ||
                $this->terminal['print_products_summary']
            )) {
            $table->addCell($product['summary'], ['condensed' => true]);
            $table->newRow();

        }

        $bodyStyle = [
            'alignment' => 'RIGHT'
        ];

        $table->addCell('');
        $table->addCell($product['quantity'], $bodyStyle);

        if ($this->printer->condensedWidth > $this->ultraSmallWidth) {
            if ($this->company['docs_show_unit_price_with_taxes'] == 1) {
                $table->addCell($product['unitPriceWithTaxes'], $bodyStyle);
            } else {
                $table->addCell($product['unitPrice'], $bodyStyle);
            }
        }

        if ($this->printer->condensedWidth > 48 && $this->document['comercial_discount_value'] > 0) {
            $table->addCell($product['discount'], $bodyStyle);
        }

        $table->addCell($product['tax'], $bodyStyle);

        if ($this->company['docs_show_values_with_taxes'] == 1) {
            $table->addCell($product['totalPriceWithTaxes'], $bodyStyle);
        } else {
            $table->addCell($product['totalPrice'], $bodyStyle);
        }

    }

    private function drawProductsFullResume()
    {
        $table = new Table($this->builder, $this->printer);
        $table->addColumns([null, 20]);
        $table->addCell($this->labels->gross_total, ['alignment' => 'RIGHT']);
        $table->addCell(Tools::priceFormat($this->document['gross_value']), ['alignment' => 'RIGHT']);

        if ($this->document['financial_discount_value'] > 0) {
            $table->addCell($this->labels->financial_discount, ['alignment' => 'RIGHT']);
            $table->addCell(Tools::priceFormat($this->document['financial_discount_value']), ['alignment' => 'RIGHT']);
        }

        if ($this->document['comercial_discount_value'] > 0) {
            $table->addCell($this->labels->commercial_discount, ['alignment' => 'RIGHT']);
            $table->addCell(Tools::priceFormat($this->document['comercial_discount_value']), ['alignment' => 'RIGHT']);
        }

        if (!empty($this->taxes)) {
            foreach ($this->taxes as $name => $values) {
                $table->addCell($name, ['alignment' => 'RIGHT']);
                $table->addCell(Tools::priceFormat($values['total']), ['alignment' => 'RIGHT']);
            }
        }

        if (!empty($this->deductions)) {
            foreach ($this->deductions as $name => $value) {
                $table->addCell($name, ['alignment' => 'RIGHT']);
                $table->addCell(Tools::priceFormat($value), ['alignment' => 'RIGHT']);
            }
        }

        $table->addCell($this->labels->total, ['alignment' => 'RIGHT', 'emphasized' => true]);
        $table->addCell(Tools::priceFormat($this->document['net_value']), ['alignment' => 'RIGHT', 'emphasized' => true]);

        if (isset($this->document['exchange_currency']) && !empty(isset($this->document['exchange_currency']))) {
            $table->newRow();
            $this->drawProductsResumeExchangeRates($table);
        }

        $table->drawTable();
        $this->linebreak();
    }

    /**
     * @param Table $table
     */
    private function drawProductsResumeExchangeRates(Table $table)
    {
        $table->addCells(['', '']);
        $table->newRow();
        $table->addCell($this->labels->total . ' ' . $this->company['currency']['iso4217'], ['alignment' => 'RIGHT', 'condensed' => true]);
        $totalBaseCurrency = Tools::priceFormat(
            $this->document['net_value'],
            '€',
            2,
            ',',
            '.',
            $this->company['currency']['symbol_right'],
            1
        );
        $table->addCell($totalBaseCurrency, ['alignment' => 'RIGHT', 'condensed' => true]);

        $table->addCell($this->labels->exchange_rate, ['alignment' => 'RIGHT', 'condensed' => true]);
        $exchangeRateString = Tools::priceFormat(
                1,
                '€',
                2,
                ',',
                '.',
                $this->company['currency']['symbol_right'],
                1
            ) . '=' . Tools::priceFormat(1);

        $table->addCell(html_entity_decode($exchangeRateString), ['alignment' => 'RIGHT', 'condensed' => true]);
    }

    protected function parseEntities()
    {
        if ($this->company['country_id'] == 1 &&
            trim($this->document['entity_vat']) == '999999990' &&
            trim($this->document['entity_name']) === 'Consumidor Final') {
            $this->isFinalConsumer = true;
        }

        if ($this->document['entity_country_id'] == 1 &&
            $this->document['entity_address'] == 'Desconhecido' &&
            $this->document['entity_city'] == 'Desconhecido' &&
            $this->document['entity_zip_code'] == '0000-000') {
            $this->hasAddress = false;
        }
    }


    /**
     * Parse products into a object we can use to print
     */
    protected function parseProducts()
    {
        foreach ($this->document['products'] as $raw) {

            if (!empty($this->productsToPrint) && is_array($this->productsToPrint)) {
                $print = false;
                foreach ($this->productsToPrint as $productToPrint) {
                    if ($raw['product_id'] == $productToPrint['product_id']) {
                        if (isset($productToPrint['qty'])) {
                            $raw['qty'] = (int)$productToPrint['qty'];
                        }
                        $print = true;
                    }
                }

                if (!$print) {
                    continue;
                }
            }

            $product = [
                'reference' => $raw['reference'],
                'name' => $raw['name'],
                'summary' => $raw['summary'],
                'discount' => $raw['discount'],
                'unitPrice' => $raw['price'],
                'unitPriceWithTaxes' => $raw['price'],
                'quantity' => $raw['qty']
            ];

            $product['unitPriceWithDiscounts'] = ($raw['price'] * (100 - $raw['discount'])) / 100;

            $product['totalPrice'] = ($product['unitPriceWithDiscounts'] * $raw['qty']);
            $product['totalPriceWithTaxes'] = ($product['unitPriceWithDiscounts'] * $raw['qty']);

            if (isset($raw['taxes']) && !empty($raw['taxes'])) {
                $originalIncidenceValue = $raw['price'];
                foreach ($raw['taxes'] as $tax) {
                    $this->taxes[$tax['name']]['value'] = $tax['value'];
                    $this->taxes[$tax['name']]['incidence'] += $tax['incidence_value'];
                    $this->taxes[$tax['name']]['total'] += $tax['total_value'];
                    $product['unitPriceWithTaxes'] += ((int)$tax['type'] === 1) ? ($originalIncidenceValue * ($tax['value'] / 100)) : ($tax['total_value'] / $raw['qty']);
                    $product['totalPriceWithTaxes'] += $tax['total_value'];
                    $product['tax'] = $tax['value'] . ($tax['type'] == 1 ? '%' : $this->currency);
                }
            } else {
                if (!empty($raw['exemption_reason'])) {
                    $this->exemptions[$raw['exemption_reason']]['description'] = $this->labels->exemption_reasons[$raw['exemption_reason']];
                    $this->exemptions[$raw['exemption_reason']]['incidence'] += $product['unitPrice'];
                    $this->exemptions[$raw['exemption_reason']]['total'] = 0;
                }
                $product['tax'] = '0%';
            }

            if (isset($raw['deduction']) && $raw['deduction'] > 0) {
                $this->deductions[$raw['deduction_name']] = ($product['unitPriceWithDiscounts'] * $raw['deduction']) / 100;
            }

            $product['discount'] = (float)round($product['discount'], 2) . '%';
            $product['unitPrice'] = Tools::priceFormat($product['unitPrice'], $this->currency);
            $product['unitPriceWithTaxes'] = Tools::priceFormat($product['unitPriceWithTaxes'], $this->currency);
            $product['totalPrice'] = Tools::priceFormat($product['totalPrice'], $this->currency);
            $product['totalPriceWithTaxes'] = Tools::priceFormat($product['totalPriceWithTaxes'], $this->currency);

            $this->products[] = $product;

            $this->productsCount++;
            $this->productsWithQuantityCount += $product['quantity'];
        }
    }

    protected function parseCopies()
    {
        $copies = 0;


        if ($this->terminal['document_settings'] && !empty($this->terminal['document_settings'])) {
            foreach ($this->terminal['document_settings'] as $documentSetting) {
                if ($documentSetting['document_type_id'] == $this->document['document_type_id']) {
                    if ((int)$documentSetting['num_copies'] > 0) {
                        $copies = $documentSetting['num_copies'];
                    }
                }
            }
        }

        if ($copies == 0 && isset($this->document['num_copies']) && (int)$this->document['num_copies'] > 0) {
            $copies = (int)$this->document['num_copies'];
        }


        if ($copies == 0 && !empty($this->company['copies']) && is_array($this->company['copies'])) {
            foreach ($this->company['copies'] as $companyCopies) {
                if ($companyCopies['document_type_id'] == $this->document['document_type_id']) {
                    if ((int)$companyCopies['copies'] > 0) {
                        $copies = $companyCopies['copies'];
                    }
                }
            }
        }

        if ($copies == 0) {
            $copies = 1;
        }

        $this->printer->copies = $copies;
    }

}