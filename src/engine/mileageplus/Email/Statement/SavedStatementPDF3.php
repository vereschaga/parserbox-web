<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

class SavedStatementPDF3 extends \TAccountCheckerExtended
{
    // United personal statement v3, saved to PDF from site and sent by email to AW
    public $mailFiles = "mileageplus/statements/st-2108091.eml";

    public function ParseStatement(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = "";

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if ($pdfs && count($pdfs) > 0) {
            $body = $parser->getAttachmentBody($pdfs[0]);

            if ($body && preg_match("#^%?PDF#", $body)) {
                $text = text(\PDF::convertToHtml($body, \PDF::MODE_COMPLEX));
            }
        }

        if (preg_match('#\s*(.*)\s+(\S+)\s+YTD Premier qualifying#i', $text, $m)) {
            $result['Name'] = $m[1];
            $result['Number'] = $m[2];
        }

        $searchFieldsPlain = [
            'Balance'       => 'Mileage balance',
            'EliteMiles'    => 'YTD Premier qualifying miles:',
            'EliteSegments' => 'YTD Premier qualifying segments:',
            'EliteDollars'  => 'YTD Premier qualifying dollars:',
        ];

        foreach ($searchFieldsPlain as $key => $title) {
            $result[$key] = re('#' . $title . '\s+([\d,]+)#', $text);
        }

        if ($result['Balance']) {
            $result['Balance'] = str_replace(',', '', $result['Balance']);
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

                    return preg_match('#MileagePlus account summary#i', $text);
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
