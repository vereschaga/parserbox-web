<?php

namespace AwardWallet\Engine\turkish\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OnlineTicket extends \TAccountChecker
{
    public $mailFiles = "turkish/it-153731479.eml, turkish/it-154439353.eml, turkish/it-193164096.eml, turkish/it-828738567.eml, turkish/it-828785091.eml";

    private $detectSubject = [
        // en
        'Turkish Airlines - Online Ticket - Information Message',
        // de
        'Turkish Airlines – Online-Ticket – Informationsmeldung',
        // tr
        'Türk Hava Yolları - Online Bilet - Bilgi Mesajı',
        // ru
        'Turkish Airlines — Электронный билет — Информационное сообщение',
    ];

    private static $dictionary = [
        'en' => [
            //            'Dear' => '',
            //            'Booking Reference' => '',
            'Journey Duration' => ['Journey Duration', 'Journey duration'],
            //            'Seat' => '',
            //            'Baggage' => '',
            'Miles' => ['MIL', 'MILES'],
            //            'Taxes and other charges' => '',
            //            'Additional Services' => '',
            'TOTAL:'            => ['TOTAL:', 'TOTAL :'],
            'Request e-Invoice' => ['Request e-Invoice', 'Request e-invoice'],
            //'Direct flight' => '',
        ],
        'de' => [
            'Dear'                    => 'Sehr geehrte/r',
            'Booking Reference'       => 'Buchungsreferenz',
            'Journey Duration'        => 'Reisedauer',
            'Seat'                    => 'Sitzplatz',
            'Baggage'                 => 'Gepäck',
            'Miles'                   => ['MIL', 'MILES'],
            'Taxes and other charges' => 'Steuern und andere Gebühren',
            //            'Additional Services' => '',
            'TOTAL:'            => 'GESAMT:',
            'Request e-Invoice' => 'Elektronische Rechnung anfordern',
            //'Direct flight' => '',
        ],
        'tr' => [
            'Dear'                    => 'Sayın',
            'Booking Reference'       => 'Rezervasyon kodu',
            'Journey Duration'        => ['Yolculuk Süresi', 'Yolculuk süresi'],
            'Seat'                    => 'Koltuk',
            'Baggage'                 => 'Bagaj',
            'Miles'                   => ['MIL', 'MILES'],
            'Taxes and other charges' => 'Vergi ve diğer harçlar',
            'Additional Services'     => 'Ek Hizmetler',
            'TOTAL:'                  => ['TOPLAM:', 'Toplam :'],
            'Request e-Invoice'       => ['E-Fatura Talep Et', 'E-fatura talep et'],
            //'Direct flight' => '',
        ],
        'ru' => [
            'Dear'                    => 'Уважаемый(ая)',
            'Booking Reference'       => 'Номер бронирования',
            'Journey Duration'        => 'Продолжительность путешествия',
            'Seat'                    => 'Место',
            'Baggage'                 => 'Багаж',
            'Miles'                   => ['MIL', 'MILES'],
            'Taxes and other charges' => 'Налоги и другие сборы',
            'Additional Services'     => 'Дополнительные услуги',
            'TOTAL:'                  => 'ВСЕГО:',
            'Request e-Invoice'       => 'Запросить электронный счет на оплату',
            //'Direct flight' => '',
        ],
    ];

    private $lang = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectBody();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return null;
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'turkishairlines.com')]")->length > 0) {
            return $this->detectBody();
        }

        return false;
    }

    public function detectBody()
    {
        foreach (self::$dictionary as $lang => $detect) {
            if (isset($detect['Journey Duration'])
                && $this->http->XPath->query("//tr[count(td[normalize-space()]) > 3]/td[normalize-space()][last()][" . $this->starts($detect['Journey Duration']) . "]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        //		if (stripos($headers['from'], 'onlineticket@thy.com') === false) {
        //			return false;
        //		}
        foreach ($this->detectSubject as $sub) {
            if (stripos($headers['subject'], 'Turkish Airlines') !== false && stripos($headers['subject'], $sub) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@thy.com') !== false
            || stripos($from, '@mail.turkishairlines.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Booking Reference')) . ']/following::text()[normalize-space(.)][1]',
            null, true, '/^\s*[A-Z\d]{5,7}\s*$/');

        $f->general()
            ->confirmation($confirmation);

        // Passengers adn Tickets
        $isAllTravellers = false;
        $travellerRegexp = "[A-Z][A-Z\-]*(?: [A-Z\-]+)+";
        $travellers = [];
        $tickets = [];
        $pXpath = "//*[" . $this->eq($this->t("Request e-Invoice")) . "]/preceding-sibling::*[normalize-space()][1]";
        $pRows = $this->http->XPath->query($pXpath);

        foreach ($pRows as $pRoot) {
            $isAllTravellers = true;
            $values = $this->http->FindNodes(".//text()[normalize-space()]", $pRoot);

            if (count($values) == 2) {
                $travellers[] = $values[1];
                $f->addTicketNumber($values[0], false, $values[1]);
            } else {
                $travellers = [];

                break;
            }
        }

        if (empty($travellers)) {
            $route = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Seat")) . " or " . $this->eq($this->t("Baggage")) . "]/following::text()[normalize-space()][2])[1]",
                null, true, "/^[^-]+ - [^-]+$/");

            if (!empty($route)) {
                $isAllTravellers = true;
                $travellers = array_unique($this->http->FindNodes("//text()[" . $this->eq($route) . "]/preceding::text()[normalize-space()][1][preceding::text()[" . $this->eq($this->t("Seat")) . " or " . $this->eq($this->t("Baggage")) . "]]"));
            }
        }

        if ($isAllTravellers) {
            $f->general()
                ->travellers($travellers, true);
        } else {
            $pax = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Dear")) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*({$travellerRegexp}),?\s*$/");

            if (!empty($pax)) {
                $f->general()
                    ->traveller($pax, true);
            }
        }

        // Price
        $total = $this->http->FindSingleNode("//td[not(.//td)][" . $this->starts($this->t('TOTAL:')) . "]",
            null, true, "/^\s*" . $this->opt($this->t('TOTAL:')) . "\s*(.+)/");

        if (preg_match("/^\s*(?<miles>[\d\.\,]+\s*{$this->opt($this->t('Miles'))})\s*(?:\+|$)/", $total, $m)
            || preg_match("/^\s*(?<miles>{$this->opt($this->t('Miles'))}\s*[\d\.\,]+)\s*(?:\+|$)/", $total, $m)
        ) {
            $f->price()
                ->spentAwards($m['miles']);
            $total = trim(str_replace($m[0], '', $total));
        }

        if (preg_match('/^\s*(?<currency>[A-Z]{3})\s+(?<total>\d[.,\d ]*)\s*$/', $total, $m)
            || preg_match('/^\s*(?<total>\d[.,\d ]*)\s+(?<currency>[A-Z]{3})\s*$/', $total, $m)
        ) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $feeXpath = "//td[" . $this->eq($this->t("Taxes and other charges")) . "]/ancestor::tr[1]/following-sibling::*[normalize-space()][td[normalize-space()][1][not(" . $this->eq($this->t("Additional Services")) . ")]]";

            foreach ($this->http->XPath->query($feeXpath) as $fRoot) {
                $name = $this->http->FindSingleNode("td[normalize-space()][1]", $fRoot);

                if (preg_match("/^\s*" . $this->opt($this->t('TOTAL:')) . "\s*(.+)/", $name)) {
                    break;
                }
                $valueStr = $this->http->FindSingleNode("td[normalize-space()][2]", $fRoot);
                $value = null;

                if (preg_match('/^\s*' . $m['currency'] . '\s+(?<total>\d[.,\d ]*)\s*$/', $valueStr, $fm)
                    || preg_match('/^\s*(?<total>\d[.,\d ]*)\s+' . $m['currency'] . '\s*$/', $valueStr, $fm)
                ) {
                    $value = PriceHelper::parse($fm['total'], $m['currency']);
                }

                if (!empty($value) && !empty($name)) {
                    $f->price()
                        ->fee($name, $value);
                }
            }
        }

        $xpath = "//tr[count(td[normalize-space()]) > 3][td[normalize-space()][last()][" . $this->starts($this->t('Journey Duration')) . "]]";
//        $xpath = "//tr[count(td[normalize-space()]) > 3]/td[normalize-space()][last()][".$this->starts($this->t('Journey Duration'))."]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->debug('Segments not found by: ' . $xpath);

            return $email;
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $date = $this->http->FindSingleNode('preceding::tr[normalize-space(.)][1]', $root, null, "/^.+\([A-Z]{3}\) - .+\([A-Z]{3}\)\s*(.*\b\d{4}\b.*)$/");

            if (preg_match("/^(?<date>\d+\s+\w+\s*\d{4})\s*[A-Z]\w+\s*(?<cabin>[A-Z]\w+)\s*\((?<bookingCode>[A-Z])\)$/u", $date, $m)) {
                $date = $m['date'];

                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['bookingCode']);
            }

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode('td[normalize-space()][1]', $root, null, "/^\s*\d{1,2}:\d{2}\s*([A-Z]{3})\s*$/"));

            $time = $this->http->FindSingleNode('td[normalize-space()][1]', $root, null, "/^\s*(\d{1,2}:\d{2})\s*[A-Z]{3}\s*$/");

            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date($this->normalizeDate($date . ', ' . $time));
            }

            $flightInfo = $this->http->FindSingleNode("td[normalize-space()][2][{$this->starts($this->t('Direct flight'))}]", $root);

            if (preg_match("/^{$this->opt($this->t('Direct flight'))}\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{2,4})$/", $flightInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            } else {
                $s->airline()
                    ->name('TK')
                    ->noNumber();
            }

            if ($connections = $this->http->FindSingleNode('td[normalize-space()][2]', $root, null, "/^\s*(\d+)/")) {
                $node = $this->http->FindSingleNode('td[normalize-space()][2]/descendant::text()[normalize-space()][last()]', $root, null, "/^\s*([A-Z]{3})\s*$/");

                if ($connections == 1 && !empty($node)) {
                    $s->arrival()
                        ->noDate()
                        ->code($node);

                    if (!empty($s->getDepCode()) && !empty($s->getArrCode())) {
                        $seats = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(), '" . $s->getDepCode() . "') and contains(normalize-space(), '" . $s->getArrCode() . "')]",
                            null, "/^\s*{$s->getDepCode()} - {$s->getArrCode()}\s*:\s*(\d{1,3}[A-Z])\s*$/"));

                        if (!empty($seats)) {
                            $s->extra()
                                ->seats($seats);
                        }
                    }

                    $s = $f->addSegment();

                    // Airline
                    $s->airline()
                        ->name('TK')
                        ->noNumber();

                    $s->departure()
                        ->noDate()
                        ->code($node);
                } elseif ($connections >= 2) { //it-828785091.eml
                    $email->removeItinerary($f);
                    $this->logger->debug('the service will not be able to build a route');
                    $email->setIsJunk(true);
                } elseif ($connections == 1 && empty($node)) { //it-828738567.eml
                    $node = $this->http->FindSingleNode('td[normalize-space()][2]', $root);

                    if (preg_match("/1\s*Connecting flight\s*(?<aNameFirst>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumberFirst>\d{2,4})\s*(?<code>[A-Z]{3})\s*(?<aNameSecond>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumberSecond>\d{2,4})$/", $node, $m)) {
                        $s->airline()
                            ->name($m['aNameFirst'])
                            ->number($m['fNumberFirst']);

                        $s->arrival()
                            ->noDate()
                            ->code($m['code']);

                        $cabin = $s->getCabin();
                        $bookingCode = $s->getBookingCode();

                        $s = $f->addSegment();

                        $s->airline()
                            ->name($m['aNameSecond'])
                            ->number($m['fNumberSecond']);

                        $s->departure()
                            ->noDate()
                            ->code($m['code']);

                        if (!empty($cabin)) {
                            $s->setCabin($cabin);
                        }

                        if (!empty($bookingCode)) {
                            $s->setBookingCode($bookingCode);
                        }
                    }
                } else {
                    $this->logger->debug('need to add this case');

                    return false;
                }
            } else {
                // Extra
                $s->extra()
                    ->duration($this->http->FindSingleNode('td[normalize-space()][last()]', $root, null, "/^" . $this->opt($this->t('Journey Duration')) . "\s*(\d.+)\s*$/"));
            }
            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode('td[normalize-space()][3]', $root, null, "/^\s*\d{1,2}:\d{2}\s*([A-Z]{3})\s*$/"));

            $time = $this->http->FindSingleNode('td[normalize-space()][3]', $root, null, "/^\s*(\d{1,2}:\d{2})\s*[A-Z]{3}\s*$/");

            if (!empty($date) && !empty($time)) {
                $arrDate = $this->normalizeDate($date . ', ' . $time);

                $nextDay = $this->http->FindSingleNode('td[normalize-space()][4]', $root, null, "/^\s*" . $this->opt($this->t('Next day')) . "\s*$/");

                if (!empty($arrDate) && !empty($nextDay)) {
                    $arrDate = strtotime("+1 day", $arrDate);
                }

                $s->arrival()
                    ->date($arrDate);
            }

            if (!empty($s->getDepCode()) && !empty($s->getArrCode())) {
                $seats = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(), '" . $s->getDepCode() . "') and contains(normalize-space(), '" . $s->getArrCode() . "')]/ancestor::tr[1]",
                    null, "/^\s*{$s->getDepCode()} - {$s->getArrCode()}\s*:\s*(\d{1,3}[A-Z])\s*$/"));

                foreach ($seats as $seat) {
                    $pax = $this->http->FindSingleNode("//text()[{$this->starts($s->getDepCode())} and {$this->contains($s->getArrCode())}]/ancestor::tr[1][{$this->contains($seat)}]/preceding::tr[2]", null, true, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/");

                    if (stripos($pax, '-') !== false) {
                        $pax = $this->http->FindSingleNode("//text()[{$this->starts($s->getDepCode())} and {$this->contains($s->getArrCode())}]/ancestor::tr[1][{$this->contains($seat)}]/ancestor::table[1]/preceding::text()[normalize-space()][1]", null, true, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/");
                    }

                    if (!empty($pax)) {
                        $s->extra()
                            ->seat($seat, true, true, $pax);
                    } else {
                        $s->extra()
                            ->seat($seat);
                    }
                }

                if (!empty($seats)) {
                    $s->extra()
                        ->seats($seats);
                }
            }
        }
    }

    private function t($str)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$str])) {
            return $str;
        }

        return self::$dictionary[$this->lang][$str];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
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
        //$this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            //Donnerstag, 15. September 2022, 11:25
            "#^\s*[[:alpha:]]+\,\s*(\d+)\.\s*([[:alpha:]]+)\s*(\d{4}),\s*(\d{1,2}:\d{2})\s*$#u",
            // 18 Ekim 2022 Salı, 19:00
            "#^\s*(\d+)\s*([[:alpha:]]+)\s*(\d{4})\s*[[:alpha:]]+[.]?\s*,\s*(\d{1,2}:\d{2})\s*$#u",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
        ];

        $str = preg_replace($in, $out, $str);

        if (preg_match("#[[:alpha:]]{3,}#u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[0], $this->lang)) {
                $str = str_replace($m[0], $en, $str);
            }
        }

        return strtotime($str);
    }
}
