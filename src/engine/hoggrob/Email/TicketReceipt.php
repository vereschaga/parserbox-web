<?php

namespace AwardWallet\Engine\hoggrob\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TicketReceipt extends \TAccountChecker
{
    public $mailFiles = "hoggrob/it-5156403.eml, hoggrob/it-5589989.eml, hoggrob/it-5589990.eml, hoggrob/it-6290004.eml, hoggrob/it-6313508.eml, hoggrob/it-6316793.eml, hoggrob/it-6690904.eml, hoggrob/it-104816008.eml";

    public $reSubject = [
        "en" => "Ticket Receipt:",
        "en2"=> "Confirmation:",
        "de" => "Reisebestätigung",
        "fr" => "Ticket Receipt",
    ];
    public $reBody = 'HRGWORLDWIDE.COM';
    public $reBody2 = [
        "en" => ["Itinerary Summary", "Ticket Receipt"],
        "de" => ["Zusammenfassung"],
        "fr" => ["Résumé de l'itinéraire"],
    ];

    public static $dictionary = [
        "en" => [
            "Trip Reference" => ["Trip Reference", "HRG Trip Reference"],
            "Equipment"      => ["Equipment", "Type of aircraft"],
        ],
        "de" => [
            "Trip Reference"       => "HRG Buchungsreferenz",
            "Frequent Flyer Cards" => "Vielfliegerkarten",
            "Departure"            => "Abflug",
            "Reference"            => "Referenz",
            //			"Status" => "",
            'Ticket Number' => ['Ticketnummer', 'Ticket Number'],
            "Traveller(s)"  => "Reisende(r)",
            "Total:"        => "Gesamt:",
            "Fare:"         => "Tarif:",
            "Taxes:"        => "Steuern/Gebühren:",
            "Arrival"       => "Ankunft",
            "Equipment"     => "Flugzeugtyp",
            "Duration"      => "Dauer",
            "Class"         => "Klasse",
            "Seat"          => "Sitzplatz",
            // hotel
            //            "Check-in" => "",
            //            "Check-out" => "",
            //            "Phone" => "",
            //            "Room Type" => "",
            //            "Rate Details" => "",
            //            "Cancellation" => "",
            //            "Total Rate" => "",
            //            "Additional" => "",
        ],
        "fr" => [
            "Trip Reference"       => "Référence de réservation HRG",
            "Frequent Flyer Cards" => "Carte de fidélité",
            "Departure"            => "Départ",
            "Reference"            => "Référence",
            "Status"               => "Statut",
            "Traveller(s)"         => "Voyageur(s)",
            "Total:"               => "Total:",
            "Fare:"                => "Tarif:",
            "Taxes:"               => "Taxes:",
            "Arrival"              => "Arrivée",
            "Equipment"            => "equipement",
            "Duration"             => "Durée",
            "Class"                => "Classe",
            "Seat"                 => "Siège",
            // hotel
            "Check-in"     => "Arrivée",
            "Check-out"    => "Départ",
            "Phone"        => "Téléphone",
            "Room Type"    => "Type de chambre",
            "Rate Details" => "Détails tarifaires",
            "Cancellation" => "Annulation",
            "Total Rate"   => "Montant total",
            //            "Additional" => "",
        ],
    ];

    public $lang = "en";
    private $providerCode = '';

    public function parseHtml(Email $email)
    {
        $tripNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Trip Reference'))}]/ancestor::td[1]/following-sibling::td[1]", null, true, "/^([A-Z\d]{5,})(?:\s*[(]|$)/");

        if (!empty($tripNumber)) {
            $email->ota()
                ->confirmation($tripNumber);
        }

        // TODO: move parsing Passengers and AccountNumbers on top general level

        //###########
        //# FLIGHT ##
        //###########

        $xpath = "//tr[ ./*[1][string-length(normalize-space(.))>0] and ./*[2][./descendant::text()[starts-with(normalize-space(.),'{$this->t('Departure')}')]] ]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->info("Segments not found by xpath: {$xpath}");
        }
        $airs = [];

        foreach ($nodes as $root) {
            if (!$rl = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(.),'" . $this->t('Reference') . "')]/following-sibling::td[1]", $root, true, "#([A-Z\d]{5,})#")) {
                $this->logger->alert('Rl not found!');

                return;
            }
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl => $roots) {
            // LH992225XxXxX6085    |    BA - 25041723
            $patterns['ffNumber'] = '(?:(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*-\s*)?[A-z\d]+';

            $f = $email->add()->flight();

            $f->general()
                ->confirmation($rl)
                ->travellers(preg_replace("/\s(?:MRS|MR|MS)$/", "", array_values(array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(.),'" . $this->t('Traveller(s)') . "')]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)]"),
                    function ($k) use ($patterns) {
                        return !preg_match("/^{$patterns['ffNumber']}(?:\s*,\s*{$patterns['ffNumber']})*$/", $k);
                    }))));

            $status = $this->http->FindSingleNode("//td[starts-with(.,'" . $this->t('Status') . "')]/following::text()[contains(.,'{$rl}')][1]/preceding::text()[normalize-space(.)][1]");

            if (!empty($status)) {
                $f->general()
                    ->status($status);
            }

            $dateRes = strtotime($this->normalizeDate($this->http->FindSingleNode("//tr[td[starts-with(normalize-space(.),'HRG')] and td[contains(.,'Email')]]/descendant::td[1]")));

            if (!empty($dateRes)) {
                $f->general()
                    ->date($dateRes);
            }

            $it['RecordLocator'] = $rl;

            $AccountNumbers = array_values(array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(.),'" . $this->t('Traveller(s)') . "')]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)]"),
                function ($k) use ($patterns) {
                    return preg_match("/^{$patterns['ffNumber']}(?:\s*,\s*{$patterns['ffNumber']})*$/", $k);
                }));

            if (count($AccountNumbers) > 0) {
                foreach ($AccountNumbers as $account) {
                    if (stripos($account, ',') !== false) {
                        $f->setAccountNumbers(explode(',', $account), false);
                    } else {
                        $f->addAccountNumber($account, false);
                    }
                }
            }

            if (count($airs) == 1) {
                if ($this->http->XPath->query("//text()[{$this->starts($this->t('Ticket Number'))}]")->length < 2) {
                    $tot = $this->getTotalCurrency($this->http->FindSingleNode("(//td[starts-with(normalize-space(.),'" . $this->t('Total:') . "')])[1]/following-sibling::td[1]"));
                    $f->price()
                        ->total($tot['Total'])
                        ->currency($tot['Currency']);

                    $cost = $this->getTotalCurrency($this->http->FindSingleNode("//td[starts-with(normalize-space(.),'" . $this->t('Fare:') . "')]/following-sibling::td[1]"))['Total'];

                    if (!empty($cost)) {
                        $f->price()
                            ->cost($cost);
                    }

                    $tax = array_filter(array_map("trim", explode("/", $this->http->FindSingleNode("//td[starts-with(normalize-space(.),'" . $this->t('Taxes:') . "')]/following-sibling::td[1]"))));
                    $tax_all = array_sum(array_filter(array_map(function ($s) {
                        return $this->getTotalCurrency($s)['Total'];
                    }, $tax)));

                    if (!empty($tax_all)) {
                        $f->price()
                            ->tax($tax_all);
                    }
                }

                if ($this->http->XPath->query("//text()[{$this->starts($this->t('Ticket Number'))}]")->length > 0) {
                    $f->setTicketNumbers($this->http->FindNodes("//text()[{$this->starts($this->t('Ticket Number'))}]", null, "#{$this->opt($this->t('Ticket Number'))}\s*:\s*(.+?)\s*\-\s*Name#"), false);
                }
            }

            foreach ($roots as $root) {
                $s = $f->addSegment();

                $node = $this->http->FindSingleNode(".//tr[1]/td[1]", $root);

                if (preg_match("#([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\s*(.+)$#", $node, $m)) {
                    $s->airline()
                        ->number($m[2])
                        ->name($m[1])
                        ->operator($m[3]);
                }
                $node = $this->http->FindSingleNode(".//tr[1]/td[2]", $root);

                if (preg_match("#\(([A-Z]{3})\)\s*(.+?)(?:,\s*(.*?Terminal.*))?(,.+)#", $node, $m)) {
                    $s->departure()
                        ->code($m[1])
                        ->name($m[2] . $m[4]);

                    if (!empty($m[3])) {
                        $s->departure()
                            ->terminal(preg_replace('/^\s*(?:Terminal\s+)+/i', '', $m[3]));
                    }
                }

                $node = $this->http->FindSingleNode(".//tr[1]/td[3]", $root);

                if (preg_match("#\(([A-Z]{3})\)\s*(.+?)(?:,\s*(.*?Terminal.*))?(,.+)#", $node, $m)) {
                    $s->arrival()
                        ->code($m[1])
                        ->name($m[2] . $m[4]);

                    if (!empty($m[3])) {
                        $s->arrival()
                            ->terminal(preg_replace('/^\s*(?:Terminal\s+)+/i', '', $m[3]));
                    }
                }
                $date = $this->normalizeDate($this->http->FindSingleNode(".//tr[2]/td[1]", $root));
                $s->departure()
                    ->date(strtotime($date . ' ' . $this->getField($this->t('Departure'), $root)));
                $s->arrival()
                    ->date(strtotime($date . ' ' . str_replace("+1", "", $this->getField($this->t('Arrival'), $root))));

                if ($s->getArrDate() && preg_match("#\+1\s*$#", $this->getField($this->t('Arrival'), $root))) {
                    $s->arrival()
                        ->date(strtotime("+1 day", $s->getArrDate()));
                }
                $s->extra()
                    ->aircraft($this->getField($this->t('Equipment'), $root))
                    ->duration($this->getField($this->t('Duration'), $root));

                $seat = $this->getField($this->t('Seat'), $root);

                if ($seat) {
                    $s->extra()
                        ->seats(preg_split('/\s*,\s*/', $seat));
                }

                $node = $this->getField($this->t('Class'), $root);

                if (preg_match("#(.*?)\s*(?:\(([A-Z]{1,2})\)|$)#", $node, $m)) {
                    $s->extra()
                        ->cabin($m[1]);

                    if (isset($m[2]) && !empty($m[2])) {
                        $s->extra()
                            ->bookingCode($m[2]);
                    }
                }
            }
        }

        //##########
        //# HOTEL ##
        //##########

        $xpath = "//tr[ ./*[1][string-length(normalize-space(.))=0] and ./*[2][./descendant::text()[starts-with(normalize-space(.),'{$this->t('Check-in')}')]] ]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $h->general()
                ->confirmation($this->http->FindSingleNode("./descendant::td[contains(.,'" . $this->t('Reference') . "')]/following-sibling::td[1]", $root, true, "#^\s*([A-Z\d]+)#"))
                ->cancellation($this->getField($this->t('Cancellation'), $root));

            $travellers = array_values(array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(.),'" . $this->t('Traveller(s)') . "')]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)]"),
                function ($k) {
                    return !preg_match("#^.*\d.*$#", $k);
                }));

            if (count($travellers) > 0) {
                $h->general()
                    ->travellers($travellers);
            }

            $h->hotel()
                ->name($this->http->FindSingleNode("./descendant::tr[1]/td[2]/p[1]", $root))
                ->address($this->http->FindSingleNode("./descendant::tr[1]/td[2]/p[2]", $root))
                ->phone($this->getField($this->t('Phone'), $root));

            $h->booked()
                ->checkIn(strtotime($this->getField($this->t('Check-in'), $root)))
                ->checkOut(strtotime($this->getField($this->t('Check-out'), $root)));

            $tot = $this->getTotalCurrency($this->getField($this->t('Total Rate'), $root));

            if (!empty($tot['Total'])) {
                $h->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }

            $accounts = array_values(array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(.),'" . $this->t('Traveller(s)') . "')]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)]"),
                function ($k) {
                    return preg_match("#^[A-Z\d]+$#", $k);
                }));

            if (count($accounts) > 0) {
                $h->setAccountNumbers($accounts, false);
            }

            $roomType = $this->getField($this->t('Room Type'), $root);
            $rateType = $this->getField($this->t('Rate Details'), $root);
            $roomTypeDescription = $this->getField($this->t('Additional'), $root);

            if (!empty($roomType) || !empty($rateType) || !empty($roomTypeDescription)) {
                $room = $h->addRoom();

                if (!empty($roomType)) {
                    $room->setType($roomType);
                }

                if (!empty($rateType)) {
                    $room->setRateType($rateType);
                }

                if (!empty($roomTypeDescription)) {
                    $room->setDescription($roomTypeDescription);
                }
            }
        }

        //########
        //# CAR ##
        //########

        $xpath = "//text()[starts-with(.,'" . $this->t('Car Type') . "')]/ancestor::table[1][contains(.,'" . $this->t('Pick Up') . "')]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            $r->general()
                ->confirmation($this->http->FindSingleNode("./descendant::td[contains(.,'" . $this->t('Reference') . "')]/following-sibling::td[1]", $root, true, "#^\s*[A-Z\d]{5,}\b#"));

            $r->pickup()
                ->date(strtotime($this->getField($this->t('Pick Up'), $root)))
                ->location($this->http->FindSingleNode("./descendant::tr[1]/td[2]/p[2]", $root));

            $r->dropoff()
                ->date(strtotime($this->getField($this->t('Drop Off'), $root)))
                ->location($this->http->FindSingleNode("./descendant::tr[1]/td[3]/p[2]", $root));

            //			$it['PickupPhone'] = $this->getField($this->t('Phone'), $root); or DropoffPhone
            $company = $this->http->FindSingleNode("./descendant::tr[1]/td[1]", $root);

            if (!empty($company)) {
                $r->setCompany($company);
            }

            $r->car()
                ->type($this->getField($this->t('Car Type'), $root));

            $travellers = array_values(array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(.),'" . $this->t('Traveller(s)') . "')]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)]"),
                function ($k) {
                    return !preg_match("#^.*\d.*$#", $k);
                }));

            if (!empty($traveller)) {
                $r->general()
                    ->travellers($travellers);
            }
            $tot = $this->getTotalCurrency($this->getField($this->t('Total Rate'), $root));

            if (!empty($tot['Total'])) {
                $r->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }

        //###########
        //# CRUISE ##
        //###########

        $xpath = "//tr[ ./*[1][string-length(normalize-space(.))=0] and ./*[2][./descendant::text()[starts-with(normalize-space(.),'{$this->t('Departure')}')]] ]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $c = $email->add()->cruise();

            $c->general()
                ->confirmation($this->http->FindSingleNode("./descendant::td[contains(text(),'" . $this->t('Reference') . "')]/following-sibling::td[1]", $root, true, "#([A-Z\d]{5,})#"))
                ->travellers($this->http->FindNodes("//text()[starts-with(normalize-space(.),'" . $this->t('Traveller(s)') . "')]/ancestor::td[1]/following-sibling::td[1]/p[contains(text(),'/')]"));

            $dateRes = strtotime($this->normalizeDate($this->http->FindSingleNode("//tr[td[starts-with(normalize-space(.),'HRG')] and td[contains(.,'Email')]]/descendant::td[1]")));

            if (!empty($dateRes)) {
                $c->general()
                    ->date($dateRes);
            }

            $c->setDescription($this->http->FindSingleNode(".//tr[1]/td[1]", $root));
            // DepPortSegment
            $s = $c->addSegment();
            $s->setAboard(strtotime($this->getField($this->t('Departure'), $root)));
            $s->setName($this->http->FindSingleNode(".//tr[1]/td[2]", $root));
            // ArrPortSegment
            $s = $c->addSegment();
            $s->setName($this->http->FindSingleNode(".//tr[1]/td[3]", $root));
            $s->setAshore(strtotime($this->getField($this->t('Arrival'), $root)));
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/\bHRG (?:Germany|France)\b/', $from) > 0
            || stripos($from, '@hrgworldwide.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Format
        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $reBody2) {
            foreach ($reBody2 as $re) {
                if (stripos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->http->SetEmailBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        // Detecting Language
        foreach ($this->reBody2 as $lang => $reBody2) {
            foreach ($reBody2 as $re) {
                if (strpos($this->http->Response["body"], $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->parseHtml($email);

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
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return ['hoggrob', 'amextravel'];
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

    private function assignProvider($headers): bool
    {
        if (stripos($headers['from'], '@amexgbt.com') !== false
            || $this->http->XPath->query('//*[contains(.,"@amexgbt.com")]')->length > 0
            || $this->http->XPath->query('//text()[contains(.,"AMERICAN EXPRESS GLOBAL BUSINESS TRAVEL")]')->length > 0
            || $this->http->XPath->query('//text()[contains(.,"American Express Global Business Travel")]')->length > 0
            || $this->http->XPath->query('//text()[contains(.,"with GB Travel Canada Inc")]')->length > 0
        ) {
            // it-104816008.eml
            $this->providerCode = 'amextravel';

            return true;
        }

        if (stripos($headers['from'], '@hrgworldwide.com') !== false
            || $this->http->XPath->query('//*[contains(normalize-space(),"HRG Trip Reference:") or contains(normalize-space(),"please contact HRG") or contains(.,"www.hrgfrance.com") or contains(.,"@HRGWORLDWIDE.COM")]')->length > 0
        ) {
            $this->providerCode = 'hoggrob';

            return true;
        }

        return false;
    }

    private function getField($field, $root)
    {
        $node = $this->http->FindSingleNode("./descendant::td[{$this->starts($field)} and not(.//td)]/following-sibling::td[1]", $root);

        return $node;
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
        $in = [
            "#^(\d+\s+\w+\s+\d{4},\s+\d+:\d+)$#",
            "#^(\d+\s+\w+\s+\d{4})$#",
            "#^(\d+\s+\w+\s+\d{4}),\s+to\s+meet\s+\w{2}\d+$#",
        ];
        $out = [
            "$1",
            "$1",
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\s-\./]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
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
            $tot = str_replace(' ', '', $m['t']);
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
