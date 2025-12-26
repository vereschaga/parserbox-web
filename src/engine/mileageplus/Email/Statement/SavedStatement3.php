<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

class SavedStatement3 extends \TAccountCheckerExtended
{
    // United personal statement v3, saved from site and sent by email to AW
    public $mailFiles = "mileageplus/statements/st-3169699.eml, mileageplus/statements/st-3170669.eml";

    public function ParseStatement(\PlancakeEmailParser $parser)
    {
        $result = [];

        $searchFieldsTd = [
            'Number' => [
                '//tr[contains(., "MileagePlus number")]/following-sibling::tr[1]/td[2]',
                '#^\w+$#',
            ],
            'Balance' => [
                '//tr[contains(., "Mileage balance")]/following-sibling::tr[1]/td[1]/descendant::text()[normalize-space(.)][1]',
                '#^[\d,]+$#', // hotfix
            ],
            'ExpirationDate' => [
                '//tr[contains(., "MileagePlus number")]/following-sibling::tr[1]/td[1]/div[2]',
                '#^Expire\s+on:\s+(\d+/\d+/\d+)$#',
            ],
            'EliteMiles' => [
                '//tr[contains(., "PQM earned in 2015")]/following-sibling::tr/td[1]',
                '#^[\d,]+$#i',
            ],
            'EliteSegments' => [
                '//tr[contains(., "PQS earned in 2015")]/following-sibling::tr/td[1]',
                '#^[\d.]+$#i',
            ],
            'EliteDollars' => [
                '//tr[contains(., "PQD earned in 2015")]/following-sibling::tr/td[1]',
                '#^\$[\d,.]+$#i',
            ],
        ];

        foreach ($searchFieldsTd as $key => [$xpath, $regex]) {
            $value = $this->http->FindSingleNode($xpath, null, false, $regex);
            $result[$key] = $value;
        }

        if (isset($result['Balance'])) {
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
        $text = $parser->getHTMLBody();

        return stripos($text, 'MileagePlus number') !== false
                    and stripos($text, 'Mileage balance') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }
}
