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

use DateTime;
use Exception;
use MoloniPrint\Job;
use MoloniPrint\Table\Table;
use MoloniPrint\Utils\Tools;

/**
 * Class Document
 *
 * @package MoloniPrint\Jobs
 */
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
        'relatedDocuments',
        'payments',
        'shippingDetails',
        'linebreak',
        'productsCounter',
        'productsWithQuantityCounter',
        'linebreak',
        'productsAvailabilityNote',
        'linebreak',
        'notes',
        'documentFooter',
        'footer',
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

    protected $hasDocumentFooter = false;

    /**
     * @var float
     */
    protected $currentCopy = 1;

    /**
     * @var float
     */
    protected $exchangeRate = 1;

    /**
     * @var bool
     */
    private $showPrice = true;

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
     *
     * @param array $document the document to print
     *
     * @return array document in printed format in json
     */
    public function create(array $document)
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

        $this->shouldShowPrices();

        $this->parseEntities();
        $this->parseProducts();

        $this->parseCopies();

        $this->drawFromScheme($this->documentSchema);
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
     *
     * @return void
     */
    public function documentIdentification()
    {
        $this->builder->textStyle(false, false, false);
        $this->builder->textFont('C');

        if (isset($this->document['second_way']) && $this->document['second_way']) {
            $this->builder->text($this->labels->second_way . ' - ');
        }

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
     *
     * @return void
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

        if ((empty($this->terminal) || (int)$this->terminal['print_salesman'] === 1) && $this->company['docs_show_salesman']) {
            if (isset($this->document['salesman_id']) && $this->document['salesman_id'] > 0) {
                $this->builder->text($this->labels->salesman . ': ' . $this->document['salesman']['name']);
                $this->linebreak();
            }
        }

        if (isset($this->document['exchange_currency']['iso4217'])) {
            $this->builder->text($this->labels->coin . ': ' . $this->document['exchange_currency']['iso4217']);
            $this->linebreak();
        }
    }

    /**
     * Show our reference
     *
     * @return void
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
     *
     * @return void
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
     *
     * @return void
     */
    public function documentDate()
    {
        try {
            $closingDate = new DateTime($this->document['date']);
            if ($this->terminal && $this->terminal['print_hour']) {
                $closingHours = new DateTime($this->document['lastmodified']);
                $dateFormatted = $closingDate->format('d-m-Y') . ' ' . $closingHours->format('H:i');
            } else {
                $dateFormatted = $closingDate->format('d-m-Y');
            }
        } catch (Exception $exception) {
            $dateFormatted = $this->document['date'];
        }

        $this->builder->textStyle(false, false, true);
        $this->builder->text($this->labels->date . ': ' . $dateFormatted);
        $this->linebreak();
    }

    /**
     * Show document Shipping Code from AT
     *
     * @return void
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
     * Area Name
     * Table Name
     *
     * @return void
     */
    public function documentTableIdentification()
    {
        if (isset($this->document['associated_table']) && !empty($this->document['associated_table'])) {
            $this->builder->textStyle(false, false, false);
            if (isset($this->document['associated_table']['table']['area']['name']) && !empty($this->document['associated_table']['table']['area']['name'])) {
                $this->builder->text($this->document['associated_table']['table']['area']['name']);
                $this->linebreak();
            }

            if (isset($this->document['associated_table']['table']['name']) && !empty($this->document['associated_table']['table']['name'])) {
                $table = $this->document['associated_table']['table'];
                $this->builder->text($table['reference'] . ' - ' . $table['name']);
                $this->linebreak();
            }
        }
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
        $this->builder->textStyle(false, false, true);
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
     *
     * @return void
     */
    public function entityAddress()
    {
        if ($this->hasAddress && !$this->isFinalConsumer) {
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

    /**
     * Add info about products in a document
     *
     * @return void
     */
    public function products()
    {
        $this->drawProductsFull();
    }

    /**
     * Draws taxes section
     *
     * @return void
     */
    public function taxes()
    {
        if (($this->showPrice && !empty($this->taxes)) || !empty($this->exemptions)) {
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

    /**
     * Draw mb reference
     *
     * @return void
     */
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

    /**
     * Draw payments
     *
     * @return void
     */
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

    /**
     * Draw related documents
     *
     * @return void
     */
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
                $table->addCell($documentName . ' ' . $document['associated_document']['document_set_name'] . '/' .
                    $document['associated_document']['number'], ['condensed' => true]);

                if ($this->printer->condensedWidth < 60) {
                    $table->newRow();
                    $table->addCell('');
                }

                $table->addCell($dateFormatted, ['condensed' => true, 'alignment' => 'RIGHT']);

                /* Hide the value from a global guide */
                if ((int)$document['associated_document']['global_guide'] === 1) {
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

    /**
     * Draw shipping details
     *
     * @return void
     */
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

                if (isset($this->document['timezone']) && !empty($this->document['timezone'])) {
                    $this->builder->text(Tools::dateFormat($this->document['delivery_datetime'], 'd-m-Y H:i', $this->document['timezone']['title']));

                    $this->linebreak();
                    $this->builder->textStyle(false, false, true);
                    $this->builder->text($this->labels->time_zone . ': ');
                    $this->builder->textStyle();
                    $this->builder->text($this->document['timezone']['title'] . ' ');
                    $this->builder->text(
                        '(GMT ' . $this->document['timezone']['offset_multiplier'] .
                        gmdate('H:i', $this->document['timezone']['offset']) . ')'
                    );
                } else {
                    $this->builder->text(Tools::dateFormat($this->document['delivery_datetime']));
                }
            }

            if (!empty($this->document['delivery_departure_address']) ||
                !empty($this->document['delivery_departure_city']) ||
                !empty($this->document['delivery_departure_zip_code']) ||
                !empty($this->document['delivery_departure_country_details']['name'])) {
                $this->linebreak();
                $this->builder->textStyle(false, false, true);
                $this->builder->text($this->labels->departure_place . ':');
                $this->builder->textStyle(false, false, false);

                if (!empty($this->document['delivery_departure_address'])) {
                    $this->builder->text(' ' . $this->document['delivery_departure_address']);
                }

                if (!empty($this->document['delivery_departure_zip_code'])) {
                    $this->builder->text(', ' . $this->document['delivery_departure_zip_code']);
                }

                if (!empty($this->document['delivery_departure_city'])) {
                    $this->builder->text(' ' . $this->document['delivery_departure_city']);
                }

                if (!empty($this->document['delivery_departure_country_details_city'])) {
                    $this->builder->text(', ' . $this->document['delivery_departure_country_details']['name']);
                }
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

    /**
     * Draw product counter
     *
     * @return void
     */
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

    /**
     * Draw products counter
     *
     * @return void
     */
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

    /**
     * Draw availability note
     *
     * @return void
     */
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

    /**
     * Draw processed by section
     *
     * @return void
     */
    public function processedBy()
    {
        $this->builder->textFont('C');
        $this->builder->textDouble();
        $this->builder->textStyle(false, false, true);
        $this->builder->textAlign('CENTER');

        if ((int)$this->company['docs_qr_code_position'] !== 3 && $this->document['qr_code'] && !empty($this->document['qr_code']['url'])) {
            $this->builder->image($this->document['qr_code']['url'], $this->printer->dotWidth, 'qr');
            $this->linebreak();
        }

        $oldCertificate = ((int)$this->document['hash_control'] === 1 || (int)$this->document['hash_control'] === 0);

        if (!empty($this->document['rsa_hash'])) {
            $hash = $this->document['rsa_hash'][0];
            $hash .= $this->document['rsa_hash'][10];
            $hash .= $this->document['rsa_hash'][20];
            $hash .= $this->document['rsa_hash'][30];
            $this->builder->text($hash . ' - ');

            $this->builder->text($oldCertificate ? $this->labels->processed_by : $this->labels->processed_by_v2);
        } else {
            $this->builder->text($oldCertificate ? $this->labels->created_by : $this->labels->created_by_v2);
        }

        $this->linebreak();
    }

    /**
     * Draw notes
     *
     * @return void
     */
    public function notes()
    {
        // Credit notes require a customer signature
        if ($this->document['document_type']['saft_code'] === 'NC') {
            $this->signature();
        }

        if (in_array($this->document['document_type']['saft_code'], $this->documentsAreNotValidInvoices, true)) {
            $this->builder->textStyle(false, false, true);
            $this->builder->text($this->labels->document_is_not_invoice);
            $this->linebreak();
        }

        if (!empty($this->document['notes'])) {
            $this->builder->textStyle();
            $this->builder->textFont('A');
            $this->builder->textDouble();
            $this->builder->textAlign();
            $this->builder->textStyle();

            $this->builder->text($this->document['notes']);
            $this->linebreak();
        }
    }

    /**
     * Draw document footer
     *
     * @return void
     */
    public function documentFooter()
    {
        // If the document is a FT, FS or FR print the terminal message
        if (!empty($this->terminal['document_settings']) && is_array($this->terminal['document_settings'])) {
            foreach ($this->terminal['document_settings'] as $documentSetting) {
                if ($documentSetting['document_type_id'] == $this->document['document_type_id'] && !empty($documentSetting['footer'])) {
                    $this->hasDocumentFooter = true;

                    $this->builder->textFont('C');
                    $this->builder->textDouble();
                    $this->builder->textAlign();
                    $this->builder->textStyle();
                    $this->builder->text($documentSetting['footer']);
                    $this->linebreak();
                }
            }
        }
    }

    /**
     * Draw footer
     *
     * @return void
     */
    public function footer()
    {
        if (isset($this->document['document_set']['template']['documents_footnote'])) {
            $footer = trim(strip_tags($this->document['document_set']['template']['documents_footnote']));
        } else {
            $footer = trim(strip_tags($this->company['docs_footer']));
        }

        if (!empty($footer) && !$this->hasDocumentFooter) {
            $this->builder->textFont('C');
            $this->builder->textDouble();
            $this->builder->textAlign();
            $this->builder->textStyle();

            $this->builder->text($footer);
            $this->linebreak();
        }
    }

    /**
     * Methods to draw a full list of products
     * If we want to add more kind of lists
     *
     * @return void
     */
    public function drawProductsFull()
    {
        $this->builder->textStyle();
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

    /**
     * Nome
     * Quantidade
     * Preço Unitário
     * Desconto
     * IVA
     * Preço Total
     *
     * @param Table $table the table reference
     *
     * @return void
     */
    private function drawProductsFullHeader(Table &$table)
    {
        $table->addColumn();
        $table->addColumn(7);

        if ($this->showPrice) {
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
        }


        $headerStyle = [
            'emphasized' => true,
            'condensed' => true,
            'alignment' => 'RIGHT'
        ];

        $table->addCell('', $headerStyle);
        $table->addCell($this->labels->qty, $headerStyle);

        if ($this->showPrice) {
            if ($this->printer->condensedWidth > $this->ultraSmallWidth) {
                $table->addCell($this->labels->pvp_unit_short, $headerStyle);
            }

            if ($this->printer->condensedWidth > 48 && $this->document['comercial_discount_value'] > 0) {
                $table->addCell($this->labels->discount_short, $headerStyle);
            }

            $table->addCell($this->labels->iva, $headerStyle);
            $table->addCell($this->labels->total, $headerStyle);
        }
    }

    /**
     * Draws product line
     *
     * @param Table $table the table reference
     * @param array $product product to print
     * @param bool $isChildProduct if the current product is a child ou parent
     *
     * @return void
     */
    private function drawProductsFullLine(Table $table, $product, $isChildProduct = false)
    {
        $description = '';

        if ($isChildProduct) {
            $description .= '  - ';
        }

        $description .= $this->terminal['print_products_reference'] ? $product['reference'] . ' ' : '';
        $description .= $product['name'];

        $table->addCell($description);
        $table->newRow();

        if (!empty($product['summary']) && (
                (!isset($this->terminal['print_products_summary']) && $this->company['docs_show_products_summary']) ||
                $this->terminal['print_products_summary']
            )) {
            $table->addCell($product['summary'], ['condensed' => true]);
            $table->newRow();
        }

        if (isset($product['properties']) && $product['properties'] !== '') {
            $propertiesJson = json_decode($product['properties'], true);

            if (is_array($propertiesJson) && count($propertiesJson) > 0) {
                foreach ($propertiesJson as $property) {
                    $table->addCell($property['titulo'] . ': ' . $property['valor'], ['condensed' => true]);
                    $table->newRow();
                }
            }
        }

        $bodyStyle = [
            'alignment' => 'RIGHT'
        ];

        $table->addCell('');
        $table->addCell($product['quantity'], $bodyStyle);

        if ($this->showPrice) {
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

            if (is_array($product['tax'])) {
                $table->addCell($product['tax'][0], $bodyStyle);
            } else {
                $table->addCell($product['tax'], $bodyStyle);
            }

            if ($this->company['docs_show_values_with_taxes'] == 1) {
                $table->addCell($product['totalPriceWithTaxes'], $bodyStyle);
            } else {
                $table->addCell($product['totalPrice'], $bodyStyle);
            }

            if (is_array($product['tax']) && count($product['tax']) > 1) {
                for ($i = 1, $iMax = count($product['tax']); $i < $iMax; $i++) {
                    $table->newRow();
                    $table->addCells(['', '', '', $product['tax'][$i], ''], $bodyStyle);
                }
            }
        }

        if ((empty($this->terminal) || (int)$this->terminal['print_products_childs'] === 1) && isset($product['child_products'])) {
            foreach ($product['child_products'] as $childProduct) {
                $this->drawProductsFullLine($table, $childProduct, true);
            }
        }

        $this->productsCount++;
    }

    /**
     * Gets the longest word size
     *
     * @param array $words words
     * @param int $min minimum size of word
     *
     * @return int size of longest word
     */
    protected function getLongestWord($words = [], $min = 14)
    {
        $longest = 0;

        foreach ($words as $word) {
            if (mb_strlen($word) > $longest) {
                $longest = mb_strlen($word);
            }
        }

        return $longest > $min ? $longest : $min;
    }

    /**
     * Draws product resume
     *
     * @return Document current instance
     */
    private function drawProductsFullResume()
    {
        if (!$this->showPrice) {
            return $this;
        }

        $table = new Table($this->builder, $this->printer);

        $valuesList = [
            Tools::priceFormat($this->document['gross_value']),
            Tools::priceFormat($this->document['financial_discount_value']),
            Tools::priceFormat($this->document['comercial_discount_value']),
            Tools::priceFormat($this->document['net_value'])
        ];

        $table->addColumns([null, $this->getLongestWord($valuesList)]);
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

        return $this;
    }

    /**
     * Draws products exchange rates
     *
     * @param Table $table table to draw
     *
     * @return void
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

    /**
     * Parses document entities
     *
     * @return void
     */
    protected function parseEntities()
    {
        if ((int)$this->company['country_id'] === 1 &&
            trim((string)$this->document['entity_vat']) === '999999990' &&
            trim($this->document['entity_name']) === 'Consumidor Final') {
            $this->isFinalConsumer = true;
        }

        if ((int)$this->document['entity_country_id'] === 1 &&
            $this->document['entity_address'] === 'Desconhecido' &&
            $this->document['entity_city'] === 'Desconhecido' &&
            (string)$this->document['entity_zip_code'] === '0000-000') {
            $this->hasAddress = false;
        }
    }

    /**
     * Parse products into a object we can use to print
     *
     * @return void
     */
    protected function parseProducts()
    {
        if (isset($this->document['products']) && is_array($this->document['products'])) {
            foreach ($this->document['products'] as $raw) {

                if (!empty($this->productsToPrint) && is_array($this->productsToPrint)) {
                    $print = false;

                    foreach ($this->productsToPrint as $productToPrint) {
                        if ((int)$raw['product_id'] === (int)$productToPrint['product_id']) {
                            if (isset($productToPrint['qty'])) {
                                $raw['qty'] = round($productToPrint['qty'], 2);
                            }

                            $print = true;
                        }
                    }

                    if (!$print) {
                        continue;
                    }
                }

                $product = $this->parseProduct($raw);

                $this->products[] = $product;

                $this->productsWithQuantityCount += $product['quantity'];
            }
        }
    }

    /**
     * Parse a single product
     *
     * @param array $raw raw product to parse
     *
     * @return array product ready to print
     */
    protected function parseProduct($raw)
    {
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

        if (isset($raw['properties'])) {
            $product['properties'] = $raw['properties'];
        }

        if (isset($raw['child_products']) && !empty($raw['child_products'])) {

            $product['child_products'] = [];
            foreach ($raw['child_products'] as $childProduct) {
                $product['child_products'][] = $this->parseProduct($childProduct);
            }

            if (empty($this->terminal) || (int)$this->terminal['print_products_childs'] === 1) {
                $product['tax'] = '0%';
            } else {
                $product['tax'] = [];
                foreach ($raw['child_products'] as $child_product) {
                    foreach ($child_product['taxes'] as $tax) {
                        $tempTax = $tax['value'] . ((int)$tax['type'] === 1 ? '%' : $this->currency);

                        if (!in_array($tempTax, $product['tax'], true)) {
                            $product['tax'][] = $tempTax;
                        }
                    }
                }

                if (empty($product['tax'])) {
                    $product['tax'] = '0%';
                }
            }
        }

        return $product;
    }

    /**
     * Parse qty of copies
     *
     * @return void
     */
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

    /**
     * Check if prices should be shown
     *
     * @return void
     */
    private function shouldShowPrices()
    {
        if (!$this->company['docs_show_values_on_orders_docs'] && (int)$this->document['document_type_id'] === 28) {
            $this->showPrice = false;
        }

        if (!$this->company['docs_show_values_on_service_sheets'] && (int)$this->document['document_type_id'] === 30) {
            $this->showPrice = false;
        }

        if (!$this->company['docs_show_values_on_movement_docs'] && (int)$this->document['document_type_id'] === 6) {
            $this->showPrice = false;
        }

        if (!$this->company['docs_show_values_on_return_docs'] && in_array($this->document['document_type_id'], [32, 16, 31])) {
            $this->showPrice = false;
        }
    }
}
