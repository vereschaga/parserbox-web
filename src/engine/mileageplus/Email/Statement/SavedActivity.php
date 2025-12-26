<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

class SavedActivity extends \TAccountChecker
{
    public $mailFiles = "mileageplus/statements/st-2154654.eml, mileageplus/statements/st-2155137.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = $this->ParseEmail($parser);

        return [
            'parsedData' => $result,
            'emailType'  => 'SavedActivity',
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($this->http->Response['body'], 'MileagePlus activity since my last statement') !== false;
    }

    protected function ParseEmail(\PlancakeEmailParser $parser)
    {
        $props = $activity = [];
        $props['Login'] = $props['Number'] = $this->http->FindSingleNode('//div[@id="Activity"]//span[contains(@id, "OnePassNumber")]');
        $balance = $this->http->FindSingleNode('//span[@id="ctl00_ContentInfo_ActivitySummaryInformation_lblEndingMPMiles"]');

        if (isset($balance)) {
            $props['Balance'] = str_replace(',', '', $balance);
        }
        $props['EliteMiles'] = $this->http->FindSingleNode('//span[@id="ctl00_ContentInfo_ActivitySummaryInformation_lblEndingMPEliteMiles"]');
        $props['EliteSegments'] = $this->http->FindSingleNode('//span[@id="ctl00_ContentInfo_ActivitySummaryInformation_lblEndingMPElitePoints"]');
        $props['EliteDollars'] = $this->http->FindSingleNode('//span[@id="ctl00_ContentInfo_ActivitySummaryInformation_lblEndingDollars"]');

        $rows = $this->http->XPath->query('//tr[*[contains(., "Airline activity")] and *[contains(., "Award miles")]]/following-sibling::tr');

        foreach ($rows as $row) {
            if ($this->isHeader($row)) {
                break;
            }
            $date = $this->http->FindSingleNode('th[1]|td[1]', $row, true, '/^\d+\/\d+\/\d+$/');

            if (!isset($date) || !strtotime($date)) {
                continue;
            }
            $new = [];
            $new['Activity Type'] = 'Airline Activity';
            $new['Activity Date'] = strtotime($date);
            $desc = $this->http->FindNodes("(th[2]|td[2])/span/text()", $row);
            $new['Description'] = implode(' ', $desc);
            $new['Award Miles'] = $this->http->FindSingleNode("(th[4]|td[4])/span[1]", $row, true, "/[\d\,\.\-]+/ims");
            $new['Bonus'] = $this->http->FindSingleNode("(th[6]|td[6])/span[1]", $row);
            $new['Total'] = str_replace(',', '', $this->http->FindSingleNode("(th[7]|td[7])/span[1]", $row, true, "/[\d\,\.\-]+/ims"));
            $new['Premier Qualifying / Miles'] = $this->http->FindSingleNode("(th[9]|td[9])/span[1]", $row, true, "/[\d\,\.\-]+/ims");
            $new['Premier Qualifying / Segments'] = $this->http->FindSingleNode("(th[10]|td[10])/span[1]", $row, true, "/[\d\,\.\-]+/ims");
            $activity[] = $new;
        }

        $rows = $this->http->XPath->query('//tr[*[contains(., "Non-airline activity")] and *[contains(., "Award miles")]]/following-sibling::tr');

        foreach ($rows as $row) {
            if ($this->isHeader($row)) {
                break;
            }
            $date = $this->http->FindSingleNode('(th[1]|td[1])', $row, true, '/^\d+\/\d+\/\d+$/');

            if (!isset($date) || !strtotime($date)) {
                continue;
            }
            $new = [];
            $new['Activity Type'] = 'Non-Airline Activity';
            $new['Activity Date'] = strtotime($date);
            $desc = $this->http->FindNodes("(th[2]|td[2])/span/text()", $row);
            $new['Description'] = implode(' ', $desc);
            //			$new['Award Miles'] = $this->http->FindSingleNode("(th[4]|td[4])/span[1]", $row, true, "/[\d\,\.\-]+/ims");
            //			$new['Bonus'] = $this->http->FindSingleNode("(th[6]|td[6])/span[1]", $row);
            $new['Total'] = str_replace(',', '', $this->http->FindSingleNode("(th[7]|td[7])/span[1]", $row, true, "/[\d\,\.\-]+/ims"));
            //			$new['Premier Qualifying / Miles'] = $this->http->FindSingleNode("(th[9]|td[9])/span[1]", $row, true, "/[\d\,\.\-]+/ims");
            //			$new['Premier Qualifying / Segments'] = $this->http->FindSingleNode("(th[10]|td[10])/span[1]", $row, true, "/[\d\,\.\-]+/ims");
            $activity[] = $new;
        }

        $rows = $this->http->XPath->query('//tr[*[contains(., "Award activity")] and *[contains(., "Award miles")]]/following-sibling::tr');

        foreach ($rows as $row) {
            if ($this->isHeader($row)) {
                break;
            }
            $date = $this->http->FindSingleNode('(th[1]|td[1])', $row, true, '/^\d+\/\d+\/\d+$/');

            if (!isset($date) || !strtotime($date)) {
                continue;
            }
            $new = [];
            $new['Activity Type'] = 'Award Activity';
            $new['Activity Date'] = strtotime($date);
            $desc = $this->http->FindNodes("(th[2]|td[2])/span/text()", $row);
            $new['Description'] = implode(' ', $desc);
            //			$new['Award Miles'] = $this->http->FindSingleNode("(th[4]|td[4])/span[1]", $row, true, "/[\d\,\.\-]+/ims");
            //			$new['Bonus'] = $this->http->FindSingleNode("(th[6]|td[6])/span[1]", $row);
            $new['Total'] = str_replace(',', '', $this->http->FindSingleNode("(th[7]|td[7])/span[1]", $row, true, "/[\d\,\.\-]+/ims"));
            $activity[] = $new;
        }

        return ['Properties' => $props, 'Activity' => $activity];
    }

    protected function isHeader(\DOMNode $row)
    {
        foreach ([
            'Airline activity',
            'Non-airline activity',
            'Award activity',
            'Upgrade activity',
        ] as $t) {
            if (stripos(CleanXMLValue($row->nodeValue), $t) !== false) {
                return true;
            }
        }

        return false;
    }
}
