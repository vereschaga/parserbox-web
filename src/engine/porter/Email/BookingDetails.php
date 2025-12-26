<?php

namespace AwardWallet\Engine\porter\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingDetails extends \TAccountChecker
{
    public $mailFiles = "porter/it-686976366.eml, porter/it-712778625.eml, porter/it-771350563.eml, porter/it-784168989.eml";
    public $subjects = [
        'Booking details:',
        // fr
        'Détails de la réservation :',
    ];

    public $lang = 'en';
    public $lastDate = '';
    public $countSegments = 1;

    public static $dictionary = [
        "en" => [
            'Thank you for booking with Porter Airlines' => ['Thank you for booking with Porter Airlines'],
            'Your travel is confirmed.'                  => ['Your travel is confirmed.'],
            'Confirmation number'                        => ['Confirmation number', 'CONFIRMATION NUMBER'], // hidden
            // 'BOOKING STATUS' => '',
            'status'            => ['Confirmed'],
            'Manage My Booking' => ['Manage My Booking'],
            // 'Terminal' => '',
            'Duration' => 'Duration',
            // 'Operated by' => '',
            // 'Passengers' => '',
            'Seats' => ['SEATS', 'Seats'],
            // 'Member' => '',
            // 'VIPorter Member' => '',
            'Receipt summary' => ['Receipt summary'],
            // 'Flights' => '',
            // 'Taxes, fees, and any prepaid extras' => '',
            // 'Total' => '',
        ],
        "fr" => [
            'Thank you for booking with Porter Airlines' => ['Merci d’avoir réservé un vol auprès de Porter Airlines'],
            'Confirmation number'                        => ['Numéro de confirmation', 'NUMÉRO DE CONFIRMATION', 'numéro de confirmation'],
            'BOOKING STATUS'                             => 'État de la réservation',
            'status'                                     => ['Confirmée'],
            'Manage My Booking'                          => ['Gérer mes réservations'],
            'Terminal'                                   => 'Aérogare',
            'Duration'                                   => 'Durée',
            'Operated by'                                => 'Exploité par',
            'Passengers'                                 => 'Passagers',
            'Seats'                                      => ['Sièges', 'SIÈGES'],
            // 'Member' => '',
            // 'VIPorter Member' => '',
            'Receipt summary'                     => ['Sommaire du reçu'],
            'Flights'                             => 'Vols',
            'Taxes, fees, and any prepaid extras' => 'Taxes, frais et autres services payés d’avance',
            'Total'                               => 'Total',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@notifications.flyporter.com') !== false) {
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
        foreach (self::$dictionary as $dict) {
            if ($this->http->XPath->query("//a/@href[contains(., '.flyporter.com')]") === 0
                && (empty($dict['Thank you for booking with Porter Airlines'])
                || $this->http->XPath->query("//text()[{$this->contains($dict['Thank you for booking with Porter Airlines'])}]")->length === 0
                )) {
                continue;
            }

            if ($this->assignLang() === true) {
                return true;
            }
        }

        return false;
    }

    public function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Manage My Booking']) && !empty($dict['Duration'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Manage My Booking'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->starts($dict['Duration'])}]")->length > 0
                && (!empty($dict['Receipt summary']) && $this->http->XPath->query("//text()[{$this->eq($dict['Receipt summary'])}]")->length > 0
                    || !empty($dict['Your travel is confirmed.']) && $this->http->XPath->query("//node()[{$this->starts($dict['Your travel is confirmed.'])}]")->length > 0
                )
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]notifications\.flyporter\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->parseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::tr[1]/following-sibling::tr/descendant::*[self::p or div[not(.//div)]][{$this->eq($this->t('Seats'))}][1]/preceding::text()[normalize-space()][1]/ancestor::*[not({$this->contains($this->t('Seats'))})][last()]/descendant::text()[normalize-space()][1]");

        $f->general()
            ->travellers($travellers, true);

        $confs = $this->http->FindNodes("//text()[{$this->contains($this->t('Confirmation number'))}]/following::text()[normalize-space()][1]");

        foreach ($confs as $conf) {
            $confDesc = $this->http->FindSingleNode("//text()[{$this->eq($conf)}]/preceding::text()[normalize-space()][1]");
            $f->general()
                ->confirmation($conf, $confDesc);
        }

        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('BOOKING STATUS'))}]/ancestor::tr[1]/following::tr[1]/descendant::text()[normalize-space()][last()]", null, true, "/({$this->opt($this->t('status'))})/");

        if (!empty($status)) {
            $f->general()
                ->status($status);
        }

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/following::text()[normalize-space()][1]/ancestor::td[1]");

        if (preg_match("/^(?<currency>\D+)(?<total>[\d\.\,\']+)\s*\D*[\d\.\,]+$/", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $f->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m['total'], $currency));

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Flights'))}]/ancestor::tr[1]/descendant::td[2]");
            $cost = preg_replace("/^\s*(.+) \\1\s*$/", '$1', $cost);

            if (preg_match("/^\D*(\d[\d \,\.\']*?)\D*$/", $cost, $m)) {
                $f->price()
                    ->cost(PriceHelper::parse($m[1], $currency));
            }

            $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes, fees, and any prepaid extras'))}]/ancestor::tr[1]/descendant::td[2]");
            $tax = preg_replace("/^\s*(.+) \\1\s*$/", '$1', $tax);

            if (preg_match("/^\D*(\d[\d \,\.\']*?)\D*$/", $tax, $m)) {
                $f->price()
                    ->tax(PriceHelper::parse($m[1], $currency));
            }
        }

        $noImg = false;

        if ($this->http->XPath->query("//img/@href[not(starts-with(normalize-space(), 'cid:'))]")->length === 0) {
            $noImg = true;
        }

        $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Duration'))}]/ancestor::table[normalize-space()][4]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->http->FindSingleNode("./preceding::img[contains(@src, 'plane')][1]/ancestor::table[1]/preceding::text()[normalize-space()][1]", $root, true, "/^(.+\s\d{4})$/");

            if (empty($date) && $noImg === true) {
                $date = $this->http->FindSingleNode("./preceding::img[@width = 24 and @height = 24][1]/ancestor::table[1]/preceding::text()[normalize-space()][1]", $root, true, "/^(.+\s\d{4})$/");
            }

            if (empty($date)) {
                $date = $this->http->FindSingleNode("./preceding::img[1][contains(@style, '24px;')][ancestor::tr[1][count(*) = 3 and *[1][normalize-space()][not(.//img)]  and *[2][not(normalize-space())][count(.//img) = 1]  and *[3][normalize-space()][not(.//img)] ] ]"
                    . "/ancestor::table[1]/preceding::text()[normalize-space()][1]", $root, true, "/^(.+\s\d{4})$/");
            }

            if (!empty($date)) {
                $this->lastDate = $date;
                $this->countSegments = 1;
            } elseif (!empty($this->lastDate)) {
                $date = $this->lastDate;
                ++$this->countSegments;
            }

            $airlineText = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $root);

            if (stripos($airlineText, ':') !== false) {
                $airlineText = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root);
            }

            if (preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})$/", $airlineText, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $operator = $this->http->FindSingleNode("./descendant::text()[normalize-space()][last()]", $root, true, "/{$this->opt($this->t('Operated by'))}\s*(.+)/");

                if (!empty($operator)) {
                    $s->airline()
                        ->operator($operator);
                }
            }

            $s->extra()
                ->duration($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Duration'))}][1]", $root, true, "/{$this->opt($this->t('Duration'))}\s*(.+)/"), true, true);

            $re = "/^\s*\d{1,2}:\d{2}(?:\s*[AP]M)?(?:\s*[+\-]\s*\d)?\n[^\d\n]*\:\s*(?<time>\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*(?<nextDay>[+\-]\s*\d)?\n(?<name>.+)\s+\((?<code>[A-Z]{3})\)\n*(?:{$this->opt($this->t('Terminal'))}\s*(?<terminal>.*))?$/u";
            $depInfo = implode("\n", $this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Duration'))}][1]/ancestor::table[1]/preceding::table[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match($re, $depInfo, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date((!empty($date)) ? $this->normalizeDate($date . ', ' . $m['time']) : null);

                if (!empty($m['terminal'])) {
                    $s->departure()
                        ->terminal($m['terminal']);
                }
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Duration'))}][1]/ancestor::table[1]/following::table[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match($re, $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date((!empty($date)) ? $this->normalizeDate($date . ', ' . $m['time']) : null);

                if (!empty($s->getArrDate()) && !empty($m['nextDay'])) {
                    $s->arrival()
                        ->date(strtotime($m['nextDay'] . " day", $s->getArrDate()));
                }

                if (!empty($m['terminal'])) {
                    $s->arrival()
                        ->terminal($m['terminal']);
                }
            }

            foreach ($travellers as $pax) {
                $seat = $this->http->FindSingleNode("//text()[{$this->eq($pax)}]/following::text()[{$this->eq($s->getDepCode() . ' to ' . $s->getArrCode())}][1]/following::text()[starts-with(normalize-space(), '-') or starts-with(translate(translate(normalize-space(),' +()-',''),'0123456789','ddddddddddd'),'d')][{$this->countSegments}]", null, true, "/^(\d+[A-Z])$/");

                if (!empty($seat)) {
                    $s->extra()
                        ->seat($seat, false, false, $pax);
                }
            }
        }

        foreach ($travellers as $pax) {
            $account = $this->http->FindSingleNode("//text()[{$this->eq($pax)}]/following::text()[normalize-space()][1]");

            if (preg_match("/^\s*(\D+?)\s*(\d{5,})\s*$/", $account, $m)) {
                $f->addAccountNumber($m[2], false, $pax, $m[1]);
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

    private function normalizeDate($str)
    {
        // $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // mar. sept. 10, 2024, 15:15
            // jeu. août 08, 2024, 18:50
            "/^\s*[[:alpha:]]+[.]\s+([[:alpha:]]+)[.]?\s+(\d{1,2})\s*,\s*(\d{4})\s*,\s*(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*$/ui",
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
            'CAD' => ['CACanadian $'],
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
}
