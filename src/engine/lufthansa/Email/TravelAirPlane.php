<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Schema\Parser\Email\Email;

class TravelAirPlane extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-162949844.eml, lufthansa/it-17802993.eml, lufthansa/it-4721436.eml, lufthansa/it-4752316.eml, lufthansa/it-269554382.eml, lufthansa/it-272817206.eml";
    public static $dict = [
        'en' => [
            'passengerRow'        => ['Your flight details', 'Check in online now', 'Happy to see you again'],
            'Departs'             => ['Departs', 'Departure'],
            'Arrives'             => ['Arrives', 'Arrival'],
            'Check in online now' => ['Check in online now', 'Happy to see you again'],
        ],
        'de' => [
            'Check in online now' => ['Nutzen Sie jetzt den Online Check-in', 'Schön, Sie wieder zu fliegen'],
            'Booking code'        => 'Buchungscode',
            'passengerRow'        => 'Ihre Flugdetails',
            'Date'                => 'Datum',
            'Departs'             => 'Abflug',
            'Arrives'             => 'Ankunft',
            'Class'               => ['Class', 'Klasse'],
        ],
        'it' => [
            'Check in online now' => ['Siamo pronti per il check‑in online'],
            'Booking code'        => 'Codice di prenotazione',
            'passengerRow'        => ['I dati del suo volo', 'Siamo pronti per il check‑in online'],
            'Date'                => 'Data',
            'Departs'             => 'Partenza',
            'Arrives'             => 'Arrivo',
            'Class'               => ['Classe'],
        ],
        'fr' => [
            'Check in online now' => ['Enregistrez-vous en ligne,'],
            'Booking code'        => 'Code de réservation',
            'passengerRow'        => 'Enregistrez-vous en ligne,',
            'Date'                => 'Date',
            'Departs'             => 'Départ',
            'Arrives'             => 'Arrivée',
            'Class'               => ['Classe'],
        ],
        'es' => [
            'Check in online now' => ['Realice ahora el Check‑in online'],
            //            'Booking code'        => 'Codice di prenotazione',
            'passengerRow'        => 'Realice ahora el Check‑in online',
            'Date'                => 'Fecha',
            'Departs'             => 'Salida',
            'Arrives'             => 'Llegada',
            'Class'               => ['Clase'],
        ],
        'pt' => [
            'Check in online now' => ['faça agora o check‑in online!'],
            //            'Booking code'        => 'Codice di prenotazione',
            'passengerRow'        => 'faça agora o check‑in online!',
            'Date'                => 'Data',
            'Departs'             => 'Partida',
            'Arrives'             => 'Chegada',
            'Class'               => ['Classe'],
        ],
    ];
    public $lang = '';

    private $subjects = [
        'en' => ['Our service offer for your flight to'],
        'de' => ['Unser Service-Angebot für Ihren Flug nach', 'steht jetzt zum Check-in bereit'],
        'it' => ['I nostri servizi per il suo volo a', 'è già pronto per il check-in'],
        'fr' => ['est prêt pour l\'enregistrement.'],
        'es' => ['está listo para el Check-in'],
        'pt' => ['Já está disponível o check-in do seu voo'],
    ];

    private static $reBody = [
        'it' => ['I dati del suo volo,', 'Siamo pronti per il check‑in online', 'Siamo pronti per il check-in online'],
        'en' => ['Your flight details', 'Check in online now', 'Happy to see you again'],
        'de' => ['Ihre Flugdetails', 'Nutzen Sie jetzt den Online Check-in', 'Schön, Sie wieder zu fliegen'],
        'fr' => ['Enregistrez-vous en ligne,', 'détail de votre réservation'],
        'es' => ['Realice ahora el Check‑in online'],
        'pt' => ['faça agora o check‑in online!'],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = html_entity_decode($this->http->Response['body']);

        if (isset(self::$reBody)) {
            foreach (self::$reBody as $lang => $reBody) {
                foreach ($reBody as $re) {
                    if (stripos($body, $re) !== false || $this->http->XPath->query("//text()[" . $this->contains($re) . "]")->length > 0) {
                        $this->lang = $lang;
                    }
                }
            }
        }

        $this->parseEmail($email);
        $email->setType('TravelAirPlane' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = html_entity_decode($this->http->Response['body']);

        if (isset(self::$reBody)) {
            foreach (self::$reBody as $lang => $reBody) {
                foreach ($reBody as $re) {
                    if (stripos($body, $re) !== false && $this->http->XPath->query("//text()[contains(normalize-space(.), 'Lufthansa')]")->length > 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (preg_match('/Your flight LH[ ]*\d+ .{7,} is now ready for Check-in/i', $headers['subject']) > 0) {
            return true;
        }

        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && (stripos($from, 'lufthansa.com') !== false
            || stripos($from, 'flightupdate@your.lufthansa-group.com') !== false);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$reBody);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$reBody);
    }

    private function getSegmentType(\DOMNode $root): string
    {
        $xpathImg = "descendant::node()[not(self::img[contains(@src,'spacer')])][self::img or self::text()[normalize-space() and normalize-space()!=' ']][1][self::img][contains(@src,'%s')]";

        if ($this->http->XPath->query(sprintf($xpathImg, 'railimg'), $root)->length > 0) {
            // it-269554382.eml
            return 'train';
        } elseif ($this->http->XPath->query(sprintf($xpathImg, 'busimg'), $root)->length > 0) {
            // it-272817206.eml
            return 'bus';
        }

        return 'flight';
    }

    private function parseEmail(Email $email): void
    {
        $xpathAirportCode = 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")="∆∆∆"';
        $xpathTime = 'contains(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")';

        $its = [];

        $xpath = "//span[" . $this->contains($this->t('passengerRow')) . "]/ancestor::table[5]/following-sibling::table[" . $this->contains($this->t('Date')) . " or " . $this->contains($this->t('Departs')) . "]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $xpath = "//text()[" . $this->contains($this->t('Date')) . "]/ancestor::table[" . $this->contains($this->t('Departs')) . "][1]/ancestor::table[1]";
            $segments = $this->http->XPath->query($xpath);
        }

        if ($segments->length === 0) {
            $this->logger->debug('Segments not found: ' . $xpath);

            return;
        }

        foreach ($segments as $key => $root) {
            $segmentType = $this->getSegmentType($root);
            $this->logger->debug("Segment-{$key} type: " . $segmentType);

            $nameDep = $codeDep = $nameArr = $codeArr = $dateDep = $dateArr = $airlineName = $flightNumber = $cabin = null;

            $xpathAirportsRow1 = "descendant::tr[count(*[descendant::text()[{$xpathAirportCode}]])=2 and not(contains(.,':'))]";
            $airportsRow1 = $this->http->FindSingleNode($xpathAirportsRow1, $root);

            if (preg_match('/^(?<DepCity>.{2,}?)\s+(?<DepCode>[A-Z]{3})\s+\S*\s+(?<ArrCity>.{2,}?)\s+(?<ArrCode>[A-Z]{3})$/', $airportsRow1, $m)) {
                $codeDep = $m['DepCode'];
                $codeArr = $m['ArrCode'];
            }

            $xpathAirportsRow2 = $xpathAirportsRow1 . "/ancestor-or-self::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()][1]/descendant-or-self::tr[ *[normalize-space()][2] ][1]";
            $nameDep = $this->http->FindSingleNode($xpathAirportsRow2 . "/*[normalize-space()][1]", $root);
            $nameArr = $this->http->FindSingleNode($xpathAirportsRow2 . "/*[normalize-space()][2]", $root);

            $dXpath = "descendant::tr/*[ count(table)=3 and table[2][count(descendant::*[not(.//tr) and ../self::tr and {$xpathTime}])=2] ]";
            $date = $this->normalizeDate($this->http->FindSingleNode($dXpath . "/table[1]/descendant::tr[count(td)=2][1]/td[1]/descendant::tr[1]", $root));
            $depTime = $this->http->FindSingleNode($dXpath . "/table[2]/descendant::td[" . $this->contains($this->t('Departs')) . "]/descendant::tr[count(td)>=2]/td[2]", $root);
            $isNewDayDep = $this->http->FindSingleNode($dXpath . "/table[2]/descendant::td[" . $this->contains($this->t('Departs')) . "]/descendant::tr[count(td)>=2]/td[3]", $root, true, '#\((.+)\)#');
            $dateDep = $this->checkDate($date, $depTime, $isNewDayDep);
            $arrTime = $this->http->FindSingleNode($dXpath . "/table[2]/descendant::td[" . $this->contains($this->t('Arrives')) . "]/descendant::tr[count(td)>=2]/td[2]", $root);
            $isNewDayArr = $this->http->FindSingleNode($dXpath . "/table[2]/descendant::td[" . $this->contains($this->t('Arrives')) . "]/descendant::tr[count(td)>=2]/td[3]", $root, true, '#\((.+)\)#');
            $dateArr = $this->checkDate($date, $arrTime, $isNewDayArr);

            $flight = $this->http->FindSingleNode($xpathAirportsRow1 . '/ancestor::*[ ../self::tr and following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1]', $root);

            if (preg_match('/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)$/', $flight, $m)) {
                $airlineName = $m[1];
                $flightNumber = $m[2];
            }

            $cabin = $this->http->FindSingleNode($dXpath . "/table[3][" . $this->contains($this->t('Class')) . "]/descendant::tr[" . $this->eq($this->t('Class')) . "]/preceding-sibling::tr[1]", $root);

            if ($segmentType === 'train') {
                if (!isset($train)) {
                    $train = $email->add()->train();
                    $its[] = $train;
                }

                $trainSegment = $train->addSegment();
                $trainSegment->departure()->name($nameDep)->date($dateDep);
                $trainSegment->arrival()->name($nameArr)->date($dateArr);
                $trainSegment->extra()->cabin($cabin, false, true)->number($flightNumber);
            } elseif ($segmentType === 'bus') {
                if (!isset($bus)) {
                    $bus = $email->add()->bus();
                    $its[] = $bus;
                }

                $busSegment = $bus->addSegment();
                $busSegment->departure()->name($nameDep)->date($dateDep);
                $busSegment->arrival()->name($nameArr)->date($dateArr);
                $busSegment->extra()->cabin($cabin, false, true)->number($flightNumber);
            } elseif ($segmentType === 'flight') {
                if (!isset($f)) {
                    $f = $email->add()->flight();
                    $its[] = $f;
                }

                if (isset($flightSegment)
                    && !empty($nameDep) && $nameDep === $flightSegment->getDepName()
                    && !empty($codeDep) && $codeDep === $flightSegment->getDepCode()
                    && !empty($nameArr) && $nameArr === $flightSegment->getArrName()
                    && !empty($codeArr) && $codeArr === $flightSegment->getArrCode()
                    && !empty($dateDep) && $dateDep === $flightSegment->getDepDate()
                    && !empty($dateArr) && $dateArr === $flightSegment->getArrDate()
                ) {
                    if (!empty($airlineName) && $airlineName === 'LH' && $flightSegment->getAirlineName() !== 'LH'
                        || !empty($cabin) && empty($flightSegment->getCabin())
                    ) {
                        $flightSegment->airline()->name($airlineName)->number($flightNumber);
                        $flightSegment->extra()->cabin($cabin, false, true);
                    }
                    $this->logger->debug("Found duplicate flight segment-{$key}!");

                    continue;
                }

                $flightSegment = $f->addSegment();
                $flightSegment->departure()->code($codeDep)->name($nameDep)->date($dateDep);
                $flightSegment->arrival()->code($codeArr)->name($nameArr)->date($dateArr);
                $flightSegment->airline()->name($airlineName)->number($flightNumber);
                $flightSegment->extra()->cabin($cabin, false, true);
            }
        }

        foreach ($its as $it) {
            $confirmation = $this->http->FindSingleNode("//tr[{$this->contains($this->t('Booking code'))}]/preceding-sibling::tr[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/");

            if (empty($confirmation)) {
                $confirmation = $this->http->FindSingleNode("//tr[not(.//tr)][{$this->contains($this->t('Booking code'))}]",
                    null, true, "/^\s*{$this->opt($this->t('Booking code'))}\s*:\s*([A-Z\d]{5,7})\s*$/");
            }

            if ($confirmation) {
                $it->general()->confirmation($confirmation);
            } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('Check in online now'))}]")->length > 0) {
                $it->general()->noConfirmation();
            }

            $passenger = $this->http->FindSingleNode("//text()[{$this->starts($this->t('passengerRow'))}]/ancestor::tr[preceding-sibling::tr and following-sibling::tr][1]/*[normalize-space()][1]", null, true, "/{$this->opt($this->t('passengerRow'))}\s*,\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/u");

            if (empty($passenger)) {
                $passenger = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('passengerRow'))}])[1]/ancestor::tr[preceding-sibling::tr and following-sibling::tr][1]/*[normalize-space()][1]", null, true, "/^\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]), {$this->opt($this->t('passengerRow'))}\s*$/u");
            }

            if (!empty($passenger)) {
                $passenger = preg_replace("/^\s*(?:Mr\.|Herr|sig\.|Signor|Sr\.)( Dr\.)?\s+/", "", $passenger);
                $it->general()->traveller($passenger);
            }
        }
    }

    /**
     * check for the existence of a new day (+1).
     *
     * @param $date
     * @param $time
     * @param null $isNewDay
     *
     * @return int|string
     */
    private function checkDate($date, $time, $isNewDay = null)
    {
        $res = '';

        if (!empty($date) && !empty($time) && $isNewDay === null) {
            $res = strtotime($date . ' ' . $time);
        } elseif (!empty($date) && !empty($time) && !empty($isNewDay)) {
            $date = strtotime('+1 day', strtotime($date));
            $date = date('m/d/y', $date);
            $res = strtotime($date . ' ' . $time);
        }

        return $res;
    }

    /**
     * example: 2016-10-21.
     *
     * @param $str
     *
     * @return string|null
     */
    private function normalizeDate($str)
    {
        $in = [
            '#(?<Year>\d{4})-(?<Month>\d+)-(?<Day>\d+)#', //2016-10-21
            '#(?<Day>\d+)\.(?<Month>\d+)\.(?<Year>\d+)#', //17.10.2016
        ];

        foreach ($in as $item) {
            if (preg_match($item, $str, $m)) {
                return $m['Month'] . '/' . $m['Day'] . '/' . $m['Year'];
            }
        }

        return null;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dict, $this->lang) || empty(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
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
}
