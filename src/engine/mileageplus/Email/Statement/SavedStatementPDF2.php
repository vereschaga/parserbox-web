<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

class SavedStatementPDF2 extends \TAccountCheckerExtended
{
    // United personal statement v2, saved to PDF from site and sent by email to AW
    public $mailFiles = "mileageplus/statements/st-2074537.eml";

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

        if (!$text) {
            return null;
        }

        if (preg_match('#Save as PDF\s*\n\s*(.*)\s*\n\s*+(\w+)\s+MileagePlus\s+status:\s*\n\s*(.*)\s+Activity\s+period#i', $text, $m)) {
            $result['Name'] = $m[1];
            $result['Number'] = $result['Login'] = $m[2];
            $result['MemberStatus'] = $m[3];
        }

        if (preg_match('#Ending\s+balance\s+as\s+of\s+\d+/\d+/\d+\s*:\s*\n\s*([\d,]+)\s*\n\s*([\d,]+)\s*\n\s*(\d+)#i', $text, $m)) {
            $result['Balance'] = str_replace(',', '', $m[1]);
            $result['EliteMiles'] = $m[2];
            $result['EliteSegments'] = $m[2];
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

                    return preg_match('#MileagePlus Statement#i', $text);
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
