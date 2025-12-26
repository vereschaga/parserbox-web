<?php

namespace AwardWallet\Engine\rapidrewards\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class SavedActivityPage3 extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/statements/it-67089584.eml, rapidrewards/statements/it-67089606.eml";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->createStatement();

        $name = $this->http->FindSingleNode("//h2[starts-with(normalize-space(),'Hi, ')]", null, false, "/Hi, (.+)!/");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }
        $st
            ->addProperty('Number',
                $this->http->FindSingleNode("//text()[normalize-space()='RAPID REWARDS NUMBER']/ancestor::div[1]/following-sibling::div[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1]"))
            ->addProperty('Login',
                $this->http->FindSingleNode("//text()[normalize-space()='RAPID REWARDS NUMBER']/ancestor::div[1]/following-sibling::div[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1]"))
            ->addProperty('LastActivity',
                strtotime($this->http->FindSingleNode("//text()[normalize-space()='LAST ACTIVITY']/ancestor::div[1]/following-sibling::div[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1]")));

        if ($this->http->FindSingleNode("//span[contains(@class,'my-account-progress-bar-tier')][contains(.,'flights')]",
            null, false, '/\d/') // exclude if compleete
        ) {
            $st
                ->addProperty('TierFlights',
                    $this->http->FindSingleNode("//span[contains(@class,'my-account-progress-bar-tier')][contains(.,'flights')]",
                        null, false, "/^(\d+) out of|/"))
                ->addProperty('TierPoints',
                    str_replace(",", '',
                        $this->http->FindSingleNode("//span[contains(@class,'my-account-progress-bar-tier')][contains(.,'points')]",
                            null, false, "/^([\d,]+) out of/")
                    ));
        }

        if ($this->http->FindSingleNode("//span[contains(@class,'my-account-progress-bar-companion')][contains(.,'flights')]",
            null, false, '/\d/') // exclude if compleete
        ) {
            $st
                ->addProperty('CPFlights',
                    $this->http->FindSingleNode("//span[contains(@class,'my-account-progress-bar-companion')][contains(.,'flights')]",
                        null, false, "/^(\d+) out of/"))
                ->addProperty('CPPoints',
                    str_replace(",", '',
                        $this->http->FindSingleNode("//span[contains(@class,'my-account-progress-bar-companion')][contains(.,'points')]",
                            null, false, "/^([\d,]+) out of/")
                    ));
        }

        $points = $this->http->FindSingleNode("(//div[normalize-space()='POINTS AVAILABLE' or normalize-space()='Points Available'])[1]/following-sibling::div[1]",
            null, false, "/^(\d.+?)\s*(Points|$)/i");

        if (empty($points)) {
            $points = $this->http->FindSingleNode("//div[@class='price']/span[contains(@class,'currency_points')]");
        }
        $st
            ->addProperty('Points', str_replace(",", '', $points))
            ->setBalance(str_replace(",", '', $points));

        $activityNodes = $this->http->XPath->query("//div[normalize-space()='DATE']/ancestor::div[1][contains(.,'CATEGORY') and contains(.,'DESCRIPTION')]/ancestor-or-self::div/following-sibling::ol//li");
        $this->logger->debug("found " . $activityNodes->length . ' rows activity');

        foreach ($activityNodes as $root) {
            $col = $this->http->FindNodes("./descendant::div[./span]/span", $root);

            if (count($col) !== 4) {
                $st->addProperty('NeedBrokeParse', null);

                continue;
            }
            $row = [
                "Posting Date" => strtotime(preg_replace("/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/", '$1' . '20$3-$1-$2',
                    $col[0])),
                "Description" => $col[2],
                "Category"    => $col[1],
                "Total Miles" => str_replace(["−", ","], ['-', ''], $col[3]),
            ];
            $st->addActivityRow($row);
        }

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//h3[text() = "A-List progress"]')->length > 0
            && $this->http->XPath->query('//h3[text() = "Companion Pass progress" or text() = "Companion Pass achieved"]')->length > 0
            && $this->http->XPath->query('//h2[text() = "My upcoming trips"]')->length === 0
            && $this->http->XPath->query('//text()[normalize-space()="LAST ACTIVITY"]')->length > 0
            && $this->http->XPath->query('//div[normalize-space()="RAPID REWARDS STATUS"]/following-sibling::div[1][.//a[contains(.,"See profile details")]]')->length > 0
            && $this->http->XPath->query('//a[contains(normalize-space(),"See profile details") and contains(@href,"https://www.southwest.com/loyalty/myaccount/profile-personal.html#personal-info")]')->length > 0;
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
