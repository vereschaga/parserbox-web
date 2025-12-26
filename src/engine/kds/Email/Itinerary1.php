<?php

namespace AwardWallet\Engine\kds\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "kds/it-1.eml, kds/it-12279926.eml, kds/it-12531951.eml, kds/it-12590598.eml, kds/it-12590615.eml, kds/it-127443341.eml, kds/it-32996406.eml";

    public $reFrom = 'wave.support@kds.com';
    public $reBody = [
        "fr" => ["Destinataires de cet email", "Veuillez cliquer sur le lien suivant uniquement si vous souhaitez consulter votre voyage", "Voyageur"],
        'en' => ['Recipients of this e-mail', 'Recipients of this email'],
        'pt' => ['Destinatários deste e-mail'],
    ];
    public $reSubject = [
        'en' => [
            'Travel request from',
            'Your trip has been cancelled',
        ],
        'fr' => [
            'Demande de voyage de',
            'Demande de voyage validée automatiquement',
            'Demande de voyage validée par',
            'Dossier en attente de validation - Voyage de',
            '',
        ],
        'pt' => [
            'A sua viagem foi cancelada',
        ],
    ];

    public $emailSubject = '';
    public $lang = 'fr';
    public static $dict = [
        'fr' => [
            //			"has been cancelled" => "",
            "N° de dossier"          => ["n° de dossier", "N° de dossier"],
            "Numéro de confirmation" => "n° de dossier",
            //			"Voyageur" => "",
            //			"Prix" => "",
            //			"Total" => "",
            // Hotel
            "Hotel Name"  => "Nom de l’hôtel",
            "Hotel Chain" => "Chaîne d’hôtels",
            "Check In"    => "Arrivée",
            //			"CHECK IN" => "",
            "Check Out" => "Départ",
            //			"CHECK OUT" => "",
            //			"Room" => "",
            "Address" => "Adresse",
            // Flighs and trains
            //			"Départ" => "",
            //			"Terminal" => "",
            //			"Arrivée" => "",
            //			"Vol N°" => "",
            //			"Classe" => "",
            //			"Train n°" => "",
            //			"Siège" => "",
            //			"de" => "",
        ],
        'en' => [
            //			"has been cancelled" => "has been cancelled",
            "N° de dossier"          => ["Reservation #", "reservation #"],
            "Numéro de confirmation" => "Confirmation Number",
            "Voyageur"               => ["Traveler", "Traveller"],
            "Prix"                   => "Price",
            "Total"                  => "Total",
            // Hotel
            //			"Hotel Name" => "",
            //			"Hotel Chain" => "",
            //			"Check In" => "",
            //			"CHECK IN" => "",
            //			"Check Out" => "",
            //			"CHECK OUT" => "",
            //			"Room" => "",
            //			"Address" => "",
            // Flighs and trains
            "Départ"   => "Departure",
            "Terminal" => "Terminal",
            "Arrivée"  => "Arrival",
            "Vol N°"   => "Flight",
            "Classe"   => "Class",
            //			"Train n°" => "",
            "Siège" => "Seat",
            "de"    => "for",
        ],
        'pt' => [
            "has been cancelled" => "foi cancelada",
            "N° de dossier"      => "Reserva n.º",
            //			"Numéro de confirmation" => "",
            "Voyageur" => "Passageiro",
            "Prix"     => "Preço",
            "Total"    => "Total",
            // Hotel
            //			"Hotel Name" => "",
            //			"Hotel Chain" => "",
            //			"Check In" => "",
            //			"CHECK IN" => "",
            //			"Check Out" => "",
            //			"CHECK OUT" => "",
            //			"Room" => "",
            //			"Address" => "",
            // Flighs and trains
            "Départ"   => "Partida",
            "Terminal" => "Terminal",
            "Arrivée"  => "Chegada",
            "Vol N°"   => "Voo",
            "Classe"   => "Classe",
            //			"Train n°" => "",
            //			"Siège" => "",
            //			"de" => "",
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getPlainBody();

        foreach ($this->reBody as $lang => $reBodies) {
            foreach ($reBodies as $reBody) {
                if (stripos($body, $reBody) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->emailSubject = $parser->getSubject();

        $this->parseEmail($email, $body);

        if (preg_match("#\n\s*" . $this->preg_implode($this->t("Total")) . "[ ]*:[ ]*(.+)#", $body, $m) || preg_match("#\n\s*" . $this->preg_implode($this->t("Prix")) . "[ ]*:[ ]*(.+)#", $body, $m)) {
            $email->price()
                ->total($this->amount($m[1]))
                ->currency($this->currency($m[1]));
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && stripos($headers["from"], $this->reFrom) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        foreach ($this->reBody as $reBodies) {
            foreach ($reBodies as $reBody) {
                if (stripos($body, $reBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'kds.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function parseEmail(Email $email, $text)
    {
        $text = str_replace(chr(194) . chr(160), ' ', $text);
        $passengers = [];

        if (preg_match("#(\d+)[ ]*" . $this->preg_implode($this->t("Voyageur")) . ".*:\s+([\s*\S]+?)" . $this->preg_implode($this->t("Prix")) . "#", $text, $m)
            && preg_match_all("#^\s*(\w[^\d]{0,5} [\w\- ]+)\s*$#um", $m[2], $mat)) {
            $passengers = array_filter(array_map('trim', $mat[1]));

            if ((int) $m[1] != count($passengers)) {
                $passengers = [];
            }
        }

        if (empty($passengers) && preg_match("#" . $this->preg_implode($this->t("Voyageur")) . "\s*\-+\s*([\s\S]+?)\-+#", $text, $m)) {
            $passengers = array_map('trim', array_filter(explode("\n", $m[1]), function ($s) { return preg_match("/^\w/", $s) > 0; }));
        }
        $cancelled = false;

        if (!empty($this->emailSubject) && stripos($this->emailSubject, $this->t("has been cancelled")) !== false) {
            $cancelled = true;
        }

        /***** HOTELS *****/
        if (strpos($text, $this->t("Hotel Name")) !== false) {
            $hotelText = $this->re("#Itinéraire(.+)Statut#su", $text);
            $hotelArray = array_filter(preg_split("/\n\d/u", $hotelText));

            if (count($hotelArray) > 0) {
                foreach ($hotelArray as $hotel) {
                    if (strpos($hotel, $this->t("Hotel Name")) !== false) {
                        $h = $email->add()->hotel();

                        $traveller = $this->re("/{$this->t('Voyageur')}\s+\:\s*([A-Z\.\s]+)\n/", $text);

                        if (!empty($traveller)) {
                            $h->general()
                                ->traveller(str_replace(['M.'], '', $traveller));
                        }

                        $confirmationNumber = re("#" . $this->preg_implode($this->t("Check Out")) . "(?:(?:.*\n){1,10})" . $this->preg_implode($this->t("Numéro de confirmation")) . "[ ]*:[ ]*([A-Z\d]*)\b#", $text);

                        if (empty($confirmationNumber)) {
                            $confirmationNumber = re("#" . $this->preg_implode($this->t("N° de dossier")) . "[ ]*\:?[ ]*([A-Z\d]*)\b#", $text);
                        }
                        $h->general()
                            ->confirmation($confirmationNumber);

                        $h->hotel()
                            ->name(trim(re("#" . $this->preg_implode($this->t("Hotel Name")) . "[ ]*:[ ]*(.+)#", $hotel)))
                            ->address(trim(re("#" . $this->preg_implode($this->t("Address")) . "[ ]*:[ ]*(.+)#", $hotel)))
                            ->chain(trim(re("#" . $this->preg_implode($this->t("Hotel Chain")) . "[ ]*:[ ]*(.+)#", $hotel)));

                        $checkIn = re("#" . $this->preg_implode($this->t("Check In")) . "[ ]*:[ ]*(.+)#", $hotel) .
                            re("#" . $this->preg_implode($this->t("CHECK IN")) . "[ ]*(\d{1,2}:\d{2}([ ]*(?:A|P)M\b)?)#", $hotel);

                        $checkOut = re("#" . $this->preg_implode($this->t("Check Out")) . "[ ]*:[ ]*(.+)#", $hotel) .
                            re("#" . $this->preg_implode($this->t("CHECK OUT")) . "[ ]*(\d{1,2}:\d{2}([ ]*(?:A|P)M\b)?)#", $hotel);

                        $h->booked()
                            ->checkIn(strtotime($this->normalizeDate($checkIn)))
                            ->checkOut(strtotime($this->normalizeDate($checkOut)));

                        $roomType = re("#" . $this->preg_implode($this->t("Room")) . "[ ]*:[ ]*(.+)#", $hotel);

                        if (!empty($roomType)) {
                            $room = $h->addRoom();
                            $room->setType($roomType);
                        }
                    }
                }
            } else {
                $h = $email->add()->hotel();

                $confirmationNumber = re("#" . $this->preg_implode($this->t("Check Out")) . "(?:(?:.*\n){1,10})" . $this->preg_implode($this->t("Numéro de confirmation")) . "[ ]*:[ ]*([A-Z\d]*)\b#", $text);

                if (empty($confirmationNumber)) {
                    $confirmationNumber = re("#" . $this->preg_implode($this->t("N° de dossier")) . "[ ]*\:?[ ]*([A-Z\d]*)\b#", $text);
                }
                $h->general()
                    ->confirmation($confirmationNumber);

                $h->hotel()
                    ->name(trim(re("#" . $this->preg_implode($this->t("Hotel Name")) . "[ ]*:[ ]*(.+)#", $text)))
                    ->address(trim(re("#" . $this->preg_implode($this->t("Address")) . "[ ]*:[ ]*(.+)#", $text)))
                    ->chain(trim(re("#" . $this->preg_implode($this->t("Hotel Chain")) . "[ ]*:[ ]*(.+)#", $text)));

                $checkIn = re("#" . $this->preg_implode($this->t("Check In")) . "[ ]*:[ ]*(.+)#", $text) .
                    re("#" . $this->preg_implode($this->t("CHECK IN")) . "[ ]*(\d{1,2}:\d{2}([ ]*(?:A|P)M\b)?)#", $text);

                $checkOut = re("#" . $this->preg_implode($this->t("Check Out")) . "[ ]*:[ ]*(.+)#", $text) .
                    re("#" . $this->preg_implode($this->t("CHECK OUT")) . "[ ]*(\d{1,2}:\d{2}([ ]*(?:A|P)M\b)?)#", $text);

                $h->booked()
                    ->checkIn(strtotime($this->normalizeDate($checkIn)))
                    ->checkOut(strtotime($this->normalizeDate($checkOut)));

                $roomType = re("#" . $this->preg_implode($this->t("Room")) . "[ ]*:[ ]*(.+)#", $text);

                if (!empty($roomType)) {
                    $room = $h->addRoom();
                    $room->setType($roomType);
                }
            }

            if ($cancelled == true) {
                $h->general()
                    ->status('Cancelled')
                    ->cancelled();
            }
        }

        /***** FLIGHTS *****/
        if (strpos($text, $this->t("Vol N°")) !== false) {
            $f = $email->add()->flight();
            $travellers = [];
            $airSegment = "#\s+" . $this->preg_implode($this->t("Départ")) . "[ ]+:[ ]*(?<dName>.+?) (?<dDate>[\d:/ ]+)(?:\s+\(?" . $this->preg_implode($this->t("Terminal")) . "[ ]*(?<dTerm>.*?)\)?)?"
                . "\s+" . $this->preg_implode($this->t("Arrivée")) . "[ ]+:[ ]*(?<aName>.+?) (?<aDate>[\d:/ ]+)(?:\s+\(?" . $this->preg_implode($this->t("Terminal")) . "[ ]*(?<aTerm>.*?)\)?)?"
                . "\s+" . $this->preg_implode($this->t("Vol N°")) . "[ ]*:[ ]*(?<alName>.+) (?<al>[A-Z\d]{2})(?<fn>\d{1,5})(?:\s+\(" . $this->preg_implode($this->t("Operated by")) . "\s*(?<operated>.*?)\))?"
                . "\s+" . $this->preg_implode($this->t("Classe")) . "[ ]*:[ ]*(?<class>.+)"
                . "(?<seats>(?:\s+" . $this->preg_implode($this->t("Siège")) . ".+[ ]*:[ ]*.+)*)"
                . "(?:\s+" . $this->preg_implode($this->t("Numéro de confirmation")) . "[ ]*:[ ]*(?<confNum>[A-Z\d]{5,7}))?#";

            preg_match_all($airSegment, $text, $flights);

            if (count($flights[0]) > 0) {
                foreach ($flights[0] as $i => $value) {
                    $s = $f->addSegment();

                    $s->airline()
                        ->name($flights['al'][$i])
                        ->number($flights['fn'][$i]);

                    if (!empty($flights['operated'][$i])) {
                        $s->airline()
                            ->operator($flights['operated'][$i]);
                    }

                    $s->departure()
                        ->name($flights['dName'][$i])
                        ->date(strtotime($this->normalizeDate($flights['dDate'][$i])))
                        ->noCode();

                    if (!empty($flights['dTerm'][$i])) {
                        $s->departure()
                            ->terminal($flights['dTerm'][$i]);
                    }

                    $s->arrival()
                        ->name($flights['aName'][$i])
                        ->date(strtotime($this->normalizeDate($flights['aDate'][$i])))
                        ->noCode();

                    if (!empty($flights['aTerm'][$i])) {
                        $s->arrival()
                            ->terminal($flights['aTerm'][$i]);
                    }

                    $cabin = trim($flights['class'][$i]);

                    if (!empty($cabin)) {
                        $s->extra()
                            ->cabin($cabin);
                    }

                    // Seats
                    if (preg_match_all("# " . $this->preg_implode($this->t("de")) . " (.+)[ ]*:[ ]*(\d{1,3}[A-Z])\b#", $flights['seats'][$i], $m)) {
                        $s->extra()
                            ->seats($m[2]);
                        $seatPassenger = array_map('trim', $m[1]);
                    }
                    $rl = '';

                    if (!empty($flights['confNum'][$i])) {
                        $rl = $flights['confNum'][$i];
                    }

                    $prevFirstSeg = substr($text, strpos($text, $flights[0][0]) - 300, 300);

                    if (empty($rl) && !empty($prevFirstSeg)
                        && preg_match("#{$this->preg_implode($this->t("Numéro de confirmation"))}[ ]*:[ ]*([A-Z\d]{5,7})[ ]*$#mu", text($prevFirstSeg), $m)) {
                        $rl = $m[1];
                    }

                    if (empty($rl) && !empty($flights['alName'][$i]) && preg_match("#\b" . $flights['alName'][$i] . "[ ]*:[ ]+([A-Z\d]{5,7})(?:\)|\s*\n)#", $text, $m)) {
                        $rl = $m[1];
                    }

                    if (empty($rl) && preg_match("#\n\s*" . $this->preg_implode($this->t("N° de dossier")) . "[ ]*:[ ]*([A-Z\d]{5,7})\s*\n#", $text, $m)) {
                        $rl = $m[1];
                    }

                    if (empty($rl) && preg_match("#\(\s*" . $this->preg_implode($this->t("N° de dossier")) . "[ ]*([A-Z\d]{5,7})\s*\)#", $text, $m)) {
                        $rl = $m[1];
                    }

                    if (empty($rl) && !empty($passengers) && preg_match("#\n\s*" . $this->preg_implode($this->t("N° de dossier")) . "[ ]*:\s+" . $this->preg_implode($passengers) . "[ ]*:[ ]*([A-Z\d]{5,7})\s*\n#", $text, $m)) {
                        $rl = $m[1];
                    }

                    if (empty($rl)) {
                        $f->general()
                            ->noConfirmation();
                    } elseif (!isset($f->getConfirmationNumbers()[0][0]) || $rl !== $f->getConfirmationNumbers()[0][0]) {
                        $f->general()
                            ->confirmation($rl);
                    }

                    $finded = false;

                    if ($finded == false) {
                        if (isset($seatPassenger)) {
                            $travellers = array_merge($travellers, $seatPassenger);
                        } elseif (isset($passengers)) {
                            $travellers = array_merge($travellers, $passengers);
                        }

                        if ($cancelled == true) {
                            $f->general()
                                ->status('Cancelled')
                                ->cancelled();
                        }
                    }
                }
            }

            $f->general()
                ->travellers(array_unique($travellers));
        }
        /***** TRAINS *****/

        if (strpos($text, $this->t("Train n°")) !== false) {
            $t = $email->add()->train();

            $travellers = [];

            $trainSegment = "#\s+" . $this->preg_implode($this->t("Départ")) . "[ ]+:[ ]*(?<dName>.+?) (?<dDate>[\d:/ ]+)"
                . "\s+" . $this->preg_implode($this->t("Arrivée")) . "[ ]+:[ ]*(?<aName>.+?) (?<aDate>[\d:/ ]+)"
                . "\s+" . $this->preg_implode($this->t("Train n°")) . "[ ]*:[ ]*(?<tName>.+ (?<tn>\d{1,6}))"
                . "\s+" . $this->preg_implode($this->t("Classe")) . "[ ]*:[ ]*(?<class>.+)"
                . "(?<seats>(?:\s+" . $this->preg_implode($this->t("Siège")) . ".+[ ]*:[ ]*.+)*)"
                . "(?:\s+" . $this->preg_implode($this->t("Numéro de confirmation")) . "[ ]*:[ ]*(?<confNum>[A-Z\d]{5,7}))?#";
            preg_match_all($trainSegment, $text, $trains);

            if (!empty($trains[0])) {
                foreach ($trains[0] as $i => $value) {
                    $s = $t->addSegment();
                    // FlightNumber
                    $s->setNumber($trains['tn'][$i]);
                    // DepName
                    $s->departure()
                        ->name($trains['dName'][$i])
                        ->date(strtotime($this->normalizeDate($trains['dDate'][$i])));

                    $s->arrival()
                        ->name($trains['aName'][$i])
                        ->date(strtotime($this->normalizeDate($trains['aDate'][$i])));

                    $s->setTrainType($trains['tName'][$i]);

                    $s->setCabin(trim($trains['class'][$i]));

                    if (preg_match_all("# " . $this->preg_implode($this->t("de")) . " (.+)[ ]*:[ ]*(\w+ [\dA-Z]{1,4}, \w+ [\dA-Z]{1,4})\b#um", $trains['seats'][$i], $m)) {
                        $s->setSeats($m[2]);
                        $seatPassenger = array_map('trim', $m[1]);
                    }

                    $rl = '';

                    if (!empty($trains['confNum'][$i])) {
                        $rl = $trains['confNum'][$i];
                    }

                    if (empty($rl) && preg_match("#\n\s*" . $this->preg_implode($this->t("N° de dossier")) . "[ ]*:[ ]*([A-Z\d]{5,7})\s*\n#", $text, $m)) {
                        $rl = $m[1];
                    }

                    if (empty($rl) && !empty($passengers) && preg_match("#\n\s*" . $this->preg_implode($this->t("N° de dossier")) . "[ ]*:\s+" . $this->preg_implode($passengers) . "[ ]*:[ ]*([A-Z\d]{5,7})\s*\n#", $text, $m)) {
                        $rl = $m[1];
                    }

                    if (empty($rl)) {
                        $t->general()
                            ->noConfirmation();
                    } elseif (!isset($t->getConfirmationNumbers()[0][0]) || $rl !== $t->getConfirmationNumbers()[0][0]) {
                        $t->general()
                            ->confirmation($rl);
                    }
                    $finded = false;

                    unset($it);

                    if ($finded == false) {
                        if (isset($seatPassenger)) {
                            $travellers = array_merge($travellers, $seatPassenger);
                        } elseif (isset($passengers)) {
                            $travellers = array_merge($travellers, $passengers);
                        }

                        if ($cancelled == true) {
                            $t->general()
                                ->status('Cancelled')
                                ->cancelled();
                        }
                    }
                    unset($seatPassenger);
                }

                $t->general()
                    ->travellers(array_unique($travellers));
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\d+)/(\d+)/(\d+)\s*$#', //30/07/2013
            '#^\s*(\d+)/(\d+)/(\d+)\s+(\d+:\d+)$#', //20/06/2016 06:20
        ];
        $out = [
            '$1.$2.$3',
            '$1.$2.$3 $4',
        ];
        $str = preg_replace($in, $out, $date);

        return $str;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\. ]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
