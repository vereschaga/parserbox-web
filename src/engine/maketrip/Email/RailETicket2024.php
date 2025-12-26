<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class RailETicket2024 extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-642760657.eml, maketrip/it-649547044.eml, maketrip/it-649552716.eml, maketrip/it-667971454.eml";

    public static $detectProvider = [
        'maketrip' => [
            'from'    => ['@makemytrip.com', 'MakeMyTrip'],
            'subject' => ['MakeMyTrip'],
            'link'    => '.makemytrip.com',
            'imgAlt'  => ['mmt_logo', 'MakeMyTrip'], // =
            'imgSrc'  => ['.mmtcdn.com'], // contains
            'text'    => ['MakeMyTrip'],
        ],
        'goibibo' => [
            'from'    => ['@goibibo.com'],
            'subject' => ['Goibibo'],
            'link'    => ['goibibo.com', 'go.ibi.bo/'],
            'imgAlt'  => [], // =
            'imgSrc'  => ['goibibo-logo.png'], // contains
            'text'    => ['Goibibo'],
        ],
        'hdfc' => [
            'from'    => ['@smartbuyoffers.co'],
            'subject' => ['SmartBuy'],
            'link'    => ['smartbuyoffers.co'],
            'imgAlt'  => [], // =
            'imgSrc'  => ['/smartbuy.png'], // contains
            'text'    => ['@irctc.co.in'],
        ],
    ];

    public $emailSubject;
    public $detectSubject = [
        'rail e-ticket for booking id',
    ];

    public static $dictionary = [
        'en' => [
            'MakeMyTrip ID:' => ['MakeMyTrip ID:', 'Goibibo ID:'],
            'Booked From:'   => ['Booked From:', 'Booked From'],
            'To:'            => ['To:', 'To'],
        ],
    ];

    private $providerCode;
    private $lang = 'en';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // $this->assignLang();

        $this->emailSubject = $parser->getSubject();
        $this->parseEmail($email);

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $foundProvider = false;

        foreach (self::$detectProvider as $code => $providerParams) {
            if ($this->http->XPath->query("//img[" . $this->eq($providerParams['imgAlt'], '@alt') . " or " . $this->contains($providerParams['imgSrc'], '@src') . "]")->length > 0
                || $this->http->XPath->query("//a[" . $this->contains($providerParams['link'], '@href') . "]")->length > 0) {
                $this->providerCode = $code;
                $foundProvider = true;

                break;
            }
        }

        if ($foundProvider === false) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Booked From:']) && !empty($dict['To:'])
                && $this->http->XPath->query("//*[*[1]/descendant::text()[normalize-space()][1][{$this->eq($dict['Booked From:'])}]][*[3]/descendant::text()[normalize-space()][1][{$this->eq($dict['To:'])}]]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectProvider as $code => $providerParams) {
            if (
                !empty($headers["from"]) && $this->striposAll($headers["from"], $providerParams['from']) === false
                || !empty($headers["subject"]) && $this->striposAll($headers["subject"], $providerParams['subject']) === false
            ) {
                continue;
            }
            $this->providerCode = $code;

            foreach ($this->detectSubject as $dSubject) {
                if (stripos($headers["subject"], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@makemytrip.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email)
    {
        // Travel Agency
        $tripNum = $this->http->FindSingleNode("//text()[{$this->starts($this->t('MakeMyTrip ID:'))}]",
            null, true, "/^\s*{$this->opt($this->t('MakeMyTrip ID:'))}\s*([A-Z\d\-]{5,})\s*$/");

        if (empty($tripNum) && (
            preg_match("/for booking id\s*-\s*([A-Z\d]{6,})\s*,\s*PNR/", $this->emailSubject, $m)
            || preg_match("/Order Ref Number ?:\s*([A-Z\d]{6,})\s+Train ?:/", $this->emailSubject, $m)
        )) {
            $tripNum = $m[1];
        }

        $email->ota()
            ->confirmation($tripNum);

        $t = $email->add()->train();

        // General
        $conf = $this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('PNR'))}]]/following-sibling::tr[1]/*[1]",
            null, true, "/^\s*(\d{5,})\s*$/");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//tr[count(*) = 3]/*[1][descendant::text()[normalize-space()][1][{$this->eq($this->t('PNR'))}]]/descendant::text()[normalize-space()][2]",
                null, true, "/^\s*(\d{5,})\s*$/");
        }
        $t->general()
            ->confirmation($conf)
            ->travellers($this->http->FindNodes("//tr[*[1][{$this->eq($this->t('#'))}]][*[2][{$this->eq($this->t('Name'))}]]/following-sibling::tr/*[2]"), true)
        ;

        $s = $t->addSegment();

        // Departure
        $departure = $this->http->FindSingleNode("//tr[count(*) = 3][*[1][{$this->starts($this->t('Booked From:'))}]][*[3][{$this->starts($this->t('To:'))}]]/*[2]");

        if (preg_match("/^\s*(?:{$this->opt($this->t('Boarding At'))}\s*)?(?<name>.+?)\s*{$this->opt($this->t('Departure'))}\*?\s*(?<date>.+?)\s*$/", $departure, $m)) {
            $s->departure()
                ->name($m['name'])
                ->date($this->normalizeDate($m['date']));
        }

        // Arrival
        $arrival = $this->http->FindSingleNode("//tr[count(*) = 3][*[1][{$this->starts($this->t('Booked From:'))}]][*[3][{$this->starts($this->t('To:'))}]]/*[3]");

        if (preg_match("/^\s*{$this->opt($this->t('To:'))}\s*(?<name>.+?)\s*{$this->opt($this->t('Arrival'))}\*?\s*(?<date>.+?)\s*$/", $arrival, $m)) {
            $s->arrival()
                ->name($m['name'])
                ->date($this->normalizeDate($m['date']));
        }

        // Extra
        $name = $this->http->FindSingleNode("//tr[*[2][{$this->eq($this->t('Train No./Name'))}]]/following-sibling::tr[1]/*[2]");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//tr[count(*) = 3]/*[2][descendant::text()[normalize-space()][1][{$this->eq($this->t('Train No./Name'))}]]/descendant::text()[normalize-space()][2]");
        }

        if (preg_match("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", $name, $m)) {
            $s->extra()
                ->number($m[1])
                ->service($m[2]);
        }

        $cabin = $this->http->FindSingleNode("//tr[*[3][{$this->eq($this->t('Class'))}]]/following-sibling::tr[1]/*[3]");

        if (empty($cabin)) {
            $cabin = $this->http->FindSingleNode("//tr[count(*) = 3]/*[2][descendant::text()[normalize-space()][1][{$this->eq($this->t('Train No./Name'))}]]/descendant::text()[normalize-space()][2]");
        }
        $s->extra()
            ->cabin($cabin);

        // Price
        $total = $this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Total Fare (all inclusive)'))}]]/*[2]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $t->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['currency']))
            ;
        } else {
            $t->price()
                ->total(null);
        }

        $total = $this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Ticket Fare'))}]]/*[2]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $t->price()
                ->cost($this->amount($m['amount']))
            ;
        }

        $feeNodes = $this->http->XPath->query("//tr[*[1][{$this->eq($this->t('Ticket Fare'))}]]/following-sibling::*[following-sibling::tr[*[1][{$this->eq($this->t('Total Fare (all inclusive)'))}]]]");

        foreach ($feeNodes as $fRoot) {
            $name = $this->http->FindSingleNode('*[1]', $fRoot);
            $value = $this->http->FindSingleNode('*[2]', $fRoot);

            if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $value, $m)
            ) {
                $t->price()
                    ->fee($name, $this->amount($m['amount']));
            }
        }

        return $email;
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('normalizeDate = '.print_r( $date,true));
        $in = [
            // 16:10 26 Mar 2024
            "/^\s*(\d{1,2}:\d{2})\s+(\d+\s+[[:alpha:]]+\s+\d{4})\s*$/ui",
        ];
        $out = [
            "$2, $1",
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('normalizeDate 2 = '.print_r( $date,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function striposAll($text, $needle): bool
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

    private function eq($field, string $node = 'normalize-space()'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return $node . ' = "' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = 'normalize-space()'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(' . $node . ', "' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, string $node = 'normalize-space()'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(' . $node . ', "' . $s . '")';
        }, $field)) . ')';
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '₹' => 'INR',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
