<?php

namespace AwardWallet\Engine\iberia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "iberia/it-1728514.eml, iberia/it-1918545.eml, iberia/it-206159516.eml, iberia/it-212923587.eml, iberia/it-3701326.eml, iberia/it-4003915.eml, iberia/it-4003995.eml, iberia/it-4004039.eml, iberia/it-4009688.eml, iberia/it-4011917.eml, iberia/it-4029536.eml, iberia/it-4030634.eml, iberia/it-4030676.eml, iberia/it-4031283.eml, iberia/it-4031284.eml, iberia/it-4036989.eml, iberia/it-4043895.eml, iberia/it-4043896.eml, iberia/it-4046859.eml, iberia/it-4052791.eml, iberia/it-4092388.eml, iberia/it-4647819.eml, iberia/it-4657184.eml, iberia/it-4657194.eml, iberia/it-4688700.eml, iberia/it-4697012.eml, iberia/it-4721127.eml, iberia/it-4722877.eml, iberia/it-4732496.eml, iberia/it-4732648.eml, iberia/it-4732651.eml, iberia/it-4740263.eml, iberia/it-4741027.eml, iberia/it-4800441.eml, iberia/it-5097094.eml, iberia/it-5097103.eml, iberia/it-6248549.eml, iberia/it-6846394.eml, iberia/it-6899797.eml, iberia/it-7116107.eml, iberia/it-8317037.eml, iberia/it-8929605.eml, iberia/it-9034637.eml, iberia/it-9040641.eml, iberia/it-9045802.eml";

    public $reFrom = "@iberia.com";

    public $reSubject = [
        "en" => "Boarding Pass Iberia",
        "Iberia Boarding Pass",
        "es" => "Tarjeta de Embarque Móvil Iberia",
        "Tarjeta de Embarque Iberia",
        "fr" => "Carte d´Embarquement Portable Iberia",
        "pt" => "Cartão de Embarque Iberia",
        "de" => "Bordkarte Hady Iberia",
        "Iberia-Bordkarte",
        "it" => "Carta d´imbarco Iberia",
        'Carta di imbarco Iberia',
        "ru" => "Посадочный талон Iberia",
        "ca" => "Targeta dembarcament Iberia",
        "nl" => "Iberia-instapkaart",
    ];

    public $reBody = 'iberia.com';
    public $subject;

    public $reBody2 = [
        "en"  => ["Flight", "Departure"],
        "es"  => ["Vuelo", "Salida"],
        "es2" => ["Vuelos:", "Salida:"],
        "fr"  => ["Vol", "Départ"],
        "fr2" => ["Vol", "DÃ©part"],
        "pt"  => ["Voo", "Chegada"],
        "de"  => ["Flug", "Abflug"],
        "de2" => ["Flug", "Ausgang"],
        "it"  => ["Volo", "Partenze"],
        "it2" => ["Volo", "Partenza"],
        "ru"  => ["Рейс", "Выход"],
        "ca"  => ["Vol", "Sortida"],
        "nl"  => ["Vlucht", "Uitgang"],
    ];

    public $dateLang = ['ru']; // Languages on which there may be a date, and which are not in the $dictionary

    public static $dictionary = [
        "en" => [
            "on the booking" => ["on the booking", "for the booking"],
        ],
        "es" => [
            "on the booking" => ["de la reserva", "para su próximo viaje", "CONFIRMACION DE CHECKIN ONLINE"],
            "Passenger"      => ["Pasajero", "PASAJEROS:"],
            "Departure"      => ["Salida", "Salida:"],
            "Terminal:"      => "Terminal:",
            "Operated by"    => "Operado por",
            "Seat"           => ["Asiento", "Asiento:"],
            'Final price'    => 'Precio final',
        ],
        "fr" => [
            "on the booking" => ["de la réservation"],
            "Passenger"      => "Passager",
            "Departure"      => ["Départ", "DÃ©part"],
            "Terminal:"      => ["Terminal :", "TerminalÂ :"],
            "Operated by"    => ["Opéré par", "Assuré par", "OpÃ©rÃ© par"],
            "Seat"           => ["Siège", "SiÃ¨ge"],
        ],
        "pt" => [
            "on the booking" => ["da reserva"],
            "Passenger"      => "Passageiro",
            "Departure"      => ["Saída", "SaÃ­da"],
            "Terminal:"      => "Terminal:",
            "Operated by"    => "Operado por",
            "Seat"           => ["Lugar", "Assento"],
        ],
        "de" => [
            "on the booking" => ["mit der Buchungsnummer"],
            "Passenger"      => "Passagier",
            "Departure"      => ["Abflug", "Ausgang"],
            "Terminal:"      => "Terminal:",
            "Operated by"    => "Durchgeführt von",
            "Seat"           => "Sitzplatz",
        ],
        "it" => [
            "on the booking" => ["della prenotazione"],
            "Passenger"      => "Passeggero",
            "Departure"      => ["Partenze", "Partenza"],
            "Terminal:"      => "Terminal:",
            "Operated by"    => ["Operado da", "Operato da"],
            "Seat"           => "Posto",
        ],
        "ru" => [
            "on the booking" => ["для бронирование"],
            "Passenger"      => "Пассажир",
            "Departure"      => "Выход",
            "Terminal:"      => "Терминал:",
            "Operated by"    => "Выполняется",
            "Seat"           => "Место",
        ],
        "ca" => [
            "on the booking" => ["de la reserva"],
            "Passenger"      => "Passatger",
            "Departure"      => "Sortida",
            "Terminal:"      => "Terminal:",
            "Operated by"    => "Operat per",
            "Seat"           => "Seient",
        ],
        "nl" => [
            "on the booking" => ["Het online inchecken voor reservering"],
            "Passenger"      => "Passagier",
            "Departure"      => "Uitgang",
            "Terminal:"      => "Terminal:",
            "Operated by"    => "Uitgevoerd door",
            "Seat"           => "Stoel",
        ],
    ];

    public $lang = "en";

    public function parseHtml(Email $email, $text, $pngNames)
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("on the booking")) . "])[1]", null, true, "#" . $this->opt($this->t("on the booking")) . "\s+([A-Z\d]+)#");

        if (empty($confirmation)) {
            $confirmation = $this->re("/\s([A-Z\d]{6})$/u", $this->subject);
        }

        if (!empty($confirmation)) {
            $f->general()
                ->confirmation($confirmation);
        } else {
            $f->general()
                ->noConfirmation();
        }

        $node = stristr(stristr($text, 'Booking code'), 'Type of Service', true);

        if (preg_match('/Booking code\s+([A-Z\d]{5,9})\s+.+\s+number\s+([\d\-]+)/u', $node, $m)) {
            $f->addTicketNumber($m[2], false);
        }

        // TripNumber
        // Passengers
        $paxs = array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("Passenger")) . "]/ancestor::table[1]/descendant::tr[position()>1]/td[1]/descendant::text()[normalize-space(.)][1]"));

        if (count($paxs) == 0) {
            $paxs = array_unique($this->http->FindNodes("//img[contains(@src, 'pasajero')]/ancestor::td[1]/following-sibling::td[1]"));
        }

        $paxs = preg_replace("/(.+?)(?:\s*\(.*\))\s*$/", '$1', $paxs);
        $f->general()
            ->travellers($paxs, true);

        $node = $this->http->FindNodes("//img[{$this->eq($this->t("Seat"))}]/ancestor::td[1]/following-sibling::td[1]");
        $seats = [];
        $cabins = [];
        array_walk($node, function ($node) use (&$seats, &$cabins) {
            if (preg_match('/\b([A-Z\d]{1,3})\b\s*.*\s*Cabina:\s*(\w+)/', $node, $m)) {
                $seats[] = $m[1];
                $cabins[] = $m[2];
            }
        });
        $cabins[] = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Class upgrading') and contains(., 'cabin') and not(.//td)]", null, true, '/cabin\s+(.+)/');

        $total = $this->http->FindSingleNode("//td[contains(., '{$this->t('Final price')}')]/following-sibling::td[1]");

        if (!empty($total)) {
            $f->price()
                ->total($this->amount($total))
                ->currency($this->currency($total));
        }

        $xpath = "//text()[" . $this->eq($this->t("Departure")) . "]/following::tr[.//img[contains(@src, \"content/booking\")]][1]";

        if ($this->http->XPath->query($xpath)->length === 0) {
            $xpath = "//text()[" . $this->eq($this->t("Departure")) . "]/following::tr[1]";
        }

        if ($this->http->XPath->query("./td[2]/descendant::text()[normalize-space(.)][1]", $this->http->XPath->query($xpath)->item(0))->length === 0) {
            $xpath = "//text()[" . $this->eq($this->t("Departure")) . "]/ancestor::*[contains(., \"Partenza\") and contains(., \"Arrivo\")][2]/following-sibling::*[1]";
        }

        if ($this->http->XPath->query($xpath)->length === 0) {
            $xpath = "//text()[{$this->eq($this->t('Your seat selection'))}]/ancestor::table[1]/descendant::text()[{$this->eq($this->t('Departure'))}]/ancestor::tr[1]";
        }

        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->logger->info("Segments root not found: $xpath");
        }

        $anchor = false;

        if ($nodes->length === 1) {
            $anchor = true;
        }
        $this->logger->info($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->number($this->http->FindSingleNode("descendant::td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#^\w{2}\s*(\d+)$#"))
                ->name($this->http->FindSingleNode("descendant::td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(\w{2})\s*\d+$#"));

            $operator = $this->http->FindSingleNode("descendant::td[2]//text()[" . $this->contains($this->t("Operated by")) . "]", $root, true, "#" . $this->opt($this->t("Operated by")) . "\s+(.+)#");

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $depDate = implode(", ", $this->http->FindNodes("descendant::td[3]/descendant::text()[normalize-space(.)][position()=1 or position()=2]", $root));
            // DepDate
            $date = $this->http->FindSingleNode("ancestor::tr[1]/preceding-sibling::tr[1]/descendant::text()[normalize-space(.)][last()]", $root);
            $fullDepDate = $this->normalizeDate($depDate);

            if (empty($fullDepDate)) {
                $fullDepDate = $this->normalizeDate($date . ' ' . $depDate);
            }

            $s->departure()
                ->name($this->http->FindSingleNode("descendant::td[3]/descendant::text()[string-length(normalize-space(.))>1][3]", $root, true, "#(.*?)\s+[\(\-](?:[A-Z]{3}|\w+)[\)]*#"))
                ->date($fullDepDate);

            $depCode = $this->http->FindSingleNode("descendant::td[3]/descendant::text()[string-length(normalize-space(.))>1][3]", $root, true, "#[\(\-]([A-Z]{3})[\)]*$#");

            if (!empty($depCode)) {
                $s->departure()
                    ->code($depCode);
            }

            $depTerminal = $this->http->FindSingleNode("descendant::td[3]//text()[" . $this->contains($this->t("Terminal:")) . "]", $root, true, "#" . $this->opt($this->t("Terminal:")) . "\s*(.+)#");

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            $s->arrival()
                ->name($this->http->FindSingleNode("descendant::td[4]/descendant::text()[string-length(normalize-space(.))>1][3]", $root, true, "#(.*?)\s+[\(\-](?:[A-Z]{3}|\w+)[\)]*#"));

            $arrCode = $this->http->FindSingleNode("descendant::td[4]/descendant::text()[string-length(normalize-space(.))>1][3]", $root, true, "#[\(\-]([A-Z]{3})[\)]*$#");

            if (!empty($arrCode)) {
                $s->arrival()
                    ->code($arrCode);
            }

            $arrTerminal = $this->http->FindSingleNode("descendant::td[4]//text()[" . $this->contains($this->t("Terminal:")) . "]", $root, true, "#" . $this->opt($this->t("Terminal:")) . "\s*(.+)#");

            if (!empty($arrTerminal)) {
                $s->arrival()
                    ->terminal($arrTerminal);
            }

            // ArrDate
            $time = implode(", ", $this->http->FindNodes("descendant::td[4]/descendant::text()[normalize-space(.)][position()=1 or position()=2]", $root));

            if (strpos($time, '--:--') !== false) {
                $s->arrival()
                    ->noDate();
            } elseif (!empty($time)) {
                $arrDate = $this->normalizeDate($time);

                if (!empty($arrDate)) {
                    $s->arrival()
                        ->date($arrDate);
                }
            }

            if (empty($s->getArrDate()) && $s->getNoArrDate() !== true) {
                $s->arrival()
                    ->date($this->normalizeDate($date . ' ' . $time));
            }

            if (empty($s->getDepCode()) && !empty($s->getDepDate()) && !empty($s->getDepName()) && $s->getFlightNumber()) {
                $s->departure()
                    ->noCode();
            }

            if (empty($s->getArrCode()) && !empty($s->getArrDate()) && !empty($s->getArrName()) && $s->getFlightNumber()) {
                $s->arrival()
                    ->noCode();
            }

            if (count(array_filter($cabins)) == 0) {
                foreach ($f->getTravellers() as $traveller) {
                    $cabins[] = $this->http->FindSingleNode("./following::tr[{$this->contains($traveller[0])}][1]/following::text()[starts-with(normalize-space(), 'Cabin')][1]", $root, true, "/{$this->opt($this->t('Cabin'))}\s*\:?\s*(.+)/s");
                }
            }

            if (count(array_filter($cabins)) > 0) {
                $cabins = array_filter($cabins);
                $s->extra()
                    ->cabin(array_shift($cabins));
            }

            // Seats

            $seatArray = $this->http->FindNodes("./ancestor::tr[1]/following-sibling::tr[1]//text()[" . $this->eq($this->t("Seat")) . "]/ancestor::table[1]/descendant::tr[position()>1]/td[2]", $root);

            if (count($seatArray) == 0) {
                foreach ($f->getTravellers() as $traveller) {
                    $seatArray[] = $this->http->FindSingleNode("./following::tr[{$this->contains($traveller)}][1]/following::text()[starts-with(normalize-space(), 'Seat')][1]", $root, true, "/{$this->opt($this->t('Seat'))}\s*(\d{1,2}[A-Z])\s*/");
                }
            }
            $seatArray = array_filter($seatArray ?? [], function ($item) {
                return preg_match('/^\d+[A-Z]$/', $item) > 0;
            });
            $seats = array_filter($seats ?? [], function ($item) {
                return preg_match('/^\d+[A-Z]$/', $item) > 0;
            });

            if (count($seatArray) > 0) {
                $s->setSeats($seatArray);
            } elseif (count($seatArray) == 0 && $anchor && $nodes->length === 1 && count($seats) > 0) {
                $s->setSeats($seats);
            } elseif (count($seatArray) == 0 && !$anchor && $nodes->length === 1 && count($seats) > 0) {
                $s->extra()
                    ->seat(array_shift($seats));
            }


            if (!empty($s->getAirlineName()) && !empty($s->getFlightNumber())
                && preg_match_all("/^\s*IBERIA_(?<rl>[A-Z\d]{5,7})_(?<name2>[A-Z]+)-(?<name1>[A-Z]+)_{$s->getAirlineName()}{$s->getFlightNumber()}_.*_\d{1,3}[A-Z]\.png\s*$/m", $pngNames, $m)
            ) {
                // IBERIA_4QA9YZ_SWOPE-NICOLE_IB3714_289Oct_25E.png

                foreach ($m[0] as $i => $v) {
                    $bp = $email->add()->bpass();
                    $bp
                        ->setRecordLocator($m['rl'][$i])
                        ->setFlightNumber($s->getAirlineName() . ' ' . $s->getFlightNumber())
                        ->setDepCode($s->getDepCode())
                        ->setDepDate($s->getDepDate())
                        ->setAttachmentName($v)
                        ->setTraveller($m['name1'][$i] . ' ' . $m['name2'][$i]);
                }
            }
//            $bp = $email->add()->bpass();
//            $bp->setFlightNumber($s->getAirlineName() . ' ' . $s->getFlightNumber());
//            $bp->setDepCode($s->getDepCode());
//            $bp->setDepDate($s->getDepDate());
//            $bp->setUrl($this->http->FindSingleNode("./ancestor::a/@href", $bpRoot));
//            $bp->setTraveller($this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $bpRoot));

        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if ($this->http->XPath->query("//text()[starts-with(normalize-space(.), '" . $re[0] . "')]")->length > 0 && $this->http->XPath->query("//text()[starts-with(normalize-space(.), '" . $re[1] . "')]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->subject = $parser->getSubject();
        $pdfBody = null;
        $pdfs = $parser->searchAttachmentByName('.*pdf');
        $pngs = $parser->searchAttachmentByName('.*\.png');
        $pngNames = '';
        foreach ($pngs as $png) {
            $pngNames .= "\n" . $this->getAttachmentName($parser, $png);
        }

        if (0 < count($pdfs)) {
            $pdfBody = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));
        }

        $this->http->FilterHTML = false;

        foreach ($this->reBody2 as $lang => $re) {
            if ($this->http->XPath->query("//text()[normalize-space(.)='" . $re[0] . "']")->length > 0 && $this->http->XPath->query("//text()[normalize-space(.)='" . $re[1] . "']")->length > 0) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $this->parseHtml($email, $pdfBody, $pngNames);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function getAttachmentName(\PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.png)[\'\"]*/i', $header, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        //	    $this->logger->info("DATE: $str");
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+:\d+),\s+([^\d\s]+?)[\s.,]+(\d+)\s+(?:d’|de )?([^\d\s]+)$#", //12:05, Thursday 19 June
            "#^(--:--),\s+([^\d\s]+?)[\s.,]+(\d+)\s+([^\d\s]+)$#", //--:--, Thursday 19 June
            '/^\w*\s*(\d{1,2})\s*de\s*(\w+)\s*de\s*(\d{2,4})\s*\w+,\s*(\d+:\d+)$/',
            '/^\w+,\s+(\d{1,2})\.\s+(\w+)\s+(\d{2,4})\s+(?:Departure|Arrival),\s+(\d{1,2}:\d{2})$/ui',
        ];
        $out = [
            "$2, $3 $4 $year, $1",
            "$2, $3 $4 $year, $1",
            '$1 $2 $3, $4',
            '$1 $2 $3, $4',
        ];
        $str = preg_replace($in, $out, $str);

        if (strtotime($str) === false && preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } else {
                $langAll = array_merge(array_keys(self::$dictionary), $this->dateLang);

                foreach ($langAll as $lang => $value) {
                    if ($en = MonthTranslate::translate($m[1], $value)) {
                        $str = str_replace($m[1], $en, $str);

                        break;
                    }
                }
            }
        }

        if (preg_match("#^(?<week>[\w\-]+), (?<date>\d+ \w+ .+)#u", $str, $m)) {
            $weekT = WeekTranslate::translate($m['week'], $this->lang);
            if (empty($weekT)) {
                $langAll = array_merge(array_keys(self::$dictionary), $this->dateLang);

                foreach ($langAll as $lang => $value) {
                    $weekT = WeekTranslate::translate($m['week'], $this->lang);
                    if (!empty($weekT)) {
                        break;
                    }
                }
            }

            $weeknum = WeekTranslate::number1($weekT);
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }
        //		$this->logger->info("RETURN: {$str}");
        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
