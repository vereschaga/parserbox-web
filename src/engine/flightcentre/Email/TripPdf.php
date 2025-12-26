<?php

namespace AwardWallet\Engine\flightcentre\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TripPdf extends \TAccountChecker
{
    public $mailFiles = "flightcentre/it-744336026.eml, flightcentre/it-12547214.eml, flightcentre/it-740760859.eml, flightcentre/it-740760495.eml, flightcentre/it-12550519.eml, flightcentre/it-740095739.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'statusVariants'        => ['Confirmed'],
            'otaConfNumber'         => ['Booking ID', 'Quote number'],
            'tripDetailsStart'      => ['Trip Details'],
            'tripDetailsEnd'        => ['Notes', 'Essential Information', 'Payment Details'],
            'travellersStart'       => 'Travellers',
            'travellersEnd'         => ['Non-travelling trip contact', 'Trip Summary', "We've Got You Covered"],
            'passengerDetailsStart' => 'Passenger Details',
            'passengerDetailsEnd'   => ['Supplements'],
            'infoMessageTexts'      => ['for booking with', 'Please review your quote', 'Purchase travel insurance for peace of mind'],
            'paymentDetailsStart'   => ['Payment Details'],
            'hotelNamePrefixes'     => ['Intrepid Pre-Tour Accommodation', 'Intrepid Post-Tour Accommodation'],
            'eventNamePrefixes'     => ['Intrepid Tour'],
            'transferNamePrefixes'  => ['Intrepid Arrival Transfer', 'Intrepid Departure Transfer'],
            'itHeaders'             => ['Flights', 'Flight to', 'Cruises', 'Stays', 'Rail', 'Car Hire', 'Tour', 'Transfer', 'Experience', 'Total Price'],
            'flightHeader'          => ['Flights', 'Flight to'],
        ],
    ];

    private $subjects = [
        'en' => ['Booking Invoice', 'Payment is required for your trip'],
    ];

    private $patterns = [
        'date'          => '\b\d{1,2}[ ]*\/[ ]*\d{1,2}[ ]*\/[ ]*\d{4}\b', // 21/05/2025
        'dateShort'     => '\b[[:alpha:]]{3} \d{1,2} [[:alpha:]]{3}\b', // Fri 1 Nov
        'time'          => '\d{1,2}[.:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.  |  12.10pm
        'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52  |  (+351) 21 342 09 07  |  713.680.2992
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:]\s]*?[[:alpha:]]', // Mr. Hao-Li Huang
        'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 175-2345005149-23  |  1752345005149/23
    ];

    private $otaConfNumbers = [];
    private $year = null;
    private $database = [];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flightcentre.com') !== false || stripos($from, '@flightcentre.ca') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ((!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true)
            && (!array_key_exists('subject', $headers) || strpos($headers['subject'], 'Flight Centre') === false)
        ) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            if (empty($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf)))) {
                continue;
            }

            if (stripos($textPdf, '@flightcentre.com') === false && stripos($textPdf, '@flightcentre.ca') === false
                && stripos($textPdf, 'www.flightcentre.com') === false && stripos($textPdf, 'help.flightcentre.com') === false
                && stripos($textPdf, 'www.flightcentre.ca') === false && stripos($textPdf, 'help.flightcentre.ca') === false
                && stripos($textPdf, 'visit flightcentre') === false
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
        $pdfTexts = [];
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            if (empty($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf)))) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $pdfTexts[] = $textPdf;
            }
        }

        foreach ($pdfTexts as $textPdf) {
            if (preg_match("/^\s*({$this->opt($this->t('otaConfNumber'))})[ :]*[ ]{2,}{$this->opt($this->t('Issued on'))}\n+[ ]*([-A-Z\d]{3,20})[ ]+\d{1,2}\b/", $textPdf, $m)
                && !in_array($m[2], $this->otaConfNumbers)
            ) {
                $email->ota()->confirmation($m[2], $m[1]);
                $this->otaConfNumbers[] = $m[2];
            }

            if (preg_match("/^\s*(?:.+[ ]{2})?{$this->opt($this->t('Issued on'))}\n+(?:.+[ ]{2})?(\d{1,2}\/\d{1,2}\/\d{4}|\d{1,2}[-,. ]+[[:alpha:]]+[-,. ]+\d{4})\n/u", $textPdf, $m)
                && preg_match("/^.{4,}\b(\d{4})$/", $m[1], $m2)
            ) {
                // 19/02/2024  |  02 Sep 2024
                $this->year = $m2[1];
            }

            $this->parsePdf($email, $textPdf);

            if (count($pdfTexts) === 1) {
                $paymentDetailsText = $this->re("/\n[ ]*{$this->opt($this->t('paymentDetailsStart'))}\n+([\s\S]+)/", $textPdf) ?? '';
                $totalPrice = preg_match("/^[ ]*{$this->opt($this->t('Grand Total'))}((?:[ ]{2}|(?:\n.*?){1,3}?).*\d.*)/m", $paymentDetailsText, $m)
                    && preg_match("/.+[ ]{2}(.*\d.*)$/", $m[1], $m2) ? $m2[1] : '';

                if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)) {
                    // 25,240.20 AUD
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                    $email->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($matches['currency']);
                }
            }
        }

        $email->setType('TripPdf' . ucfirst($this->lang));

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

    private function parsePdf(Email $email, ?string $text): void
    {
        // remove garbage
        $text = preg_replace('/\n(?:[ ]*|.+[ ]{2})\d{1,3}[ ]*\/[ ]*\d{1,3}\n/', "\n", $text);

        /* Travellers (global) */

        $travellers = [];
        $travellersText = $this->re("/\n[ ]*{$this->opt($this->t('travellersStart'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('travellersEnd'))}/", $text) ?? '';
        $travellersText = preg_replace([
            "/^[ ]*\d+[ ]*{$this->opt($this->t('adult'))}.*\n+/im",
            "/^([\s\S]+?)\n+.*{$this->opt($this->t('infoMessageTexts'))}[\s\S]*$/",
        ], [
            '',
            '$1',
        ], $travellersText);

        $travellersRows = preg_split("/\n{2,}/", $travellersText);

        foreach ($travellersRows as $tRow) {
            $tablePos = $this->rowColsPos($this->re("/^\n*(.+)/", $tRow));
            $table = $this->splitCols($tRow, $tablePos);

            foreach ($table as $cellText) {
                $passengerName = $this->normalizeTraveller($this->re("/^\s*({$this->patterns['travellerName']})(?i)(?:\n+[ ]*{$this->opt($this->t('adult'))}|\s*$)/u", $cellText));

                if ($passengerName && !in_array($passengerName, $travellers)) {
                    $travellers[] = preg_replace('/\s+/', ' ', $passengerName);
                }
            }
        }

        /* Trip Details */

        $flights = $cruises = $hotels = $rails = $cars = $events = $transfers = [];

        $tripDetailsText = $this->re("/\n[ ]*{$this->opt($this->t('tripDetailsStart'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('tripDetailsEnd'))}/", $text) ?? '';
        $tripDetailsText = preg_replace("/^([\s\S]+?)\n+.*{$this->opt($this->t('infoMessageTexts'))}[\s\S]*$/", '$1', $tripDetailsText);
        $tripSegments = $this->splitText($tripDetailsText, "/^([ ]*{$this->opt($this->t('itHeaders'))}(?: [-\')([:alpha:]]+)?(?:[ ]{2}.+|\s*$))/m", true);

        if (count($tripSegments) === 0) {
            $this->logger->debug('Trip segments not found!');
        }

        foreach ($tripSegments as $key => $segText) {
            if (preg_match("/^[ ]*{$this->opt($this->t('flightHeader'))}(?:[ ]{2}.+| [-\')([:alpha:]]+)?\n+([\s\S]+)$/", $segText, $m)) {
                $this->logger->debug("Trip segment-{$key}: Flight");
                $flights[] = $m[1];
            } elseif (preg_match("/^[ ]*{$this->opt($this->t('Cruises'))}(?:[ ]{2}.+)?\n+([\s\S]+)$/", $segText, $m)) {
                $this->logger->debug("Trip segment-{$key}: Cruise");
                $cruises[] = $m[1];
            } elseif (preg_match("/^[ ]*{$this->opt($this->t('Stays'))}(?:[ ]{2}.+)?\n+([\s\S]+)$/", $segText, $m)) {
                $this->logger->debug("Trip segment-{$key}: Hotel");
                $hotels[] = $m[1];
            } elseif (preg_match("/^[ ]*{$this->opt($this->t('Rail'))}(?:[ ]{2}.+)?\n+([\s\S]+)$/", $segText, $m)) {
                $this->logger->debug("Trip segment-{$key}: Rail");
                $rails[] = $m[1];
            } elseif (preg_match("/^[ ]*{$this->opt($this->t('Car Hire'))}(?:[ ]{2}.+)?\n+([\s\S]+)$/", $segText, $m)) {
                $this->logger->debug("Trip segment-{$key}: Car");
                $cars[] = $m[1];
            } elseif (preg_match("/^[ ]*{$this->opt($this->t('Tour'))}(?:[ ]{2}.+)?\n+([\s\S]+)$/", $segText, $m)) {
                $this->logger->debug("Trip segment-{$key}: Event");
                $events[] = $m[1];
            } elseif (preg_match("/^[ ]*{$this->opt($this->t('Transfer'))}(?:[ ]{2}.+)?\n+([\s\S]+)$/", $segText, $m)) {
                $this->logger->debug("Trip segment-{$key}: Transfer");
                $transfers[] = $m[1];
            } elseif (preg_match("/^[ ]*{$this->opt($this->t('Experience'))}(?:[ ]{2}.+|\n|\s*$)/", $segText)
                && preg_match("/^(?:.+\n+){0,4}[ ]*(?:Captain's Value Package|.*\bCombo Tour Public\b.*)(?:[ ]{2}.+|\n|\s*$)/i", $segText)
            ) {
                $this->logger->debug("Trip segment-{$key}: WTF");

                continue;
            } elseif (preg_match("/^[ ]*{$this->opt($this->t('Total Price'))}(?:[ ]{2}.+|\n|\s*$)/", $segText)) {
                $this->logger->debug("Trip segment-{$key}: cost");

                continue;
            } else {
                $this->logger->debug("Trip segment-{$key}: UNKNOWN");
                $email->add()->flight(); // for 100% fail
            }
        }

        $flightsByPnr = [];

        foreach ($flights as $i => $itText) {
            if (preg_match_all("/(?:^[ ]*|[ ]{2})({$this->opt($this->t('PNR reference'))})[: ]+([A-Z\d]{5,10})$/m", $itText, $confNoMatches)
                && count(array_unique($confNoMatches[2])) === 1
            ) {
                $pnr = $confNoMatches[2][0];
            } else {
                $pnr = 'unknown-' . $i;
            }

            if (array_key_exists($pnr, $flightsByPnr)) {
                $flightsByPnr[$pnr][] = $itText;
            } else {
                $flightsByPnr[$pnr] = [$itText];
            }
        }

        foreach ($flightsByPnr as $pnr => $flights) {
            $itText = implode("\n\n", $flights);
            $this->parseFlight($email, $itText, $travellers, $pnr);
        }

        foreach ($cruises as $itText) {
            $this->parseCruise($email, $itText, $travellers);
        }

        foreach ($hotels as $itText) {
            $this->parseHotel($email, $itText, $travellers);
        }

        foreach ($rails as $itText) {
            $this->parseRail($email, $itText, $travellers);
        }

        foreach ($cars as $itText) {
            $this->parseCar($email, $itText, $travellers);
        }

        foreach ($events as $itText) {
            $this->parseEvent($email, $itText, $travellers);
        }

        foreach ($transfers as $itText) {
            $this->parseTransfer($email, $itText, $travellers);
        }
    }

    private function parseFlight(Email $email, string $text, array $travellers, string $pnr): void
    {
        $this->logger->debug(__FUNCTION__);

        $it = $email->add()->flight();

        if (!empty($pnr) && stripos($pnr, 'unknown') === false) {
            $it->general()->confirmation($pnr);
        } elseif (!preg_match("/{$this->opt($this->t('PNR reference'))}/i", $text) && count($this->otaConfNumbers) > 0) {
            $it->general()->noConfirmation();
        }

        $passengers = $tickets = [];
        $segments = $this->splitText($text, "/^([ ]{0,50}{$this->patterns['dateShort']}(?: |\n))/mu", true);

        foreach ($segments as $key => $sText) {
            if (preg_match("/^\s*.+\n+.+\s*$/", $sText)) {
                continue;
            }

            $s = $it->addSegment();

            $firstRow = $this->re("/^[ •]*(.+?)[ •]*\n/", $sText);
            $secondRow = $this->re("/^.+\n+[ •]*(.+?)[ •]*\n/", $sText);

            $firstParams = preg_split("/(?:[ ]+[•]+[ ]+|[ ]{2,})/", $firstRow);
            $secondParams = preg_split("/(?:[ ]+[•]+[ ]+|[ ]{2,})/", $secondRow);

            $dateDep = $dateArr = null;
            $dateVal = count($firstParams) > 0 ? $firstParams[0] : '';

            if (preg_match("/^(?<wday>[-[:alpha:]]+)\s+(?<date>\d{1,2}\s+[[:alpha:]]+)$/u", $dateVal, $matches)) {
                // Fri 1 Nov
                $weekDateNumber = WeekTranslate::number1($matches['wday']);

                if ($weekDateNumber && $this->year) {
                    $dateDep = EmailDateHelper::parseDateUsingWeekDay($matches['date'] . ' ' . $this->year, $weekDateNumber);
                }
            } elseif (preg_match("/^.{4,}\b\d{4}$/", $dateVal)) {
                // Fri 1 Nov 2024
                $dateDep = strtotime($dateVal);
            }

            if (count($firstParams) > 1 && preg_match("/^({$this->patterns['time']})(?:[ ]*[-–]+[ ]*|[ ]{2,})({$this->patterns['time']})(?:[ ]*[+]+[ ]*(?<overnight>\d{1,3}))?$/", $firstParams[1], $m)) {
                // 05:55pm - 11:00pm  |  05:55pm - 11:00pm +1
                $timeDep = $m[1];
                $timeArr = $m[2];

                if (!empty($m['overnight']) && $dateDep) {
                    $dateArr = strtotime('+' . $m['overnight'] . ' days', $dateDep);
                } else {
                    $dateArr = $dateDep;
                }
            } else {
                $timeDep = $timeArr = null;
            }

            if ($dateDep && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $dateDep));
            }

            if ($dateArr && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $dateArr));
            }

            if (preg_match("/^((?:\d{1,3} ?[hm][ ]*)+)$/im", implode("\n", $firstParams), $m)) {
                $s->extra()->duration($m[1]);
            }

            if (preg_match("/^Non[ -]*stop$/im", implode("\n", $firstParams))) {
                $s->extra()->stops(0);
            }

            if (count($secondParams) > 1 && preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $secondParams[1], $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            if (preg_match("/^(Premium economy|Economy|Business|First)$/im", implode("\n", $secondParams), $m)) {
                $s->extra()->cabin($m[1]);
            }

            if (preg_match("/(?:^[ ]*|[•][ ]+){$this->opt($this->t('Airline Reference'))}[: ]+([A-Z\d]{5,10})(?:[ ]+[•]|$)/m", $sText, $m)) {
                $s->airline()->confirmation($m[1]);
            }

            $departVal = preg_replace('/\s+/', ' ', trim($this->re("/^[ ]{0,50}{$this->opt($this->t('Depart'))}[: ]+((?:.+\n+?){1,2}?)[ ]{0,50}{$this->opt($this->t('Arrive'))}/m", $sText) ?? ''));
            $arriveVal = preg_replace('/\s+/', ' ', trim($this->re("/^[ ]{0,50}{$this->opt($this->t('Arrive'))}[: ]+((?:.+\n+?){1,2}?)(?:\n|[ ]{0,50}{$this->opt($this->t('passengerDetailsStart'))})/m", $sText) ?? ''));

            $departParams = preg_split("/(?:[ ]+[•]+[ ]+|[ ]{2,})/", $departVal);
            $arriveParams = preg_split("/(?:[ ]+[•]+[ ]+|[ ]{2,})/", $arriveVal);

            $pattern1 = "/^(?:{$this->opt($this->t('Terminal'))}[-\s:]+)+([^\-\s:].*)$/i"; // Terminal 3
            $pattern2 = "/^(.+?)(?:[-\s]+{$this->opt($this->t('Terminal'))})+$/i"; // North terminal

            if (count($departParams) > 1
                && (preg_match($pattern1, $departParams[1], $m) || preg_match($pattern2, $departParams[1], $m))
            ) {
                $s->departure()->terminal($m[1]);
            }

            if (count($arriveParams) > 1
                && (preg_match($pattern1, $arriveParams[1], $m) || preg_match($pattern2, $arriveParams[1], $m))
            ) {
                $s->arrival()->terminal($m[1]);
            }

            if (preg_match($pattern3 = "/^(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/", $departParams[0], $m)) {
                // London Heathrow Airport (LHR)
                $s->departure()->name($m['name'])->code($m['code']);
            } else {
                // London Heathrow Airport
                $s->departure()->name($departParams[0])->noCode();
            }

            if (preg_match($pattern3, $arriveParams[0], $m)) {
                $s->arrival()->name($m['name'])->code($m['code']);
            } else {
                $s->arrival()->name($arriveParams[0])->noCode();
            }

            $this->database[] = [
                'type' => 'flightSegment',
                'ref'  => $s,
            ];

            $passengerDetailsText = $this->re("/\n[ ]*{$this->opt($this->t('passengerDetailsStart'))}\n+([\s\S]+?)(?:\n+[ ]*{$this->opt($this->t('passengerDetailsEnd'))}|$)/", $sText) ?? '';

            if ($passengerDetailsText && count($travellers) > 0) {
                $passengersRows = $this->splitText($passengerDetailsText, "/^([ ]*(?:[[:alpha:]]+[. ]+)?{$this->opt($travellers)})$/imu", true);
                $seats = [];

                foreach ($passengersRows as $pRow) {
                    $passengerName = $this->normalizeTraveller($this->re("/^[ ]*(.{2,})/", $pRow));

                    if (!in_array($passengerName, $passengers)) {
                        $passengers[] = $passengerName;
                    }

                    $seat = $this->re("/^[ ]*.{2,}\n{1,3}.*\b{$this->opt($this->t('Seat'))} (\d+[A-Z])(?:[ ]+•|[ ]{2}|\n|$)/i", $pRow);

                    if ($seat && !in_array($seat, $seats)) {
                        $s->extra()->seat($seat, false, false, $passengerName);
                        $seats[] = $seat;
                    }

                    $tkt = $this->re("/^[ ]*.{2,}\n{1,3}.*\bTKT ({$this->patterns['eTicket']})(?:[ ]+•|[ ]{2}|\n|$)/i", $pRow);

                    if ($tkt && !in_array($tkt, $tickets)) {
                        $it->issued()->ticket($tkt, false, $passengerName);
                        $tickets[] = $tkt;
                    }
                }
            }

            if ($key + 1 !== count($segments)) {
                continue;
            }

            $totalPrice = preg_match("/^[\s\S]+[ ]{2}(.*\d[,.]\d{2}(?:\b|\D).*)[\s\S]*$/", $sText, $m) ? $m[1] : '';

            if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
                // $2,318.96
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $it->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
            }
        }

        if (count($passengers) > 0) {
            $it->general()->travellers($passengers, true);
        } elseif (count($travellers) > 0) {
            $it->general()->travellers($travellers, true);
        }
    }

    private function parseCruise(Email $email, string $text, array $travellers): void
    {
        $this->logger->debug(__FUNCTION__);

        $it = $email->add()->cruise();
    }

    private function parseHotel(Email $email, string $text, array $travellers): void
    {
        $this->logger->debug(__FUNCTION__);

        $it = $email->add()->hotel();

        if (preg_match_all("/(?:^[ ]*|[ ]{2})({$this->opt($this->t('statusVariants'))})(?:[ ]+{$this->opt($this->t('Booking Reference'))}|$)/im", $text, $statusMatches)
            && count(array_unique($statusMatches[1])) === 1
        ) {
            $it->general()->status($statusMatches[1][0]);
        }

        if (preg_match_all("/(?:^[ ]*|{$this->opt($this->t('statusVariants'))} |[ ]{2})({$this->opt($this->t('Booking Reference'))})[: ]+([-A-Z\d]{4,35})$/m", $text, $confNoMatches)
            && count(array_unique($confNoMatches[2])) === 1
        ) {
            $it->general()->confirmation($confNoMatches[2][0], $confNoMatches[1][0]);
        } elseif (!preg_match("/{$this->opt($this->t('Booking Reference'))}/i", $text)) {
            $it->general()->noConfirmation();
        }

        $text = preg_replace([
            "/[ ]+{$this->opt($this->t('statusVariants'))}(?:[ ]+{$this->opt($this->t('Booking Reference'))}[: ]+.*|$)/im",
            "/[ ]+{$this->opt($this->t('Booking Reference'))}[: ]+.*/",
        ], '', $text);

        $hotelName = preg_match("/^\s*((?:.+\n+?){1,3}?)\n{0,1}.*•/", $text, $m) ? preg_replace([
            '/\s+/',
            "/^{$this->opt($this->t('hotelNamePrefixes'))}[: ]+(.{2,})$/i",
        ], [
            ' ',
            '$1',
        ], trim($m[1])) : null;

        $paramsText = preg_match("/(?:^\s*|\n)[ •]*(.*•.*(?:\n+.*•.*)*)/", $text, $m) ? preg_replace('/\s+/', ' ', trim($m[1], '• ')) : '';
        $params = preg_split("/(?:[ ]+[•]+[ ]+|[ ]{2,})/", $paramsText);

        $dateCheckInVal = $dateCheckOutVal = null;

        if (count($params) > 0 && preg_match("/^(.{3,}?)\s+[-–]+\s+(.{3,})$/", $params[0], $m)) {
            $dateCheckInVal = $m[1];
            $dateCheckOutVal = $m[2];
        }

        $dateCheckIn = $dateCheckOut = null;

        if (preg_match($pattern = "/^(?<wday>[-[:alpha:]]+)\s+(?<date>\d{1,2}\s+[[:alpha:]]+)$/u", $dateCheckInVal, $matches)) {
            // Mon 14 Oct
            $weekDateNumber = WeekTranslate::number1($matches['wday']);

            if ($weekDateNumber && $this->year) {
                $dateCheckIn = EmailDateHelper::parseDateUsingWeekDay($matches['date'] . ' ' . $this->year, $weekDateNumber);
            }
        } elseif (preg_match("/^.{4,}\b\d{4}$/", $dateCheckInVal)) {
            // Mon 14 Oct 2024
            $dateCheckIn = strtotime($dateCheckInVal);
        }

        if (preg_match($pattern, $dateCheckOutVal, $matches)) {
            $weekDateNumber = WeekTranslate::number1($matches['wday']);

            if ($weekDateNumber && $this->year) {
                $dateCheckOut = EmailDateHelper::parseDateUsingWeekDay($matches['date'] . ' ' . $this->year, $weekDateNumber);
            }
        } elseif (preg_match("/^.{4,}\b\d{4}$/", $dateCheckOutVal)) {
            $dateCheckOut = strtotime($dateCheckOutVal);
        }

        if (preg_match("/{$this->opt($this->t('Check in'))}[: ]+({$this->patterns['time']})/", $paramsText, $m)) {
            $dateCheckIn = strtotime($m[1], $dateCheckIn);
        }

        if (preg_match("/{$this->opt($this->t('Check out'))}[: ]+({$this->patterns['time']})/", $paramsText, $m)) {
            $dateCheckOut = strtotime($m[1], $dateCheckOut);
        }

        $it->booked()->checkIn($dateCheckIn)->checkOut($dateCheckOut);

        $address = null;

        foreach ($params as $i => $p) {
            if (preg_match("/^\d{1,3}\s*(?:{$this->opt($this->t('day'))}|{$this->opt($this->t('night'))})/i", $p)
                && array_key_exists($i + 1, $params)
            ) {
                $address = $params[$i + 1];
            }
        }

        if ($address && !preg_match('/\d/', $address) && $hotelName) {
            $address = $hotelName . ', ' . $address;
        }

        $it->hotel()->name($hotelName)->address($address);

        $this->database[] = [
            'type' => 'hotel',
            'ref'  => $it,
        ];

        $cancellationPolicy = $this->re("/\n[ ]*{$this->opt($this->t('Cancellation Policy'))}[: ]*\n{0,1}((?:\n.+){1,4}?)(?:\n[ ]{50}|\n{2}|\s*$)/", $text);

        if ($cancellationPolicy) {
            $it->general()->cancellation(preg_replace('/\s+/', ' ', trim($cancellationPolicy)));
        }

        if (count($travellers) > 0) {
            $it->general()->travellers($travellers, true);
        }

        $totalPrice = preg_match("/^[\s\S]+[ ]{2}(.*\d[,.]\d{2}(?:\b|\D).*)[\s\S]*$/", $text, $m) ? $m[1] : '';

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $240.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $it->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    private function parseRail(Email $email, string $text, array $travellers): void
    {
        $this->logger->debug(__FUNCTION__);

        $it = $email->add()->train();

        if (preg_match_all("/(?:^[ ]*|[ ]{2})({$this->opt($this->t('Booking Reference'))})[: ]+([-A-Z\d]{4,35})$/m", $text, $confNoMatches)
            && count(array_unique($confNoMatches[2])) === 1
        ) {
            $it->general()->confirmation($confNoMatches[2][0], $confNoMatches[1][0]);
        }

        $text = preg_replace("/[ ]+{$this->opt($this->t('Booking Reference'))}[: ]+.*/", '', $text);

        $s = $it->addSegment();

        $serviceName = preg_match("/^\s*((?:.+\n+?){1,3}?)\n{0,1}.*•/", $text, $m) ? trim(preg_replace('/\s+/', ' ', $m[1])) : null;
        $s->extra()->service($serviceName, false, true);

        $locationDep = $locationArr = null;

        $checkInVal = preg_match("/\n[ ]{0,50}{$this->opt($this->t('Check-in time & location'))}[: ]*(.*(?:\n.+?){0,2}?)(?:\n\n|\n[ ]*{$this->opt($this->t('Inclusions'))}|\n[ ]*{$this->opt($this->t('Cancellation Policy'))}|\s*$)/", $text, $m) ? preg_replace('/\s+/', ' ', trim($m[1])) : '';

        if (preg_match("/^.+ prior to train departure time at (\S.{1,105}?\S)\s*(?:[.;!]|$)/i", $checkInVal, $m)) {
            $locationDep = $m[1];
        }

        $paramsText = $this->re("/^[ •]*(.+•.+?)[ •]*$/m", $text);
        $params = preg_split("/(?:[ ]+[•]+[ ]+|[ ]{2,})/", $paramsText);

        if (count($params) > 2 && preg_match("/{$this->opt($this->t('day'))}/", $params[1])) {
            $locationArr = $params[2];
        }

        if (preg_match("/\b(?<city1>[-'[:alpha:]]{3,30})\s+{$this->opt($this->t('to'))}\s+(?<city2>[-'[:alpha:]]{3,30})\b/u", $serviceName ?? '', $m)) {
            $from = $m['city1'];
            $to = $m['city2'];
        } else {
            $from = $to = null;
        }

        if (empty($locationDep) && $from) {
            $matchItems = [];
            $pattern = "/^(({$this->opt($from)})(?:[- ].*|$))/m";

            foreach ($this->database as $dbItem) {
                if ($dbItem['type'] === 'flightSegment') {
                    /** @var \AwardWallet\Schema\Parser\Common\FlightSegment $obj */
                    $obj = $dbItem['ref'];

                    if (!empty($obj->getDepName()) && preg_match($pattern, $obj->getDepName())
                        || !empty($obj->getArrName()) && preg_match($pattern, $obj->getArrName())
                    ) {
                        $matchItems[] = $dbItem;
                    }
                }
            }

            if (count($matchItems) === 1) {
                if ($matchItems[0]['type'] === 'flightSegment') {
                    $obj = $matchItems[0]['ref'];

                    if (preg_match_all($pattern, implode("\n", array_filter([$obj->getDepName(), $obj->getArrName()])), $locMatches)
                        && count(array_unique($locMatches[2])) === 1
                    ) {
                        $locationDep = $locMatches[1][0];
                    }
                }
            } else {
                $this->logger->debug('Too many matches for rail departure location!');
            }
        }

        if (empty($locationArr) && $to) {
            $matchItems = [];
            $pattern = "/^(({$this->opt($to)})(?:[- ].*|$))/m";

            foreach ($this->database as $dbItem) {
                if ($dbItem['type'] === 'flightSegment') {
                    /** @var \AwardWallet\Schema\Parser\Common\FlightSegment $obj */
                    $obj = $dbItem['ref'];

                    if (!empty($obj->getDepName()) && preg_match($pattern, $obj->getDepName())
                        || !empty($obj->getArrName()) && preg_match($pattern, $obj->getArrName())
                    ) {
                        $matchItems[] = $dbItem;
                    }
                }
            }

            if (count($matchItems) === 1) {
                if ($matchItems[0]['type'] === 'flightSegment') {
                    $obj = $matchItems[0]['ref'];

                    if (preg_match_all($pattern, implode("\n", array_filter([$obj->getDepName(), $obj->getArrName()])), $locMatches)
                        && count(array_unique($locMatches[2])) === 1
                    ) {
                        $locationArr = $locMatches[1][0];
                    }
                }
            } else {
                $this->logger->debug('Train: too many matches for rail arrival location!');
            }
        }

        $s->departure()->name($locationDep);
        $s->arrival()->name($locationArr);

        $departVal = $this->re("/^[ ]{0,50}{$this->opt($this->t('Departs'))}[: ]+(.{3,})\n+[ ]{0,50}{$this->opt($this->t('Arrives'))}/m", $text) ?? '';
        $arriveVal = $this->re("/^[ ]{0,50}{$this->opt($this->t('Arrives'))}[: ]+(.{3,})/m", $text) ?? '';

        if (preg_match($pattern = "/^(?<date>{$this->patterns['date']})\s+{$this->opt($this->t('at'))}\s+(?<time>{$this->patterns['time']})/", $departVal, $m)) {
            // 21/05/2025 at 12.10pm
            $dateDep = strtotime($this->normalizeDate($m['date']));
            $timeDep = $this->normalizeTime($m['time']);
        } else {
            $dateDep = $timeDep = null;
        }

        if (preg_match($pattern, $arriveVal, $m)) {
            $dateArr = strtotime($this->normalizeDate($m['date']));
            $timeArr = $this->normalizeTime($m['time']);
        } else {
            $dateArr = $timeArr = null;
        }

        if ($dateDep && $timeDep) {
            $s->departure()->date(strtotime($timeDep, $dateDep));
        }

        if ($dateArr && $timeArr) {
            $s->arrival()->date(strtotime($timeArr, $dateArr));
        }

        if (!empty($s->getDepDate()) && !empty($s->getArrDate())) {
            $s->extra()->noNumber();
        }

        $cancellationPolicy = $this->re("/\n[ ]*{$this->opt($this->t('Cancellation Policy'))}[: ]*\n{0,1}((?:\n.+){1,4}?)(?:\n[ ]{50}|\n{2}|\s*$)/", $text);

        if ($cancellationPolicy) {
            $it->general()->cancellation(preg_replace('/\s+/', ' ', trim($cancellationPolicy)));
        }

        if (count($travellers) > 0) {
            $it->general()->travellers($travellers, true);
        }

        $totalPrice = preg_match("/^[\s\S]+[ ]{2}(.*\d[,.]\d{2}(?:\b|\D).*)[\s\S]*$/", $text, $m) ? $m[1] : '';

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $5,742.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $it->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    private function parseCar(Email $email, string $text, array $travellers): void
    {
        $this->logger->debug(__FUNCTION__);

        $it = $email->add()->rental();

        if (preg_match_all("/(?:^[ ]*|[ ]{2})({$this->opt($this->t('statusVariants'))})(?:[ ]+{$this->opt($this->t('Booking Reference'))}|$)/im", $text, $statusMatches)
            && count(array_unique($statusMatches[1])) === 1
        ) {
            $it->general()->status($statusMatches[1][0]);
        }

        if (preg_match_all("/(?:^[ ]*|{$this->opt($this->t('statusVariants'))} |[ ]{2})({$this->opt($this->t('Booking Reference'))})[: ]+([-A-Z\d]{4,35})$/m", $text, $confNoMatches)
            && count(array_unique($confNoMatches[2])) === 1
        ) {
            $it->general()->confirmation($confNoMatches[2][0], $confNoMatches[1][0]);
        }

        $text = preg_replace([
            "/[ ]+{$this->opt($this->t('statusVariants'))}(?:[ ]+{$this->opt($this->t('Booking Reference'))}[: ]+.*|$)/im",
            "/[ ]+{$this->opt($this->t('Booking Reference'))}[: ]+.*/",
        ], '', $text);

        /*
        $serviceName = preg_match("/^\s*((?:.+\n+?){1,3}?)\n{0,1}.*•/", $text, $m) ? trim(preg_replace('/\s+/', ' ', $m[1])) : null;

        $paramsText = preg_match("/(?:^\s*|\n)[ •]*(.+•[\s\S]*?)[ •]*(?:\n{3}|\n+[ ]{50}|\n[ ]+{$this->opt($this->t('Booking Note'))}|\n[ ]+{$this->opt($this->t('Cancellation Policy'))})/", $text, $m) ? trim(preg_replace('/\s+/', ' ', $m[1])) : '';
        $params = preg_split("/(?:[ ]+[•]+[ ]+|[ ]{2,})/", $paramsText);
        */

        $rentalProvider = preg_match("/(?:^\s*|\n[ ]*){$this->opt($this->t('Car Rental Provider'))}[: ]+(.*(?:\n+.+){1,2}?)(?:\n+[ ]*{$this->opt($this->t('Vehicle Details'))}|\n{3}|$)/", $text, $m) ? trim(preg_replace('/\s+/', ' ', $m[1])) : null;

        if (($code = $this->normalizeProvider($rentalProvider))) {
            $it->program()->code($code);
        } else {
            $it->extra()->company($rentalProvider);
        }

        $vehicleDetails = preg_match("/(?:^\s*|\n[ ]*){$this->opt($this->t('Vehicle Details'))}[: ]+(.*(?:\n+.+){1,2}?)(?:\n+[ ]*{$this->opt($this->t('Duration'))}|\n{3}|$)/", $text, $m) ? trim(preg_replace('/\s+/', ' ', $m[1])) : null;

        if (preg_match("/^(?<type>.*?\S)\s+[-–]+\s+(?<model>\S.+)$/", $vehicleDetails ?? '', $m)) {
            $it->car()->type($m['type'])->model($m['model']);
        } else {
            $it->car()->type($vehicleDetails, false, true);
        }

        $pickUpText = preg_match("/(?:^\s*|\n[ ]*){$this->opt($this->t('Pick-up Location & Time'))}[: ]+(.*(?:\n+.+){1,2}?)(?:\n+[ ]*{$this->opt($this->t('Drop-off Location & Time'))}|\n{3}|$)/", $text, $m) ? trim(preg_replace('/\s+/', ' ', $m[1])) : '';
        $dropOffText = preg_match("/(?:^\s*|\n[ ]*){$this->opt($this->t('Drop-off Location & Time'))}[: ]+(.*(?:\n+.+){1,2}?)(?:\n+[ ]*{$this->opt($this->t('Inclusions'))}|\n+[ ]*{$this->opt($this->t('Exclusions'))}|\n+[ ]*{$this->opt($this->t('Cancellation Policy'))}\n{3}|$)/", $text, $m) ? trim(preg_replace('/\s+/', ' ', $m[1])) : '';

        $pickUpDateVal = $dropOffDateVal = '';

        if (preg_match($pattern1 = "/^(?<loc>.{3,}?)\s+@\s+(?<date>.{3,})$/", $pickUpText, $m)) {
            // Boston Logan Airport (BOS) @ 1700 - 01 Oct 2024
            $it->pickup()->location($m['loc']);
            $pickUpDateVal = $m['date'];
        }

        if (preg_match($pattern1, $dropOffText, $m)) {
            $it->dropoff()->location($m['loc']);
            $dropOffDateVal = $m['date'];
        }

        if (preg_match($pattern2 = "/^(?<time>\d{4}|{$this->patterns['time']})\s+-\s+(?<date>.{3,}\b\d{4})$/", $pickUpDateVal, $m)) {
            // 1700 - 01 Oct 2024
            $it->pickup()->date(strtotime($this->normalizeTime($m['time']), strtotime($this->normalizeDate($m['date']))));
        }

        if (preg_match($pattern2, $dropOffDateVal, $m)) {
            $it->dropoff()->date(strtotime($this->normalizeTime($m['time']), strtotime($this->normalizeDate($m['date']))));
        }

        $cancellationPolicy = $this->re("/\n[ ]*{$this->opt($this->t('Cancellation Policy'))}[: ]*\n{0,1}((?:\n.+){1,4}?)(?:\n[ ]{50}|\n{2}|\s*$)/", $text);

        if ($cancellationPolicy) {
            $it->general()->cancellation(preg_replace('/\s+/', ' ', trim($cancellationPolicy)));
        }

        if (count($travellers) > 0) {
            $it->general()->travellers($travellers, true);
        }

        $totalPrice = preg_match("/^[\s\S]+[ ]{2}(.*\d[,.]\d{2}(?:\b|\D).*)[\s\S]*$/", $text, $m) ? $m[1] : '';

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $575.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $it->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    private function parseEvent(Email $email, string $text, array $travellers): void
    {
        $this->logger->debug(__FUNCTION__);

        $it = $email->add()->event();
        $it->type()->event();

        if (preg_match_all("/(?:^[ ]*|[ ]{2})({$this->opt($this->t('statusVariants'))})(?:[ ]+{$this->opt($this->t('Booking Reference'))}|$)/im", $text, $statusMatches)
            && count(array_unique($statusMatches[1])) === 1
        ) {
            $it->general()->status($statusMatches[1][0]);
        }

        if (preg_match_all("/(?:^[ ]*|{$this->opt($this->t('statusVariants'))} |[ ]{2})({$this->opt($this->t('Booking Reference'))})[: ]+([-A-Z\d]{4,35})$/m", $text, $confNoMatches)
            && count(array_unique($confNoMatches[2])) === 1
        ) {
            $it->general()->confirmation($confNoMatches[2][0], $confNoMatches[1][0]);
        } elseif (!preg_match("/{$this->opt($this->t('Booking Reference'))}/i", $text)) {
            $it->general()->noConfirmation();
        }

        $text = preg_replace([
            "/[ ]+{$this->opt($this->t('statusVariants'))}(?:[ ]+{$this->opt($this->t('Booking Reference'))}[: ]+.*|$)/im",
            "/[ ]+{$this->opt($this->t('Booking Reference'))}[: ]+.*/",
        ], '', $text);

        $eventName = preg_match("/^\s*((?:.+\n+?){1,3}?)\n{0,1}.*•/", $text, $m) ? preg_replace([
            '/\s+/',
            "/^{$this->opt($this->t('eventNamePrefixes'))}[: ]+(.{2,})$/i",
        ], [
            ' ',
            '$1',
        ], trim($m[1])) : null;

        $paramsText = preg_match("/(?:^\s*|\n)[ •]*(.*•.*(?:\n+.*•.*)*)/", $text, $m) ? preg_replace('/\s+/', ' ', trim($m[1], '• ')) : '';
        $params = preg_split("/(?:[ ]+[•]+[ ]+|[ ]{2,})/", $paramsText);

        $dateStartVal = $dateEndVal = null;

        if (count($params) > 0 && preg_match("/^(.{3,}?)\s+[-–]+\s+(.{3,})$/", $params[0], $m)) {
            $dateStartVal = $m[1];
            $dateEndVal = $m[2];
        }

        $dateStart = $dateEnd = null;

        if (preg_match($pattern = "/^(?<wday>[-[:alpha:]]+)\s+(?<date>\d{1,2}\s+[[:alpha:]]+)$/u", $dateStartVal, $matches)) {
            // Mon 14 Oct
            $weekDateNumber = WeekTranslate::number1($matches['wday']);

            if ($weekDateNumber && $this->year) {
                $dateStart = EmailDateHelper::parseDateUsingWeekDay($matches['date'] . ' ' . $this->year, $weekDateNumber);
            }
        } elseif (preg_match("/^.{4,}\b\d{4}$/", $dateStartVal)) {
            // Mon 14 Oct 2024
            $dateStart = strtotime($dateStartVal);
        }

        if (preg_match($pattern, $dateEndVal, $matches)) {
            $weekDateNumber = WeekTranslate::number1($matches['wday']);

            if ($weekDateNumber && $this->year) {
                $dateEnd = EmailDateHelper::parseDateUsingWeekDay($matches['date'] . ' ' . $this->year, $weekDateNumber);
            }
        } elseif (preg_match("/^.{4,}\b\d{4}$/", $dateEndVal)) {
            $dateEnd = strtotime($dateEndVal);
        }

        $timeStart = $timeEnd = null;

        if (!$timeStart) {
            $timeStart = '00:00';
        }

        if (!$timeEnd) {
            $timeEnd = '23:59';
        }

        if ($dateStart && $timeStart) {
            $it->booked()->start(strtotime($timeStart, $dateStart));
        }

        if ($dateEnd && $timeEnd) {
            $it->booked()->end(strtotime($timeEnd, $dateEnd));
        }

        $address = null;

        foreach ($params as $i => $p) {
            if (preg_match("/^\d{1,3}\s*(?:{$this->opt($this->t('day'))}|{$this->opt($this->t('night'))})/i", $p)
                && array_key_exists($i + 1, $params)
            ) {
                $address = $params[$i + 1];
            }
        }

        $it->place()->name($eventName)->address($address);

        $cancellationPolicy = $this->re("/\n[ ]*{$this->opt($this->t('Cancellation Policy'))}[: ]*\n{0,1}((?:\n.+){1,4}?)(?:\n[ ]{50}|\n{2}|\s*$)/", $text);

        if ($cancellationPolicy) {
            $it->general()->cancellation(preg_replace('/\s+/', ' ', trim($cancellationPolicy)));
        }

        if (count($travellers) > 0) {
            $it->general()->travellers($travellers, true);
        }

        $totalPrice = preg_match("/^[\s\S]+[ ]{2}(.*\d[,.]\d{2}(?:\b|\D).*)[\s\S]*$/", $text, $m) ? $m[1] : '';

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $9,543.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $it->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    private function parseTransfer(Email $email, string $text, array $travellers): void
    {
        $this->logger->debug(__FUNCTION__);

        $it = $email->add()->transfer();

        if (preg_match_all("/(?:^[ ]*|[ ]{2})({$this->opt($this->t('statusVariants'))})(?:[ ]+{$this->opt($this->t('Booking Reference'))}|$)/im", $text, $statusMatches)
            && count(array_unique($statusMatches[1])) === 1
        ) {
            $it->general()->status($statusMatches[1][0]);
        }

        if (preg_match_all("/(?:^[ ]*|{$this->opt($this->t('statusVariants'))} |[ ]{2})({$this->opt($this->t('Booking Reference'))})[: ]+([-A-Z\d]{4,35})$/m", $text, $confNoMatches)
            && count(array_unique($confNoMatches[2])) === 1
        ) {
            $it->general()->confirmation($confNoMatches[2][0], $confNoMatches[1][0]);
        } elseif (!preg_match("/{$this->opt($this->t('Booking Reference'))}/i", $text)) {
            $it->general()->noConfirmation();
        }

        $cancellationPolicy = $this->re("/\n[ ]*{$this->opt($this->t('Cancellation Policy'))}[: ]*\n{0,1}((?:\n.+){1,4}?)(?:\n[ ]{50}|\n{2}|\s*$)/", $text);

        if ($cancellationPolicy) {
            $it->general()->cancellation(preg_replace('/\s+/', ' ', trim($cancellationPolicy)));
        }

        if (count($travellers) > 0) {
            $it->general()->travellers($travellers, true);
        }

        $totalPrice = preg_match("/^[\s\S]+[ ]{2}(.*\d[,.]\d{2}(?:\b|\D).*)[\s\S]*$/", $text, $m) ? $m[1] : '';

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $25.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $it->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $text = preg_replace([
            "/[ ]+{$this->opt($this->t('statusVariants'))}(?:[ ]+{$this->opt($this->t('Booking Reference'))}[: ]+.*|$)/im",
            "/[ ]+{$this->opt($this->t('Booking Reference'))}[: ]+.*/",
        ], '', $text);

        $transferName = preg_match("/^\s*((?:.+\n+?){1,3}?)\n{0,1}.*•/", $text, $m) ? preg_replace([
            '/\s+/',
            "/^{$this->opt($this->t('transferNamePrefixes'))}[: ]+(.{2,})$/i",
        ], [
            ' ',
            '$1',
        ], trim($m[1])) : '';

        if (preg_match("/^(.{2,}?)\s+{$this->opt($this->t('to'))}\s+(.{2,})$/", $transferName, $m)) {
            $from = $m[1];
            $to = $m[2];
        } else {
            $from = $to = '';
        }

        $fromType = $toType = null;

        $patternsType = [
            'flightSegment' => "/^(.+\S)\s+Airport$/i",
            'hotel'         => "/^(.+\S)\s+Hotel$/i",
        ];

        if (preg_match($patternsType['flightSegment'], $from, $m)) {
            $from = $m[1];
            $fromType = 'flightSegment';
        } elseif (preg_match($patternsType['hotel'], $from, $m)) {
            $from = $m[1];
            $fromType = 'hotel';
        }

        if (preg_match($patternsType['flightSegment'], $to, $m)) {
            $to = $m[1];
            $toType = 'flightSegment';
        } elseif (preg_match($patternsType['hotel'], $to, $m)) {
            $to = $m[1];
            $toType = 'hotel';
        }

        $paramsText = preg_match("/(?:^\s*|\n)[ •]*(.*•.*(?:\n+.*•.*)*)/", $text, $m) ? preg_replace('/\s+/', ' ', trim($m[1], '• ')) : '';
        $params = preg_split("/(?:[ ]+[•]+[ ]+|[ ]{2,})/", $paramsText);

        $dateDepVal = $dateArrVal = null;

        if (count($params) > 0 && preg_match("/^(.{3,}?)\s+[-–]+\s+(.{3,})$/", $params[0], $m)) {
            $dateDepVal = $m[1];
            $dateArrVal = $m[2];
        }

        if (!$fromType || !$toType || !$dateDepVal || !$dateArrVal) {
            $this->logger->debug('Transfer: missing required information!');

            return;
        }
        $dateFormat = 'D j M'; // Sun 3 Nov

        $s = $it->addSegment();

        $depMatchItems = $arrMatchItems = [];

        /* Step 1/2: finding info for segment */

        foreach ($this->database as $dbItem) {
            $obj = $dbItem['ref'];

            if ($dbItem['type'] === $fromType) {
                if ($dbItem['type'] === 'flightSegment') {
                    /** @var \AwardWallet\Schema\Parser\Common\FlightSegment $obj */
                    if (!empty($obj->getArrName()) && strpos($obj->getArrName(), $from) !== false
                        && !empty($obj->getArrDate()) && preg_match('/^(?:' . implode('|', [date($dateFormat, $obj->getArrDate()), date($dateFormat, strtotime('+1 day', $obj->getArrDate()))]) . ')$/i', $dateDepVal)
                    ) {
                        $depMatchItems[] = $dbItem;
                    }
                } elseif ($dbItem['type'] === 'hotel') {
                    /** @var \AwardWallet\Schema\Parser\Common\Hotel $obj */
                    if (!empty($obj->getAddress()) && strpos($obj->getAddress(), $from) !== false) {
                        $depMatchItems[] = $dbItem;
                    }
                }
            } elseif ($dbItem['type'] === $toType) {
                if ($dbItem['type'] === 'flightSegment') {
                    /** @var \AwardWallet\Schema\Parser\Common\FlightSegment $obj */
                    if (!empty($obj->getDepName()) && strpos($obj->getDepName(), $to) !== false
                        && !empty($obj->getDepDate()) && preg_match('/^(?:' . implode('|', [date($dateFormat, $obj->getDepDate()), date($dateFormat, strtotime('-1 day', $obj->getDepDate()))]) . ')$/i', $dateArrVal)
                    ) {
                        $arrMatchItems[] = $dbItem;
                    }
                } elseif ($dbItem['type'] === 'hotel') {
                    /** @var \AwardWallet\Schema\Parser\Common\Hotel $obj */
                    if (!empty($obj->getAddress()) && strpos($obj->getAddress(), $to) !== false) {
                        $arrMatchItems[] = $dbItem;
                    }
                }
            }
        }

        /* Step 2/2: using info for segment */

        if (count($depMatchItems) === 1) {
            $obj = $depMatchItems[0]['ref'];

            if ($depMatchItems[0]['type'] === 'flightSegment') {
                /** @var \AwardWallet\Schema\Parser\Common\FlightSegment $obj */
                if (!empty($obj->getArrName())) {
                    $s->departure()->name($obj->getArrName());
                }

                if (!empty($obj->getArrCode())) {
                    $s->departure()->code($obj->getArrCode());
                }

                if (!empty($obj->getArrDate())) {
                    $s->departure()->date(strtotime('+30 minutes', $obj->getArrDate()));
                }
            } elseif ($depMatchItems[0]['type'] === 'hotel') {
                /** @var \AwardWallet\Schema\Parser\Common\Hotel $obj */
                $s->departure()->name($obj->getAddress())->noDate();
            }
        } else {
            $this->logger->debug('Transfer: too many matches for departure location!');
        }

        if (count($arrMatchItems) === 1) {
            $obj = $arrMatchItems[0]['ref'];

            if ($arrMatchItems[0]['type'] === 'flightSegment') {
                /** @var \AwardWallet\Schema\Parser\Common\FlightSegment $obj */
                if (!empty($obj->getDepName())) {
                    $s->arrival()->name($obj->getDepName());
                }

                if (!empty($obj->getDepCode())) {
                    $s->arrival()->code($obj->getDepCode());
                }

                if (!empty($obj->getDepDate())) {
                    $s->arrival()->date(strtotime('-3 hours', $obj->getDepDate()));
                }
            } elseif ($arrMatchItems[0]['type'] === 'hotel') {
                /** @var \AwardWallet\Schema\Parser\Common\Hotel $obj */
                $s->arrival()->name($obj->getAddress())->noDate();
            }
        } else {
            $this->logger->debug('Transfer: too many matches for arrival location!');
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['tripDetailsStart'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['tripDetailsStart']) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
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

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
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

    /**
     * @param string|null $string Provider keyword
     *
     * @return string|null Provider code
     */
    private function normalizeProvider(?string $string): ?string
    {
        $string = trim($string);
        $providers = [
            'avis'         => ['Avis'],
            'alamo'        => ['Alamo'],
            'perfectdrive' => ['Budget'],
            'dollar'       => ['Dollar'],
            'rentacar'     => ['Enterprise'],
            'europcar'     => ['Europcar'],
            'hertz'        => ['Hertz'],
            'national'     => ['National'],
            'sixt'         => ['Sixt'],
            'thrifty'      => ['Thrifty'],
        ];

        foreach ($providers as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (strcasecmp($string, $keyword) === 0) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MSTR|MISS|MRS|MR|MS|DR)';

        return preg_replace([
            "/^(.{2,}?)\s+(?:{$namePrefixes}[.\s]*)+$/is",
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
        ], [
            '$1',
            '$1',
        ], $s);
    }

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace([
            '/(\d)[ ]*[.][ ]*(\d)/', // 12.10pm    ->    12:10pm
        ], [
            '$1:$2',
        ], $s);

        return $s;
    }

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 21/05/2025
            '/^\s*(\d{1,2})\s*\/\s*(\d{1,2})\s*\/\s*(\d{4})\s*$/',
        ];
        $out = [
            '$2/$1/$3',
        ];

        return preg_replace($in, $out, $text);
    }
}
