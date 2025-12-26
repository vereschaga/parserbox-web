<?php

namespace AwardWallet\Engine\lotpair\Email;

use AwardWallet\Engine\MonthTranslate;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "lotpair/it-10799491.eml, lotpair/it-12638233.eml, lotpair/it-12741224.eml, lotpair/it-17208551.eml, lotpair/it-6119764.eml, lotpair/it-6119778.eml";

    public $reSubject = [
        'en' => 'Confirmation for',
        'pl' => 'Potwierdzenie dla',
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            // type_1
            //			"DEPARTURE FLIGHT" => "",
            "Reservation number:" => "Reservation number:",
            //			"Total:" => "",
            //			"Data" => "",
            //			"Name and surname" => "",
            //			"Ticket number" => "",
            //			"Carrier" => "",
            //			"Flight" => "",
            // type_2
            'Booking number:' => ['Booking number:', 'Number of reservation:'],
            //			"Total price:" => "",
            "Adult" => ["Adult", "Children"],
            //			"Flight number" => "",
            //			"Class" => "",
            //			"Seat selection" => "",
        ],
        'pl' => [
            // type_1
            "DEPARTURE FLIGHT"    => "WYLOT",
            "Reservation number:" => "Numer rezerwacji:",
            "Total:"              => "Cena końcowa:",
            "Data"                => "Data",
            "Name and surname"    => "Imię i nazwisko",
            "Ticket number"       => "Numer biletu",
            "Carrier"             => "Przewoźnik",
            "Flight"              => "Lot",
            // type_2
            'Booking number:' => 'Numer rezerwacji:',
            "Total price:"    => ["Cena całkowita:", "Cena calkowita:"],
            "Adult"           => ["Dorosły"],
            "Flight number"   => ["Numer rejsu", "Flight number"],
            "Class"           => "Klasa",
            "Seat selection"  => "Wybrane miejsce",
        ],
    ];

    private $detectors = [
        'en' => ['Booking confirmation', 'Confirmation for'],
        'pl' => ['Potwierdzenie rezerwacji'],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return null;
        }
        $type = '';

        if ($this->http->FindSingleNode("//text()[" . $this->eq($this->t('DEPARTURE FLIGHT')) . "]")) {
            $its = $this->parseEmail_1();
            $type = '1';
        } else {
            $its = $this->parseEmail_2();
            $type = '2';
        }

        return [
            'emailType'  => "Confirmation" . ucfirst($this->lang) . $type,
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"www.lot.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Thank you for choosing LOT Polish Airlines") or contains(normalize-space(),"Dziękujemy za wybór PLL LOT") or contains(normalize-space(),"Kontakt do LOT") or contains(normalize-space(),"© LOT.com") or contains(.,"@lot.pl")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@lot.com") !== false || stripos($from, "@lot.pl") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if (($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) !== false) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            } elseif (($translatedMonthName = MonthTranslate::translate($monthNameOriginal, 'pl')) !== false) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail_1()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Reservation number:')) . "]/following::text()[normalize-space(.)][1]");

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Total:')) . "]/following::text()[normalize-space(.)][1]"));
        $it['TotalCharge'] = $tot['Total'];
        $it['Currency'] = $tot['Currency'];

        $it['ReservationDate'] = strtotime($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Data')) . "]/following::text()[normalize-space(.)][1]"));
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->contains($this->t('Name and surname')) . "]/following::text()[normalize-space(.)][1]");

        // TicketNumbers
        $ticketNumbers = $this->http->FindNodes("//text()[" . $this->contains($this->t('Ticket number')) . "]/following::text()[normalize-space(.)][1]", null, '/^(\d[-\d ]{5,}\d)$/');
        $ticketNumbers = array_values(array_filter($ticketNumbers));

        if (!empty($ticketNumbers[0])) {
            $it['TicketNumbers'] = $ticketNumbers;
        }

        $xpath = "//img[contains(@src,'/arrow')]/ancestor::table[3]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];

            $node = $this->http->FindSingleNode("./descendant::tr[1]/td[1]/descendant::table[2]/preceding::tr[1]", $root);

            if (preg_match("#^\s*(.+?)\s+([A-Z]{3})\s*$#", $node, $m)) {
                $seg['DepCode'] = $m[2];
                $seg['DepDate'] = strtotime($this->normalizeDate($m[1]));
            }
            $seg['DepName'] = $this->http->FindSingleNode("./descendant::tr[1]/td[1]/descendant::table[2]", $root);

            $node = $this->http->FindSingleNode("./descendant::tr[1]/td[1]/descendant::table[3]/preceding::tr[1]", $root);

            if (preg_match("#^\s*(.+?)\s+([A-Z]{3})\s*$#", $node, $m)) {
                $seg['ArrCode'] = $m[2];
                $seg['ArrDate'] = strtotime($this->normalizeDate($m[1]));
            }
            $seg['ArrName'] = $this->http->FindSingleNode("./descendant::tr[1]/td[1]/descendant::table[3]", $root);

            // REGIONAL JET    |    LO
            $seg['AirlineName'] = $this->http->FindSingleNode("./descendant::tr[1]/td[2]/descendant::tr[ count(.//*)=2 and {$this->contains($this->t('Carrier'), './*[1]')} ]/following-sibling::tr[1]/td[1]", $root);
            $seg['FlightNumber'] = $this->http->FindSingleNode(".//img[contains(@src,'/arrow')]/ancestor::table[3]/descendant::tr[1]/td[2]/descendant::tr[" . $this->contains($this->t('Flight')) . "]/following-sibling::tr[1]/td[1]", $root, true, "#^\s*(\d+)\s*$#");

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function parseEmail_2()
    {
        $xpathFragmentP = '(self::p or self::div)';
        $xpathFragmentValue = "/ancestor::*[{$xpathFragmentP} and ./following-sibling::node()][1]/following-sibling::*[{$xpathFragmentP} and normalize-space(.)][1]";

        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t('Booking number:')) . "])[last()]/following::text()[normalize-space(.)][1][not(contains(normalize-space(.), ':'))]");

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Total price:')) . "]/ancestor::*[local-name()='td' or local-name()='th'][1]", null, true, "#" . $this->preg_implode($this->t('Total price:')) . "\s*(.+)#"));
        $it['TotalCharge'] = $tot['Total'];
        $it['Currency'] = $tot['Currency'];
        $it['Passengers'] = array_unique($this->http->FindNodes("//img[contains(@src, 'ticket.png')]/ancestor::table[1]/preceding::p[normalize-space()][1]/descendant::text()[normalize-space()][1]"));

        if (empty($it['Passengers'])) {
            $it['Passengers'] = array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t('Adult')) . "]/preceding::text()[normalize-space()][1][string-length()>4]"));
        }
        $it['TicketNumbers'] = array_unique(array_filter($this->http->FindNodes("//img[contains(@src, 'ticket.png')]/ancestor::*[1]/following::text()[normalize-space()][1]", null, "#^([\d\- ]{5,})$#")));

        $xpath = "//img[contains(@src,'/arrow.png')]/ancestor::table[3]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $dateStr = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]/ancestor::*[local-name()='th' or local-name()='td'][1][contains(translate(., '0123456789', 'dddddddddd'), 'dddd') or contains(translate(., '0123456789', 'dddddddddd'), 'dd/dd')]", $root, true, "#:\s*(.+)#");

            if (!empty($dateStr)) {
                $date = strtotime($this->normalizeDate($dateStr));
            }
            $route = $this->http->FindSingleNode(".//table[not(.//table) and contains(.//img/@src,'/arrow.png')]", $root);

            if (preg_match("#^\s*(?<dtime>\d{1,2}:\d{2})\s*(?<dname>.+)\((?<dcode>[A-Z]{3})\)\s*(?<atime>\d{1,2}:\d{2})(?:\s*\(\+(?<nextday>\d+)d\))?\s*(?<aname>.+)\((?<acode>[A-Z]{3})\)\s*$#", $route, $m)) {
                if (isset($date) && !empty($date)) {
                    $seg['DepDate'] = strtotime($m['dtime'], $date);
                    $seg['ArrDate'] = strtotime($m['atime'], $date);

                    if (!empty($m['nextday'])) {
                        $seg['ArrDate'] = strtotime("+" . $m['nextday'] . "day", $seg['ArrDate']);
                        $date = strtotime("+" . $m['nextday'] . "day", $date);
                    }
                }
                $seg['DepName'] = trim($m['dname']);
                $seg['DepCode'] = $m['dcode'];
                $seg['ArrName'] = trim($m['aname']);
                $seg['ArrCode'] = $m['acode'];
            }
            $flight = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Flight number'))}]" . $xpathFragmentValue, $root);

            if (preg_match("#^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d+)$#", $flight, $m)) {
                $seg['AirlineName'] = $m['al'];
                $seg['FlightNumber'] = $m['fn'];
            }

            $seg['Cabin'] = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Class'))}]" . $xpathFragmentValue, $root);

            $seats = array_filter($this->http->FindNodes(".//text()[" . $this->eq($this->t('Seat selection')) . "]/following::text()[normalize-space()][1]", $root, "#^\s*(\d{1,5}[A-Z])\s*$#"));

            if (!empty($seats)) {
                $seg['Seats'] = $seats;
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\d+)\/(\d+)\/(\d+)\s+(\d+:\d+)\s*$#i',
            '#^\s*(\d+)\/(\d+)\/(\d+)\s+(\d+:\d+\s*[AP]M)\s*$#i',
            '#^\s*(\d+)\/(\d+)\/(\d+)\s*$#i',
        ];
        $out = [
            '$3-$2-$1 $4',
            '$3-$1-$2 $4',
            '$1.$2.20$3',
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $date));
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Reservation number:']) || empty($phrases['Booking number:'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Reservation number:'])}]")->length > 0
                || $this->http->XPath->query("//node()[{$this->contains($phrases['Booking number:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = (float) str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
