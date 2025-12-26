<?php

namespace AwardWallet\Engine\triprewards\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ActivityOnAccount extends \TAccountChecker
{
    public $mailFiles = "triprewards/it-35195566.eml";

    public $reFrom = ["wyndhamrewards.com"];
    public $reBody = [
        'en' => ['You successfully redeemed', 'Redemption Confirmation'],
    ];
    public $reSubject = [
        'Activity on your Wyndham Rewards account',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Check-in'  => 'Check-in',
            'Check-out' => 'Check-out',
        ],
    ];
    private $keywordProv = 'Wyndham Rewards';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!$this->parseEmail($email)) {
            return null;
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || strpos($headers["subject"], $this->keywordProv) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'wyndhamhotelgroup')]")->length > 0
            && $this->detectBody($parser->getHTMLBody())
            && $this->assignLang()
        ) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->hotel();

        $r->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]/following::text()[normalize-space()!=''][1]"),
                false);

        $r->program()
            ->account($this->http->FindSingleNode("//text()[{$this->eq($this->t('Member'))}]/ancestor::td[1]", null,
                false, "#Member[\# ]+([A-Z\d]+)#"), false)
            ->phone(str_replace('‑', '-',
                $this->http->FindSingleNode("//text()[{$this->contains($this->t('please call Member Services at'))}]",
                    null, false,
                    "#{$this->opt($this->t('please call Member Services at'))}\s+([\d\-\+\(\)‑]{5,})[\s\.]*$#")),
                $this->t('If you have any questions about your award'));

        $nodes = $this->http->FindNodes("//text()[{$this->eq($this->t('Check-in'))}]/preceding::tr[normalize-space()!=''][1]//text()[normalize-space()!='']");

        if (count($nodes) !== 2) {
            $this->logger->debug("other format hotel Name");

            return false;
        }
        $hotelName = preg_replace("#[\s\-]+An All\-inclusive Resort\s*$#", '', $nodes[0]);
        $r->hotel()
            ->address($nodes[1])
            ->name($hotelName);

        $r->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in'))}]/following::text()[normalize-space()!=''][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out'))}]/following::text()[normalize-space()!=''][1]")));

        $points = $this->http->FindSingleNode("//text()[{$this->starts($this->t('You successfully redeemed'))}]", null,
            false, "#{$this->opt($this->t('You successfully redeemed'))}\s+(\d[\d\,]+)\b#");
        $r->price()
            ->spentAwards($points . ' points');

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //03/22/2019
            '#^\s*(\d+)\/(\d+)\/(\d{4})\s*$#',
        ];
        $out = [
            '$3-$1-$2',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Check-in"], $words["Check-out"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Check-in'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Check-out'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
