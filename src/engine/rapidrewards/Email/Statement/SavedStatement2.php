<?php

namespace AwardWallet\Engine\rapidrewards\Email\Statement;

class SavedStatement2 extends \TAccountChecker
{
    // Southwest Airlines personal statement, saved from site and sent by email to AW
    public $mailFiles = "rapidrewards/statements/st-2912771.eml, rapidrewards/statements/st-2922794.eml, rapidrewards/statements/st-2922797.eml, rapidrewards/statements/st-33215348.eml";

    public function ParseStatement(\PlancakeEmailParser $parser)
    {
        $result = [];
        $nameParts = $this->http->FindNodes('(//*[contains(@class, "name-container") and contains(normalize-space(.), "FULL NAME")])[1]//*[contains(@class, "name-unit")]');

        if (count($nameParts) > 1) {
            $result['Name'] = implode(' ', $nameParts);
        } else {
            $result['Name'] = $this->http->FindSingleNode('(//h5[normalize-space(.) = "FULL NAME"]/following-sibling::*[normalize-space()!=""][1])[1]');
        }
        $number = $this->http->FindSingleNode('(//*[contains(@class, "header--number-info")])[1]', null, true, '/RR (\d+)\b/');

        if (!isset($number)) {
            $number = $this->http->FindSingleNode('(//span[contains(text(), "Member since")]/preceding-sibling::*[last()])[1]', null, true, '#^\s*RR\s+(\d+)\s*$#i');
        }

        if (isset($number)) {
            $result['Number'] = $result['Login'] = $number;
        }
        $result['LastActivity'] = $this->http->FindSingleNode('(//*[contains(@class, "last-activity-date") and not(contains(@class, "area"))])[1]');

        if (!isset($result['LastActivity'])) {
            $result['LastActivity'] = $this->http->FindSingleNode('(//div[normalize-space(.) = "LAST ACTIVITY"]/following-sibling::div/span[1])[1]', null, true, '#^\s*([\d/]+)\s*$#');
        }

        if (isset($result['LastActivity']) && strtotime($result['LastActivity']) > strtotime("01/01/2010")) {
            $result["AccountExpirationDate"] = strtotime("+ 2 years", strtotime($result['LastActivity']));
        }
        $balance = $this->http->FindSingleNode('(//*[contains(@class, "points-balance")])[1]');

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode('(//div[normalize-space(.) = "TOTAL BALANCE"]/following-sibling::div[1]/span[1])[1]', null, true, '#^\s*([\d,]+)\s*$#i');
        }

        if (isset($balance)) {
            $result['Balance'] = str_replace(',', '', $balance);
        }
        $result['TierFlights'] = $this->http->FindSingleNode('(//*[normalize-space(.) = "*"]/preceding-sibling::*[contains(., "flights")]/ancestor::*[1])[1]', null, true, '#([\d,]+)\s*/#i');
        $result['TierPoints'] = $this->http->FindSingleNode('(//*[normalize-space(.) = "*"]/preceding-sibling::*[contains(., "points")]/ancestor::*[1])[1]', null, true, '#([\d,]+)\s*/#i');
        $result['CPFlights'] = $this->http->FindSingleNode('(//*[normalize-space(.) = "†"]/preceding-sibling::*[contains(., "flights")]/ancestor::*[1])[1]', null, true, '#([\d,]+)\s*/#i');
        $result['CPPoints'] = $this->http->FindSingleNode('(//*[normalize-space(.) = "†"]/preceding-sibling::*[contains(., "points")]/ancestor::*[1])[1]', null, true, '#([\d,]+)\s*/#i');

        if ($this->http->XPath->query('//text()[ contains(normalize-space(),"You\'ve earned Companion Pass!") and preceding::*[normalize-space()="*"] and following::*[normalize-space()="†"] ]')->length === 1) {
            $result['Tier'] = 'Companion Pass';
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
        return stripos($this->http->Response['body'], 'Rapid Rewards') !== false && stripos($this->http->Response['body'], 'TOTAL BALANCE') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }
}
