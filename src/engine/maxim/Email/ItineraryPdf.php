<?php

namespace AwardWallet\Engine\maxim\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Common\Rental;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "maxim/it-34624222.eml, maxim/it-34624225.eml, maxim/it-34624226.eml, maxim/it-35327174.eml, maxim/it-36555890.eml, maxim/it-36717318.eml, maxim/it-40978449.eml, maxim/it-42551394.eml, maxim/it-42777766.eml, maxim/it-43759944.eml, maxim/it-44379606.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'MAXIMise reference number' => ['MAXIMise reference number'],
            'Arrive At'                 => ['Arrive At'],
            'itineraryEnd'              => ['Smart Traveller Advice', 'Emergency Assistance', 'Online Itinerary'],
            'itineraryTableEnd'         => ['Other Confirmation Numbers', 'Advice for Travel to', 'Unconfirmed Segments', 'Avis Car Refuelling', 'Passport Validity', 'Waitlisted Segments'],
            'Nightly Rate:'             => ['Nightly Rate:', 'Average Nightly Rate:', 'Prepaid Rate:'],
        ],
    ];

    private $preparedFor = null;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@maxims-travel.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProvider = $this->detectEmailFromProvider($parser->getHeader('from')) === true;

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($detectProvider === false && strpos($textPdf, 'MAXIMise') === false
                && stripos($textPdf, 'www.maxims-travel.com') === false
                && stripos($textPdf, "Maxim's Travel Mobile App") === false
            ) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->parsePdf($email, $textPdf);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('ItineraryPdf' . ucfirst($this->lang));

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

    private function parsePdf(Email $email, $text): void
    {
        $email->ota(); // because Maxim's Travel is travel agency

        $itineraryStart = $this->strposArray($text, $this->t('This itinerary is specially prepared for'));

        if ($itineraryStart !== false) {
            $text = substr($text, $itineraryStart);
        }

        $itineraryEnd = $this->strposArray($text, $this->t('itineraryEnd'), true);

        if ($itineraryEnd !== false) {
            $text = substr($text, 0, $itineraryEnd);
        }

        $this->preparedFor = preg_match("/{$this->opt($this->t('This itinerary is specially prepared for'))}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s+{$this->opt($this->t('MAXIMise reference number'))}/u", $text, $m) ? $m[1] : null;

        if (preg_match("/^[ ]*({$this->opt($this->t('MAXIMise reference number'))})[ :]+([A-Z\d]{5,})$/m", $text, $m)) {
            $email->obtainTravelAgency()->addConfirmationNumber($m[2], $m[1]);
        }

        if ($this->strposArray($text, $this->t('Itinerary Printed')) !== false) {
            // remove line (example): Itinerary Printed: Saturday, 27 April 2019, 8:05:55 AM
            $text = preg_replace("/.*{$this->opt($this->t('Itinerary Printed'))}.*/", "\n", $text);
        }

        // it-36717318.eml
        $text = preg_replace("/\s+((?:^.+$\n)?^[ ]*#[ ]*{$this->opt($this->t('Flight Number'))} .+)/m", "\n\n\n\n\n$1", $text);

        if ($this->strposArray($text, $this->t('Flight operated by:')) !== false) {
            // it-35327174.eml
            $text = preg_replace("/({$this->opt($this->t('Flight operated by:'))}.*(?:\s+^.+$){1,3})\n{5,}/m", "$1\n\n", $text);
        }

        if (!preg_match("/\n(?<head>[ ]*#[ ]+{$this->opt($this->t('Flight Number'))}[ ].+\n.*)\n+(?<body>[\s\S]+?)(?:\s*?\n[ ]*{$this->opt($this->t('itineraryTableEnd'))}|\n\s*$)/", $text, $table)) {
            $this->logger->debug('Itinerary table not found!');

            return;
        }

//        $tablePos = [0];
//        if ( preg_match("/^(.+?){$this->opt($this->t('Departure Airport'))}/m", $table['head'], $matches) )
//            $tablePos[] = mb_strlen($matches[1]);
//        if ( preg_match("/^(.+?){$this->opt($this->t('Arrival Airport'))}/m", $table['head'], $matches) )
//            $tablePos[] = mb_strlen($matches[1]);
//        if (count($tablePos) !== 3) {
//            $this->logger->debug('Wrong headers in itinerary table!');
//            return;
//        }

        $fSegments = [];
        // remove extra blank lines
        $table['body'] = preg_replace("/\n{2,}(.+[ ]+({$this->opt(array_merge((array) $this->t('Check-in:'), (array) $this->t('Check-out:'), (array) $this->t('Pick-up:'), (array) $this->t('Drop-off:')))})[ ]+)/", "\n$1", $table['body']);

        $tBodyRows = preg_split('/\n{2,}/', $table['body']);

        for ($key = 1; count($tBodyRows); $key++) {
            $row = array_shift($tBodyRows);
            // Example: it-40978449.eml
            if (preg_match("/#\s+{$this->opt($this->t('Flight Number'))}/", $row)) {
                $key--;
                $this->logger->debug('Continue in itinerary table!');

                continue;
            }

            if ($key === 1 && !preg_match('/^[ ]{0,12}' . $key . '(?:[ ]{2}|$)/m', $row)
                && preg_match('/^[ ]{0,12}' . ($key + 1) . '(?:[ ]{2}|$)/m', $row)
            ) {// it-42777766.eml
                $this->logger->debug('itinerary table starts form #2');
                $key++;
            }

            if (preg_match("/^[ ]+{$this->opt($this->t('Segment remark'))}\b/im", $row)) {
                // it-36555890.eml
                if (count($tBodyRows)) {
                    $key--;
                }
                $this->logger->debug('Continue in itinerary table! (Segment remark)');

                continue;
            } elseif (preg_match("/[ ]{2}{$this->opt($this->t('Own Arrangements'))}$/im", $row)) {
                $this->logger->debug('Continue in itinerary table! (Own Arrangements)');

                continue;
            }

            /*if (!preg_match('/^[ ]{0,12}\d+(?:[ ]{2}|$)/m', $row)) {
                $this->logger->debug('$row '.$row);
                $this->logger->debug('Wrong row #' . $key . ' in itinerary table!');
                return;
            }*/

            if ($this->strposArray($row, $this->t('Check-in:')) && $this->strposArray($row, $this->t('Check-out:'))) {
                $hotel = $this->parseHotel($email, $row);

                if ($this->preparedFor) {
                    $hotel->general()->traveller($this->preparedFor);
                }
            } elseif ($this->strposArray($row, $this->t('Pick-up:')) && $this->strposArray($row, $this->t('Drop-off:'))) {
                $car = $this->parseCar($email, $row);

                if ($this->preparedFor) {
                    $car->general()->traveller($this->preparedFor);
                }
            } else {
                $fSegments[] = $row;
            }
        }

        if (count($fSegments) > 0) {
            $this->parseFlights($email, $fSegments, $text);
        }
    }

    private function parseFlights(Email $email, $fSegments, $text): void
    {
        $flights = [];
        $otherConfNumbersStart = $this->strposArray($text, $this->t('Other Confirmation Numbers'));
        $otherConfNumbersText = $otherConfNumbersStart !== false ? substr($text, $otherConfNumbersStart) : '';
        $otherConfNumbersEnd = $this->strposArray($text, $this->t('Seat Assignments'));

        if ($otherConfNumbersEnd !== false) {
            $otherConfNumbersText = substr($otherConfNumbersText, 0, $otherConfNumbersEnd);
        } elseif (preg_match("/^([\s\S]+?)\n{5}/", $otherConfNumbersText, $m)) {
            $otherConfNumbersText = $m[1];
        }
        // Virgin Australia (VA)    MU8BCA
        if (preg_match_all("/^[ ]*(?<carrierFull>.+?)[ ]*\((?<carrier>[A-Z][A-Z\d]|[A-Z\d][A-Z])\)[ ]+(?<confNumbers>[A-Z\d][A-Z\d, ]{3,}[A-Z\d])$/m",
            $otherConfNumbersText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $flight = $email->add()->flight();

                foreach (preg_split('/\s*,\s*/', $m['confNumbers']) as $confNumber) {
                    $flight->general()->confirmation($confNumber);
                }
                // Duplicate FlightNumber, example it-44379606.eml
                if (isset($flights[$m['carrier']])) {
                    $email->removeItinerary($flights[$m['carrier']]);
                }
                $flights[$m['carrier']] = $flight;
                //$this->logger->error(json_encode($flight->getConfirmationNumbers()));
            }
        }

        if (count($flights) === 0) {
            return;
        }

        $passengersBySegment = [];

        $seatsStart = $this->strposArray($text, $this->t('Seat Assignments'));
        $seatsEnd = $this->strposArray($text, $this->t('Frequent Flyer Memberships'));
        $seatsText = $seatsStart !== false && $seatsEnd !== false ? substr($text, $seatsStart, $seatsEnd - $seatsStart) : '';
        $seatsRecords = preg_match_all("/.+ - (?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+[ ]{2,}.+/", $seatsText, $seatMatches) ? $seatMatches[0] : [];

        foreach ($fSegments as $key => $sText) {
            $s = $this->parseFlightSegment($sText, $flights);
            //$this->logger->error(var_export($s, true));

            if ($s === null) {
                continue;
            }

            // seats
            if (!empty($seatsRecords[$key]) && !empty($s->getAirlineName()) && !empty($s->getFlightNumber())
                && preg_match('/ - ' . $s->getAirlineName() . '[ ]*' . $s->getFlightNumber() . '[ ]{2,}(?<seat>\d{1,5}[A-Z]) - (?<passenger>[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:[ ]*\(|$)/mu', $seatsRecords[$key], $m)
            ) {
                // 09MAR - VA 559        007C - Karen Ward (Confirmed)
                $s->addSeat($m['seat']);
                $passengersBySegment[$s->getAirlineName()][] = $m['passenger'];
            }
        }

        // accountNumbers
        $ffNumbersStart = $this->strposArray($text, $this->t('Frequent Flyer Memberships'));
        $ffNumbersText = $ffNumbersStart !== false ? substr($text, $ffNumbersStart) : '';
        $ffNumbersEnd = $this->strposArray($ffNumbersText, $this->t('Smart Traveller Advice'));

        if ($ffNumbersEnd !== false) {
            $ffNumbersText = substr($ffNumbersText, 0, $ffNumbersEnd);
        }

        if (preg_match_all("/^(?:[ ]*[[:alpha:]][-,.'[:alpha:] ]*[[:alpha:]])?[ ]{2,}(([A-Z][A-Z\d]|[A-Z\d][A-Z])\d{7,})$/mu", $ffNumbersText, $matches, PREG_SET_ORDER)) {
            // Ward, Karen Ms        QF7259244
            foreach ($matches as $m) {
                $accounts[$m[2]][] = $m[1];
            }
        }

        foreach ($flights as $carrier => $flight) {
            // filtration
            if ($flight->getSegments() === null || count($flight->getSegments()) === 0) {
                $email->removeItinerary($flight);
                unset($flights[$carrier]);

                continue;
            }

            // travellers
            if (!empty($passengersBySegment[$carrier])) {
                foreach (array_unique($passengersBySegment[$carrier]) as $passenger) {
                    $flight->addTraveller($passenger);
                }
            } elseif ($this->preparedFor) {
                $flight->general()->traveller($this->preparedFor);
            }

            if (count($flights) == 1 && isset($accounts)) {
                foreach ($accounts as $key => $values) {
                    if (is_array($values)) {
                        $flight->program()->accounts($values, false);
                    }
                }
            } else {
                if (isset($accounts[$carrier]) && $this->hasCarrier($flight, $carrier)) {
                    $flight->program()->accounts($accounts[$carrier], false);
                }
            }
        }
    }

    private function hasCarrier(Flight $flight, string $carrier): bool
    {
        if (count($flight->getSegments()) > 0) {
            foreach ($flight->getSegments() as $s) {
                if ($s->getAirlineName() === $carrier) {
                    return true;
                }
            }
        }

        return false;
    }

    private function parseFlightSegment($sText, $flights): ?FlightSegment
    {
        // Wed 13 Mar 2019
        $patterns['date'] = "[[:alpha:]]{3} \d{2} [[:alpha:]]{3} \d{4}";
        // 09:30
        $patterns['time'] = '\d{1,2}:\d{2}';

        // attempt to align text FE: it-43759944.eml segemnts: EY 295
        // adding spaces before dates to align columns
        // TODO: don't delete region AlignColumn below
        //region AlignColumn
        $posDates = [];
        $posTimes = [];

        if (preg_match("/^((.+ ){$patterns['date']} .+ ){$patterns['date']}(?: .+|$)/mu", $sText, $matches)) {
            // one line
            unset($matches[0]);

            foreach (array_reverse($matches) as $m) {
                $posDates[] = mb_strlen($m);
            }
        } elseif (preg_match_all("/^((.+? ){$patterns['date']})(?: .+|$)/mu", $sText, $posMatches)
            && count($posMatches[1]) === 2
        ) {
            // different lines
            foreach (array_merge($posMatches[1], $posMatches[2]) as $m) {
                $posDates[] = mb_strlen($m);
            }
            sort($posDates);
        }

        if (preg_match("/^((.+ ){$patterns['time']} .+ ){$patterns['time']}(?: .+|$)/mu", $sText, $matches)) {
            // one line
            unset($matches[0]);

            foreach (array_reverse($matches) as $m) {
                $posTimes[] = mb_strlen($m);
            }
        } elseif (preg_match_all("/^((.+? ){$patterns['time']})(?: .+|$)/mu", $sText, $posMatches)
            && count($posMatches[1]) === 2
        ) {
            // different lines
            foreach (array_merge($posMatches[1], $posMatches[2]) as $m) {
                $posTimes[] = mb_strlen($m);
            }
            sort($posTimes);
        }

        if (count($posDates) === 2 && count($posTimes) === 2) {
            for ($i = 0; $i < 2; $i++) {
                $diff = $posTimes[$i] - $posDates[$i];

                if ($diff > 10) {
                    $diff = $diff - 10 + 1;

                    switch ($i) {
                        case 0:
                            if (preg_match("/^(.+?)({$patterns['date']})([ ]{{$diff}})(.+?[ ]{$patterns['date']}[ ].+)$/su", $sText, $m)) {
                                $sText = $m[1] . $m[3] . $m[2] . $m[4];
                            }

                            break;

                        case 1:
                            if (preg_match("/^(.+?[ ]{$patterns['date']}[ ].+?[ ])({$patterns['date']})([ ]{6})( .+)/su", $sText, $m)) {
                                $sText = $m[1] . $m[3] . $m[2] . $m[4];
                            }

                            break;
                    }
                }
            }
        }
        //endregion

        //$this->logger->debug("==============================");
        //$this->logger->debug($sText);
        // main parsing
        $tablePos = [0];

        if (preg_match("/^([ ]{0,10}\d{1,3})(?: |$)/m", $sText, $m)) {
            $tablePos[] = mb_strlen($m[1]) + 1;
        }

        if (preg_match("/^((?:[ ]{0,10}\d{1,3})?[ ]*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+)(?: |$)/m", $sText, $m)) {
            $tablePos[] = mb_strlen($m[1]) + 1;
        }

        if (preg_match("/^((((.+ ){$patterns['date']}) .+ ){$patterns['date']})(?: .+|$)/mu", $sText, $matches)) {
            // one line
            unset($matches[0]);

            foreach (array_reverse($matches) as $m) {
                $tablePos[] = mb_strlen($m);
            }
        } elseif (preg_match_all("/^((.+? ){$patterns['date']})(?: .+|$)/mu", $sText, $posMatches)
            && count($posMatches[1]) === 2
        ) {
            // different lines
            $datePos = [];

            foreach (array_merge($posMatches[1], $posMatches[2]) as $m) {
                $datePos[] = mb_strlen($m);
            }
            sort($datePos);
            $tablePos = array_merge($tablePos, $datePos);
        }
        // correcting columns position by terminals
        if (preg_match("/^((.+)\b{$this->opt($this->t('Terminal'))}\b.+)\b{$this->opt($this->t('Terminal'))}\b/im", $sText, $m)) {
            // one line
            $tablePos[2] = mb_strlen($m[2]);
            $tablePos[4] = mb_strlen($m[1]);
        } elseif (preg_match_all("/(.+)\b{$this->opt($this->t('Terminal'))}\b[\s\S]+?/i", $sText, $terminalMatches)
            && count($terminalMatches[1]) === 2
        ) {
            // different lines
            $terminalPos = [];

            foreach ($terminalMatches[1] as $m) {
                $terminalPos[] = mb_strlen($m);
            }
            sort($terminalPos);
            $tablePos[2] = $terminalPos[0];
            $tablePos[4] = $terminalPos[1];
        }

        // exporting fragment with offset times (it-34624222.eml)
        if (!empty($tablePos[5])
            && preg_match("/^.{1," . $tablePos[5] . "}\b({$patterns['time']})( [^:\n]+? )({$patterns['time']}) /m", $sText, $m)
        ) {
            $timeDep = $m[1];
            $timeArr = $m[3];

            $sText = str_replace($m[1] . $m[2] . $m[3], str_repeat(' ', strlen($m[1])) . $m[2] . str_repeat(' ', strlen($m[3])), $sText);
        } else {
            $timeDep = $timeArr = null;
        }

        //The last line of the segment. Remove extra spaces
        if (!empty($tablePos[4])) {
            if (preg_match('/(.+)$/', $sText, $m)) {
                $row = $m[1];
                $partRow = substr($row, $tablePos[4], strlen($row));
                $part1Row = substr($row, 0, $tablePos[4]);
                $part2Row = $this->re('/^\s*(.+)$/', $partRow);
                $sText = preg_replace('/(.+)$/', $part1Row . $part2Row, $sText);
            }
        }

        $gTable = $this->splitCols($sText, $tablePos);

        if (count($gTable) !== 7) {
            $this->logger->debug('Error split cols in segment!');

            return null;
        }

        if (preg_match("/^[ ]*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<flightNumber>\d+)$/m", $gTable[1], $m)
            && !empty($flights[$m['airline']])
        ) {
            // Remove duplicate changed segments
            // Example: it-34624222.eml
            $currIt = $flights[$m['airline']];
            $currSegments = $currIt->getSegments();

            foreach ($currSegments as &$segment) {
                if ($segment->getAirlineName() == $m['airline'] && $segment->getFlightNumber() == $m['flightNumber']) {
                    $currIt->removeSegment($segment);

                    break;
                }
            }
            /** @var FlightSegment $s */
            $s = $flights[$m['airline']]->addSegment();
            $s->airline()
                ->name($m['airline'])
                ->number($m['flightNumber'])
            ;
        } else {
            $this->logger->debug('Flight mapped by carrier not found! See table "Other Confirmation Numbers".');

            return null;
        }

        if (preg_match("/{$this->opt($this->t('Flight operated by:'))}\s*([\s\S]{2,}?)\s*$/", $gTable[1], $m)) {
            $s->airline()->operator($m[1]);
        }

        /*
            Sydney (Kingsford Smith)
            Airport
            Terminal 2
            Sydney, Australia

            [or]

            Singapore Changi International
            Airport (SIN),
            Terminal not provided
            Singapore, Singapore
        */

        $patterns['airportTerminalCity'] = "/^"
            . "\s*(?<airport>[\s\S]{3,}?)[ ,]*"
            . "\s+{$this->opt($this->t('Terminal'))}\s+(?<terminal>[A-Z\d]+\b|EM\b|(?i){$this->opt($this->t('not provided'))}(?-i))[ ,]*"
            . "\s+(?<city>[\s\S]{3,}?)\s*"
            . "$/";

        if (preg_match($patterns['airportTerminalCity'], $gTable[2], $m)) {
            $airportDep = preg_replace('/\s+/', ' ', $m['airport']);
            $s->departure()
                ->name($airportDep . ', ' . preg_replace('/\s+/', ' ', $m['city']))
                ->terminal((preg_match("#not\s+provided#i", $m['terminal'])) ? null : $m['terminal'], true, true)
            ;

            if (preg_match("/^.{2,}\s*\(\s*([A-Z]{3})\s*\)(?:\s*[,;]|$)/", $airportDep, $m)) {
                $s->departure()->code($m[1]);
            } else {
                $s->departure()->noCode();
            }
        }

        if (preg_match($patterns['airportTerminalCity'], $gTable[4], $m)) {
            $airportArr = preg_replace('/\s+/', ' ', $m['airport']);
            $s->arrival()
                ->name($airportArr . ', ' . preg_replace('/\s+/', ' ', $m['city']))
                ->terminal((preg_match("#not\s+provided#i", $m['terminal'])) ? null : $m['terminal'], true, true)
            ;

            if (preg_match("/^.{2,}\s*\(\s*([A-Z]{3})\s*\)(?:\s*[,;]|$)/", $airportArr, $m)) {
                $s->arrival()->code($m[1]);
            } else {
                $s->arrival()->noCode();
            }
        }

        $dateDep = str_replace("\n", ' ', $gTable[3]) . ($timeDep ?? '');
        $dateArr = preg_replace(["/\n/", '/\s{3,}re/', '/\b(?:lia|ore)/'], ' ', $gTable[5]) . ($timeArr ?? '');

        $s->departure()->date2($dateDep);
        $s->arrival()->date2($dateArr);

        if (preg_match("/\b(\d{1,2}:\d{2})$/m", $gTable[6], $m)) {
            // 05:05
            $s->extra()->duration($m[1]);
        }

        $extraTablePos = [0];

        if (preg_match("/^(.+ )(?:Confirmed|Refer)/m", $gTable[6], $m)) {
            $extraTablePos[] = mb_strlen($m[1]);
        }

        if (preg_match("/^(.+ )\d{1,2}:\d{2}$/m", $gTable[6], $m)) {
            $extraTablePos[] = mb_strlen($m[1]);
        }

        $extraTable = $this->splitCols($gTable[6], $extraTablePos);

        if (count($extraTable) !== 3) {
            return $s;
        }

        if (preg_match("/^\s*([\s\S]+?)\s*\(([A-Z]{1,2})\)/", $extraTable[0], $m)) {
            // Economy (K)
            $s->extra()
                ->cabin(preg_replace('/\s+/', ' ', $m[1]))
                ->bookingCode($m[2])
            ;
        }

        if (preg_match("/^\s*([\s\S]+?)\s*\(/", $extraTable[1], $m)) {
            // Confirmed (HK)
            $s->extra()->status(preg_replace('/\s+/', ' ', $m[1]));
        }

        return $s;
    }

    private function parseHotel(Email $email, $text): Hotel
    {
        $hotel = $email->add()->hotel();

        $gTablePos = [0];

        if (preg_match("/^(.{7,}? ){$this->opt($this->t('Check-in:'))}/m", $text, $matches)) {
            $gTablePos[] = mb_strlen($matches[1]);
        }
        $gTable = $this->splitCols($text, $gTablePos);

        if (count($gTable) !== 2
            || !preg_match("/^([\s\S]*?)([ ]*{$this->opt($this->t('Check-in:'))}[\s\S]+)$/", $gTable[1], $rows)
        ) {
            $this->logger->debug('Incorrect hotel table!');

            return $hotel;
        }

        if (preg_match("/^\s*(?<name>.+?)[ ]+-[ ]+(?<address>.+)\s*$/", $rows[1], $m)) {
            $hotel->hotel()
                ->name($m['name'])
                ->address($m['address'])
            ;
        } elseif (strpos($rows[1], ' - ') === false) {
            $hotel->hotel()
                ->name(trim($rows[1]))
                ->noAddress()
            ;
        }

        $tablePos = [0];

        if (!preg_match('/^(((((([ ]*' . implode('[ ]+)', ['Check-in:', 'Check-out:', 'Confirmation number:', 'Phone:', 'Fax:', $this->opt($this->t('Nightly Rate:')), 'Status:']) . '/m', $rows[2], $matches)) {
            $this->logger->debug('Table hotel headers not found!');

            return $hotel;
        }
        unset($matches[0]);

        foreach (array_reverse($matches) as $textHeaders) {
            $tablePos[] = mb_strlen($textHeaders);
        }
        $table = $this->splitCols($rows[2], $tablePos);

        if (count($table) !== 7) {
            $this->logger->debug('Incorrect column in table hotel!');

            return $hotel;
        }

        $dateCheckIn = preg_match("/{$this->opt($this->t('Check-in:'))}\s*([\s\S]{6,})/", $table[0], $m) ? $this->normalizeDate($m[1]) : null;
        $hotel->booked()->checkIn2($dateCheckIn);

        $dateCheckOut = preg_match("/{$this->opt($this->t('Check-out:'))}\s*([\s\S]{6,})/", $table[1], $m) ? $this->normalizeDate($m[1]) : null;
        $hotel->booked()->checkOut2($dateCheckOut);

        if (preg_match("/({$this->opt($this->t('Confirmation number:'))})\s*([A-z\d\-]{3,})/", $table[2], $m)) {
            $hotel->general()->confirmation($m[2], preg_replace('/\s*:+\s*$/', '', $m[1]));
        } elseif (preg_match("/({$this->opt($this->t('Confirmation number:'))})\s*([\d\-\s]{5,})\s*$/", $table[2], $m)) {
            /* FE:
            Confirmation number:
            20190518 7031
            43044626
             */
            $hotel->general()->confirmation(preg_replace("/\s+/", '-', $m[2]), preg_replace('/\s*:+\s*$/', '', $m[1]));
        } elseif (preg_match("#{$this->opt($this->t('Confirmation number:'))}\s*N/A\s*$#i", $table[2])) {
            $hotel->general()->noConfirmation();
        }

        $phone = preg_match("/{$this->opt($this->t('Phone:'))}\s*([+)(\d][-.\s\d)(]{5,}[\d)(])\s*$/", $table[3], $m) ? $m[1] : null;
        $hotel->hotel()->phone($phone, false, true);

        $fax = preg_match("/{$this->opt($this->t('Fax:'))}\s*([+)(\d][-.\s\d)(]{5,}[\d)(])\s*$/", $table[4], $m) ? $m[1] : null;
        $hotel->hotel()->fax($fax, false, true);

        if (preg_match("/{$this->opt($this->t('Nightly Rate:'))}\s*([\s\S]*\d[\s\S]*?)\s*$/", $table[5], $m)) {
            $hotel->addRoom()->setRate(preg_replace('/\s+/', ' ', $m[1] . ' / Night'));
        }

        $status = preg_match("/{$this->opt($this->t('Status:'))}\s*([^)(]+)\s*(?:\(|$)/", $table[6], $m) ? $m[1] : null;
        $hotel->general()->status(preg_replace('/\s+/', ' ', $status));

        return $hotel;
    }

    private function parseCar(Email $email, $text): Rental
    {
        // it-34624226.eml
        $car = $email->add()->rental();

        $gTablePos = [0];

        if (preg_match("/^(.{7,}? ){$this->opt($this->t('Pick-up:'))}/m", $text, $matches)) {
            $gTablePos[] = mb_strlen($matches[1]);
        }
        $gTable = $this->splitCols($text, $gTablePos);

        if (count($gTable) !== 2
            || !preg_match("/^([\s\S]*?)([ ]*{$this->opt($this->t('Pick-up:'))}[\s\S]+)$/", $gTable[1], $rows)
        ) {
            $this->logger->debug('Incorrect car table!');

            return $car;
        }

        $tablePos = [0];

        if (!preg_match('/^(((((([ ]*' . implode('[ ]+)', ['Pick-up:', 'Drop-off:', 'Confirmation number:', 'Phone:', 'Car Type:', '(?:Estimated Total:|Daily Rate:|Package Rate:)', 'Status:']) . '/m', $rows[2], $matches)) {
            $this->logger->debug('Table car headers not found!');

            return $car;
        }
        unset($matches[0]);

        foreach (array_reverse($matches) as $textHeaders) {
            $tablePos[] = mb_strlen($textHeaders);
        }
        $table = $this->splitCols($rows[2], $tablePos);

        if (count($table) !== 7) {
            $this->logger->debug('Incorrect column in table car!');

            return $car;
        }

        /*
            16:00
            Fri 09 Apr 2021
            8 Franklin St vic
            Melbourne
        */
        $patterns['dateLoc'] = "(?<date>[\s\S]+\s+\d{1,2}\s+[[:alpha:]]{3}\s+\d{4})\n+(?<location>[\s\S]{3,})";

        if (preg_match("/{$this->opt($this->t('Pick-up:'))}\s*{$patterns['dateLoc']}$/u", $table[0], $m)) {
            $car->pickup()
                ->date2($this->normalizeDate($m['date']))
                ->location(preg_replace('/\s+/', ' ', $m['location']));
        } elseif (preg_match("/{$this->opt($this->t('Pick-up:'))}\s*([\s\S]{6,})$/", $table[0], $m)) {
            $car->pickup()->date2($this->normalizeDate($m[1]));
        }

        if (preg_match("/{$this->opt($this->t('Drop-off:'))}\s*{$patterns['dateLoc']}$/u", $table[1], $m)) {
            $car->dropoff()
                ->date2($this->normalizeDate($m['date']))
                ->location(preg_replace('/\s+/', ' ', $m['location']));
        } elseif (preg_match("/{$this->opt($this->t('Drop-off:'))}\s*([\s\S]{6,})/", $table[1], $m)) {
            $car->dropoff()->date2($this->normalizeDate($m[1]));
        }

        if (preg_match("/({$this->opt($this->t('Confirmation number:'))})\s*([A-Z\d-]{5,})\s*(?:PEXP\s*|CNTR\s*|\s+.*)?$/", $table[2], $m)) {
            $car->general()->confirmation($m[2], preg_replace('/\s*:+\s*$/', '', $m[1]));
        } elseif (preg_match("/Status:\s+\d+:\d+\s+\d+:\d+\s+([A-Z\d-]{5,})/", $rows[2], $m)) {
            $car->general()->confirmation($m[1]);
        } elseif (preg_match("#({$this->opt($this->t('Confirmation number:'))})\s*N/A#", $table[2])) {
            $car->general()
                ->noConfirmation();
        }

        $phone = preg_match("/{$this->opt($this->t('Phone:'))}\s*([+(\d][-.\s\d)(]{5,}[\d)])\s*$/", $table[3], $m) ? $m[1] : null;
        $car->pickup()->phone($phone, false, true);

        $type = preg_match("/{$this->opt($this->t('Car Type:'))}\s*([\s\S]+)\s*$/", $table[4], $m) ? $m[1] : null;
        $car->car()->type(preg_replace('/\s+/', ' ', $type));

        if (preg_match("/{$this->opt($this->t('Estimated Total:'))}\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d]*)\s*$/", $table[5], $m)) {
            // AUD 101.38
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
            $car->price()->currency($m['currency'])->total(PriceHelper::parse($m['amount'], $currencyCode));
        }

        $status = preg_match("/{$this->opt($this->t('Status:'))}\s*([^)(]+?)\s*(?:\(|$)/", $table[6], $m) ? $m[1] : null;
        $car->general()->status(preg_replace('/\s+/', ' ', $status));

        if (preg_match("/^\s*(?<company>.+?)[ ]+-[ ]+(?<address>.+)\s*$/", $rows[1], $m)) {
            $car->extra()->company($m['company']);
            $address = $m['address'];
        } else {
            $address = $rows[1];
        }

        if (empty($car->getPickUpLocation()) && empty($car->getDropOffLocation())) {
            $car->pickup()->location($address);
            $car->setSameLocation(true);
        }

        return $car;
    }

    private function assignLang($text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['MAXIMise reference number']) || empty($phrases['Arrive At'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['MAXIMise reference number']) !== false
                && $this->strposArray($text, $phrases['Arrive At']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray($text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
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

    private function rowColsPos($row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function normalizeDate(string $text): string
    {
        //$this->logger->debug('In '.$text);
        $in = [
            // 11:00 Thu 12 Sep 2019 960 Hay St Wa 6000 Perth
            // 21:05 Wed 05 Aug 2020 Apo Mascot Nsw 2020 Sydney
            // 16:00 392704 Wed 12 Aug 2020 Terminal Bldg Qld Mackay
            '/^(\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)(?:[\d\s]+)?\s+(\w.+?[[:alpha:]]{3}\s+\d{4}).*$/su',
        ];
        $out = [
            '$2 $1',
        ];

        return preg_replace($in, $out, $text);
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
