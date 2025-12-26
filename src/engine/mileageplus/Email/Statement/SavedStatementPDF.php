<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

class SavedStatementPDF extends \TAccountCheckerExtended
{
    // United personal statement, saved to PDF from site and sent by email to AW
    public $mailFiles = "mileageplus/statements/it-5238481.eml, mileageplus/statements/st-2064877.eml";

    public function ParseStatement(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = "";

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if ($pdfs && count($pdfs) > 0) {
            $body = $parser->getAttachmentBody($pdfs[0]);

            if ($body && preg_match("#^%?PDF#", $body)) {
                $text = text(\PDF::convertToText($body));
            }
        }

        if (empty($text)) {
            return [];
        }

        $result['Name'] = re('#MileagePlus Member/Primary Traveler:\s+(.+?)\s+Date of Birth#is', $text);
        $result['Number'] = $result['Login'] = re('#My MileagePlus account\s+.+?\s+([A-Z]{2}\d+)\s+Mileage balance#i', $text);
        $searchFieldsPlain = [
            'Balance'       => 'Most recent account activity',
            'LifetimeMiles' => 'Lifetime flight miles:',
            'EliteMiles'    => 'YTD Premier qualifying miles:',
            'EliteSegments' => 'YTD Premier qualifying segments:',
            'EliteDollars'  => 'YTD Premier qualifying dollars:',
        ];

        foreach ($searchFieldsPlain as $key => $title) {
            $result[$key] = re('#' . $title . '\s+(\$?[\d,]+)#', $text);
        }

        if ($result['Balance']) {
            $result['Balance'] = str_replace(',', '', $result['Balance']);
        }

        if (($exp = strtotime(re('#Most recent account activity\s+[\d,]+\s+(\d+/\d+/\d+)#i', $text))) && $exp > time()) {
            $result['AccountExpirationDate'] = $exp;
        }

        return $result;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $props = $this->ParseStatement($parser);

        return [
            'parsedData' => ['Properties' => $props],
            'emailType'  => 'SavedStatements',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($pdfs = $parser->searchAttachmentByName('.*pdf')) {
            if ($pdfs && count($pdfs) > 0) {
                $body = $parser->getAttachmentBody($pdfs[0]);

                if ($body && preg_match("#^%?PDF#", $body)) {
                    $text = text(\PDF::convertToHtml($body, \PDF::MODE_SIMPLE));

                    return preg_match('#My[ \s]+MileagePlus[ \s]+account#i', $text);
                }
            }
            //$text = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]), \PDF::MODE_SIMPLE);
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }
}
