<?php

namespace AwardWallet\Engine\hotels\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parsers hotels/Offers, hotels/RewardsAccountSummary (in favor of hotels/RewardsAccountSummary)

class NextStatus extends \TAccountChecker
{
    public $mailFiles = "hotels/statements/it-61455404.eml";

    public static $dictionary = [
        'en' => [
            //            'Membership Number:' => "",
            'Free¹ night to redeem'                        => ["Free¹ night to redeem", "Free¹ nights to redeem", "Reward¹ night to redeem"],
            'NightsToRedeemRe'                             => "You still have (\d+) reward nights? to redeem\.",
            "nights away from earning another free¹ night" => [
                "nights away from earning another free¹ night",
                "nights away from earning another reward¹ night",
                "night away from earning another free¹ night",
                "night away from earning another reward¹ night",
                "night away from earning a free¹ night",
                "night away from earning a reward¹ night",
            ],
            'NightFromFreeRe' => "You are (\d{1,2}) nights? away from earning (?:another|a) (?:free|reward)¹ night!",
        ],
        'no' => [
            'Membership Number:'    => "Medlemsnummer:",
            'Free¹ night to redeem' => ["bonusovernatting¹ å løse inn"],
            //            'NightsToRedeemRe' => "You still have (\d+) reward nights? to redeem\.",
            "nights away from earning another free¹ night" => [
                "netter fra enda en bonusovernatting!¹",
            ],
            'NightFromFreeRe' => "Du er (\d{1,2}) netter fra enda en bonusovernatting!¹",
        ],
    ];

    private $detectSubjects = [
        // en
        "You're about to reach Hotels.com Rewards Silver!",
        "You've reached Hotels.com Rewards Silver status!",
        // no
        "Du har nådd nivået Hotels.com Rewards Silver!",
    ];

    private $detectBody = [
        'en' => [
            'You’ve qualified for Hotels.com® Rewards',
        ],
        "no" => [
            'Du er kvalifisert til å motta Hotels.com™ Rewards',
        ],
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]hote(?:l|i)s\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubjects) {
            if (stripos($headers['subject'], $dSubjects) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'click.mail.hotels.com')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//text()[" . $this->starts($this->t("Membership Number:")) . "]/ancestor::div[2]/following-sibling::div[normalize-space()]//text()[" . $this->eq($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $t) {
            if (!empty($t['Membership Number:']) && !empty($this->http->FindSingleNode("//text()[" . $this->eq($t["Membership Number:"]) . "]"))) {
                $this->lang = $lang;

                break;
            }
        }

        $st = $email->add()->statement();

        // Number
        $number = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Membership Number:")) . "]/following::text()[normalize-space()][1]",null, true,
            "/^\s*(\d{5,12})\s*$/");
        $st->addProperty('Number', $number);

        // Balance
        $balance = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Free¹ night to redeem")) . "]/preceding::text()[normalize-space()][1]/ancestor::*[1]",null, true,
            "/^\s*(\d+)\s*$/");

        if (empty($balance) && !empty($number)) {
            $st->setNoBalance(true);
        } else {
            $st->setBalance($balance);
        }

        // Status
        if ($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Membership Number:")) . "]/ancestor::tr[1][.//img]"
            . "[.//img[contains(@src, 'purple_Inv.png')] or ancestor::table[contains(@style, '#7B1FA2')]]")) {
            $st->addProperty('Status', "Member");
        }

        if ($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Membership Number:")) . "]/ancestor::tr[1][.//img]"
            . "[.//img[contains(@src, 'silver_Inv.png')] or ancestor::table[contains(@style, '#4F6772')]]")) {
            $st->addProperty('Status', "Silver");
        }

        if ($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Membership Number:")) . "]/ancestor::tr[1][.//img]"
            . "[.//img[contains(@src, 'gold_Inv.png')] or ancestor::table[contains(@style, '#8F6F32')]]")) {
            $st->addProperty('Status', "Gold");
        }

        // UntilNextFreeNight
        $st->addProperty('UntilNextFreeNight',
            $this->http->FindSingleNode("//text()[" . $this->contains($this->t("nights away from earning another free¹ night")) . "]", null, true,
                "/^\s*" . $this->t("NightFromFreeRe") . "\s*$/"));
        //text()[normalize-space()='to get another reward night.']/preceding::text()[normalize-space()][1]

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
