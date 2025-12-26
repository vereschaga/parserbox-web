<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

class SavedStatement extends \TAccountCheckerExtended
{
    // United personal statement, saved from site and sent by email to AW
    public $mailFiles = "mileageplus/statements/st-2064860.eml, mileageplus/statements/st-2064869.eml, mileageplus/statements/st-2101839.eml, mileageplus/statements/st-2108089.eml, mileageplus/statements/st-2451511.eml, mileageplus/statements/st-2454934.eml, mileageplus/statements/st-2454937.eml, mileageplus/statements/st-3132173.eml";

    public function ParseStatement()
    {
        $result = [];
        $searchFieldsId = [
            'Name' => [
                'ctl00_ContentInfo_AccountSummary_lblOPNameNew',
                'ctl00_ContentInfo_accountsummary_spanName',
            ],
            'Number' => [
                'ctl00_ContentInfo_AccountSummary_lblOPNumberNew',
                'ctl00_ContentInfo_accountsummary_spanMPNumber',
            ],
            'Balance' => [
                'ctl00_ContentInfo_AccountSummary_lblMileageBalanceNew',
                'ctl00_ContentInfo_accountsummary_spanMPBalance',
            ],
            'LifetimeMiles' => [
                'ctl00_ContentInfo_AccountSummary_lblEliteLifetimeMilesNew',
            ],
            'AccountExpirationDate' => [
                'ctl00_ContentInfo_AccountSummary_lblMileageExpireDateNew',
                'ctl00_ContentInfo_accountsummary_spanMileageExpirationDate',
            ],
        ];

        foreach ($searchFieldsId as $key => $ids) {
            $conditions = [];

            foreach ($ids as $id) {
                $conditions[] = 'contains(@id, "' . $id . '")';
            }

            if ($conditions) {
                $result[$key] = $this->http->FindSingleNode('//*[' . implode(' or ', $conditions) . ']');
            }
        }

        if (!$result['Balance']) {
            $result['Balance'] = $this->http->FindPreg('#Mileage\s+balance\s*<[^>]*>([\d,]+)\s*<#i');
        }

        if (!$result['AccountExpirationDate']) {
            $result['AccountExpirationDate'] = $this->http->FindSingleNode('//div[@id="divMileageExpDate"]/following-sibling::*[1]/span[1]', null, true, '/^\d{1,2}\/\d{1,2}\/20\d{2}$/');
        }

        if (!$result['AccountExpirationDate']) {
            $result['AccountExpirationDate'] = $this->http->FindSingleNode('//div[contains(., "Mileage expiration") and not(.//div)]/following-sibling::span[1]');
        }

        if ($result['Balance']) {
            $result['Balance'] = str_replace(',', '', $result['Balance']);
        }
        $result['AccountExpirationDate'] = strtotime($result['AccountExpirationDate']);

        if (!$result['AccountExpirationDate']) {
            $result['AccountExpirationDate'] = null;
        }

        $searchFieldsTd = [
            'EliteMiles'    => 'YTD Premier qualifying miles:',
            'EliteSegments' => 'YTD Premier qualifying segments:',
            'EliteDollars'  => 'YTD Premier qualifying dollars:',
        ];

        foreach ($searchFieldsTd as $key => $title) {
            $result[$key] = $this->http->FindSingleNode('(//*[contains(., "' . $title . '")]/following-sibling::td[1])[1]');
        }

        if (isset($result["Number"])) {
            $result["Login"] = $result["Number"];
        }

        if ($img = $this->http->FindSingleNode('//img[contains(@src, "/statusbadges/")]/@src')) {
            foreach (['Silver', 'Gold', 'Platinum'] as $name) {
                if (strpos($img, '/' . $name . '_StatusBadge.png') !== false) {
                    $result['MemberStatus'] = $name;

                    break;
                }
            }
        }

        return $result;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $props = $this->ParseStatement();

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
        return $this->http->FindPreg('#My\s+MileagePlus\s+account#i')
            || $this->http->FindPreg('#MileagePlus\s+account\s+summary#i');
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }
}
