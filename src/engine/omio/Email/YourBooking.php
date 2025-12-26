<?php

namespace AwardWallet\Engine\omio\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "omio/it-112243512.eml, omio/it-118025852.eml, omio/it-79168915.eml, omio/it-88830172.eml, omio/it-89765521.eml, omio/it-89780409.eml, omio/it-140173230-es.eml, omio/it-153600164-pt.eml";

    public $lang = '';
    public $provCode = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['Your Booking Code', 'Your booking number is:', 'Your booking number'],
            'route'      => ['Outbound', 'Return'],
            'feeNames'   => ['Service fee', 'Card Processing Fee'],
            'Adult'      => ['Adult', 'ADULT', 'Youth', 'Senior'],
        ],
        'ru' => [
            'confNumber' => ['Ваш код бронирования:'],
            'Passengers' => ['Пассажиры'],
            'route'      => ['Вылет'],
            'feeNames'   => ['Повышение класса обслуживания', 'Сервисный сбор'],
            'Total'      => ['Всего'],
            'Ticket'     => ['Билеты'],
            'Adult'      => ['Взрослый', 'Пассажир'],
        ],
        'it' => [
            'confNumber' => ['Il tuo codice di prenotazione è:'],
            'Passengers' => ['Passeggeri'],
            'route'      => ['Andata', 'Ritorno'],
            'feeNames'   => ['Costi di servizio'],
            'Total'      => ['Totale'],
            'Ticket'     => ['Biglietti'],
            'Adult'      => ['Giovane'],
        ],
        'fr' => [
            'confNumber' => ['Votre numéro de réservation :'],
            'Passengers' => ['Passagers - Passagères'],
            'route'      => ['Aller'],
            'feeNames'   => ['Surclassement', 'Changement de tarif', 'Frais de service'],
            'Total'      => ['Total'],
            'Ticket'     => ['Billets'],
            'Adult'      => ['Adulte'],
        ],
        'es' => [
            'confNumber' => ['Tu código de reserva es:', 'Tu código de reserva es :', 'Tu código de reserva'],
            'Passengers' => ['Pasajeros'],
            'route'      => ['Ida', 'Vuelta'],
            'feeNames'   => ['Tasa de servicio'],
            'Total'      => ['Total'],
            'Ticket'     => ['Billetes'],
            'Adult'      => ['Adulto', 'ADULTO', 'Joven'],
        ],
        'pt' => [
            'confNumber' => ['Número de reserva:', 'Número de reserva :', 'Código de reserva:'],
            'Passengers' => ['Passageiros'],
            'route'      => ['Ida', 'Regresso'],
            'feeNames'   => ['Taxa de serviço'],
            'Total'      => ['Total'],
            'Ticket'     => ['Bilhetes'],
            'Adult'      => ['Adulto', 'Jovem', 'Sénior'],
        ],
    ];

    private $subjects = [
        'es' => ['Su reserva a'],
        'pt' => ['Os seus bilhetes estão aqui'],
        'en' => ['Your tickets to'],
    ];

    private $detectors = [
        'es' => ['Tu código de reserva'],
        'pt' => ['A sua reserva', 'Reserva bem sucedida'],
        'en' => ['Your Booking', 'Your ticket details', 'Your booking'],
        'ru' => ['Ваше бронирование'],
        'it' => ['Il tuo codice di prenotazione'],
        'fr' => ['Votre réservation est enregistrée'],
    ];

    public static function getEmailProviders()
    {
        return ['omio', 'uber'];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@omio.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".omio.com/") or contains(@href,"emailservicelinks.omio.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(.,"www.omio.com")]')->length > 0) {
            $this->provCode = 'omio';

            return $this->detectBody() && $this->assignLang();
        }

        if ($this->http->XPath->query('//text()[contains(.,"Uber London Ltd")]')->length > 0
            && $this->http->XPath->query('//*[contains(.,"You can open the attached ticket PDF and use it to travel")]')->length > 0) {
            $this->provCode = 'uber';

            return $this->detectBody() && $this->assignLang();
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        if (!empty($this->provCode)) {
            $email->setProviderCode($this->provCode);
        }

        $email->setType('YourBooking' . ucfirst($this->lang));

        $regionByKeywords = [
            // Spain
            'es'          => ['Alsa', 'Avanza'],
            // Switzerland
            'ch'    => ['Eurolines Switzerland'],
            // United Kingdom
            'uk' => ['National Express', 'Great Western Railway', 'Heathrow Express', 'Cross Country', 'East Midlands Railway', 'Southern', 'South Western Railway'],
            // USA
            'us'             => ['Amtrak'],
            'europe'         => ['Italo', 'Scotrail', 'Northern Rail', 'Deutsche Bahn', 'NordWestBahn', 'Comboios De Portugal',
                'Transportes Comes', 'RegioJet', 'Regionale', 'Megabus', 'Snav', 'Infobus', 'Swiss Tours', 'Frecciarossa', 'SNCB', 'ÖBB', ],
        ];

        $xpathTime = 'contains(translate(normalize-space(),"0123456789：","dddddddddd:"),"d:dd")';
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        $otaConfirmationValue = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[, A-z\d\-]{5,}$/');

        if (empty($otaConfirmationValue)) {
            $otaConfirmationValue = $this->http->FindSingleNode("//text()[{$this->starts($this->t('route'))}]/preceding::text()[{$this->eq($this->t('confNumber'))}][1]/following::text()[normalize-space()][1]", null, true, '/^[, A-z\d\-]{5,}$/');
        }

        $otaConfirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)(?:\s+is)?[\s:：]*$/u');

        if (empty($otaConfirmationTitle)) {
            $otaConfirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('route'))}]/preceding::text()[{$this->eq($this->t('confNumber'))}][1]", null, true, '/^(.+?)(?:\s+is)?[\s:：]*$/u');
        }

        foreach (preg_split("/\s*[,]+\s*/", $otaConfirmationValue) as $otaConfirmation) {
            $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
        }

        $passengers = array_filter($this->http->FindNodes("//*[{$xpathBold} and {$this->eq($this->t('Passengers'))}]/following-sibling::div[contains(.,'(') and contains(.,')')][not(contains(normalize-space(), 'Adult'))]", null, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*\(|$)/u"));

        if (count($passengers) == 0) {
            $passengers = $this->http->FindNodes("//text()[{$this->eq($this->t('Adult'))}]/ancestor::td[1]/preceding::td[1][not(contains(normalize-space(), 'Adult'))]");
        }

        if (count($passengers) == 0) {
            $passengers = $this->http->FindNodes("//text()[{$this->contains(preg_replace("/^(.+)$/", '($1)', $this->t('Adult')))}]",
                null, "/^(\D+)\s*\(/");
        }

        $noTravellers = false;

        if (!empty($passengers) && empty(array_filter($passengers, function ($v) {return preg_match("/^\s*{$this->opt($this->t('Passengers'))}\s*$/", $v) ? false : true; }))) {
            $noTravellers = true;
        }

        $xpathSegment = "//tr[ *[1][{$xpathTime}] and following-sibling::tr[2]/*[1][{$xpathTime}] and following-sibling::tr[2]/*[2]/descendant::img[contains(@src,'/Circle') or contains(@src,'/CircleSmall.') or contains(@src,'/Pin')] ]";

        // BUS (it-79168915.eml)
        $busSegments = $this->http->XPath->query($xpathSegment . "[ *[2]/descendant::img[contains(@src,'/bus.')] ]");

        if ($busSegments->length == 0) {
            $busSegments = $this->http->XPath->query($xpathSegment . "[ *[2]/descendant::img[contains(@src,'/Bus')] ]");
        }

        if ($busSegments->length) {
            $this->logger->debug('Found ' . $busSegments->length . ' BUS segments.');
            $bus = $email->add()->bus();
            $bus->general()->noConfirmation();

            if ($noTravellers !== true) {
                $bus->general()->travellers($passengers);
            }
        }

        foreach ($busSegments as $bSegment) {
            $s = $bus->addSegment();
            $fields = $this->getSegmentFields($bSegment);

            $region = null;

            if (!empty($fields['type'])) {
                foreach ($regionByKeywords as $r => $keywords) {
                    foreach ($keywords as $k) {
                        if (strpos($fields['type'], $k) !== false) {
                            $region = $r;

                            break 2;
                        }
                    }
                }
            }

            $s->departure()
                ->date($fields['dateDep'])
                ->name($fields['nameDep'])
            ;
            $s->arrival()
                ->date($fields['dateArr'])
                ->name($fields['nameArr'])
            ;

            if (!empty($region)) {
                $s->departure()
                    ->geoTip($region);

                $s->arrival()
                    ->geoTip($region);
            }
            $dRegion = $this->regionByStationName($s->getDepName(), $region);

            if (!empty($dRegion)) {
                $s->departure()
                    ->geoTip($dRegion);
            }
            $aRegion = $this->regionByStationName($s->getArrName(), $region);

            if (!empty($aRegion)) {
                $s->arrival()
                    ->geoTip($aRegion);
            }

            $s->extra()
                ->duration($fields['duration'])
                ->type(empty($fields['type']) ? null : preg_replace("/\|.+/", "", $fields['type']), false, true)
            ;

            if ($fields['number'] !== null) {
                $s->extra()->number($fields['number']);
            }
        }

        // TRAIN (bahn/it-53916811.eml)
        $trainSegments = $this->http->XPath->query($xpathSegment . "[ *[2]/descendant::img[contains(@src,'/Train')] ]");

        if ($trainSegments->length) {
            $train = $email->add()->train();
            $train->general()->noConfirmation();

            if ($noTravellers !== true) {
                $train->general()->travellers($passengers);
            }
        }

        foreach ($trainSegments as $tSegment) {
            $s = $train->addSegment();
            $fields = $this->getSegmentFields($tSegment);

            $region = null;

            $type = $fields['operator'] ?? $fields['type'];

            if (!empty($type)) {
                foreach ($regionByKeywords as $r => $keywords) {
                    foreach ($keywords as $k) {
                        if (strpos($type, $k) !== false) {
                            $region = $r;

                            break 2;
                        }
                    }
                }
            }

            $s->departure()
                ->date($fields['dateDep'])
                ->name($fields['nameDep'])
            ;
            $s->arrival()
                ->date($fields['dateArr'])
                ->name($fields['nameArr'])
            ;

            if (!empty($region)) {
                $s->departure()
                    ->geoTip($region);
                $s->arrival()
                    ->geoTip($region);
            }

            $dRegion = $this->regionByStationName($s->getDepName(), $region);

            if (!empty($dRegion)) {
                $s->departure()
                    ->geoTip($dRegion);
            }
            $aRegion = $this->regionByStationName($s->getArrName(), $region);

            if (!empty($aRegion)) {
                $s->arrival()
                    ->geoTip($aRegion);
            }

            $s->extra()
                ->duration($fields['duration']);

            if (!empty($fields['type'])) {
                $s->extra()
                    ->type($fields['type']);
            }

            if (!empty($fields['operator'])) {
                $s->setServiceName($fields['operator']);
            }

            if ($fields['number'] !== null) {
                $s->extra()->number($fields['airName'] . $fields['number']);
            } else {
                $s->extra()
                    ->noNumber();
            }

            if ($fields['cabin'] !== null) {
                $s->extra()->cabin($fields['cabin']);
            }
        }

        // FLIGHT
        $flightSegments = $this->http->XPath->query($xpathSegment . "[ *[2]/descendant::img[contains(@src,'/Flight')] ]");

        if ($flightSegments->length) {
            $flight = $email->add()->flight();
            $flight->general()->noConfirmation();

            if ($noTravellers !== true) {
                $flight->general()->travellers($passengers);
            }
        }

        foreach ($flightSegments as $tSegment) {
            $s = $flight->addSegment();
            $fields = $this->getSegmentFields($tSegment);
            $s->airline()
                ->name($fields['airName'])
                ->operator($fields['operator'])
                ->number($fields['number']);
            $s->departure()
                ->date($fields['dateDep'])
                ->name($fields['nameDep'])
                ->code($fields['codeDep']);
            $s->arrival()
                ->date($fields['dateArr'])
                ->name($fields['nameArr'])
                ->code($fields['codeArr']);
            $s->extra()
                ->duration($fields['duration']);
        }

        //FERRY
        $ferrySegments = $this->http->XPath->query($xpathSegment . "[ *[2]/descendant::img[contains(@src,'/Ferry') or contains(@src,'/ferry.')] ]");

        if ($ferrySegments->length) {
            $ferry = $email->add()->ferry();
            $ferry->general()->noConfirmation();

            if ($noTravellers !== true) {
                $ferry->general()->travellers($passengers);
            }
        }

        foreach ($ferrySegments as $tSegment) {
            $s = $ferry->addSegment();

            $fields = $this->getSegmentFields($tSegment);

            $region = null;

            $type = $fields['operator'] ?? $fields['type'];

            if (!empty($type)) {
                foreach ($regionByKeywords as $r => $keywords) {
                    foreach ($keywords as $k) {
                        if (strpos($type, $k) !== false) {
                            $region = $r;

                            break 2;
                        }
                    }
                }
            }
            $s->departure()
                ->date($fields['dateDep'])
                ->name($fields['nameDep'])
                ->geoTip($region)
            ;
            $s->arrival()
                ->date($fields['dateArr'])
                ->name($fields['nameArr']);

            if (!empty($region)) {
                $s->departure()
                    ->geoTip($region);
                $s->arrival()
                    ->geoTip($region);
            }

            $s->extra()
                ->duration($fields['duration'])
                ->carrier($fields['operator'] ?? $fields['type']);
        }

        $totalPrice = $this->http->FindSingleNode("//div[ count(div)=2 and div[1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/div[2]");

        if (empty($totalPrice)) {
            $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/following::text()[normalize-space()][not({$this->contains($this->t('tax'))})][1]");
        }

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)
            || preg_match('/^(?<amount>\d[,.\'\d ]*)(?<currency>[^\d)(]+?)[ ]*$/', $totalPrice, $m)
        ) {
            // $56.48
            $currency = $this->normalizeCurrency($m['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $email->price()->currency($currency)->total(PriceHelper::parse($m['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//div[ count(div)=2 and div[1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Ticket'))} or {$this->starts($this->t('Ticket'))} and contains(.,'(')] ]/div[2]");

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $baseFare, $matches)
                || preg_match('/(?<amount>\d[,.\'\d ]*)/', $baseFare, $matches)
            ) {
                $email->price()->cost(PriceHelper::parse($matches['amount'], $currencyCode));
            }

            $feeRows = $this->http->XPath->query("//div[ count(div)=2 and div[1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('feeNames'))}] ]");

            if ($feeRows->length == 0) {
                $feeRows = $this->http->XPath->query("//text()[{$this->eq($this->t('feeNames'))}]/ancestor::tr[contains(normalize-space(), '.')][1]");
            }

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('div[2]', $feeRow);

                if (empty($feeCharge)) {
                    $feeCharge = $this->http->FindSingleNode('./descendant::table[last()]', $feeRow);
                }

                if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d]*)$/', $feeCharge, $matches)
                    || preg_match('/(?<amount>\d[,.\'\d]*)/', $feeCharge, $matches)
                ) {
                    $feeName = $this->http->FindSingleNode('div[1]', $feeRow);

                    if (empty($feeName)) {
                        $feeName = $this->http->FindSingleNode('./descendant::table[1]', $feeRow);
                    }
                    $email->price()->fee($feeName, PriceHelper::parse($matches['amount'], $currencyCode));
                }
            }
        }

        return $email;
    }

    public function regionByStationName($name, $region)
    {
        switch ($name) {
            case $name === 'Antwerp-Central' && $region == 'europe':
                // Belgium
                return 'be';

            case $name === 'Hamelin station' && $region == 'europe':
                // Germany
                return 'de';

            case $name === 'Gloucester station' && $region == 'europe':
            case $name === 'Winchester station (WIN)' && $region == 'europe':
                // United Kingdom
                return 'gb';

            case $name === 'Otranto station' && $region == 'europe':
            case $name === 'Alba station' && $region == 'europe':
                // Italy
                return 'it';
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

    private function getSegmentFields(\DOMNode $root): array
    {
        $result = [
            'dateDep'  => null,
            'dateArr'  => null,
            'nameDep'  => null,
            'nameArr'  => null,
            'type'     => null,
            'number'   => null,
            'duration' => null,
            'operator' => null,
            'codeDep'  => null,
            'codeArr'  => null,
            'airName'  => null,
            'cabin'    => null,
        ];

        $patterns['time'] = '\d{1,2}[:：]\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?';

        $dateValue = $this->http->FindSingleNode("ancestor::table[1]/ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1]", $root, true, "/^{$this->opt($this->t('route'))}\s+-\s+(.{6,})$/");

        if (empty($dateValue)) {
            $dateValue = $this->http->FindSingleNode("ancestor::table[1]/preceding::text()[normalize-space()][1]/ancestor::table[1]", $root, true, "/{$this->opt($this->t('route'))}\s*\-*\s*(.{6,})/");
        }

        $date = strtotime($this->normalizeDate($dateValue));

        $timeDep = $this->http->FindSingleNode("*[1]", $root, true, "/^({$patterns['time']})/");

        if ($date && $timeDep) {
            $result['dateDep'] = strtotime($timeDep, $date);
        }

        $result['nameDep'] = $this->http->FindSingleNode("*[3]", $root);

        if (preg_match("/^(.+)\s\(([A-Z]{3})\)$/u", $result['nameDep'], $m)) {
            $result['codeDep'] = $m[2];
            $result['nameDep'] = $m[1];
        }

        // 3h10    |    0h50
        $result['duration'] = $this->http->FindSingleNode("following-sibling::tr[1]/*[1]", $root, true, "/^\d.+$/");

        // Eurolines Switzerland |    [OR]    Deutsche Bahn | IR 2122
        $serviceValue = $this->http->FindSingleNode("following-sibling::tr[1]/*[3]", $root);

        if (preg_match("/^(?<operator>.+)\s*\|\s*(?<airname>[A-z]{2})(?<number>\d{2,4})$/", $serviceValue, $m)
        || preg_match("/^.+\s*\|\s*(?<airname>[A-z]{2})(?<number>\d{2,4})\s*Operated by\s*(?<operator>.+)$/", $serviceValue, $m)) {
            $result['operator'] = $m['operator'];
            $result['airName'] = $m['airname'];
            $result['number'] = $m['number'];
        } elseif (preg_match("/^(?<operator>.+)\s*\|\s*[A-Z\-]+\d{4}\-\d+\-\d+\-[\d\:]+$/u", $serviceValue, $m)) {
            $result['operator'] = $m['operator'];
        } elseif (preg_match("/^(?<type>.+)\s*\|\s*(?<number>\d+)/u", $serviceValue, $m)) {
            if (!empty($m['type'])) {
                $result['type'] = $m['type'];
            }
            $result['number'] = $m['number'];
        } elseif (preg_match("/^(?<operator>.+)\s*\|\s*(?<airname>[A-z\d]{2})(?<number>\d{2,4})$/", $serviceValue, $m)) {
            $result['operator'] = $m['operator'];
            $result['airName'] = $m['airname'];
            $result['number'] = $m['number'];
        } elseif (preg_match("/^(?<type>.+?)\s+\|\s+(?<number>[A-Z\d]+\_[A-Z\d]+)/u", $serviceValue, $m)) {
            if (!empty($m['type'])) {
                $result['type'] = $m['type'];
            }
            $result['number'] = $m['number'];
        } elseif (preg_match("/^(?<type>.+?)?[\s\|]*(?<number>\d+)/u", $serviceValue, $m)) {
            if (!empty($m['type'])) {
                $result['type'] = $m['type'];
            }
            $result['number'] = $m['number'];
        } elseif (preg_match("/^(?<type>.+?)?[\s\|]*$/", $serviceValue, $m)) {
            if (!empty($m['type'])) {
                $result['type'] = $m['type'];
            }
        } elseif ($serviceValue !== null) {
            $result['type'] = trim($serviceValue, '| ');
        }

        $timeArr = $this->http->FindSingleNode("following-sibling::tr[2]/*[1]", $root, true, "/^({$patterns['time']})/");

        if ($date && $timeArr) {
            $result['dateArr'] = strtotime($timeArr, $date);

            if ($result['dateArr'] < $result['dateDep']) {
                $result['dateArr'] = strtotime('+1 day', $result['dateArr']);
            }
        }

        $result['nameArr'] = $this->http->FindSingleNode("following-sibling::tr[2]/*[3]", $root);

        if (preg_match("/^(.+)\s\(([A-Z]{3})\)$/", $result['nameArr'], $m)) {
            $result['codeArr'] = $m[2];
            $result['nameArr'] = $m[1];
        }

        $cabin = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Class:')]", null, true, "/{$this->opt($this->t('Class:'))}\s*(.+)/");

        if (!empty($cabin)) {
            $result['cabin'] = $cabin;
        }

        return $result;
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

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
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
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['route'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['route'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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

    private function normalizeDate($str)
    {
        $in = [
            // mar 03 ago 2021    |    сб, 22 мая 2021 г.    |    sáb., 23 de abr. de 2022
            // 21 de ago. de 2023
            '/^\s*(?:[-[:alpha:]]+[,.\s]+)?(\d{1,2})[.\s]+(?:de\s+)?([[:alpha:]]+)[,.\s]+(?:de\s+)?(\d{4})\D*$/u',
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'BRL' => ['R$'],
            'GBP' => ['£'],
            'EUR' => ['€'],
            'RUB' => ['₽'],
            'AUD' => ['AU $'],
            'USD' => ['US$'],
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
