<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Schema\Parser\Email\Email;

class CheckMyTripPDFTemp extends \AwardWallet\Engine\checkmytrip\Email\PDF
{
    public $mailFiles = "mta/it-166653513.eml, mta/it-28178592.eml, mta/it-29662296.eml, mta/it-31614220.eml";

    private $detectBody = [
        'en'  => 'Electronic Ticket Receipt',
        'en2' => 'Airline Booking Reference',
        'en3' => 'Your trip',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "mtatravel.com.au") !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $flag = false;
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $pdfBody = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach ($this->detectBody as $detect) {
                if (stripos($pdfBody, $detect) !== false) {
                    $flag = true;

                    break 2;
                }
            }
        }

        if ($flag && isset($pdfBody)) {
            return $this->assignLang($pdfBody);
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (strpos(implode(" ", $parser->getFrom()), 'mtatravel.com.au') === false) {
            return null;
        } //goto parse by parent-parser

        $type = parent::ParsePlanEmailLocal($parser, $email);

        $email->setProviderCode('mta');
        $email->setType('CheckMyTripPDFTemp' . $type . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailProviders()
    {
        return ['mta'];
    }

    //if turn on for traxo then delete parser and ADD mta in \AwardWallet\Engine\checkmytrip\Email\PDF
}
