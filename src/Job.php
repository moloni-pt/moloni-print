<?php

namespace MoloniPrint;

use MoloniPrint\Settings\Labels;
use MoloniPrint\Settings\Printer;

use MoloniPrint\Jobs\Document;
use MoloniPrint\Jobs\OfferTicket;

class Job
{

    public $company;
    public $terminal;
    public $labels;
    public $printer;
    public $data;

    /**
     * Start the build with the printer settings
     * @param $company array From a company/getOne call
     * @param $terminal array From a terminals/getOne call
     * @param $labels array From documents/getPrintingLabels call
     * @param $printer array From printers/getOne call
     */
    public function __construct($company, $terminal, $labels = [], $printer = [])
    {
        $this->company = $company;
        $this->terminal = $terminal;
        $this->labels = new Labels($labels);
        $this->printer = new Printer($printer);
    }

    /**
     * Create a new document JSON ready be sent to Moloni Print Client
     * @param $document array Array from a document/getOne API call
     * @return string JSON ready to be sent
     */
    public function Document(Array $document)
    {
        $printingDocument = (new Document($this))->create($document);
        return $printingDocument;
    }

    public function OfferTicket(Array $document, $products = [])
    {
        $printingDocument = (new OfferTicket($this))->create($document, $products);
        return $printingDocument;
    }

    public function Cashflow(Array $cashflow)
    {
        if ($cashflow['type_id'] == 4) {
            $printingDocument = (new Jobs\Cashflows\Closing($this))->create($cashflow);
        } else {
            $printingDocument = (new Jobs\Cashflows\Regular($this))->create($cashflow);
        }

        return $printingDocument;
    }

}
