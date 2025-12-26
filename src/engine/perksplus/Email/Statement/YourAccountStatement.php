<?php

namespace AwardWallet\Engine\perksplus\Email\Statement;

// TODO: delete what not use
use AwardWallet\Schema\Parser\Email\Email;

class YourAccountStatement extends \TAccountChecker
{
    public $mailFiles = "perksplus/statements/it-338593230.eml, perksplus/statements/it-349495678.eml, perksplus/statements/it-353802815.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'PerksPlus Points'     => 'PerksPlus Points',
            'Points balance as of' => ['Statement balance as of', 'Points balance as of'],
            'Last Air Activity:'   => ['Last Air Activity:', 'Last air activity:'],
        ],
    ];

    private $detectFrom = ["unitedperksplus@united.com", "unitedforbusiness@go.united.com"];
    private $detectSubject = [
        // en
        ', your account statement ✈️',
        ', your monthly account overview is here',
    ];

    private $detectCompany = [
        // en
        'account with United PerksPlus',
        'monthly overview of your United PerksPlus account',
    ];

    public function detectEmailFromProvider($from)
    {
        return $this->containsText($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['go.unitedforbiz.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//node()[{$this->contains($this->detectCompany)}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Points balance as of']) && !empty($dict['PerksPlus Points'])
                && $this->http->XPath->query("//*[{$this->contains($dict['Points balance as of'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['PerksPlus Points'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmailHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function parseEmailHtml(Email $email)
    {
        $st = $email->add()->statement();

        $companyName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Company:'))}]/following::text()[normalize-space()][1]");

        if (!preg_match("/(^\s*Account|.+:\s*$)/i", $companyName)) {
            $st->addProperty('CompanyName', $companyName);
        }

        $number = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Account ID:'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*([A-Z\d]+)\s*$/");
        $st->setNumber($number)
            ->setLogin($number);

        $points = $this->http->FindSingleNode("//text()[{$this->eq($this->t('PerksPlus Points'))}]/preceding::text()[normalize-space()][1]",
            null, true, "/^\s*(\d+)\s*$/");
        $st->setBalance($points);

        $date = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Points balance as of'))}]",
            null, true, "/^\s*{$this->opt($this->t('Points balance as of'))} (.{5,}?)(?:\. |\s*$)/"));
        $st->setBalanceDate($date);

        // SpendYTD
        $spendYTD = $this->http->FindSingleNode("//text()[{$this->eq($this->t('PerksPlus Points'))}]/following::text()[normalize-space()][1][following::text()[normalize-space()][1][starts-with(normalize-space(), '20') and contains(., ' Spend')]]",
            null, true, "/^\s*(.*\d+.*)\s*$/");

        if (!empty($spendYTD)) {
            $st->addProperty('SpendYTD', $spendYTD);
        }
        // LastPointsRedemption
        $lastPointsRedemption = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Last Points Redemption:'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(.*\d+.*)\s*$/");

        if (!empty($lastPointsRedemption)) {
            $st->addProperty('LastPointsRedemption', $this->normalizeDate($lastPointsRedemption));
        }
        // LastAirActivity
        $lastAirActivity = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Last Air Activity:'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(.*\d+.*)\s*$/");

        if (!empty($lastAirActivity)) {
            $st->addProperty('LastAirActivity', $this->normalizeDate($lastAirActivity));
        }
        // TourCode
        $tourCode = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Tour Code:'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*([A-Z\d]+)\s*$/");
        $st->addProperty('TourCode', $tourCode);

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r($date, true));

        $in = [
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1 :43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date end = ' . print_r($date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
