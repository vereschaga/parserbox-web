<?php

namespace AwardWallet\Engine\payless\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RentalConfirmation extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = ["paylesscar.com"];
    public $reBody = [
        'en' => ['thank you for choosing Payless', 'Base Rate'],
    ];
    public $reSubject = [
        'Your Payless Rental Confirmation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Taxes' => ['Taxes', 'Local Tax'],
            'Fees'  => [
                'Charge Surcharge',
                'Airport Concession Fee',
                'Other Fee',
                'Optional Equipment',
                'Optional Coverages',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));
        $email->setProviderCode('payless');
        $email->setUserEmail($this->http->FindSingleNode("//text()[{$this->eq($this->t('E-mail Address'))}]/ancestor::td[1]/following-sibling::td[1]"));

        $xpath = "//text()[{$this->eq($this->t('Pick-up'))}]";

        if ($this->http->XPath->query($xpath)->length > 0) {
            $this->rental($email);
        }

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'carrental.com') or contains(@href,'Payless')] | //img[contains(@src,'Payless') or @alt='Payless']")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            if (stripos($headers["subject"], 'payless') === false) {
                $flag = false;

                foreach ($this->reFrom as $reFrom) {
                    if (stripos($headers['from'], $reFrom) !== false) {
                        $flag = true;
                    }
                }
            } else {
                $flag = true;
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
                }
            }
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

    protected function rental(Email $email)
    {
        $r = $email->add()->rental();
//        $r->program()
//            ->keyword($this->http->FindSingleNode("//text()[{$this->eq($this->t('Frequent Traveler Program'))}]/ancestor::td[1]/following-sibling::td[1]"))
//            ->accounts($this->http->FindNodes("//text()[{$this->eq($this->t('Frequent Traveler Number'))}]/ancestor::td[1]/following-sibling::td[1]",
//                null, "#^\s*([A-Z\d]{5,})\s*$#"), false);

        $node = implode("\n",
            $this->http->FindNodes("//text()[{$this->eq($this->t('Pick-up'))}]/ancestor::td[1]//text()[normalize-space(.)!='']"));

        if (preg_match("#^Pick\-up\s+([^\n]+)\n(.+?)\n\s*([\d\-\+\(\) ]+)\s*$#s", $node, $m)) {
            $r->pickup()
                ->location($this->nice($m[2]))
                ->date($this->normalizeDate($m[1]));
            $r->setPickUpPhone(trim($m[3]));
        }

        $node = implode("\n",
            $this->http->FindNodes("//text()[{$this->eq($this->t('Return'))}]/ancestor::td[1]//text()[normalize-space(.)!='']"));

        if (preg_match("#^Return\s+([^\n]+)\n(.+?)\n\s*([\d\-\+\(\) ]+)\s*$#s", $node, $m)) {
            $r->dropoff()
                ->location($this->nice($m[2]))
                ->date($this->normalizeDate($m[1]));
            $r->setDropOffPhone(trim($m[3]));
        }
        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Car Information'))}]/following::text()[normalize-space(.)!=''][1]");

        if (preg_match("#(.+?)\s*(?:\-\s*(.*)|)$#", $node, $m)) {
            $r->car()
                ->type($m[1]);

            if (isset($m[2]) && !empty($m[2])) {
                $r->car()
                    ->model($m[2]);
            }
        }

        if (!empty($node = $this->http->FindSingleNode("//text()[normalize-space(.)='Car Information']/ancestor::td[1]/img/@src",
            null, true, "#^https?:.+#"))
        ) {
            $r->car()
                ->image($node);
        }

        $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation Confirmation Number'))}]/ancestor::*[1]");

        if (preg_match("#({$this->opt($this->t('Reservation Confirmation Number'))})[\s:]+([A-Z\d]{5,})#", $node,
            $m)) {
            $r->general()
                ->confirmation($m[2], $m[1], true);
        }
        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Personal Information'))}]/following::text()[normalize-space(.)!=''][1][{$this->contains($this->t('Name'))}]/following::text()[normalize-space(.)!=''][1]");
        $r->general()
            ->travellers([$node], true);

        /*
        Base Rate	37.87 USD
        Taxes and Surcharges	26.66 USD ---sum above---


        Taxes	4.08 USD ---sum above---
        Local Tax	4.08 USD

        Surcharges	22.58 USD ---sum above---
        Charge Surcharge	1.33 USD
        Airport Concession Fee	4.21 USD
        Other Fee	15.00 USD
        Other Fee	2.04 USD
        Optional Equipment	0.00 USD
        Optional Coverages	0.00 USD

        Estimated Total	64.53 USD        */
        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Base Rate'))}]/ancestor::td[1]/following-sibling::td[1]");
        $totCost = $this->getTotalCurrency($node);

        if (!empty($totCost['Total'])) {
            $r->price()
                ->cost($totCost['Total'])
                ->currency($totCost['Currency']);
        }

        $node = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Taxes'))}]/ancestor::td[1]/following-sibling::td[1])[1]");
        $totTax = $this->getTotalCurrency($node);

        if (!empty($totTax['Total'])) {
            $r->price()
                ->tax($totTax['Total'])
                ->currency($totTax['Currency']);
        }

        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Estimated Total'))}]/ancestor::td[1]/following-sibling::td[1]");
        $tot = $this->getTotalCurrency($node);

        if (!empty($tot['Total'])) {
            $r->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }
        $fees = (array) $this->t('Fees');

        foreach ($fees as $fee) {
            $nodes = $this->http->FindNodes("//text()[{$this->eq($fee)}]/ancestor::td[1]/following-sibling::td[1]");

            foreach ($nodes as $value) {
                $tot = $this->getTotalCurrency($value);

                if (!empty($tot['Total'])) {
                    $r->price()
                        ->fee($fee, $tot['Total']);
                }
            }
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            //Tue May 01, 2018 at 1:00 PM
            '#^[\w\-]+\s+(\w+)\s+(\d+),\s+(\d{4})\s+at\s+(\d+:\d+(?:\s*[ap]m)?)\s*$#ui',
        ];
        $out = [
            '$2 $1 $3, $4',
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

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
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
