<?php

namespace AwardWallet\Engine\budgetair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "budgetair/it-820641876.eml, budgetair/it-845784987.eml";
    public $subjects = [
        'Votre paiement est validé',
    ];

    public $lang = '';

    public $detectLang = [
        'fr' => ['Détails du vol'],
        'en' => ['Flight details'],
    ];

    public static $dictionary = [
        "fr" => [
            'notContainsPrice' => ['Billet(s)', 'Remise', 'Total'],
        ],

        "en" => [
            'Détails du vol'         => 'Flight details',
            'Temps'                  => 'Time',
            'Passager(s)'            => 'Passenger(s)',
            'Numéro de réservation:' => 'Booking number:',
            //'enfant' => '',
            'Total'                              => 'Total',
            'Détails du prix'                    => 'Price details',
            'Billet(s)'                          => 'ticket(s)',
            'Remise'                             => 'Service package',
            'notContainsPrice'                   => ['Service package', 'Total', 'ticket(s)'],
            'Date'                               => 'Date',
            'Aéroport'                           => 'Airport',
            'Numéro de vol'                      => 'Flight number',
            'Départ de'                          => 'Departure from',
            'Vol de correspondance au départ de' => 'Connecting flight from',
            'Arrivée à'                          => 'Arrival in',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@t.budgetair.fr') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Budgetair')]")->length === 0
            && $this->http->XPath->query("//img[contains(@src, 'budgetair')]")->length === 0
            && $this->http->XPath->query("//a[contains(@href, 'budgetair')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Détails du vol'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Temps'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Aéroport'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Numéro de vol'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Détails du prix'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]t\.budgetair\.fr$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Passager(s)'))}]/ancestor::table[2]/following-sibling::table/descendant::text()[contains(normalize-space(), '(')][not({$this->contains($this->t('enfant'))})]", null, "/^(.+)\(/"))
            ->noConfirmation();

        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Numéro de réservation:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Numéro de réservation:'))}\s*([A-Z\d\-]+)$/"));

        $infants = $this->http->FindNodes("//text()[{$this->eq($this->t('Passager(s)'))}]/ancestor::table[2]/following-sibling::table/descendant::text()[{$this->contains($this->t('enfant'))}]", null, "/^(.+)\(/");

        if (count($infants) > 0) {
            $f->general()
                ->infants($infants);
        }

        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]/descendant::td[last()]");

        if (preg_match("/^(?<total>[\d\.\,]+)(?<currency>\D{1,3})$/", $total, $m)
        || preg_match("/^(?<currency>\D{1,3})(?<total>[\d\.\,]+)$/", $total, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $cost = array_sum($this->http->FindNodes("//text()[{$this->eq($this->t('Détails du prix'))}]/ancestor::table[2]/following-sibling::table[{$this->contains($this->t('Billet(s)'))}]/descendant::td[normalize-space()][last()]", null, "/^(?:\D{1,3})?\s*([\d\.\,]+)\s*(?:\D{1,3})?$/"));

            if ($cost !== null) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $discount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Détails du prix'))}]/ancestor::table[2]/following-sibling::table[{$this->contains($this->t('Remise'))}]/descendant::td[normalize-space()][last()]", null, true, "/^\s*\-\s*([\d\.\,]+)\s*\D{1,3}$/u");

            if ($discount !== null) {
                $f->price()
                    ->discount(PriceHelper::parse($discount, $currency));
            }

            $feeNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Détails du prix'))}]/ancestor::table[2]/following-sibling::table[not({$this->contains($this->t('notContainsPrice'))})]");

            foreach ($feeNodes as $feeRoot) {
                $feeName = $this->http->FindSingleNode("./descendant::td[1]", $feeRoot);
                $summ = $this->http->FindSingleNode("./descendant::td[last()]", $feeRoot, true, "/^(?:\D{1,3})?\s*([\d\.\,]+)\s*(?:\D{1,3})?$/");

                if (!empty($feeName) && $summ !== null) {
                    $f->price()
                        ->fee($feeName, $summ);
                }
            }
        }

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Date'))}]/ancestor::table[1]");

        foreach ($nodes as $root) {
            $segmentType = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root);
            $airport = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Aéroport'))}]/ancestor::tr[1]/descendant::td[2]", $root);
            $date = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Date'))}]/ancestor::tr[1]/descendant::td[2]", $root);
            $time = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Temps'))}]/ancestor::tr[1]/descendant::td[2]", $root);
            $airlineName = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Numéro de vol'))}]/ancestor::tr[1]/descendant::td[2]", $root, true, "/^((?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\d{2,4}$/");
            $flightNumber = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Numéro de vol'))}]/ancestor::tr[1]/descendant::td[2]", $root, true, "/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(\d{2,4})$/");

            if (preg_match("/{$this->opt($this->t('Départ de'))}\s+(?<depName>.+)/u", $segmentType, $m)) {
                $s = $f->addSegment();

                $s->airline()
                    ->name($airlineName)
                    ->number($flightNumber);

                $s->departure()
                    ->name($m['depName'] . ', ' . $airport)
                    ->date($this->normalizeDate($date . ', ' . $time))
                    ->noCode();
            }

            if (preg_match("/{$this->opt($this->t('Vol de correspondance au départ de'))}\s+(?<connectionName>.+)/u", $segmentType, $m)) {
                $s->arrival()
                    ->name($m['connectionName'] . ', ' . $airport)
                    ->noCode()
                    ->noDate();

                $s = $f->addSegment();

                $s->airline()
                    ->name($airlineName)
                    ->number($flightNumber);

                $s->departure()
                    ->name($m['connectionName'] . ', ' . $airport)
                    ->noCode()
                    ->date($this->normalizeDate($date . ', ' . $time));
            }

            if (preg_match("/{$this->opt($this->t('Arrivée à'))}\s+(?<arrName>.+)/u", $segmentType, $m)) {
                $s->arrival()
                    ->name($m['arrName'] . ', ' . $airport)
                    ->date($this->normalizeDate($date . ', ' . $time))
                    ->noCode();
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function assignLang()
    {
        foreach ($this->detectLang as $lang => $array) {
            foreach ($array as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeCurrency($s)
    {
        $string = trim($s);
        $currencies = [
            'GBP' => ['£'],
            'INR' => ['₹'],
            'AUD' => ['A$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar', 'US$'],
            'CAD' => ['C$'],
            'SGD' => ['S$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^[\w\-]+\,\s*(\d+)\.?\s*(?:de\s+)?(\w+)(?:\s+de)?\s*(\d{4})$#u", //Miércoles, 19 de mayo de 2021
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }
}
