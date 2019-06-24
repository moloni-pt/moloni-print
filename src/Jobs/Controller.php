<?php

namespace MoloniPrint\Jobs;

use MoloniPrint\Utils\Builder;
use MoloniPrint\Utils\Tools;

class Controller
{

    protected $mainUrl = "https://moloni.pt";
    protected $imageUrl = "https://moloni.pt/_imagens/";

    protected $logActive = false;
    protected $processStarted = false;
    protected $lastLog = [];
    protected $logToSend = [];

    protected $company;
    protected $terminal;
    protected $labels;
    protected $printer;
    protected $builder;

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
        'linebreak',
        'products',
        'linebreak',
        'taxes',
        'linebreak',
        'payments',
        'relatedDocuments',
        'linebreak',
        'notes',
        'linebreak',
        'productsCounter',
        'productsWithQuantityCounter',
        'linebreak',
        'productsAvailabilityNote',
        'linebreak',
        'processedBy',
        'poweredBy'
    ];

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
        'processedBy',
        'poweredBy'
    ];

    protected $cashflowRegularSchema = [
        'image',
        'header',
        'details',
        'payments',
        'linebreak',
        'signature',
        'linebreak',
        'createdAt',
        'linebreak',
        'processedBy',
        'poweredBy'
    ];

    protected $cashflowClosingSchema = [
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
        'signature',
        'linebreak',
        'createdAt',
        'linebreak',
        'processedBy',
        'poweredBy'
    ];

    /**
     * Common constructor.
     * @param \MoloniPrint\Job $job
     */
    public function __construct(\MoloniPrint\Job &$job)
    {
        $this->company = $job->company;
        $this->terminal = $job->terminal;
        $this->labels = $job->labels;
        $this->printer = $job->printer;
        $this->builder = new Builder();

        $this->builder->addSettings($this->printer);

        $this->builder->textFont();
        $this->builder->textStyle();
        $this->builder->textDouble();
        $this->builder->textAlign();
    }

    /**
     * Finish by resetting styles and send a cut command
     */
    protected function finish()
    {
        $this->builder->text("\n");
        $this->builder->textFont();
        $this->builder->textStyle();
        $this->builder->textDouble();
        $this->builder->textAlign();
        if ($this->printer->hasCutter) {
            $this->builder->cut();
        }
    }

    /**
     * Draw a line with the printer table split char
     */
    protected function drawLine()
    {
        $this->builder->textFont('C');
        $this->builder->textDouble();
        $this->builder->textStyle(false, false, true);
        $this->builder->textAlign('LEFT');
        $this->builder->text(Tools::mb_str_pad('', $this->printer->condensedWidth, $this->printer->tableSplitChar));
    }

    /**
     * Add linebreak
     */
    protected function linebreak()
    {
        $this->builder->text("\n");
    }

    /**
     * Get an output based on a schema
     * Check $this->documentSchema for an example
     * @param $scheme
     */
    protected function drawFromScheme($scheme)
    {
        $this->log("Start drawing");
        if (is_array($scheme)) {
            foreach ($scheme as $item) {
                if (is_array($item)) {
                    $this->drawFromScheme($item);
                } elseif (method_exists($this, $item)) {
                    $this->log("Start " . $item);
                    $this->{$item}();
                    $this->log("Finish " . $item);
                } else {
                    echo "{valid:0, error:$item}";
                }
            }
        }
    }

    /********************
     * Logging functions
     ********************/

    /**
     * Add a log entrance
     * @param string $message
     */
    protected function log($message)
    {
        if ($this->logActive) {
            if ($this->processStarted == 0) {
                $this->processStarted = microtime(true);
                $this->lastLog = $this->processStarted;
            }
            $now = microtime(true);
            array_push($this->logToSend, $message . "\r\n\t@ " . number_format($now - $this->processStarted, 3, '.', ',') . ' (' . number_format($now - $this->lastLog, 3, '.', ',') . ')');
            $this->lastLog = $now;
        }
    }

    /**
     * Print the log details
     */
    public function sendLog()
    {
        if ($this->logActive) {
            die(join("\r\n\r\n", $this->logToSend));
        }

    }

}