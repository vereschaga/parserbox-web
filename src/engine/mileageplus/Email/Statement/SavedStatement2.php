<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

class SavedStatement2 extends \TAccountCheckerExtended
{
    // United personal statement v2, saved from site and sent by email to AW
    public $mailFiles = "mileageplus/statements/st-2157081.eml";

    public function ParseStatement(\PlancakeEmailParser $parser)
    {
        $result = [];

        $searchFieldsId = [
            'Name' => [
                'ctl00_ContentInfo_HeaderInformation_lblFullName',
            ],
            'Number' => [
                'ctl00_ContentInfo_HeaderInformation_lblOnePassNumber',
            ],
        ];

        foreach ($searchFieldsId as $key => $ids) {
            $conditions = [];

            foreach ($ids as $id) {
                $conditions[] = '@id = "' . $id . '"';
            }

            if ($conditions) {
                $result[$key] = $this->http->FindSingleNode('//*[' . implode(' or ', $conditions) . ']');
            }
        }

        $searchFieldsTd = [
            'Balance'       => 1,
            'EliteMiles'    => 3,
            'EliteSegments' => 4,
            'EliteDollars'  => 5,
        ];

        foreach ($searchFieldsTd as $key => $index) {
            $result[$key] = $this->http->FindSingleNode('//td[contains(normalize-space(.), "Ending balance as of") and not(.//td)]/following-sibling::td[' . $index . ']');
        }

        if (isset($result['Balance'])) {
            $result['Balance'] = str_replace(',', '', $result['Balance']);
        }

        if (isset($result["Number"])) {
            $result["Login"] = $result["Number"];
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
        $text = $parser->getHTMLBody();

        return stripos($text, 'MileagePlus activity since my last statement') !== false
            || stripos($text, 'MileagePlus Statement') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }
}
