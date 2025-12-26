<?php

namespace AwardWallet\Engine\mta\Email;

class ETicketPdfTemp extends \AwardWallet\Engine\aeroflot\Email\ETicketPdf
{
    public $mailFiles = "aeroflot/it-11715974.eml, aeroflot/it-5587431.eml, aeroflot/it-6996591.eml, mta/it-22407752.eml";

    public static $reBody = [
        'mta' => ['MTA Travel', '@mtatravel.com.au', 'Mobile Travel Agents', 'BESTANDLESSTRAVEL.COM.AU'],
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
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->logger->debug($textPdf);

            if ($this->getProvider($textPdf) === false && $this->http->XPath->query("//a[contains(@href, 'www.mtatravel.com')]")->length == 0) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$reBody);
    }

    private function getProvider($text)
    {
        foreach (self::$reBody as $providerCode => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    return $providerCode;
                }
            }
        }

        return false;
    }

    //if turn on for traxo then delete parser and ADD mta in \AwardWallet\Engine\aeroflot\Email\ETicketPdf
}
