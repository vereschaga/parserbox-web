<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class SavedActivityHtml extends \TAccountChecker
{
    public $mailFiles = "mileageplus/statements/it-69088645.eml, mileageplus/statements/it-69801741.eml";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $keywords = [
            'Activity date'    => 'Activity Date',
            'Activity details' => 'Description',
            'pqf'              => 'Premier Qualifying / Segments', // 'Premier Qualifying / Flights',
            'pqp'              => 'Premier Qualifying / Miles', // 'Premier Qualifying / Point',
            'pqm'              => 'Premier Qualifying / Miles',
            'pqs'              => 'Premier Qualifying / Segments',
            'pqd'              => 'Premier Qualifying / Dollars',
            'Award Miles'      => 'Award Miles',
        ];

        $st = $email->createStatement();
        $balance = str_replace(',', '',
            $this->http->FindSingleNode("//ul[.//text()[normalize-space()='MILES' or normalize-space()='miles']]/descendant::text()[not(contains(.,'MILES') or contains(.,'miles'))]")
        );

        if ($balance == null) {
            $balance = str_replace(',', '', $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'HI')]/following::text()[contains(normalize-space(), ' miles')][1]", null, true, "/^([\d\.\,]+)\s*miles/"));
        }
        $st->setBalance(
            $balance
        );
        $st
            ->addProperty('EliteFlights',
                $this->http->FindSingleNode("//ul[.//text()[normalize-space()='MILES' or normalize-space()='miles']]/following-sibling::div//div[normalize-space()='PQF']/following-sibling::div[1]"))
            ->addProperty('ElitePoints',
                $this->http->FindSingleNode("//ul[.//text()[normalize-space()='MILES' or normalize-space()='miles']]/following-sibling::div//div[normalize-space()='PQP']/following-sibling::div[1]"));

        $plusPoints = $this->http->FindSingleNode("//text()[normalize-space()='PLUSPOINTS']/following::text()[normalize-space()='Available']/ancestor::div[2]", null, true, "/^([\d\.\,]+)\s*Available/u");

        if ($plusPoints !== null) {
            $st->addProperty('PlusPoints', $plusPoints);
        }

        $roots = $this->http->XPath->query("//li[./span[contains(.,'Award activity details')]]");

        foreach ($roots as $root) {
            $row = [];

            foreach ($keywords as $key => $field) {
                $value = $this->http->FindSingleNode("./div[starts-with(normalize-space(),'{$key}')]", $root, false,
                    "/{$key}(.*)/");

                if (isset($value) && $value !== null) {
                    if ($key === 'Activity date') {
                        $row[$field] = strtotime($value);
                    } else {
                        $row[$field] = $value;
                    }
                }
            }

            if (count($row) > 0) {
                $st->addActivityRow($row);
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//h2[contains(.,'Mileage activity details')]")->length > 0
            && $this->http->XPath->query("//a[contains(@href,'www.united.com')]")->length > 0
            && $this->http->XPath->query("//text()[normalize-space()='Activity']")->length > 0;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }
}
