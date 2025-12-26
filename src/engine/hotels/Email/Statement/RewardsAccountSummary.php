<?php

namespace AwardWallet\Engine\hotels\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parsers hotels/NextStatus, hotels/Offers (in favor of hotels/RewardsAccountSummary)

class RewardsAccountSummary extends \TAccountChecker
{
    public $mailFiles = "hotels/statements/it-61481218.eml, hotels/statements/it-66406654.eml, hotels/statements/it-66923919.eml";

    public static $dictionary = [
        'en' => [
            //            'Membership Number:' => "",
            'to get another reward night.' => ["to get another reward night.", "to get another free night."],
            'CollectNightsRe'              => "Collect (\d{1,2}) more nights",
            //            'Reward Nights' => "",
            'NightsToRedeemRe' => "You still have (\d+) reward nights to redeem\.",
        ],
        'zh' => [
            'Membership Number:'           => "會員編號:",
            'to get another reward night.' => ["就可以再獲得 1 晚免費住宿。"],
            'CollectNightsRe'              => "只要再集 (\d{1,2}) 晚",
            //            'Reward Nights' => "",
            //            'NightsToRedeemRe' => "You still have (\d+) reward nights to redeem\.",
        ],
    ];

    private $detectSubjects = [
        // zh
        'Rewards 帳戶摘要',
        // en
        'Rewards Account Summary',
    ];

    private $detectBody = [
        'zh' => [
            '您的帳戶摘要',
        ],
        'en' => [
            'Your Rewards Account Summary',
            'Thanks for joining Hotels.com',
        ],
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]hote(?:l|i)s\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Hotels.com') === false) {
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

        $xpathTable = '(self::div or self::table)';

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//text()[{$this->starts($this->t('Membership Number:'))}]/ancestor::*[{$xpathTable}][ following-sibling::*[{$xpathTable}][normalize-space()] ][1][ preceding-sibling::*[{$xpathTable}][normalize-space()]/descendant::text()[{$this->starts($detectBody)}] or following-sibling::*[{$xpathTable}][normalize-space()]/descendant::text()[{$this->starts($detectBody)}] ]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $t) {
            if (!empty($t['Membership Number:']) && $this->http->XPath->query("//text()[{$this->starts($t['Membership Number:'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }
        $email->setType('RewardsAccountSummary' . ucfirst($this->lang));

        $st = $email->add()->statement();

        // Number
        $number = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Membership Number:'))}]/following::text()[normalize-space()][1])[1]", null, true, "/^\d{5,}$/");

        if ($number === null) {
            $number = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Membership Number:'))}][1]", null, true, "/^{$this->preg_implode($this->t("Membership Number:"))}[:\s]+(\d{5,})$/");
        }

        if ($number !== null) {
            $st->setNumber($number);
        }

        // Balance
        $balance = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Reward Nights')) . "]/following::text()[normalize-space()][1]/ancestor::*[1]", null, true,
            "/^\s*" . $this->t('NightsToRedeemRe') . "\s*$/");

        //text()[normalize-space()='Reward Nights']/following::text()[normalize-space()][1]/ancestor::*[1]
        // Status
        if ($this->http->FindSingleNode("//text()[" . $this->starts($this->t('Membership Number:')) . "]/ancestor::tr[1][.//img]"
            . "[.//img[contains(@src, 'purple_Inv.png')] or ancestor::table[contains(@style, '#7B1FA2')]]")) {
            $st->addProperty('Status', "Member");
        }

        if ($this->http->FindSingleNode("//text()[" . $this->starts($this->t('Membership Number:')) . "]/ancestor::tr[1][.//img]"
            . "[.//img[contains(@src, 'silver_Inv.png')] or ancestor::table[contains(@style, '#4F6772')]]")) {
            $st->addProperty('Status', "Silver");
        }

        if ($this->http->FindSingleNode("//text()[" . $this->starts($this->t('Membership Number:')) . "]/ancestor::tr[1][.//img]"
            . "[.//img[contains(@src, 'gold_Inv.png')] or ancestor::table[contains(@style, '#8F6F32')]]")) {
            $st->addProperty('Status', "Gold");
        }

        // UntilNextFreeNight
        $collectNightsRe = $this->http->FindSingleNode("//text()[{$this->eq($this->t('to get another reward night.'))}]/preceding::text()[normalize-space()][1]", null, true, "/^\s*{$this->t("CollectNightsRe")}\s*$/");

        if ($collectNightsRe) {
            $st->addProperty('UntilNextFreeNight', $collectNightsRe);
        }
        //text()[normalize-space()='to get another reward night.']/preceding::text()[normalize-space()][1]

        $login = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Your login e-mail is:'))}]/following::text()[normalize-space()][1])[1]", null, true, "/^\S+@[-.A-z\d]+$/");

        if ($login) {
            $st->setLogin($login);
        }

        if ($balance) {
            $st->setBalance($balance);
        } elseif ($number || $login) {
            $st->setNoBalance(true);
        }

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

    private function preg_implode($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
