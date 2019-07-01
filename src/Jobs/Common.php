<?php

namespace MoloniPrint\Jobs;

class Common extends Controller
{

    protected $currency = '€';

    /************************************************
     * Document array from document/getOne API call
     * Used in OfferTickets and Documents
     ************************************************/
    protected $document = [];
    protected $isFinalConsumer = false;
    protected $hasAddress = true;


    /**
     * Common constructor.
     * @param \MoloniPrint\Job $job
     */
    public function __construct(\MoloniPrint\Job &$job)
    {
        parent::__construct($job);
    }

    /**
     * Create document image if there is any
     */
    public function image()
    {
        if (!empty($this->company['image'])) {
            $this->builder->image($this->imageUrl . '?macro=imgWebPOSCompanyLogoPrinterRaw&img=' . $this->company['image'], $this->printer->dotWidth);
            $this->linebreak();
        }
    }

    /**
     * Create a document header with company details
     */
    public function header()
    {
        $this->companyName();
        $this->companyAddress();
        $this->companyContacts();
        $this->companySocialCapital();
    }

    /**
     * Add the company name
     */
    public function companyName()
    {
        $this->builder->textStyle(false, false, true);
        $this->builder->text($this->company['name'] . "\n");
    }

    /**
     * Add company address
     */
    public function companyAddress()
    {
        $this->builder->textStyle(false, false, false);
        $this->builder->textFont('C');

        $this->builder->text($this->labels->vat . ': ' . $this->company['vat'] . "\n");
        $this->builder->text($this->company['address'] . "\n");
        $this->builder->text($this->company['zip_code'] . ' ' . $this->company['city'] . ', ' . $this->company['country']['name'] . "\n");
    }

    /**
     * Add company contacts
     */
    public function companyContacts()
    {
        $this->builder->textStyle(false, false, false);
        $this->builder->textFont('C');

        if (!empty($this->company['email']) || !empty($this->company['phone'])) {
            if (!empty($this->company['email'])) {
                $this->builder->text($this->labels->email . ': ' . $this->company['email']);
                if (!empty($this->company['phone'])) {
                    $this->builder->text(", " . $this->labels->phone . ': ' . $this->company['phone']);
                }
            } else {
                $this->builder->text($this->labels->phone . ': ' . $this->company['phone']);
            }
        }

        $this->linebreak();
    }

    /**
     * Add company social capital
     */
    public function companySocialCapital()
    {
        $this->builder->textStyle(false, false, false);
        $this->builder->textFont('C');

        if (!empty($this->company['capital']) || !empty($this->company['commercial_registration_number'])) {
            if (!empty($this->company['capital'])) {
                $this->builder->text($this->labels->social_capital . ': ' . $this->company['capital']);
                if (!empty($this->company['commercial_registration_number'])) {
                    $this->builder->text(", " . $this->labels->commercial_registration_number . ':');
                    $this->builder->text(' ' . $this->company['commercial_registration_number']);
                    $this->builder->text(' ' . $this->company['registry_office']);
                }
            } else {
                $this->builder->text($this->labels->commercial_registration_number . ': ' . $this->company['commercial_registration_number']);
            }
        }

        $this->linebreak();
    }

    public function signature()
    {
        $this->builder->textFont('A');
        $this->builder->textDouble();
        $this->builder->textAlign();
        $this->builder->textStyle();
        $this->builder->text($this->labels->signature);
        $this->linebreak();
        $this->drawLine();
        $this->linebreak();
    }

    public function poweredBy()
    {
        $this->builder->textFont('C');
        $this->builder->textDouble();
        $this->builder->textStyle(false, false, true);
        $this->builder->textAlign('CENTER');
        $this->builder->text($this->labels->powered_by);
        $this->linebreak();
    }

}