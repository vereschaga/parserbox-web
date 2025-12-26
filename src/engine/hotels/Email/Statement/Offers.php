<?php

namespace AwardWallet\Engine\hotels\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parsers hotels/NextStatus, hotels/RewardsAccountSummary (in favor of hotels/RewardsAccountSummary)

class Offers extends \TAccountChecker
{
    public $mailFiles = "hotels/statements/it-61148101.eml, hotels/statements/it-61415854.eml, hotels/statements/it-73946082.eml, hotels/statements/it-74147518.eml, hotels/statements/it-76319877.eml";

    public static $dictionary = [
        'en' => [
            //            'Membership Number:' => "",
            'This advertisement was sent to:' => ['This advertisement was sent to:', 'This email was sent to:'],
        ],
    ];

    private $detectSubjects = [
        // en
        'Get twice the rewards – ',
        'Get your reward night faster',
        "! You're a Silver member", // Congratulations Carlos! You're a Silver member
        "Take a trip (… not to the grocery store)",
        'Welcome back from',
        'Insta-worthy properties around the world',
        'Stay flexible this year with free cancellation',
    ];

    private $detectBody = [
        'en' => [
            'Collect double stamps for each night you stay',
            '! You\'re a Silver member.',
            'Where will you travel to next',
            'Some of the most picture-perfect properties in the world',
            'Book with confidence – free cancellation on most hotels',
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
            if ($this->http->XPath->query("//text()[" . $this->starts($this->t("Membership Number:")) . "]/ancestor::div[2]/following-sibling::div[normalize-space()]//text()[" . $this->contains($detectBody) . "]")->length > 0
                || $this->http->XPath->query("//text()[" . $this->starts($this->t("Membership Number:")) . "]/following::text()[" . $this->contains($detectBody) . "]")->length > 0
                || $this->http->XPath->query("//text()[" . $this->starts($this->t("Nice work,")) . "]/following::text()[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        // Number
        $number = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Membership Number:")) . "]", null, true,
            "/" . $this->preg_implode($this->t("Membership Number:")) . "\s*(\d{5,12})\s*$/");

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Membership Number:")) . "]/following::text()[normalize-space()][1]", null, true,
                "/\s*(\d{5,12})\s*$/");
        }

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This advertisement was sent to:'))}]/ancestor::*[1]", null, true, "/{$this->opt($this->t('This advertisement was sent to:'))}\s*(\S+[@]\S+\.\S+)/");
        $st->setLogin($login);

        $st->setNoBalance(true);

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Nice work,')]/ancestor::*[1]", null, true, "/{$this->opt($this->t('Nice work,'))}\s*(\D+)\!/u");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        // Status
        if ($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Membership Number:")) . "]/ancestor::div[2][.//img]"
            . "[.//img[contains(@src, 'purple-Inv.png')] or contains(@style, '#7B1FA2')]")) {
            $st->addProperty('Status', "Member");
        }

        $this->logger->error("//text()[" . $this->starts($this->t("Membership Number:")) . "]/ancestor::div[2][.//img]" . "[.//img[contains(@src, 'silver-Inv.png')] or contains(@style, '#4F6772')]");

        if ($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Membership Number:")) . "]/ancestor::div[2][.//img]"
            . "[.//img[contains(@src, 'silver-Inv.png')] or contains(@style, '#4F6772')]")) {
            $st->addProperty('Status', "Silver");
        }

        if ($this->http->XPath->query("//text()[{$this->starts($this->t("Membership Number:"))}]/preceding::img[contains(@src, 'silver_Inv.png')]")->length > 0) {
            $st->addProperty('Status', "Silver");
        }

        if ($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Membership Number:")) . "]/ancestor::div[2][.//img]"
            . "[.//img[contains(@src, 'gold-Inv.png')] or contains(@style, '#8F6F32')]")) {
            $st->addProperty('Status', "Gold");
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

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }
}
