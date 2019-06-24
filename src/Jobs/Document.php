<?php

namespace MoloniPrint\Jobs;

use MoloniPrint\Table\Table;
use MoloniPrint\Utils\Tools;

class Document extends Common
{

    protected $taxes = [];
    protected $exemptions = [];
    protected $deductions = [];
    protected $products = [];
    protected $productsCount = 0;
    protected $productsWithQuantityCount = 0;
    protected $productsToPrint = [];

    /**
     * Document constructor.
     * @param \MoloniPrint\Job $job
     */
    public function __construct(\MoloniPrint\Job &$job)
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

        $this->parseEntities();
        $this->parseProducts();

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

        if (isset($this->document['second_way']) && $this->document['second_way']) {
            $this->builder->text($this->labels->second_way);
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
            $date = new \DateTime($this->document['lastmodified']);
            $dateFormatted = $date->format("d-m-Y H:i");
        } catch (\Exception $exception) {
            $dateFormatted = $this->document['lastmodified'];
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
            $this->builder->addTittle($this->labels->taxes);
            $this->linebreak();

            $table = new Table($this->builder, $this->printer);
            $table->addColumns([null, 7, 10, 10]);

            $headerStyle = ["emphasized" => true, "condensed" => true, "alignment" => "RIGHT"];
            $table->addCells(['', $this->labels->value, $this->labels->incidence, $this->labels->total], $headerStyle);

            $table->addLineSplit();

            foreach ($this->taxes as $name => $tax) {
                $table->addCell($name, ["condensed" => true]);
                $table->addCell(Tools::priceFormat($tax['value'], '%'), ["condensed" => true, "alignment" => "RIGHT"]);
                $table->addCell(Tools::priceFormat($tax['incidence'], $this->currency), ["condensed" => true, "alignment" => "RIGHT"]);
                $table->addCell(Tools::priceFormat($tax['total'], $this->currency), ["condensed" => true, "alignment" => "RIGHT"]);
            }

            foreach ($this->exemptions as $exemption) {
                $table->addCell($exemption['description'], ["condensed" => true]);
                $table->addCell(Tools::priceFormat($exemption['total'], '%'), ["condensed" => true, "alignment" => "RIGHT"]);
                $table->addCell(Tools::priceFormat($exemption['incidence'], $this->currency), ["condensed" => true, "alignment" => "RIGHT"]);
                $table->addCell(Tools::priceFormat($exemption['total'], $this->currency), ["condensed" => true, "alignment" => "RIGHT"]);
            }

            $table->drawTable();
            $this->linebreak();
        }
    }

    public function payments()
    {
        if (!empty($this->document['payments'])) {
            $this->builder->addTittle($this->labels->payments);
            $this->linebreak();

            $table = new Table($this->builder, $this->printer);
            $table->addColumns([null, 15]);

            $headerStyle = ["emphasized" => true, "condensed" => true, "alignment" => "RIGHT"];
            $table->addCells(['', $this->labels->total], $headerStyle);
            $table->addLineSplit();

            foreach ($this->document['payments'] as $payment) {
                $table->newRow();
                $table->addCell($payment['payment_method_name'], ["condensed" => true]);
                $table->addCell(Tools::priceFormat($payment['value'], $this->currency), ["condensed" => true, "alignment" => "RIGHT"]);
                if (!empty($payment['notes'])) {
                    $table->addCell($this->labels->obs_short . ': ' . $payment['notes'], ["condensed" => true]);
                }
            }

            $table->drawTable();
            $this->linebreak();
        }
    }

    public function relatedDocuments()
    {
        if (!empty($this->document['associated_documents'])) {
            $this->builder->addTittle($this->labels->associated_documents);
            $this->linebreak();

            $table = new Table($this->builder, $this->printer);
            $table->addColumns([null, 10, 10, 20]);

            $headerStyle = ["emphasized" => true, "condensed" => true, "alignment" => "RIGHT"];
            $table->addCells(['', $this->labels->date, $this->labels->value, $this->labels->conciliated], $headerStyle);
            $table->addLineSplit();

            foreach ($this->document['associated_documents'] as $document) {
                try {
                    $date = new \DateTime($document['associated_document']['date']);
                    $dateFormatted = $date->format("d-m-Y");
                } catch (\Exception $exception) {
                    $dateFormatted = $document['associated_document']['date'];
                }

                $documentName = $this->labels->document_types[$document['associated_document']['document_type_id']];
                $table->addCell($documentName . ' ' . $document['associated_document']['document_set_name'] . '/' . $document['associated_document']['number'], ["condensed" => true]);
                $table->addCell($dateFormatted, ["condensed" => true, "alignment" => "RIGHT"]);
                $table->addCell(Tools::priceFormat($document['associated_document']['net_value'], $this->currency), ["condensed" => true, "alignment" => "RIGHT"]);
                $table->addCell(Tools::priceFormat($document['value'], $this->currency), ["condensed" => true, "alignment" => "RIGHT"]);
            }

            $table->drawTable();
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
            $date = new \DateTime($this->document['lastmodified']);
            $dateFormatted = $date->format("d-m-Y");
        } catch (\Exception $exception) {
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
        $hash = $this->document['rsa_hash'][0];
        $hash .= $this->document['rsa_hash'][10];
        $hash .= $this->document['rsa_hash'][20];
        $hash .= $this->document['rsa_hash'][30];
        $this->builder->text($hash . ' - ');
        $this->builder->text($this->labels->processed_by);
        $this->linebreak();
    }

    public function notes()
    {
        // Credit notes require a customer signature
        if ($this->document['document_type']['saft_code'] == 'NC') {
            $this->signature();
        }

        if (!empty($this->document['notes'])) {
            $this->builder->textFont('A');
            $this->builder->textDouble();
            $this->builder->textAlign();
            $this->builder->textStyle();

            $this->builder->text($this->document['notes']);
        }
    }

    /******************************************
     * Methods to draw a full list of products
     * If we want to add more kind of lists
     ******************************************/
    public function drawProductsFull()
    {
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
    }

    private function drawProductsFullHeader(Table &$table)
    {
        $table->addColumn();
        $table->addColumn(7);
        $table->addColumn(12);

        if ($this->printer->condensedWidth > 48 && $this->document['comercial_discount_value'] > 0) {
            $table->addColumn(8);
        }

        $table->addColumn(8);
        $table->addColumn(12);

        $headerStyle = [
            "emphasized" => true,
            "condensed" => true,
            "alignment" => "RIGHT"
        ];

        $table->addCell('', $headerStyle);
        $table->addCell($this->labels->qty, $headerStyle);
        $table->addCell($this->labels->pvp_unit_short, $headerStyle);

        if ($this->printer->condensedWidth > 48 && $this->document['comercial_discount_value'] > 0) {
            $table->addCell($this->labels->discount_short, $headerStyle);
        }

        $table->addCell($this->labels->iva, $headerStyle);
        $table->addCell($this->labels->total, $headerStyle);
    }

    private function drawProductsFullLine(Table &$table, $product)
    {
        $description = $this->terminal['print_products_reference'] ? $product['reference'] . ' ' : '';
        $description .= $product['name'];
        $table->addCell($description);
        $table->newRow();

        if ($this->terminal['print_products_summary'] && !empty($product['summary'])) {
            $table->addCell($product['summary'], ['condensed' => true]);
            $table->newRow();
        }

        $bodyStyle = [
            "alignment" => "RIGHT"
        ];

        $table->addCell('');
        $table->addCell($product['quantity'], $bodyStyle);
        $table->addCell($product['unitPrice'], $bodyStyle);

        if ($this->printer->condensedWidth > 48 && $this->document['comercial_discount_value'] > 0) {
            $table->addCell($product['discount'], $bodyStyle);
        }

        $table->addCell($product['tax'], $bodyStyle);
        $table->addCell($product['totalPriceWithTaxes'], $bodyStyle);
    }

    private function drawProductsFullResume()
    {
        $table = new Table($this->builder, $this->printer);
        $table->addColumn();
        $table->addColumn(12);
        $table->addCell($this->labels->gross_total, ["alignment" => "RIGHT"]);
        $table->addCell(Tools::priceFormat($this->document['gross_value']), ["alignment" => "RIGHT"]);

        if ($this->document['comercial_discount_value'] > 0) {
            $table->addCell($this->labels->total_discounts, ["alignment" => "RIGHT"]);
            $table->addCell(Tools::priceFormat($this->document['comercial_discount_value']), ["alignment" => "RIGHT"]);
        }

        if (!empty($this->taxes)) {
            foreach ($this->taxes as $name => $values) {
                $table->addCell($name, ["alignment" => "RIGHT"]);
                $table->addCell(Tools::priceFormat($values['total']), ["alignment" => "RIGHT"]);
            }
        }

        if (!empty($this->deductions)) {
            foreach ($this->deductions as $name => $value) {
                $table->addCell($name, ["alignment" => "RIGHT"]);
                $table->addCell(Tools::priceFormat($value), ["alignment" => "RIGHT"]);
            }
        }

        $table->addCell($this->labels->total, ["alignment" => "RIGHT", 'emphasized' => true]);
        $table->addCell(Tools::priceFormat($this->document['net_value']), ["alignment" => "RIGHT", 'emphasized' => true]);
        $table->drawTable();
        $this->linebreak();
    }

    protected function parseEntities()
    {
        $this->currency = $this->company['currency']['symbol'];
        if ($this->company['country_id'] == 1 &&
            trim($this->document['entity_vat']) == '999999990' &&
            trim($this->document['entity_name']) == 'Consumidor Final') {
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
                foreach ($raw['taxes'] as $tax) {
                    $this->taxes[$tax['name']]['value'] = $tax['value'];
                    $this->taxes[$tax['name']]['incidence'] += $tax['incidence_value'];
                    $this->taxes[$tax['name']]['total'] += $tax['total_value'];
                    $product['unitPriceWithTaxes'] += $tax['total_value'] / $raw['qty'];
                    $product['totalPriceWithTaxes'] += $tax['total_value'];
                    $product['tax'] = $tax['value'] . ($tax['type'] == 1 ? '%' : $this->company['currency']['symbol']);
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

            $product['discount'] = floatval(round($product['discount'], 2)) . "%";
            $product['unitPrice'] = Tools::priceFormat($product['unitPrice'], $this->currency);
            $product['unitPriceWithTaxes'] = Tools::priceFormat($product['unitPriceWithTaxes'], $this->currency);
            $product['totalPrice'] = Tools::priceFormat($product['totalPrice'], $this->currency);
            $product['totalPriceWithTaxes'] = Tools::priceFormat($product['totalPriceWithTaxes'], $this->currency);

            $this->products[] = $product;

            $this->productsCount++;
            $this->productsWithQuantityCount += $product['quantity'];
        }
    }

}