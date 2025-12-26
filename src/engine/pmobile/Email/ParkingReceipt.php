<?php

namespace AwardWallet\Engine\pmobile\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ParkingReceipt extends \TAccountChecker
{
    public $mailFiles = "pmobile/it-32671711.eml";

    public $reFrom = ["alert@parkmobileglobal.com"];
    public $reBody = [
        'en'  => ['Thank you for using Parkmobile', 'Your Parking Receipt'],
        'en2' => ['Thank you for using Parkmobile', 'Your Payment is processed successfully'],
    ];
    public $reSubject = [
        'Your Parking Receipt',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'fees' => ['Parking fee', 'Transaction fee'],
        ],
    ];
    private $keywordProv = 'ParkMobile';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.parkmobile.com/images') or @alt='Parkmobile'] | //a[contains(@href,'parkmobile.com') or contains(@href,'parkmobile.us')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
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

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->parking();

        $r->general()
            ->confirmation($this->nextTD($this->t('Parking Ref')));

        $node = $this->nextTD($this->t('Description'));

        if (preg_match("/{$this->opt($this->t('Parking in'))} (?<location>\d+) {$this->t('at')} (?<date>.+)/",
            $node,
            $m)) {
            $r->place()
                ->location($m['location']);
            $r->booked()
                ->start(strtotime($m['date']));
        }

        if ('NA' !== $this->nextTD($this->t('End time'))) {
            $r->booked()
                ->end(strtotime($this->nextTD($this->t('End time'))));
        } else {
            $r->booked()
                ->noEnd();
        }

        $total = $this->getTotalCurrency($this->nextTD($this->t('Total')));
        $r->price()
            ->total($total['Total'])
            ->currency($total['Currency']);
        $fees = $this->t('fees');

        foreach ($fees as $fee) {
            $sum = $this->getTotalCurrency($this->nextTD($fee));

            if ((float) $sum['Total'] > 0) {
                $r->price()
                    ->fee($fee, $sum['Total']);
            }
        }

        return true;
    }

    private function nextTD($field, $root = null)
    {
        $field = (array) $field;

        return $this->http->FindSingleNode("./descendant::text()[{$this->eq($field)}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
            $root);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
