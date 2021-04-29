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

    protected $documentsAreNotValidInvoices = [
        'FF', 'VDF', 'NCF', 'NDVF', 'FSF', 'AV',
        'GC', 'GA', 'GD', 'PGF', 'DI', 'SM', 'SMVD',
        'SMFS', 'SMNC', 'SMND', 'SMFR', 'CM'
    ];

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
        if (!empty($this->document['document_set']['template']['image'])) {
            $this->builder->image(
                $this->document['document_set']['template']['image'],
                $this->printer->dotWidth,
                $this->printer->imageType
            );
            $this->linebreak();
        } elseif (!empty($this->company['image'])) {
            $this->builder->image($this->company['image'], $this->printer->dotWidth, $this->printer->imageType);
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
        $this->builder->textFont();
        $this->builder->textAlign();
        $this->builder->textDouble();
        $this->builder->textStyle(false, false, true);
        if (isset($this->document['document_set']['template']['business_name'])) {
            $this->builder->text($this->document['document_set']['template']['business_name'] . "\n");
            $this->builder->textFont('C');
            $this->builder->textStyle();
            $this->builder->text($this->labels->company . ': ' . $this->company['name'] . "\n");
        } else {
            $this->builder->text($this->company['name'] . "\n");
        }
    }

    /**
     * Add company address
     */
    public function companyAddress()
    {
        $this->builder->textStyle(false, false, false);
        $this->builder->textFont('C');

        $this->builder->text($this->labels->vat . ': ' . $this->company['vat'] . "\n");
        if (isset($this->document['document_set']['template']) && !empty($this->document['document_set']['template'])) {
            if (!empty($this->document['document_set']['template']['address'])) {
                $this->builder->text($this->document['document_set']['template']['address'] . "\n");
            }

            if (!empty($this->document['document_set']['template']['zip_code'])) {
                $this->builder->text($this->document['document_set']['template']['zip_code'] . ' ');
            }

            if (!empty($this->document['document_set']['template']['city'])) {
                $this->builder->text($this->document['document_set']['template']['city'] . ', ');
            }

            $this->builder->text($this->document['document_set']['template']['country']['name'] . "\n");
        } else {
            if (!empty($this->company['address'])) {
                $this->builder->text($this->company['address'] . "\n");
            }

            if (!empty($this->company['zip_code'])) {
                $this->builder->text($this->company['zip_code'] . ' ');
            }

            if (!empty($this->company['city'])) {
                $this->builder->text($this->company['city'] . ', ');
            }

            $this->builder->text( $this->company['country']['name']);
            $this->linebreak();
        }
    }

    /**
     * Add company contacts
     */
    public function companyContacts()
    {
        $this->builder->textStyle(false, false, false);
        $this->builder->textFont('C');

        if (isset($this->document['document_set']['template'])) {
            $email = $this->document['document_set']['template']['email'];
            $phone = $this->document['document_set']['template']['phone'];
        } else {
            $email = $this->company['email'];
            $phone = $this->company['phone'];
        }

        if (!empty($email) || !empty($phone)) {
            if (!empty($email)) {
                $this->builder->text($this->labels->email . ': ' . $email);
                if (!empty($phone)) {
                    $this->builder->text(", " . $this->labels->phone . ': ' . $phone);
                }
            } else {
                $this->builder->text($this->labels->phone . ': ' . $phone);
            }
        }

        $this->linebreak();
    }

    /**
     * Add company social capital
     */
    public function companySocialCapital()
    {
        if (!empty($this->company['capital']) || !empty($this->company['commercial_registration_number'])) {
            $this->builder->textStyle(false, false, false);
            $this->builder->textFont('C');

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

            $this->linebreak();
        }
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