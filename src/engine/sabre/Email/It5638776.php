<?php

namespace AwardWallet\Engine\sabre\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class It5638776 extends \TAccountChecker
{
    public $mailFiles = "sabre/it-11863591.eml, sabre/it-12320201.eml, sabre/it-13320939.eml, sabre/it-13357663.eml, sabre/it-5029985.eml, sabre/it-5077874.eml, sabre/it-5638755.eml, sabre/it-6176064.eml, sabre/it-641679487-cancelled.eml";

    public static $detectProvider = [
        'wagonlit' => [
            'from' => ['cwt.com', 'cwtsatotravel.com', 'carlsonwagonlit.com', 'contactcwt.com', '@cwt-ecenter.com'],
            'body' => [
                '//a[contains(@href,"cwt.com")]',
                '//a[contains(@href,"cwtsatotravel.com")]',
                '//a[contains(@href,"contactcwt.com")]',
                '//a[contains(@href,"carlsonwagonlit.com")]',
                'Carlson Wagonlit Travel',
            ],
        ],
        'bcd' => [
            'from' => ['@bcdtravel.com'],
            'body' => [
                '//a[contains(@href,"bcdtravel.com")]',
                'BCD',
            ],
        ],
        'sabre' => [
            'from' => ['@getthere.com'],
            'body' => [
                '@getthere.com',
            ],
        ],
        'hoggrob' => [
            'from' => ['hrgworldwide.com', '@ar.hrgworldwide.com'],
            'body' => [
                //                '@getthere.com',
            ],
        ],
    ];

    public $lang = "en";

    public $detectTravelAgency = [
        'sabre' => [
            'SABRE',
        ],
        'tport' => [
            'Apollo',
        ],
        'hoggrob' => [
            'HRG',
        ],
    ];

    public static $rentalProviders = [
        'national' => ['National'],
        'hertz'    => ['Hertz'],
    ];

    public static $dictionary = [
        "en" => [
            "Record Locator" => "Record Locator", // trip number
            //            "Airline Record Locator" => "",
            //            "Hotel Confirmation" => "",
            //            "Car Rental Confirmation" => "",
            "Rail Confirmation" => ["Rail Confirmation", "Rail Record Locator"],
            //            "CONFIRMATION NUMBERS" => "",
            "NAME(S) OF PEOPLE TRAVELING"         => ['NAME(S) OF PEOPLE TRAVELING', 'NAME(S) OF PEOPLE TRAVELLING'],
            'THIS RESERVATION HAS BEEN CANCELLED' => ['THIS RESERVATION HAS BEEN CANCELLED', 'THIS RESERVATION HAS BEEN CANCELED'],
            //            'ITINERARY' => '',
            //            "Name:" => "",
            //            "AIR" => "",
            //            "Flight/Equip.:" => "",
            //            "Operated By:" => "",
            //            "Depart:" => "",
            //            "Arrive:" => "",
            //            "Status:" => "",
            //            "Class:" => "",
            //            "Miles:" => "",
            //            "Seats Requested:" => "",
            //            "Stops:" => "",
            "Base Airfare" => ["Base Airfare", "Flight Cost"],
            //            "Total Taxes" => "",
            //            "TRAIN" => "",
            "Rail Company / Train Number:" => ["Rail Company / Train Number:", "Rail Company / Number:"],
            //            "Address:" => "",
            //            "Rail Fare" => "",
            //            "Accommodations Fare" => "",
            //            "Total Fare" => "",
            //            "HOTEL" => "",
            //            "Hotel Confirmation #:" => "",
            //            "Location:" => "",
            //            "Phone:" => "",
            //            "Fax:" => "",
            //            "Check-in:" => "",
            //            "Check-out:" => "",
            //            "Number of Rooms:" => "",
            "Average Rate:" => ["Average Rate:", "Rate:"],
            //            "Average Rate before taxes and fees:" => "",
            //            "CAR" => "",
            //            "Confirmation #:" => "",
            //            "Pick-up:" => "",
            //            "Tel.:" => "",
            //            "Vendor:" => "",
            //            "Car size:" => "",
            //            "Total Car Cost" => "",
        ],
        'es' => [
            'Record Locator'         => '# de Localizador de Registro', // trip number
            "Airline Record Locator" => "# de Localizador de la Aerolínea",
            //            "Hotel Confirmation" => "",
            //            "Car Rental Confirmation" => "",
            //            "Rail Confirmation" => "",
            'CONFIRMATION NUMBERS'        => 'NÚMEROS DE CONFIRMACIÓN',
            'NAME(S) OF PEOPLE TRAVELING' => 'NOMBRE DE PASAJERO',
            // 'THIS RESERVATION HAS BEEN CANCELLED' => '',
            //            'ITINERARY' => '',
            'Name:'          => 'Nombre:',
            'AIR'            => 'AÉREO',
            "Flight/Equip.:" => "Flight/Equip.:",
            //            "Operated By:" => "",
            'Depart:'          => ['Partida:', 'Salida:'],
            'Arrive:'          => ['Arribo:', 'Llegada:'],
            "Status:"          => "Estado:",
            'Class:'           => 'Clase:',
            "Miles:"           => "Millas:",
            'Seats Requested:' => 'Asientos Solicitados:',
            'Stops:'           => 'Escalas:',
            //            "Base Airfare" => "",
            //            "Total Taxes" => "",
            //            "TRAIN" => "",
            //            "Rail Company / Train Number:" => "",
            'Address:' => 'dirección:',
            //            "Rail Fare" => "",
            //            "Accommodations Fare" => "",
            //            "Total Fare" => "",
            // hotel
            //            "HOTEL" => "",
            'Hotel Confirmation #:' => '# de Confirmación de Hotel:',
            //            "Location:" => "",
            'Phone:' => 'Teléfono:',
            //            "Fax:" => "",
            //            "Check-in:" => "",
            'Check-out:'       => 'Salida:',
            'Number of Rooms:' => 'Número de Habitaciones:',
            'Average Rate:'    => 'Tarifa promedio:',
            //            "Average Rate before taxes and fees:" => "",
            "CAR"             => "AUTO",
            "Confirmation #:" => "# de Confirmación:",
            "Pick-up:"        => "Recoger:",
            "Drop-Off:"       => "Entrega:",
            //            "Tel.:" => "",
            "Vendor:"        => "Proveedor:",
            "Car size:"      => "Tamaño del Auto:",
            "Total Car Cost" => "Costo total de autos:",
        ],
        "pt" => [
            "Record Locator"         => ["Nº do localizador", 'SABRE Nº do localizador'], // trip number
            "Airline Record Locator" => "Nº do localizador da empresa aérea",
            "Hotel Confirmation"     => "Nº de confirmação do hotel",
            //            "Car Rental Confirmation" => "",
            //            "Rail Confirmation" => ["Rail Confirmation", "Rail Record Locator"],
            "CONFIRMATION NUMBERS"                => "NÚMEROS DE CONFIRMAÇÃO",
            "NAME(S) OF PEOPLE TRAVELING"         => ['Nomes dos passageiros que estão viajando', 'NOMES DOS PASSAGEIROS QUE ESTÃO VIAJANDO'],
            'THIS RESERVATION HAS BEEN CANCELLED' => 'ESTA RESERVA FOI CANCELADA',
            'ITINERARY'                           => 'ITINERÁRIO',
            "Name:"                               => "Nome:",
            "AIR"                                 => "AÉREO",
            "Flight/Equip.:"                      => "Flight/Equip.:",
            //            "Operated By:" => "",
            "Depart:"          => "Saída:",
            "Arrive:"          => "Chegada:",
            "Status:"          => "Status:",
            "Class:"           => "Classe:",
            "Miles:"           => "Milhas:",
            "Seats Requested:" => "Assentos solicitados:",
            //            "Stops:" => "Escalas:",
            "Base Airfare" => ["Total do voo"],
            "Total Taxes"  => "Total de impostos e/ou taxas aplicáveis",
            //            "TRAIN" => "",
            //            "Rail Company / Train Number:" => ["Rail Company / Train Number:", "Rail Company / Number:"],
            "Address:" => "Endereço:",
            //            "Rail Fare" => "",
            //            "Accommodations Fare" => "",
            //            "Total Fare" => "",
            "HOTEL"                               => "HOTEL",
            "Hotel Confirmation #:"               => "Nº de confirmação do hotel:",
            "Location:"                           => "Localidade:",
            "Phone:"                              => "Telefone:",
            "Fax:"                                => "Fax:",
            "Check-in:"                           => "Check-in:",
            "Check-out:"                          => "Check-out:",
            "Number of Rooms:"                    => "Número de quartos:",
            "Average Rate:"                       => ["Average Rate:", "Rate:"],
            "Average Rate before taxes and fees:" => "Tarifa Média antes dos impostos e das taxas:",

            "CAR"             => "CARRO",
            "Confirmation #:" => "Nº de confirmação da locação do carro",
            "Pick-up:"        => "Retirada:",
            "Drop-Off:"       => "Devolução:",
            "Tel.:"           => "Tel.:",
            "Vendor:"         => "Fornecedor:",
            "Car size:"       => "Tamanho do carro:",
            "Total Car Cost"  => "Custo Total da Locação:",
        ],
    ];

    private $detectSubject = [
        // en
        "Booking Confirmation",
        // es
        "Confirmación de Reserva",
        // pt
        'Confirmação da reserva', 'Cancelamento da reserva',
    ];

    private $patterns = [
        'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
    ];

    private $date = 0;
    private $confNumbers = [];
    private $travellers = [];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'getthere.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        $detectedFrom = false;

        foreach (self::$detectProvider as $info) {
            foreach ($info['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $detectedFrom = true;
                }
            }
        }

        if ($detectedFrom == false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach ($this->detectTravelAgency as $code => $ta) {
            if ($this->http->XPath->query("//*[" . $this->contains($ta) . "]")->length > 0) {
                foreach (self::$dictionary as $lang => $dict) {
                    if (!empty($dict['Record Locator']) && $this->http->XPath->query("//text()[" . $this->contains($dict["Record Locator"]) . " and " . $this->contains($ta) . "]")->length > 0) {
                        return true;
                    }
                }
            }
        }

        $text = $parser->getPlainBody();

        foreach ($this->detectTravelAgency as $code => $ta) {
            if ($this->striposAll($text, $ta) == true) {
                foreach (self::$dictionary as $lang => $dict) {
                    if (!empty($dict['Record Locator']) && preg_match("#^\s*" . $this->preg_implode($ta) . '\s*' . $this->preg_implode($dict["Record Locator"]) . "[ \#:]+([\w\-]+)#m",
                        $text, $m)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Record Locator']) && $this->http->XPath->query("//text()[" . $this->contains($dict["Record Locator"]) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($email);
        $type = 'Html';

        if (empty($email->getItineraries())) {
            $text = $parser->getPlainBody();
            $text = preg_replace('#^>#m', '', $text);

            if (empty($text)) {
                $text = $this->htmlToText($this->http->Response['body']);
            }

            foreach (self::$dictionary as $lang => $dict) {
                if (!empty($dict['Record Locator']) && preg_match("#" . $this->preg_implode($dict["Record Locator"]) . "[ \#:]+([\w\-]+)#m",
                        $text, $m)) {
                    $this->lang = $lang;

                    break;
                }
            }

            $type = 'Plain';
            $this->parsePlain($email, $text);
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . $type . ucfirst($this->lang));

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

    protected function splitter($regular, $text): array
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseHtml(Email $email): void
    {
        // Travel Agency
        $email->obtainTravelAgency();

        foreach ($this->detectTravelAgency as $code => $ta) {
            if ($this->http->XPath->query("//tr[not(.//tr) and " . $this->contains($this->t("Record Locator")) . " and " . $this->contains($ta) . "]")->length > 0) {
                $email->ota()->code($code);
            }
        }

        //##############
        //##   RLs   ###
        //##############

        $xpath = "//text()[" . $this->eq($this->t('CONFIRMATION NUMBERS')) . "]/following::table[1]//tr[not(./tr)][normalize-space()]";
        // $this->logger->info($xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $cnVal = implode(' ', $this->http->FindNodes('descendant::text()[normalize-space()]', $root));

            if (preg_match("#" . $this->preg_implode($this->t("Airline Record Locator")) . "\s?\#?\d?:?\s+\w{2}-(\w+)\s*\((.*?)\)$#", $cnVal, $m)
                || preg_match("#" . $this->preg_implode($this->t("Hotel Confirmation")) . "\s?\#?\d?\:?\s+\w{2}-(\w+)\s*\((.*?)\)$#", $cnVal, $m)
                || preg_match("#" . $this->preg_implode($this->t("Car Rental Confirmation")) . "\s?\#?\d?:?\s+\w{2}-(\w+)\s*\((.*?)\)$#", $cnVal, $m)
                || preg_match("#" . $this->preg_implode($this->t("Rail Confirmation")) . "\s?\#?\d?:?\s+(\w+)\s*\((.*?)\)\s*$#", $cnVal, $m)
            ) {
                $this->confNumbers[strtolower($m[2])] = $m[1];

                continue;
            }

            if (preg_match("#(.+ " . $this->preg_implode($this->t("Record Locator")) . ")[\s\#]*:\s+([A-Z\d]+)\s*$#",
                $root->nodeValue, $m)) {
                $email->ota()->confirmation($m[2], $m[1]);

                continue;
            }
        }

        $this->travellers = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t('NAME(S) OF PEOPLE TRAVELING')) . "]/following::table[1]//text()[" . $this->eq($this->t('Name:')) . "]/following::td[1]"));

        $this->parseHtmlFlight($email);
        $this->parseHtmlTrain($email);
        $this->parseHtmlHotel($email);
        $this->parseHtmlRental($email);

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('THIS RESERVATION HAS BEEN CANCELLED'))}]")->length === 1) {
            foreach ($email->getItineraries() as $it) {
                $it->general()
                    ->status('Cancelled')
                    ->cancelled();
            }
        }
    }

    private function parseHtmlFlight(Email $email)
    {
        if (count($this->http->FindNodes("//text()[" . $this->eq($this->t('AIR')) . "]/following::table[1]")) === 0) {
            return $email;
        }

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
            ->travellers($this->travellers)
        ;

        $xpath = "//text()[" . $this->eq($this->t('AIR')) . "]/following::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->debug("Segments did not found for air by xpath: {$xpath}");
        }

        // Segments
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $airline = $this->nextCol($this->t("Flight/Equip.:"), $root);

            if (preg_match("/^\s*(?<al>.+?) (?<fn>\d{1,5})(\s+(?<ac>.+)|$)/i", $airline, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;

                if (!empty($this->confNumbers[strtolower($m['al'])])) {
                    $s->airline()->confirmation($this->confNumbers[strtolower($m[1])]);
                }

                if (!empty($m['ac'])) {
                    $s->extra()->aircraft($m['ac']);
                }
            }

            // Departure
            $regexp1 = "#^(?<date>.*?\d+:\d+(?:\s+[AP]M)?)\s+(?<name>.+?)\s*\((?<code>[A-Z]{3})\)$#";
            $regexp2 = "#^\s*(?<name>.+?)\s*\((?<code>[A-Z]{3})\)\s*(?<date>.*?\d+:\d+(?:\s+[AP]M)?)\s*$#";
            $departure = $this->nextCol($this->t("Depart:"), $root);

            if (preg_match($regexp1, $departure, $m) || preg_match($regexp2, $departure, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m['date']))
                    ->code($m['code'])
                    ->name($m['name'])
                ;
            }

            // Arrival
            $arrival = $this->nextCol($this->t("Arrive:"), $root);

            if (preg_match($regexp1, $arrival, $m) || preg_match($regexp2, $arrival, $m)) {
                $s->arrival()
                    ->date($this->normalizeDate($m['date']))
                    ->code($m['code'])
                    ->name($m['name'])
                ;
            }

            // Extra
            $s->extra()
                ->status($this->nextCol($this->t("Status:"), $root), true, true)
                ->cabin($this->nextCol($this->t("Class:"), $root), true, true)
                ->seat($this->nextCol($this->t("Seats Requested:"), $root), true, true)
            ;

            $stops = $this->nextCol($this->t("Stops:"), $root);

            if (preg_match("#^\D*(\d+)\D*$#", $stops, $m)) {
                $s->extra()->stops($m[1]);
            } elseif (!empty($stops) && preg_match("#^\D+$#", $stops, $m)) {
                $s->extra()->stops(0);
            }
        }

        if (count($this->travellers) > 0) {
            $total = $this->http->FindSingleNode("//tr[" . $this->contains($this->t("Base Airfare")) . "]/td[2]");

            if (preg_match('/([\d\.]+)\s+([A-Z]{3})/', $total, $m)) {
                $f->price()
                    ->cost($m[1] * count($this->travellers))
                    ->currency($m[2])
                ;
            }
            $total = $this->http->FindSingleNode("//tr[" . $this->contains($this->t("Base Airfare")) . "]/following-sibling::tr[1][" . $this->contains($this->t("Total Taxes")) . "]/td[2]");

            if (preg_match('/([\d\.]+)\s+([A-Z]{3})/', $total, $m)) {
                $f->price()
                    ->tax($m[1] * count($this->travellers))
                ;
            }
            $total = $this->http->FindSingleNode("//tr[" . $this->contains($this->t("Base Airfare")) . "]/following-sibling::tr[2][" . $this->contains($this->t("Total Flight")) . "]/td[2]");

            if (preg_match('/([\d\.]+)\s+([A-Z]{3})/', $total, $m)) {
                $f->price()
                    ->total($m[1] * count($this->travellers))
                    ->currency($m[2])
                ;
            }
        }
    }

    private function parseHtmlTrain(Email $email): void
    {
        $xpath = "//text()[" . $this->eq($this->t('TRAIN')) . "]/following::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->debug("Segments did not found for train by xpath: {$xpath}");

            return;
        }

        foreach ($nodes as $root) {
            $name = $this->re("/(" . implode("|", array_keys($this->confNumbers)) . ")/i", $this->nextCol($this->t('Rail Company / Train Number:'), $root));

            if (!empty($name) && !empty($this->confNumbers[strtolower($name)])) {
                $rails[$this->confNumbers[strtolower($name)]][] = $root;
            } else {
                $rails['undefined'][] = $root;
            }
        }

        foreach ($rails as $rl => $roots) {
            $t = $email->add()->train();

            // General
            if ($rl === 'undefined') {
                $t->general()->noConfirmation();
            } else {
                $t->general()->confirmation($rl);
            }
            $t->general()->travellers($this->travellers, true);

            $railCompany = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Rail Confirmation")) . "]/ancestor::td[1]/following-sibling::td[1][contains(.,'{$rl}')]",
                null, true, "#\((.+)\)#");
            $account = $this->http->FindSingleNode("//tr[contains(., 'Rewards Number') and contains(.,'{$railCompany}')]/td[2]",
                null, true, '/\b(\d+)\b/');

            if (!empty($account)) {
                $t->program()
                    ->account($account, false);
            }

            foreach ($roots as $root) {
                $s = $t->addSegment();

                // Departure
                $s->departure()->date($this->normalizeDate($this->nextCol($this->t("Depart:"), $root, "#\([A-Z]{3}\)\s+(\w+,\s+\w+\s+\d{1,2}\s+\d{1,2}:\d{2}(?:\s+[amp]{2})?)#")));
                $addressDep = $this->http->FindSingleNode("descendant::tr[ *[not(.//tr) and normalize-space()][1][{$this->eq($this->t("Depart:"))}] ]/following-sibling::tr[normalize-space()][1]/*[normalize-space()][1][{$this->contains($this->t("Address:"))}]/following-sibling::*[normalize-space()]", $root, true, '/^.*\d.*$/');

                if ($addressDep) {
                    $s->departure()->address($addressDep);
                } else {
                    $s->departure()
                        ->name($this->nextCol($this->t("Depart:"), $root, "#(.+)\s+\([A-Z]{3}\)#"))
                        ->code($this->nextCol($this->t("Depart:"), $root, "#\(([A-Z]{3})\)#"))
                    ;
                }

                // Arrival
                $s->arrival()->date($this->normalizeDate($this->nextCol($this->t("Arrive:"), $root, "#\([A-Z]{3}\)\s+(\w+,\s+\w+\s+\d{1,2}\s+\d{1,2}:\d{2}(?:\s+[amp]{2})?)#")));
                $addressArr = $this->http->FindSingleNode("descendant::tr[ *[not(.//tr) and normalize-space()][1][{$this->eq($this->t("Arrive:"))}] ]/following-sibling::tr[normalize-space()][1]/*[normalize-space()][1][{$this->contains($this->t("Address:"))}]/following-sibling::*[normalize-space()]", $root, true, '/^.*\d.*$/');

                if ($addressArr) {
                    $s->arrival()->address($addressArr);
                } else {
                    $s->arrival()
                        ->name($this->nextCol($this->t("Arrive:"), $root, "#(.+)\s+\([A-Z]{3}\)#"))
                        ->code($this->nextCol($this->t("Arrive:"), $root, "#\(([A-Z]{3})\)#"))
                    ;
                }

                // Extra
                $s->extra()
                    ->number($this->nextCol($this->t("Rail Company / Train Number:"), $root, "#\s+(\d+)\s*$#"))
                    ->cabin($this->nextCol($this->t("Class:"), $root))
                    ->seat($this->nextCol($this->t("Seats Requested:"), $root, "/^\d+[A-Z]$/"), false, true)
                    ->car($this->nextCol($this->t("Coach:"), $root), false, true)
                ;
            }
        }

        if (count($rails) === 1) {
            $total = $this->http->FindSingleNode("//tr[" . $this->contains($this->t("Rail Fare")) . "]/td[2]");

            if (preg_match('/([\d\.]+)\s+([A-Z]{3})/', $total, $m)) {
                $t->price()
                    ->cost($m[1])
                    ->currency($m[2])
                ;
            }
            $total = $this->http->FindSingleNode("//tr[" . $this->contains($this->t("Rail Fare")) . "]/following-sibling::tr[1][" . $this->contains($this->t("Accommodations Fare")) . "]/td[2]");

            if (preg_match('/([\d\.]+)\s+([A-Z]{3})/', $total, $m)) {
                $t->price()
                    ->tax($m[1])
                ;
            }
            $total = $this->http->FindSingleNode("//tr[" . $this->contains($this->t("Rail Fare")) . "]/following-sibling::tr[2][" . $this->contains($this->t("Total Fare")) . "]/td[2]");

            if (preg_match('/([\d\.]+)\s+([A-Z]{3})/', $total, $m)) {
                $t->price()
                    ->total($m[1])
                    ->currency($m[2])
                ;
            }
        }
    }

    private function parseHtmlHotel(Email $email): void
    {
        $xpath = "//text()[" . $this->eq($this->t("HOTEL")) . "]/following::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();
            $hotelName = $this->nextCol($this->t("Name:"), $root);

            // General
            $h->general()
                ->travellers($this->travellers);

            if ($this->http->XPath->query("descendant::tr[ count(*)=2 and *[1][{$this->eq($this->t('Hotel Confirmation #:'))}] and *[2][normalize-space()=''] ]", $root)->length > 0
                || $hotelName && stripos($hotelName, 'hyatt hotels and resorts') !== false && array_key_exists('hyatt hotels and resorts', $this->confNumbers) && $this->confNumbers['hyatt hotels and resorts'] === 'NULL'
                || $hotelName && stripos($hotelName, 'holiday inn') !== false && array_key_exists('holiday inn', $this->confNumbers) && $this->confNumbers['holiday inn'] === 'NULL'
            ) {
                $h->general()->noConfirmation();
            } else {
                $conf = $this->nextCol($this->t("Hotel Confirmation #:"), $root);

                if (preg_match("/^\s*[\w\d\-\s*]{5,}\W*$/u", $conf) && preg_match("/^\s*([a-z\d\-\s*?]{5,})\s*-\s*[^\da-z\-\s]+\s*$/ui", $conf)) {
                    $conf = $this->re("/^\s*([a-z\d\-]{5,})\s*-\s*/ui", $conf);
                }
                $h->general()
                    ->confirmation($conf);
            }

            // Hotel
            $h->hotel()
                ->name($hotelName)
                ->phone($this->nextCol($this->t("Phone:"), $root), true, true)
                ->fax($this->nextCol($this->t("Fax:"), $root), true, true)
            ;

            $address = $this->nextCol($this->t("Address:"), $root, '/^.{3,}$/') ?? $this->nextCol($this->t("Location:"), $root, '/^.{3,}$/');

            if ($address) {
                $h->hotel()->address($address);
            } elseif ($this->http->XPath->query("descendant::tr[ count(*)=2 and *[1][{$this->eq($this->t('Address:'))}] and *[2][normalize-space(translate(.,',.;!',''))=''] ]", $root)->length > 0
                && $this->http->XPath->query("descendant::tr[ count(*)=2 and *[1][{$this->eq($this->t('Location:'))}] ]", $root)->length === 0
                || $this->http->XPath->query("descendant::tr[ count(*)=2 and *[1][{$this->eq($this->t('Location:'))}] and *[2][normalize-space(translate(.,',.;!',''))=''] ]", $root)->length > 0
                && $this->http->XPath->query("descendant::tr[ count(*)=2 and *[1][{$this->eq($this->t('Address:'))}] ]", $root)->length === 0
            ) {
                // it-641679487-cancelled.eml
                $h->hotel()->noAddress();
            }

            // Booked
            $h->booked()
                ->checkIn($this->normalizeDate($this->nextCol($this->t("Check-in:"), $root)))
                ->checkOut($this->normalizeDate($this->nextCol($this->t("Check-out:"), $root)))
                ->rooms($this->nextCol($this->t("Number of Rooms:"), $root))
            ;

            $rate = $this->nextCol($this->t("Average Rate:"), $root);

            if (empty($rate)) {
                $rate = $this->nextCol($this->t("Average Rate before taxes and fees:"), $root);
            }

            if (!empty($rate)) {
                $h->addRoom()
                    ->setRate($rate, true, true)
                ;
            }
        }
    }

    private function parseHtmlRental(Email $email): void
    {
        $xpath = "//text()[" . $this->eq($this->t("CAR")) . "]/following::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            // General
            $conf = $this->nextCol($this->t("Confirmation #:"), $root, "#^\s*(\w{5,})(?: |$)#");

            if (empty($conf)) {
                $r->general()
                    ->noConfirmation();
            } else {
                $r->general()
                    ->confirmation($conf);
            }

            $r->general()
                ->travellers($this->travellers);

            // Pick Up
            $r->pickup()
                ->date($this->normalizeDate($this->nextCol($this->t("Pick-up:"), $root, "#^(.+?\d+:\d+(?:\s*[ap]m)?)\s#i")))
                ->location(trim(
                    $this->nextCol($this->t("Pick-up:"), $root, "#^.+?\d+:\d{2}(?:\s*[ap]m)?\s+(.+)#i") . ', ' .
                    $this->http->FindSingleNode("(.//td[not(.//td) and " . $this->eq($this->t("Pick-up:")) . "])[1]/ancestor::tr[1]/following-sibling::tr[1][" . $this->contains($this->t("Address:")) . "]/td[2]", $root)))
                ;

            $phone = $this->http->FindSingleNode("(.//td[not(.//td) and " . $this->eq($this->t("Pick-up:")) . "])[1]/ancestor::tr[1]/following-sibling::tr[2][" . $this->contains($this->t("Tel.:")) . "]/td[2]",
                $root);

            if (!empty($phone)) {
                $r->pickup()
                    ->phone($phone);
            }

            // Drop Off
            $r->dropoff()
                ->date($this->normalizeDate($this->nextCol($this->t("Drop-Off:"), $root, "#^(.+?\d+:\d+(?:\s*[ap]m)?)\s#i")))
                ->location(trim(
                    $this->nextCol($this->t("Drop-Off:"), $root, "#^.+?\d+:\d{2}(?:\s*[ap]m)?\s+(.+)#i") . ', ' .
                    $this->http->FindSingleNode("(.//td[not(.//td) and " . $this->eq($this->t("Drop-Off:")) . "])[1]/ancestor::tr[1]/following-sibling::tr[1][" . $this->contains($this->t("Address:")) . "]/td[2]", $root)))
                ;

            $phone = $this->http->FindSingleNode("(.//td[not(.//td) and " . $this->eq($this->t("Drop-Off:")) . "])[1]/ancestor::tr[1]/following-sibling::tr[2][" . $this->contains($this->t("Tel.:")) . "]/td[2]",
                $root);

            if (!empty($phone)) {
                $r->dropoff()
                    ->phone($phone);
            }

            // Extra
            $company = $this->nextCol($this->t("Vendor:"), $root);

            if (($code = $this->normalizeRentalProvider($company))) {
                $r->program()->code($code);
            } else {
                $r->extra()->company($company);
            }

            // Car
            $r->car()
                ->type($this->nextCol($this->t("Car size:"), $root))
            ;

            $total = $this->http->FindSingleNode(".//tr[" . $this->contains($this->t("Total Car Cost")) . "]/td[2]", $root);

            if (preg_match('/([\d\.]+)\s+([A-Z]{3})/', $total, $m)) {
                $r->price()
                    ->total($m[1])
                    ->currency($m[2])
                ;
            }
        }
    }

    private function parsePlain(Email $email, string $text): void
    {
//        $this->logger->debug('$text = '.print_r( $text,true));

        // Travel Agency

        $email->obtainTravelAgency();

        foreach ($this->detectTravelAgency as $code => $ta) {
            if (preg_match("#^\s*" . $this->preg_implode($ta) . '\s*' . $this->preg_implode($this->t("Record Locator")) . "[ \#:]+([\w\-]+)#m", $text, $m)) {
                $email->ota()
                    ->code($code)
                    ->confirmation($m[1])
                ;
            }
        }

        if (
            preg_match("#" . $this->preg_implode($this->t("Airline Record Locator")) . "\s?\#?\d?\s+\w{2}-(\w+)\s*\((.*?)\)\s*\n#",
                $text, $m)
            || preg_match("#" . $this->preg_implode($this->t("Hotel Confirmation")) . "\s?\#?\d?\s+\w{2}-(\w+)\s*\((.*?)\)\s*\n#",
                $text, $m)
            || preg_match("#" . $this->preg_implode($this->t("Car Rental Confirmation")) . "\s?\#?\d?\s+\w{2}-(\w+)\s*\((.*?)\)\s*\n#",
                $text, $m)
            || preg_match("#" . $this->preg_implode($this->t("Rail Confirmation")) . "\s?\#?\d?\s+(?:\w{2}-)?(\w+)\s*\((.*?)\)\s*\n#",
                $text, $m)
        ) {
            $this->confNumbers[strtolower($m[2])] = $m[1];
        }

        if (preg_match_all("#\s*{$this->opt($this->t('Name:'))}\s*([^\n]+)#u", $this->re("#{$this->opt($this->t('NAME(S) OF PEOPLE TRAVELING'))}(.*?){$this->opt($this->t('ITINERARY'))}#umis", $text), $nameMatches)) {
            $this->travellers = array_map('trim', $nameMatches[1]);
        }

        $airs = [];
        $trains = [];
        $hotels = [];
        $cars = [];

        $segRegxep = "#\n\s*((?:{$this->preg_implode($this->t('AIR'))}|{$this->preg_implode($this->t('Rail Company / Train Number:'))}|{$this->preg_implode($this->t('HOTEL'))}|{$this->preg_implode($this->t('CAR'))}))#ms";
        $segments = $this->splitter($segRegxep, $text);

        foreach ($segments as $seg) {
            if (strpos($seg, $this->t('AIR')) === 0) {
                $airs[] = $seg;
            } elseif ($this->strposArray($seg, $this->t('Rail Company / Train Number:')) === 0) {
                $trains[] = $seg;
            } elseif (strpos($seg, $this->t('HOTEL')) === 0) {
                $hotels[] = $seg;
            } elseif (strpos($seg, $this->t('CAR')) === 0) {
                $cars[] = $seg;
            }
        }
//        $this->logger->debug('$segments = '.print_r( count($segments),true));
//        $this->logger->debug('$cars = '.print_r( count($cars),true));
//        $this->logger->debug('$airs = '.print_r( count($airs),true));
//        $this->logger->debug('$trains = '.print_r( count($trains),true));
//        $this->logger->debug('$hotels = '.print_r( count($hotels),true));

        if (count($airs) > 0) {
            $this->parsePlainAir($email, $airs);
        }

        if (count($trains) > 0) {
            $this->parsePlainTrain($email, $trains);
        }

        if (count($hotels) > 0) {
            $this->parsePlainHotel($email, $hotels);
        }

        if (count($cars) > 0) {
            $this->parsePlainCar($email, $cars);
        }
    }

    private function parsePlainAir(Email $email, $nodes)
    {
        $f = $email->add()->flight();
        $f->general()->noConfirmation();

        if ($this->travellers) {
            $f->general()->travellers($this->travellers);
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            if ($status = $this->getField($this->t("Status:"), $root)) {
                $s->extra()->status($status);
            }

            // Flight/Equip.:United Airlines 1043
            // Flight/Equip.: United Airlines 3493    Embraer ERJ-170
            // Operated By: SHUTTLE AMERICA DBA UNITED EXPRESS
            if (preg_match("#{$this->preg_implode($this->t('Flight/Equip.:'))}\s*(.*?)\s+(\d+)\s*(.*?)\s+(?:{$this->opt($this->t('Operated By:'))}|{$this->opt($this->t('Depart:'))})#is", $root, $m)) {
                $s->airline()->name($m[1]);
                $s->airline()->number($m[2]);
                $s->extra()->aircraft($m[3], true, false);
            }

            if (preg_match("#{$this->opt($this->t('Operated By:'))}\s*(.+?)\s*(?:DBA|\n)#i", $root, $m)) {
                $s->airline()->operator($m[1]);
            }

            // Depart: Santa Ana(SNA) Thursday, Jul 24 9:17 PM
            // Depart:Los Angeles(LAX)/Sunday, Feb 22 11:50
            // Saída: Brasilia(BSB) Segunda-feira, Ago 11 10:35
            // Llegada: La Paz(LPB) miércoles, feb 25 02:58
            $regex = "#{$this->opt($this->t('Depart:'))}\s*(.+?)\s*\(([A-Z]{3})\)[\s/]+([\w\-]+,\s+.+?\d+:\d+.*)#u";

            if (preg_match($regex, $root, $m)) {
                $s->departure()->name($m[1]);
                $s->departure()->code($m[2]);
                $s->departure()->date($this->normalizeDate($m[3]));
            }
            $regex = "#{$this->opt($this->t('Arrive:'))}\s*(.+?)\s*\(([A-Z]{3})\)[\s/]+([\w\-]+,\s+.+?\d+:\d+.*)#u";

            if (preg_match($regex, $root, $m)) {
                $s->arrival()->name($m[1]);
                $s->arrival()->code($m[2]);
                $s->arrival()->date($this->normalizeDate($m[3]));
            }

            $s->extra()->miles($this->re("#\s+{$this->opt($this->t('Miles:'))}\s*(\d+)#", $root), false, true);
            $s->extra()->cabin($this->getField($this->t('Class:'), $root), true, false);

            if ($stop = $this->re("#{$this->opt($this->t('Stops:'))}\s+(.*?);?\s+{$this->opt($this->t('Miles:'))}#i", $root)) {
                if ($stop == 'non-stop') {
                    $s->extra()->stops(0);
                }
            }

            if ($subj = $this->re("#{$this->opt($this->t('Seats Requested:'))}\s*((?:,?\s*\d{1,3}[A-Z])*)#", $root)) {
                $s->extra()->seats(array_filter(explode(",", $subj)));
            }
        }
    }

    // it-2654483.eml
    private function parsePlainTrain(Email $email, $nodes)
    {
        $t = $email->add()->train();
        $t->general()->travellers($this->travellers);

        foreach ($nodes as $rl => $root) {
            $s = $t->addSegment();
            //  Number: Thalys  9328
            if (preg_match("#{$this->opt($this->t('Rail Company / Train Number:'))}\s*(.*?)\s+(\d+)#i", $root, $m)) {
                $s->extra()->number($m[2]);
            }
            // Depart: Rotterdam (NLRTC) Monday, 4 May 09:58
            // Address: Rotterdam, Rotterdam Netherlands
            $regex = "#{$this->opt($this->t('Depart:'))}\s*(.+?)\s+([\w\-]+,\s+.+?\d+:\d+.*?)\n";
            $regex .= "\s*{$this->opt($this->t('Address:'))}\s*(.+?)\s+\n#";

            if (preg_match($regex, $root, $m)) {
                $s->departure()->name("{$m[1]} - {$m[3]}");
                $s->departure()->date($this->normalizeDate($m[2]));
            }
            $regex = "#{$this->opt($this->t('Arrive:'))}\s*(.+?)\s+([\w\-]+,\s+.+?\d+:\d+.*?)\n";
            $regex .= "\s*{$this->opt($this->t('Address:'))}\s*(.+?)\s+\n#";

            if (preg_match($regex, $root, $m)) {
                $s->arrival()->name("{$m[1]} - {$m[3]}");
                $s->arrival()->date($this->normalizeDate($m[2]));
            }
            $s->extra()->cabin($this->re("#\s+{$this->opt($this->t('Class:'))}\s*([^\n]+){$this->opt($this->t('Seats Requested:'))}#", $root));

            if ($seats = $this->re("#\s+{$this->opt($this->t('Seats Requested:'))}\s*([^\n]+)#", $root)) {
                $s->extra()->seat($seats);
            }
        }
    }

    private function parsePlainHotel(Email $email, $nodes)
    {
        foreach ($nodes as $root) {
            $h = $email->add()->hotel();
            $num = $this->re("#{$this->opt($this->t('Hotel Confirmation #:'))}\s*([\w\-]+)#", $root);

            if ($num == 'NULL') {
                $num = $this->re("#{$this->opt($this->t('Hotel Confirmation #:'))}\s*\d+\s*\w{2}-([\w\-]+)#", $root);
            }

            if ($num == 'NULL') {
                $h->general()->noConfirmation();
            } else {
                $h->general()->confirmation($num);
            }
            $h->general()->travellers($this->travellers);

            $h->hotel()->name($this->re("#\n\s*{$this->opt($this->t('Name:'))}\s*([^\n]*?)\s+{$this->opt($this->t('Location:'))}#u", $root));

            if (preg_match("#{$this->t('Check-in:')}\s+([\w\-]+,\s+.+?\d+:\d+(?:\s*[ap]m)?)#i", $root, $m)) {
                $h->booked()->checkIn($this->normalizeDate($m[1]));
            } elseif (preg_match("#{$this->t('Check-in:')}\s+([\w\-]+,\s+.+?\d+:\d+(?:\s*[ap]m)?)#i", $root, $m)) {
                $h->booked()->checkIn($this->normalizeDate($m[1]));
            }

            if (preg_match("#{$this->t('Check-out:')}\s+([\w\-]+,\s+.+?\d+:\d+(?:\s*[ap]m)?)#i", $root, $m)) {
                $h->booked()->checkOut($this->normalizeDate($m[1]));
            } elseif (preg_match("#{$this->t('Check-out:')}\s+([\w\-]+,\s+.+?\d+:\d+(?:\s*[ap]m)?)#i", $root, $m)) {
                $h->booked()->checkOut($this->normalizeDate($m[1]));
            }

            $h->hotel()->address($this->re("#\s+{$this->opt($this->t('Address:'))}\s*([^\n]+)#", $root));
            $h->hotel()->phone($this->re("#\s+{$this->opt($this->t('Phone:'))}\s*([\d\- ]+)#", $root));
            $h->hotel()->fax($this->re("#\s+{$this->opt($this->t('Fax:'))}\s*([\d\- ]+)#", $root), true, false);
            $r = $h->addRoom();
            $r->setRate(trim($this->re("#{$this->opt($this->t('Average Rate:'))}\s*([^\n]+)#", $root), " ,."), true, true);
            $h->booked()->rooms($this->re("#{$this->opt($this->t('Number of Rooms:'))}\s*(\d+)#", $root), false, true);
        }
    }

    private function parsePlainCar(Email $email, $nodes)
    {
        foreach ($nodes as $root) {
            $r = $email->add()->rental();
            $r->general()->confirmation($this->re("/{$this->opt($this->t('Confirmation #:'))}\s*+([\w\-]+)/", $root));

            $company = $this->getField($this->t("Vendor:"), $root);

            if (($code = $this->normalizeRentalProvider($company))) {
                $r->program()->code($code);
            } else {
                $r->extra()->company($company);
            }

            // Pick-up: Friday, Jul 4 08:00 New York Jfk
            // Address: Building 308 Federal Circle
            // Tel.: 888-826-6890
            if (
            preg_match("#{$this->t('Pick-up:')}\s+(\w+,\s+.+?\d+:\d+(?:\s*[ap]m)?)\s*([^\n]*)\s+{$this->opt($this->t('Address:'))}\s+(.+?)\s+{$this->opt($this->t('Tel.:'))}\s+([\d\- ]+)#i",
                $root, $m)
            ) {
                $r->pickup()->date($this->normalizeDate($m[1]));
                $m[2] = trim($m[2]);

                if (!empty($m[2])) {
                    $r->pickup()->location($m[2] . ', ' . trim($m[3]));
                } else {
                    $r->pickup()->location(trim($m[3]));
                }
                $r->pickup()->phone($m[4]);
            }

            if (
            preg_match("#{$this->t('Drop-Off:')}\s+(\w+,\s+.+?\d+:\d+(?:\s*[ap]m)?)\s*([^\n]*)\s+{$this->opt($this->t('Address:'))}\s+(.+?)\s+{$this->opt($this->t('Tel.:'))}\s+([\d\- ]+)#i",
                $root, $m)
            ) {
                $r->dropoff()->date($this->normalizeDate($m[1]));
                $m[2] = trim($m[2]);

                if (!empty($m[2])) {
                    $r->dropoff()->location($m[2] . ', ' . trim($m[3]));
                } else {
                    $r->dropoff()->location(trim($m[3]));
                }
                $r->dropoff()->phone($m[4]);
            }
            $r->car()->type($this->getField($this->t("Car size:"), $root));
            $node = $this->getField($this->t('Total Car Cost'), $root);
            $tot = $this->getTotalCurrency($node);

            if (!empty($tot['Total'])) {
                $r->price()->total($tot['Total']);
                $r->price()->currency($tot['Currency']);
            }
        }
    }

    private function nextCol($field, $root = null, $regexp = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and " . $this->eq($field) . "])[{$n}]/following-sibling::td[1]",
            $root, true, $regexp);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date)
    {
        $this->logger->debug('$date = ' . print_r($date, true));
        $year = date('Y', $this->date);
        // first dates with a year, then dates without a year
        $in = [
            // Quarta-feira, 13 Ago 2014, 20:39
            "/^[-[:alpha:]]+[,\s]+(\d{1,2})[-,.\s]+([[:alpha:]]+)[-,.\s]+(\d{4})[-,.\s]+({$this->patterns['time']})/u",
            // Quarta-feira, Ago 13, 2014 20:39
            "/^[-[:alpha:]]+[,\s]+([[:alpha:]]+)[-,.\s]+(\d{1,2})[-,.\s]+(\d{4})[-,.\s]+({$this->patterns['time']})/u",

            // Quarta-feira, 13 Ago, 20:39
            "/^([-[:alpha:]]+)[,\s]+(\d{1,2})[-,.\s]+([[:alpha:]]+)[-,.\s]+({$this->patterns['time']})/u",
            // Quarta-feira, Ago 13, 20:39
            "/^([-[:alpha:]]+)[,\s]+([[:alpha:]]+)[-,.\s]+(\d{1,2})[-,.\s]+({$this->patterns['time']})/u",
        ];
        $out = [
            '$1 $2 $3, $4',
            '$2 $1 $3, $4',

            "$1, $2 $3 $year, $4",
            "$1, $3 $2 $year, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match('/\d+\s+([[:alpha:]]+)\s+\d{4}/u', $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match('/^(?<week>[-[:alpha:]]+), (?<date>\d{1,2} [[:alpha:]]+ .+)/u', $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . str_replace("#", "\#", preg_quote($s)) . ")";
        }, $field)) . ')';
    }

    private function getField($field, $root)
    {
        return trim($this->re("#{$this->opt($field)}[ ]*([^\n]*)#", $root));
    }

    private function normalizeRentalProvider(?string $string): ?string
    {
        $string = trim($string);

        foreach (self::$rentalProviders as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (strcasecmp($string, $keyword) === 0) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function htmlToText($s, $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        //$node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[.\d,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[.\d,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>-*?)(?<t>\d[.\d,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00    ->    11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790        ->    2790        or    4.100,00    ->    4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,        ->    18800
            $m['t'] = preg_replace('/,(\d{1,2})$/', '.$1', $m['t']);    // 18800,00        ->    18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

    private function strposArray($text, $needle)
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            $positions = [];

            foreach ($needle as $n) {
                $pos = strpos($text, $n);

                if ($pos !== false) {
                    $positions[] = $pos;
                }
            }

            if (!empty($positions)) {
                return min($positions);
            } else {
                return false;
            }
        } elseif (is_string($needle)) {
            return strpos($text, $needle);
        }

        return false;
    }
}
