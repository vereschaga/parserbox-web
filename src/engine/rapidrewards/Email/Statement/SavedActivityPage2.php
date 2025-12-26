<?php

namespace AwardWallet\Engine\rapidrewards\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class SavedActivityPage2 extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/statements/it-66754027.eml, rapidrewards/statements/it-67099174.eml, rapidrewards/statements/it-67158768.eml, rapidrewards/statements/it-67229515.eml, rapidrewards/statements/it-67366102.eml, rapidrewards/statements/it-69941017.eml, rapidrewards/statements/it-70035778.eml, rapidrewards/statements/it-70055949.eml";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->createStatement();

        $number = str_replace(' ', '',
            $this->http->FindSingleNode("(//text()[normalize-space()='Rapid Rewards #'])[1]/following-sibling::span[normalize-space()!=''][1]"));
        $st
            ->addProperty('Name',
                $this->http->FindSingleNode("(//h2[starts-with(normalize-space(),'Hi, ')])[1]", null, false,
                    "/Hi, (.+)!/"))
            ->addProperty('Number', $number)
            ->addProperty('Login', $number);
        $points = $this->http->FindSingleNode("(//div[normalize-space()='POINTS AVAILABLE' or normalize-space()='Points Available'])[1]/following-sibling::div[1]",
            null, false, "/^(\d.*?)\s*(Points|$)/i");
        $st
//            ->addProperty('Points', str_replace(",", '', $points)) // type of this field "YTD Miles/Points"?, it's not a balance
            ->setBalance(str_replace(",", '', $points));

        $funds = $this->http->FindSingleNode("(//div[normalize-space()='TRAVEL FUNDS AVAILABLE'])[1]/following-sibling::div[1]",
            null, false, "/^\D+\d[\d\., ]*/i");
        if (!empty($funds)) {
            $st
                ->addProperty('Funds', $funds);
        }

        if ($this->http->FindSingleNode("(//span[contains(@class,'my-account-progress-bar-tier')][contains(.,'flights') and contains(., 'out of')])[1]")) {
            $st
                ->addProperty('TierFlights',
                    $this->http->FindSingleNode("(//span[contains(@class,'my-account-progress-bar-tier')][contains(.,'flights')]//text()[normalize-space()!=''][contains(.,'out of')])[1]",
                        null, false, "/^(\d+) out of|/"))
                ->addProperty('TierPoints',
                    str_replace(",", '',
                        $this->http->FindSingleNode("(//span[contains(@class,'my-account-progress-bar-tier')][contains(.,'points')]//text()[normalize-space()!=''][contains(.,'out of')])[1]",
                            null, false, "/^([\d,]+) out of/")));
        }

        if ($this->http->FindSingleNode("(//span[contains(@class,'my-account-progress-bar-companion')][contains(.,'flights') and contains(., 'out of')])[1]")
        ) {
            $st
                ->addProperty('CPFlights',
                    $this->http->FindSingleNode("//span[contains(@class,'my-account-progress-bar-companion')][contains(.,'flights')]//text()[normalize-space()!=''][contains(.,'out of')]",
                        null, false, "/^(\d+) out of/"))
                ->addProperty('CPPoints',
                    str_replace(",", '',
                        $this->http->FindSingleNode("//span[contains(@class,'my-account-progress-bar-companion')][contains(.,'points')]//text()[normalize-space()!=''][contains(.,'out of')]",
                            null, false, "/^([\d,]+) out of/")));
        }

        // format_1
        $activityNodes = $this->http->XPath->query("(//h2[normalize-space()='Points activity'])[1]/following-sibling::div[1]//table//tr[not(contains(.,'DESCRIPTION') or contains(.,'Description'))]");

        foreach ($activityNodes as $root) {
            $row = [
                "Posting Date" => strtotime($this->http->FindSingleNode("./td[1]", $root)),
                "Description"  => $this->http->FindSingleNode("./td[2]", $root),
                //                "Category" => '????',
                "Total Miles" => str_replace([",", '−'], ['', '-'],
                    $this->http->FindSingleNode("./td[3]", $root, false, "/([+\-−]\d.*)/u")),
            ];

            if (strpos('Credit Card', $row['Description']) !== false) {
                $row['Category'] = 'Credit Card';
            } elseif (strpos('Partner Activity', $row['Description']) !== false) {
                $row['Category'] = 'Other';
            } elseif (strpos('Converted To Points', $row['Description']) !== false) {
                $row['Category'] = 'Other';
            } elseif (preg_match("/^[A-Z\d]{6}\s+\-\s+.+? [A-Z]{3} to .+? [A-Z]{3} .+?\/\d{2}\/\d{4}\s*$/",
                $row['Description'])) {
                $row['Category'] = 'Flight';
            } elseif (preg_match("/^REFUND \- [A-Z\d]{6}\s*$/", $row['Description'])) {
                $row['Category'] = 'Flight';
            }
            $st->addActivityRow($row);
        }

        if ($activityNodes->length === 0) {
            // format_2
            $activityNodes = $this->http->XPath->query("(//h2[normalize-space()='Points activity'])[1]/following::div[normalize-space()!=''][1]/descendant::div[contains(@class,'table') and (contains(.,'DESCRIPTION') or contains(.,'Description'))][1]//ol/li/descendant::div[count(./span)=4]");

            foreach ($activityNodes as $root) {
                $row = [
                    "Posting Date" => strtotime($this->http->FindSingleNode("./span[1]", $root)),
                    "Description"  => $this->http->FindSingleNode("./span[3]", $root),
                    "Category"     => $this->http->FindSingleNode("./span[2]", $root),
                    "Total Miles"  => str_replace(["−", ","], ['-', ''],
                        $this->http->FindSingleNode("./span[4]", $root, false, "/([+-−]\s*\d.*)/u")),
                ];
                $st->addActivityRow($row);
            }
        }

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return ($this->http->XPath->query('//h2[text() = "My Rapid Rewards"]')->length > 0
                || $this->http->XPath->query('//h2[text() = "Personal info"]')->length > 0
                || $this->http->XPath->query("//text()[normalize-space()='OUTBOUND']/ancestor::div[1]/following-sibling::div[1]/descendant::text()[normalize-space()!=''][1][normalize-space()='DEPART']")
            )
            && $this->http->XPath->query("//h2[starts-with(normalize-space(),'Hi, ')]")->length > 0
            && $this->http->XPath->query('//text()[normalize-space()="LAST ACTIVITY"]')->length === 0
            && ($this->http->XPath->query('//h2[text() = "My upcoming trips"]')->length > 0
                || $this->http->XPath->query('//h2[text() = "Travel related info"]')->length > 0
                || $this->http->XPath->query('//h2[text() = "Points activity"]')->length > 0
                || $this->http->XPath->query('//h2[starts-with(text(),"You have") and contains(.,\'upcoming trips\')]')->length > 0
            )
            && $this->http->XPath->query('//a[contains(normalize-space(),"Profile Details") and contains(@href,"https://www.southwest.com/loyalty/myaccount/profile-personal.html#personal-info")]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }
}
