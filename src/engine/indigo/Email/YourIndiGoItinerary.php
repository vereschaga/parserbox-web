<?php

namespace AwardWallet\Engine\indigo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class YourIndiGoItinerary extends \TAccountChecker
{
    public $mailFiles = "indigo/it-11685212.eml, indigo/it-11736081.eml, indigo/it-117515312.eml, indigo/it-11814236.eml, indigo/it-162624834.eml, indigo/it-336548841.eml, indigo/it-336553465.eml, indigo/it-336556397.eml, indigo/it-339373210.eml, indigo/it-34389756.eml, indigo/it-696942330.eml";

    private $langDetectors = [
        'en' => ['From (Terminal)', 'Booking Reference:', 'Booking Reference :', 'PNR/Booking Ref.:'],
    ];
    private $lang = '';

    private static $dict = [
        'en' => [
            'Booking Reference:' => ['Booking Reference:', 'Booking Reference :', 'PNR/Booking Ref.:'],
            'Indigo Passenger'   => ['Indigo Passenger', 'IndiGo Passenger'],
            'infant :'           => ['infant :', 'infant:', 'Infant :', 'Infant:'],
            'Total Fare'         => ['Total Fare', 'Fare Summary', 'Price Summary'],
            'CANCELLED'          => ['CANCELLED'],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'IndiGo') !== false
            || stripos($from, '@customer.goindigo.') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your IndiGo Itinerary') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".goindigo.in/")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Indigo Passenger") or contains(normalize-space(),"IndiGo Flight") or contains(normalize-space(),"ltd.(IndiGo)") or contains(.,"www.goindigo.")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $text = implode("\n", $parser->getRawBody());

        if (substr_count($text, 'Content-Type: text/html') > 1) {
            $text = implode("\n", $parser->getRawBody());

            if (preg_match_all("#(<html>[\s\S]+<\/html>)#iU", $text, $m)) {
                $this->http->SetEmailBody(quoted_printable_decode(implode('', $m[1])));
            }
        }

        $this->http->SetEmailBody(preg_replace('/[\x{200B}-\x{200D}]/u', '',
            $this->http->Response['body'])); // different zero width space

        if ($this->assignLang() === false) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $flights = $this->http->XPath->query("//*[ tr[normalize-space()][2] and tr[normalize-space()][1]/*[normalize-space()][2][{$this->starts($this->t('Date of Booking'))}] ]/ancestor-or-self::table[ following-sibling::table[string-length(normalize-space())>1] ][1]");

        foreach ($flights as $fRoot) {
            $nextTableCount[] = count($this->http->FindNodes('following-sibling::table[normalize-space()]', $fRoot));
        }

        foreach ($flights as $i => $fRoot) {
            if ($this->http->XPath->query("following-sibling::table[normalize-space()][.//*[{$this->starts($this->t('Date of Booking'))}]]", $fRoot)->length > 0) {
                $nextTableCount[$i] = $nextTableCount[$i] - ($nextTableCount[$i + 1] ?? 0);
            } else {
                $nextTableCount[$i]++;
            }
        }

        foreach ($flights as $i => $fRoot) {
            $positions = [
                'nextSiblingTableCount' => $nextTableCount[$i],
                'beforeSegment'         => $i,
                'nextSegment'           => $flights->length - $i,
            ];
            $this->parseFlight($email, $fRoot, $positions, $parser->getSubject());
        }

        $this->mergeAllFlights($email);

        $xpathFragment2 = "//td[{$this->eq($this->t('Total Fare'))}]";

        $currency = $this->http->FindSingleNode($xpathFragment2 . '/following-sibling::td[normalize-space()][1]', null, true, '/^[^\d)(]+$/');
        $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;

        if ($currency) {
            $email->price()->currency($currency);

            $feeRows = $this->http->XPath->query($xpathFragment2 . '/ancestor::tr[1]/preceding-sibling::tr[ td[3][normalize-space()] ]');

            foreach ($feeRows as $feeRow) {
                $feeName = $this->http->FindSingleNode('td[1]', $feeRow);
                $feeCurrency = $this->http->FindSingleNode('td[2]', $feeRow);
                $feeCharge = $this->http->FindSingleNode('td[3]', $feeRow, true, '/^(\d[,.\'\d\s]*)/');

                if ($feeName && $feeCurrency === $currency && $feeCharge !== null) {
                    if (stripos($feeName, 'Airfare Charges') !== false) {
                        $email->price()->cost(PriceHelper::parse($this->normalizeAmount($feeCharge), $currencyCode));
                    } else {
                        $feeSumm = PriceHelper::parse($this->normalizeAmount($feeCharge), $currencyCode);

                        if (!empty($feeName) && !empty($feeSumm)) {
                            $email->price()->fee($feeName, $feeSumm);
                        }
                    }
                }
            }
        }

        $totalFare = $this->http->FindSingleNode($xpathFragment2 . '/following-sibling::td[normalize-space()][2]', null, true, '/^(\d[,.\'\d\s]*)/');

        if ($totalFare !== null) {
            $email->price()->total(PriceHelper::parse($this->normalizeAmount($totalFare), $currencyCode));
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseFlight(Email $email, \DOMNode $fRoot, array $positions, string $subject): void
    {
        $followingSiblingTable = "following-sibling::table[string-length(normalize-space())>1][position() < {$positions['nextSiblingTableCount']}]";
        $positionConditions = "[count(preceding::tr/*[{$this->starts($this->t('Date of Booking'))}]) = " . ($positions['beforeSegment'] + 1) . "]"
            . "[count(following::tr/*[{$this->starts($this->t('Date of Booking'))}]) = " . ($positions['nextSegment'] - 1) . "]";

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
            'travellerName' => '[\.[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $r = $email->add()->flight();

        $confs = [];

        $cXpath = '//text()[' . $this->contains($this->t('Booking Reference:')) . ']';
        $cnodes = $this->http->XPath->query($cXpath);

        foreach ($cnodes as $croot) {
            $confs[$this->http->FindSingleNode('.', $croot, true, "/{$this->opt($this->t('Booking Reference:'))}\s*([A-Z\d]{5,})$/")] =
                trim($this->http->FindSingleNode('.', $croot, null, "/^(.*{$this->opt($this->t('Booking Reference:'))})\s*[A-Z\d]{5,}$/"), ' :');
        }

        $cXpath = '//text()[' . $this->eq($this->t('Booking Reference:')) . ']/following::text()[normalize-space()][1]';
        $cnodes = $this->http->XPath->query($cXpath);

        foreach ($cnodes as $croot) {
            $confs[$this->http->FindSingleNode('following::text()[normalize-space()][1]', $croot, true, '/^\s*([A-Z\d]{5,})\s*$/')] =
                trim($this->http->FindSingleNode('.', $croot), ' :');
        }

        $confs = array_filter($confs);

        if (count($confs) === 0 && preg_match("/\sItinerary[-\s]+(?-i)([A-Z\d]{5,10})$/i", $subject, $m)) {
            $confs[$m[1]] = null;
        }

        foreach ($confs as $conf => $title) {
            $r->general()
                ->confirmation($conf, $title);
        }

        $xpathFragment1 = "descendant::tr[ *[normalize-space()][1][{$this->eq($this->t('Status'))}] and *[normalize-space()][2][{$this->starts($this->t('Date of Booking'))}] ][1]";
        $status = $this->http->FindSingleNode($xpathFragment1 . '/following-sibling::tr[normalize-space()][1]/td[1]', $fRoot);
        $dateBooking = $this->http->FindSingleNode($xpathFragment1 . '/following-sibling::tr[normalize-space()][1]/td[2]', $fRoot, false, "/(.+?)\s*(?:\(|$)/");

        $r->general()
            ->status($status)
            ->date(strtotime($dateBooking));

        if (in_array($status, (array) $this->t('CANCELLED'))) {
            $r->general()->cancelled();
        }

        $passengerTexts = $this->http->FindNodes($followingSiblingTable . "[1]/descendant-or-self::*[ count(tr[normalize-space()])=2 and tr[normalize-space()][1][descendant::*[self::td or self::th][normalize-space() and not(.//tr)][1][{$this->contains($this->t('Indigo Passenger'))}]] ]/tr[normalize-space()][2]/descendant::td[normalize-space() and not(.//tr)]", $fRoot, "/^(?:\d{1,3}\s*\.)?\s*(?:Chd\. +)?({$patterns['travellerName']})$/u");
        $passengerValues = array_filter($passengerTexts);

        if (empty($passengerValues)) {
            $pXpath = $followingSiblingTable . "[.//img[contains(@src, 'barcode.gif')]][1]/descendant-or-self::tr[count(.//td[not(.//td)][normalize-space()]) < 3 and  *[1][not(normalize-space())][.//img[contains(@src, 'barcode.gif')]]]/*[2]";
            $passengerTexts = $this->http->FindNodes($pXpath, $fRoot, "/^(?:\d{1,3}\s*\.)?\s*(?:Chd\. +)?({$patterns['travellerName']})(?:\s[iI]nfant\s*:\s*.+)?\s*$/u");
            $passengerValues = array_filter($passengerTexts);
        }

        if (empty($passengerValues)) {
            $pXpath = "following::tr[.//img[contains(@src, 'barcode.gif')]][1]/descendant-or-self::tr[count(.//td[not(.//td)][normalize-space()]) < 3 and  *[1][not(normalize-space())]{$positionConditions}[.//img[contains(@src, 'barcode.gif')]]]/*[2]";
            $passengerTexts = $this->http->FindNodes($pXpath, $fRoot, "/^(?:\d{1,3}\s*\.)?\s*(?:Chd\. +)?({$patterns['travellerName']})(?:\s[iI]nfant\s*:\s*.+)?\s*$/u");
            $passengerValues = array_filter($passengerTexts);
        }

        if (empty($passengerValues)) {
            $pXpath = $followingSiblingTable . "[{$this->contains($this->t('Passenger(s) Details'))}][1]/descendant::td[not(.//td)][normalize-space()]/descendant::text()[normalize-space()][1]";
            $passengerTexts = $this->http->FindNodes($pXpath, $fRoot, "/^(?:\d{1,3}\s*\.)?\s*(?:Chd\. +)?({$patterns['travellerName']})$/u");
            $passengerValues = array_filter($passengerTexts);
        }

        if (empty($passengerValues)) {
            $pXpath = $followingSiblingTable . "[{$this->eq($this->t('IndiGo Flight(s)'))}]/following-sibling::table[normalize-space()][1][not(.//text()[{$this->eq($this->t('Date'))}])][.//img]/descendant-or-self::tr[count(.//td[not(.//td)][normalize-space()]) = 1 and  *[1][not(normalize-space())][.//img]]/*[2]";
            $passengerTexts = $this->http->FindNodes($pXpath, $fRoot, "/^\s*(?:Chd\. +)?({$patterns['travellerName']})$/u");
            $passengerValues = array_filter($passengerTexts);
        }

        if (empty($passengerValues)) {
            $pXpath = $followingSiblingTable . "[{$this->eq($this->t('IndiGo Flight(s)'))}]/following-sibling::table[normalize-space()][1][not(.//text()[{$this->eq($this->t('Date'))}])]/descendant::text()[normalize-space()]";
            $passengerTexts = $this->http->FindNodes($pXpath, $fRoot, "/^\s*(?:Chd\. +)?({$patterns['travellerName']})$/u");
            $passengerValues = array_filter($passengerTexts);
        }

        $passengerValues = array_map(function ($item) { return $this->normalizeTraveller($item); }, $passengerValues);
        $passengerValues = array_unique($passengerValues);

        $infants = [];
        $pXpath = $followingSiblingTable . "//td[not(.//td)][{$this->starts($this->t('infant :'))}]";
        $infants = $this->http->FindNodes($pXpath, $fRoot, "/{$this->opt($this->t('infant :'))}\s*(.+)\s*$/u");

        if (empty($infants)) {
            $pXpath = "//td[not(.//td)][{$this->starts($this->t('infant :'))}]{$positionConditions}";
            $infants = $this->http->FindNodes($pXpath, $fRoot, "/^\s*({$patterns['travellerName']})$/u");
        }
        $infants = array_filter($infants, function ($v) {
            if (empty($v) || preg_match("/^(\s*baby\s*)+$/i", $v)) {
                return false;
            }

            return true;
        });

        if (empty($travellers) && empty($infants) && $r->getCancelled()) {
        } else {
            if (count($passengerValues) > 0) {
                $passengerValues = preg_replace('/^\s*Chd\. +/', '', $passengerValues);
                $r->general()->travellers($passengerValues, true);

                if (!empty($infants)) {
                    $r->general()->infants($infants, true);
                }
            }
        }

        $flightXpath = $followingSiblingTable . "/descendant-or-self::tr[ *[normalize-space()][1][{$this->contains($this->t('Date'))}] and *[normalize-space()][3][{$this->contains($this->t('Departs'))}] ][1]/following-sibling::tr[normalize-space()]";
        $segments = $this->http->XPath->query($flightXpath, $fRoot);

        if ($segments->length === 0) {
            $flightXpath = $followingSiblingTable . "[count(.//td[not(.//td)]) > 3]/descendant-or-self::tr[ *[normalize-space()][1][{$this->contains($this->t('Date'))}] and *[normalize-space()][3][{$this->contains($this->t('Departs'))}] ][1]/following-sibling::tr[normalize-space()]";
            $segments = $this->http->XPath->query($flightXpath, $fRoot);
        }

        if ($segments->length === 0) {
            // it-162624834.eml
            $flightXpath = "following::tr[ *[starts-with(normalize-space(),'Date')] and *[starts-with(normalize-space(),'Departs')] ]{$positionConditions}/following-sibling::tr[normalize-space()]";
            $segments = $this->http->XPath->query($flightXpath, $fRoot);
        }
//        $this->logger->debug('$flightXpath = '.print_r( $flightXpath,true));

        foreach ($segments as $segment) {
            $s = $r->addSegment();

            $dateValue = $this->http->FindSingleNode("td[normalize-space()][1]", $segment, true, "/^.*\d.*$/");
            $date = strtotime($dateValue);

            $from = $this->http->FindSingleNode("./td[normalize-space()!=''][2]", $segment);

            /*if (in_array($date.' '.$from, $this->flights) === true){
                $email->removeItinerary($r);
                continue;
            } else {
                $this->flights[] = $date.' '.$from;
            }*/

            $timeDep = '';
            $flight = '';

            if (preg_match("/^\d+\:\d+/", $from)) {
                $from = '';
                $timeDep = $this->http->FindSingleNode("./td[normalize-space()!=''][2]", $segment, true,
                    '/^' . $patterns['time'] . '$/');
                $flight = $this->http->FindSingleNode("./td[normalize-space()!=''][3]", $segment);
            }

            if (preg_match('/^\s*(?<name>\w.+?)\s*\((?<terminal>[^)(]+)\)\s*$/u', $from, $matches)
                || preg_match('/^\s*\((?<terminal>[^)(]+)\)\s*$/u', $from, $matches)
                || preg_match('/^\s*(?<name>\w.+?)\s*$/u', $from, $matches)
            ) {
                if (!empty($matches['name'])) {
                    $s->departure()
                        ->name($matches['name']);
                }

                if (!empty($matches['terminal'])) {
                    $s->departure()
                        ->terminal(trim(preg_replace("/^(?:Terminal|T)\s*/i", '', $matches['terminal'])));
                }
            }

            if (empty($timeDep)) {
                $timeDep = $this->http->FindSingleNode("./td[normalize-space()!=''][3]", $segment, true,
                    '/^' . $patterns['time'] . '$/');
            }

            if ($date && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $date));
            }

            if (empty($flight)) {
                $flight = $this->http->FindSingleNode("./td[normalize-space()!=''][4]", $segment);
            }

            if (preg_match('/^([A-Z\d]{2})\s*(\d+)$/', $flight, $matches)) {
                $s->airline()
                    ->name($matches[1])
                    ->number($matches[2]);
            } else {
                if (!empty($this->http->FindSingleNode("(./preceding-sibling::tr/*[self::th or self::td][normalize-space()!=''][4][{$this->contains($this->t('Aircraft type'))}])[1]",
                        $segment))
                    && preg_match("/^\s*([A-Z\d]{2})\s*(\d+)\s*\((.+)\)$/", $flight, $matches)
                ) {
                    $s->airline()
                        ->name($matches[1])
                        ->number($matches[2]);
                    $s->extra()->aircraft(trim($matches[3]));
                }
            }

            $columnNum = 5;
            $info = $this->http->FindSingleNode("./td[normalize-space()!=''][5]", $segment);

            if (preg_match("/\d{1,2}:\d{2}/", $info)) {
                $columnNum++;
            }

            $to = $this->http->FindSingleNode("./td[normalize-space()!=''][{$columnNum}]", $segment);
            $columnNum++;

            if (preg_match("/^\d+\:\d+/", $to)) {
                --$columnNum;
                $to = '';
            }

            if (preg_match('/^\s*(?<name>\w.+?)\s*\((?<terminal>[^)(]+)\)\s*$/u', $to, $matches)
                || preg_match('/^\s*\((?<terminal>[^)(]+)\)\s*$/u', $to, $matches)
                || preg_match('/^\s*(?<name>\w.+?)\s*$/u', $to, $matches)
            ) {
                if (!empty($matches['name'])) {
                    $s->arrival()
                        ->name($matches['name']);
                }

                if (!empty($matches['terminal'])) {
                    $s->arrival()
                        ->terminal(trim(preg_replace("/^(?:Terminal|T)\s*/i", '', $matches['terminal'])));
                }
            }

            $timeArr = $overnight = null;
            $timeArrValue = $this->http->FindSingleNode("td[normalize-space()][{$columnNum}]", $segment);

            if (empty($timeArrValue) && empty($s->getArrName())) {
                $columnNum -= 2;
                $timeArrValue = $this->http->FindSingleNode("td[normalize-space()][{$columnNum}]", $segment);
            }

            if (preg_match("/^(?<time>{$patterns['time']})\s*[+]\s*(?<overnight>\d{1,3})$/", $timeArrValue, $m)) {
                $timeArr = $m['time'];
                $overnight = $m['overnight'];
            } elseif (preg_match("/^{$patterns['time']}$/", $timeArrValue)) {
                $timeArr = $timeArrValue;
            }

            if ($date && $timeArr) {
                $s->arrival()->date(strtotime($timeArr . ($overnight ? ' +' . $overnight . ' days' : ''), $date));
            }
        }

        $airportCodes = [];
        $seatsBySegment = [];
        $seatRows = $this->http->XPath->query("following-sibling::table[string-length(normalize-space())>1][position()>4][{$this->contains($this->t('Passenger'))}]", $fRoot);

        if (count($seatRows) === 0) {
            // special case
            $seatRows = $this->http->XPath->query("ancestor::div[1]/following-sibling::div[normalize-space()][1]/descendant::*[table[normalize-space()] and count(*[normalize-space()])>1][1]/table[string-length(normalize-space())>1][position()<4][{$this->contains($this->t('Passenger'))}]", $fRoot);
        }

        if (count($seatRows) == 0) {
            $seatRows = $this->http->XPath->query("following::table[string-length(normalize-space())>1][position()>4]", $fRoot);
        }

        foreach ($seatRows as $seatRow) {
            $seatHeaderRows = $this->http->XPath->query("descendant-or-self::tr[ *[normalize-space()][1][{$this->contains($this->t('Passenger'))}] and *[normalize-space()][2][{$this->contains($this->t('Seat'))}] and not(.//tr) ]", $seatRow);

            if ($seatHeaderRows->length > 0) {
                $seatHeaderRow = $seatHeaderRows->item(0);
            } else {
                break;
            }

            $seatHeaderExample = $this->http->FindSingleNode("*[2][{$this->contains($this->t('Seat'))}]", $seatHeaderRow);

            if (!$seatHeaderExample) {
                continue;
            }
            $seatHeaders = $this->http->FindNodes("*[{$this->eq($seatHeaderExample)}]", $seatHeaderRow);
            $flightCount = count($seatHeaders);

            if (!$flightCount || $flightCount < 1) {
                continue;
            }

            for ($i = 0; $i < $flightCount; $i++) {
                $codes = $this->http->FindSingleNode('preceding-sibling::tr[normalize-space()][1]/*[' . ($i + 2) . ']', $seatHeaderRow);

                if (preg_match('/^([A-Z]{3})\s*([A-Z]{3})$/', $codes, $matches)) {
                    $airportCodes[] = [
                        'DepCode' => $matches[1],
                        'ArrCode' => $matches[2],
                    ];
                }

                $seats = [];
                $passengerRows = $this->http->XPath->query("following-sibling::tr[normalize-space()]", $seatHeaderRow);

                foreach ($passengerRows as $pRow) {
                    $passengerName = $this->http->FindSingleNode('*[1]', $pRow, true, "/^\s*(?:Chd\. +)?(.+?)\s*(?:\s+Double Seat)?$/");
                    $seatVal = $this->http->FindSingleNode('*[' . ($i + 1) * 2 . ']', $pRow, true, '/^\d+[A-Z](?: \d+[A-Z])*$/');

                    if ($seatVal) {
                        $seatVal = explode(' ', $seatVal);

                        foreach ($seatVal as $v) {
                            $seats[] = ['seat' => $v, 'traveller' => $passengerName];
                        }
                    }
                }

                $seatsBySegment[] = $seats;
            }
        }

        if (count($airportCodes) === count($r->getSegments())) {
            foreach ($r->getSegments() as $key => $segment) {
                if (!empty($airportCodes[$key]['DepCode'])) {
                    $segment->departure()->code($airportCodes[$key]['DepCode']);
                } else {
                    $segment->departure()->noCode();
                }

                if (!empty($airportCodes[$key]['ArrCode'])) {
                    $segment->arrival()->code($airportCodes[$key]['ArrCode']);
                } else {
                    $segment->arrival()->noCode();
                }
            }
        } else {
            $ruleTranslateForCode = "translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆')";
            $codeHeaders = $this->http->XPath->query("//text()[normalize-space()='Travel and Baggage Information']/ancestor::table[1]/following-sibling::table/descendant::tr[count(.//tr)=0][count(.//text()[normalize-space({$ruleTranslateForCode})='∆∆∆'])=2]");

            if ($codeHeaders->length === count($r->getSegments())) {
                $airportCodes = [];

                foreach ($codeHeaders as $codeHeader) {
                    $airportCodes[] = [
                        'DepCode' => $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]",
                            $codeHeader),
                        'ArrCode' => $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][2]",
                            $codeHeader),
                    ];
                }

                foreach ($r->getSegments() as $key => $segment) {
                    if (!empty($airportCodes[$key]['DepCode'])) {
                        $segment->departure()->code($airportCodes[$key]['DepCode']);
                    } else {
                        $segment->departure()->noCode();
                    }

                    if (!empty($airportCodes[$key]['ArrCode'])) {
                        $segment->arrival()->code($airportCodes[$key]['ArrCode']);
                    } else {
                        $segment->arrival()->noCode();
                    }
                }
            } else {
                foreach ($r->getSegments() as $segment) {
                    $segment->departure()->noCode();
                    $segment->arrival()->noCode();
                }
            }
        }

        if (count($r->getSegments()) && (count($seatsBySegment) === count($r->getSegments()) || count($seatsBySegment) % count($r->getSegments()) === 0)) {
            foreach ($r->getSegments() as $key => $segment) {
                if (!empty($seatsBySegment[$key])) {
                    foreach ($seatsBySegment[$key] as $seats) {
                        $segment->extra()->seat($seats['seat'], false, false, empty($seats['traveller']) ? null : $this->normalizeTraveller($seats['traveller']));
                    }
                }
            }
        }

        // for cropped from bottom emails
        if ($positions['nextSegment'] === 1 && count($r->getSegments()) === 0) {
            $this->logger->debug('THIS EMAIL CROPPED FROM BOTTOM!');
            $this->logger->debug('Fligh segments not found! Trying to fix it...');

            $fSegmentsAll = [];
            $addedFlights = $email->getItineraries();

            foreach ($addedFlights as $j => $f) {
                /** @var Flight $f */
                if ($f->getType() !== 'flight') {
                    $fSegmentsAll = [];
                    $this->logger->debug('Operation aborted!');

                    break;
                }

                if ($j === count($addedFlights) - 1) {
                    continue;
                }

                $fSegmentsWithoutSeats = [];
                $fSegments = $f->getSegments();

                foreach ($fSegments as $fSeg) {
                    $segFields = array_diff_key($fSeg->toArray(), ['seats' => []]);

                    if (count($segFields) > 0) {
                        $fSegmentsWithoutSeats[] = $segFields;
                    }
                }

                if (count($fSegmentsWithoutSeats) > 0) {
                    $fSegmentsAll[] = serialize($fSegmentsWithoutSeats);
                }
            }

            if (count(array_unique($fSegmentsAll)) === 1) {
                $this->logger->debug('Added flight segments from other itineraries.');
                $ss = array_shift($fSegmentsAll);
                $r->fromArray(['segments' => unserialize($ss)]);
            }
        }
    }

    private function normalizeAmount(?string $s): string
    {
        // 1,09 , 215.00    ->    109,215.00
        $s = preg_replace('/^(\d+)\s*,\s*(\d{2})\s*,\s*(\d{3})\s*\.\s*(\d{1,2})$/', '$1$2$3.$4', $s);

        return $s;
    }

    private function normalizeTraveller(?string $s): string
    {
        return preg_replace('/^\s*(?:Ms|Miss|Mrs|Ms|Mr|Mstr|Dr|Master|\.)\.?\s+/', '', $s);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field, $delim = '/')
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) use ($delim) {
            return str_replace(' ', '\s+', preg_quote($s, $delim));
        }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Successfully tested on types: flight.
     */
    private function removePersonalizedFields(array $fields): array
    {
        $fieldsCleaned = [];

        foreach ($fields as $name => $value) {
            if (in_array($name, ['travellers', 'infants', 'ticketNumbers', 'accountNumbers'])) {
                continue;
            }

            if ($name === 'segments' && is_array($value)) {
                $segmentsCleaned = [];

                foreach ($value as $i => $segFields) {
                    $segFieldsCleaned = [];

                    foreach ($segFields as $segName => $segValue) {
                        if (in_array($segName, ['seats', 'assignedSeats'])) {
                            continue;
                        }
                        $segFieldsCleaned[$segName] = $segValue;
                    }

                    $segmentsCleaned[$i] = $segFieldsCleaned;
                }

                $value = $segmentsCleaned;
            }

            $fieldsCleaned[$name] = $value;
        }

        return $fieldsCleaned;
    }

    /**
     * Merging two flights.
     */
    private function mergeTwoFlights(array $flight1, array $flight2): array
    {
        if (array_key_exists('segments', $flight1) && is_array($flight1['segments']) && count($flight1['segments']) > 0
            && array_key_exists('segments', $flight2) && is_array($flight2['segments']) && count($flight2['segments']) > 0
        ) {
            // segments1 - OK; segments2 - OK;
            $flight1['segments'] = array_values($flight1['segments']);
            $flight2['segments'] = array_values($flight2['segments']);

            if (count($flight1['segments']) === count($flight2['segments'])) {
                foreach ($flight1['segments'] as $i => $seg) {
                    if (array_key_exists('seats', $flight1['segments'][$i]) && is_array($flight1['segments'][$i]['seats']) && count($flight1['segments'][$i]['seats']) > 0
                        && array_key_exists('seats', $flight2['segments'][$i]) && is_array($flight2['segments'][$i]['seats']) && count($flight2['segments'][$i]['seats']) > 0
                    ) {
                        // seats1 - OK; seats2 - OK
                        $flight1['segments'][$i]['seats'] = array_values(array_unique(array_merge($flight1['segments'][$i]['seats'], $flight2['segments'][$i]['seats'])));
                    } elseif ((!array_key_exists('seats', $flight1['segments'][$i]) || !is_array($flight1['segments'][$i]['seats']) || count($flight1['segments'][$i]['seats']) === 0)
                        && array_key_exists('seats', $flight2['segments'][$i]) && is_array($flight2['segments'][$i]['seats']) && count($flight2['segments'][$i]['seats']) > 0
                    ) {
                        // seats1 - BAD; seats2 - OK
                        $flight1['segments'][$i]['seats'] = $flight2['segments'][$i]['seats'];
                    } elseif (array_key_exists('seats', $flight1['segments'][$i]) && is_array($flight1['segments'][$i]['seats']) && count($flight1['segments'][$i]['seats']) > 0
                        && (!array_key_exists('seats', $flight2['segments'][$i]) || !is_array($flight2['segments'][$i]['seats']) || count($flight2['segments'][$i]['seats']) === 0)
                    ) {
                        // seats1 - OK; seats2 - BAD
                    }

                    if (array_key_exists('assignedSeats', $flight1['segments'][$i]) && is_array($flight1['segments'][$i]['assignedSeats']) && count($flight1['segments'][$i]['assignedSeats']) > 0
                        && array_key_exists('assignedSeats', $flight2['segments'][$i]) && is_array($flight2['segments'][$i]['assignedSeats']) && count($flight2['segments'][$i]['assignedSeats']) > 0
                    ) {
                        // assignedSeats1 - OK; assignedSeats2 - OK
                        foreach ($flight2['segments'][$i]['assignedSeats'] as $item) {
                            $found = false;

                            foreach ($flight1['segments'][$i]['assignedSeats'] as $itemX) {
                                if (serialize($itemX) === serialize($item)) {
                                    $found = true;

                                    break;
                                }
                            }

                            if (!$found) {
                                $flight1['segments'][$i]['assignedSeats'][] = $item;
                            }
                        }
                    } elseif ((!array_key_exists('assignedSeats', $flight1['segments'][$i]) || !is_array($flight1['segments'][$i]['assignedSeats']) || count($flight1['segments'][$i]['assignedSeats']) === 0)
                        && array_key_exists('assignedSeats', $flight2['segments'][$i]) && is_array($flight2['segments'][$i]['assignedSeats']) && count($flight2['segments'][$i]['assignedSeats']) > 0
                    ) {
                        // assignedSeats1 - BAD; assignedSeats2 - OK
                        $flight1['segments'][$i]['assignedSeats'] = $flight2['segments'][$i]['assignedSeats'];
                    } elseif (array_key_exists('assignedSeats', $flight1['segments'][$i]) && is_array($flight1['segments'][$i]['assignedSeats']) && count($flight1['segments'][$i]['assignedSeats']) > 0
                        && (!array_key_exists('assignedSeats', $flight2['segments'][$i]) || !is_array($flight2['segments'][$i]['assignedSeats']) || count($flight2['segments'][$i]['assignedSeats']) === 0)
                    ) {
                        // assignedSeats1 - OK; assignedSeats2 - BAD
                    }
                }
            } elseif (count($flight1['segments']) !== count($flight2['segments'])) {
                $this->logger->debug('Segments merging cannot be performed. The number of segments is different!');
            }
        } elseif ((!array_key_exists('segments', $flight1) || !is_array($flight1['segments']) || count($flight1['segments']) === 0)
            && array_key_exists('segments', $flight2) && is_array($flight2['segments']) && count($flight2['segments']) > 0
        ) {
            // segments1 - BAD; segments2 - OK;
            $flight1['segments'] = $flight2['segments'];
        } elseif (array_key_exists('segments', $flight1) && is_array($flight1['segments']) && count($flight1['segments']) > 0
            && (!array_key_exists('segments', $flight2) || !is_array($flight2['segments']) || count($flight2['segments']) === 0)
        ) {
            // segments1 - OK; segments2 - BAD;
        }

        foreach (['travellers', 'infants', 'ticketNumbers', 'accountNumbers'] as $fieldName) {
            if (array_key_exists($fieldName, $flight1) && is_array($flight1[$fieldName]) && count($flight1[$fieldName]) > 0
                && array_key_exists($fieldName, $flight2) && is_array($flight2[$fieldName]) && count($flight2[$fieldName]) > 0
            ) {
                // field1 - OK; field2 - OK;
                foreach ($flight2[$fieldName] as $item) {
                    if (is_array($item) && count($item) > 0 && !in_array($item[0], array_column($flight1[$fieldName], 0))) {
                        $flight1[$fieldName][] = $item;
                    }
                }
            } elseif ((!array_key_exists($fieldName, $flight1) || !is_array($flight1[$fieldName]) || count($flight1[$fieldName]) === 0)
                && array_key_exists($fieldName, $flight2) && is_array($flight2[$fieldName]) && count($flight2[$fieldName]) > 0
            ) {
                // field1 - BAD; field2 - OK;
                $flight1[$fieldName] = $flight2[$fieldName];
            } elseif (array_key_exists($fieldName, $flight1) && is_array($flight1[$fieldName]) && count($flight1[$fieldName]) > 0
                && (!array_key_exists($fieldName, $flight2) || !is_array($flight2[$fieldName]) || count($flight2[$fieldName]) === 0)
            ) {
                // field1 - OK; field2 - BAD;
                continue;
            }
        }

        return $flight1;
    }

    /**
     * Dependencies `$this->removePersonalizedFields()` and `$this->mergeTwoFlights()`.
     */
    private function mergeAllFlights(Email $email): void
    {
        $flightsSource = [];
        $itineraries = $email->getItineraries();

        foreach ($itineraries as $it) {
            /** @var Flight $it */
            if ($it->getType() === 'flight') {
                $flightsSource[] = $it->toArray();
                $email->removeItinerary($it);
            }
        }

        if (count($flightsSource) === 0) {
            $this->logger->debug('Merge all flights aborted! Flights not added!');

            return;
        }

        if (count($email->getItineraries()) === 0) {
            $email->clearItineraries(); // for reset array indexes
        }

        /*
            Step 1/3: grouping flights
        */

        $flightsByGroups = [];

        foreach ($flightsSource as $key => $flight) {
            $hash = md5(serialize($this->removePersonalizedFields($flight)));

            if (array_key_exists($hash, $flightsByGroups)) {
                $flightsByGroups[$hash][] = $key;
            } else {
                $flightsByGroups[$hash] = [$key];
            }
        }

        /*
            Step 2/3: merging flights by groups
        */

        $flightsNew = [];

        foreach ($flightsByGroups as $flightIndexes) {
            $flight = [];

            foreach ($flightIndexes as $key => $index) {
                if ($key === 0) {
                    $flight = $flightsSource[$index];

                    continue;
                }

                $flight = $this->mergeTwoFlights($flight, $flightsSource[$index]);
            }

            $flightsNew[] = $flight;
        }

        /*
            Step 3/3: output merged flights
        */

        foreach ($flightsNew as $flight) {
            $f = $email->add()->flight();
            $f->fromArray($flight);
        }
    }
}
