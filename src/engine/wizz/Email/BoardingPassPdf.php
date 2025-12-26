<?php

namespace AwardWallet\Engine\wizz\Email;

use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "wizz/it-35396211.eml, wizz/it-42230253-hu.eml, wizz/it-706403826-flyone.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            // 'Name' => '',
            'passengerEnd'   => ['Gender / Passenger', 'Passenger type', 'PNR'],
            'DEP / DEST'     => ['DEP/DEST', 'DEP / DEST'],
            // 'FLIGHT DATE:' => '',
            'Flight number'  => ['Flight number'],
            // 'Arrival' => '',
            // 'Departure' => '',
            'Gate closes'    => ['Gate closes', 'Gate close'],
            'tableDelimiter' => ['Gender / Passenger', 'Passenger type'],
            'PNR'            => ['PNR', 'PNR / SEQ'],
            // 'Seat' => '',
        ],
        'hu' => [
            'Name'              => 'Név',
            'passengerEnd'      => 'Nem / Utas típusa',
            'DEP / DEST'        => ['DEP/DEST', 'DEP / DEST'],
            'FLIGHT DATE:'      => 'JÁRAT DÁTUMA:',
            'Flight number'     => ['Járatszám'],
            'Arrival'           => 'Érkezés',
            'Departure'         => 'Indulás',
            'Gate closes'       => ['Kapuzárás'],
            'tableDelimiter'    => ['Nem / Utas típusa'],
            'PNR'               => ['PNR', 'PNR / Sorszám'],
            'Seat'              => 'Ülőhely',
        ],
        'it' => [
            'Name'              => 'Nome',
            'passengerEnd'      => 'Sesso / Tipo di passeggero',
            'DEP / DEST'        => ['PARTENZA/DESTINAZIONE'],
            'FLIGHT DATE:'      => 'DATA DEL VOLO:',
            'Flight number'     => ['N. volo'],
            'Arrival'           => 'Arrivo',
            'Departure'         => 'Partenza',
            'Gate closes'       => ['Chiusura gate:'],
            'tableDelimiter'    => ['Sesso / Tipo di passeggero'],
            'PNR'               => ['PNR / N.'],
            'Seat'              => 'Posto',
        ],
    ];

    private $detectors = [
        'en' => ['BOARDING PASS', 'BOARDING CARD'],
        'hu' => ['BESZÁLLÓKÁRTYA'],
        'it' => ["CARTE D'IMBARCO"],
    ];

    private $pdfNamePattern = '.*pdf';
    private $providerCode = '';

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $this->assignProvider($parser->getCleanFrom(), $parser->getSubject());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            if (empty($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf)))) {
                continue;
            }

            // Detect Provider
            if (empty($this->providerCode) && !$this->assignProviderPdf($textPdf)) {
                continue;
            }

            // Detect Format
            if ($this->detectBody($textPdf) && $this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignProvider($parser->getCleanFrom(), $parser->getSubject());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf) || !$this->assignLang($textPdf)) {
                continue;
            }

            if (empty($this->providerCode)) {
                $this->assignProviderPdf($textPdf);
            }

            // TODO: collecting flight segments by PNR

            $pdfFileName = $this->getAttachmentName($parser, $pdf);
            $this->parsePdf($email, $textPdf, $pdfFileName);
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('BoardingPassPdf' . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        $this->mergeAllFlights($email);

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
        return ['flyone', 'wizz'];
    }

    private function assignProvider(?string $from, string $subject): bool
    {
        if (preg_match('/[.@]flyone\.eu$/i', $from) > 0 || strpos($subject, 'FLYONE') !== false) {
            $this->providerCode = 'flyone';

            return true;
        }

        if ($this->http->XPath->query('//*[contains(.,"www.wizztours.com") or contains(.,"@wizztours.com")]')->length > 0) {
            $this->providerCode = 'wizz';

            return true;
        }

        return false;
    }

    private function assignProviderPdf(string $text): bool
    {
        if (stripos($text, 'FLYONE NON-PRIORITY') !== false || stripos($text, 'www.flyone.eu') !== false) {
            $this->providerCode = 'flyone';

            return true;
        }

        if (stripos($text, 'WIZZ PRIORITY') !== false || stripos($text, 'WIZZ ELSŐBBSÉGI') !== false) {
            $this->providerCode = 'wizz';

            return true;
        }

        return false;
    }

    private function parsePdf(Email $email, $text, $fileName): void
    {
        $patterns = [
            'time'          => '\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?', // 4:19PM    |    2:00 p.m.
            'travellerName' => '[[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]]',
        ];

        $f = $email->add()->flight();

        $tableMainPos = [0];

        if (preg_match("/^(.{2,}[ ]{2}){$this->opt($this->t('tableDelimiter'))}/m", $text, $matches)) {
            $tableMainPos[] = mb_strlen($matches[1]);
        }
        $tableMain = $this->splitCols($text, $tableMainPos);

        if (count($tableMain) !== 2) {
            $this->logger->debug('Incorrect main table!');

            return;
        }

        $traveller = null;

        if (preg_match("/^{$this->opt($this->t('Name'))}$/m", $tableMain[1])) {
            if (preg_match("/^[ ]*(.+)\n{$this->opt($this->t('Name'))}\n[ ]*(.+)\s+^{$this->opt($this->t('passengerEnd'))}/m", $tableMain[1], $m)) {
                $pax = $m[1] . ' ' . $m[2];
                $traveller = preg_match("/^{$patterns['travellerName']}$/mu", $pax) ? preg_replace('/\s+/', ' ', $pax) : null;
            }
        } else {
            $traveller = preg_match("/^{$this->opt($this->t('Name'))}[ ]{2,}({$patterns['travellerName']})\s+^{$this->opt($this->t('passengerEnd'))}/mu", $tableMain[1], $m) ? preg_replace('/\s+/', ' ', $m[1]) : null;
        }
        $f->general()->traveller($traveller);

        $confirmation = preg_match("/^{$this->opt($this->t('PNR'))}[ ]{2,}([A-Z\d]{5,7})(?:[ ]*\/|$)/m", $tableMain[1], $m) ? $m[1] : null;
        $f->general()->confirmation($confirmation);

        $s = $f->addSegment();

        if (preg_match("/^{$this->opt($this->t('DEP / DEST'))}[ ]{2,}([A-Z]{3}) ?- ?([A-Z]{3})$/m", $tableMain[1], $m)) {
            $s->departure()->code($m[1]);
            $s->arrival()->code($m[2]);
        }

        if (preg_match("/^{$this->opt($this->t('Flight number'))}[ ]{2,}(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<flightNumber>\d+)$/m", $tableMain[1], $m)) {
            $s->airline()
                ->name($m['airline'])
                ->number($m['flightNumber'])
            ;
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('FLIGHT DATE:'))}.+[\s\S]+?[ ]{2}{$this->opt($this->t('Arrival'))}[: ]*(?:\s*^.+$){1,4}/m", $tableMain[0], $m)) {
            $tableMain[0] = $m[0];
        }

        if (preg_match_all("/^[ ]*{$this->opt($this->t('TERMINAL'))} ([A-Z\d][A-Z\d ]*)\b(?:[ ]{2}|$)/m", $tableMain[0], $terminalMatches)
            && count($terminalMatches[1]) === 2
        ) {
            $s->departure()->terminal($terminalMatches[1][0]);
            $s->arrival()->terminal($terminalMatches[1][1]);
        }

        $textDetailsSrc = preg_match("/\n([ ]*{$this->opt($this->t('Flight number'))}[: ]+{$this->opt($this->t('Gate closes'))}.*\n*(?:\n.*){1,2})/", $tableMain[0], $m) ? $m[1] : null;
        $tableDetailsPos = [0];

        if (preg_match("/^(.+? ){$this->opt($this->t('Gate closes'))}/", $textDetailsSrc, $matches)) {
            $tableDetailsPos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.+? ){$this->opt($this->t('Departure'))}/", $textDetailsSrc, $matches)) {
            $tableDetailsPos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.+? ){$this->opt($this->t('Seat'))}/", $textDetailsSrc, $matches)) {
            $tableDetailsPos[] = mb_strlen($matches[1]);
        }

        $tableDetails = array_map('trim', $this->splitCols($textDetailsSrc, $tableDetailsPos));
        $textDetails = implode("\n\n", $tableDetails);

        $tableArrivalPos = [0];

        if (preg_match("/^(.{6,}[ ]{2}){$this->opt($this->t('Arrival'))}[: ]*$/m", $tableMain[0], $matches)) {
            $tableArrivalPos[] = mb_strlen($matches[1]) - 1;
        }
        $tableArrival = $this->splitCols($tableMain[0], $tableArrivalPos);
        $textArrival = implode("\n", $tableArrival);

        $date = preg_match("/^[ ]*{$this->opt($this->t('FLIGHT DATE:'))}[ ]*(.{6,}?)(?:[ ]{2}|$)/m", $tableMain[0], $m) ? strtotime($this->normalizeDate($m[1])) : false;

        if ($date) {
            if (preg_match("/^[ ]*{$this->opt($this->t('Departure'))}[: ]*$\s+^[ ]*({$patterns['time']})/m", $textDetails, $m)
                || preg_match("/^[ ]*{$this->opt($this->t('Departure'))}[: ]*$\s+^[ ]*({$patterns['time']})/m", $textArrival, $m)
            ) {
                $s->departure()->date(strtotime($m[1], $date));
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Arrival'))}[: ]*$\s+^[ ]*({$patterns['time']})/m", $textArrival, $m)
                || preg_match("/^[ ]*{$this->opt($this->t('Arrival'))}[: ]*$\s+^[ ]*.+?[ ]+({$patterns['time']})/m", $text, $m)
            ) {
                $s->arrival()->date(strtotime($m[1], $date));
            }
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Seat'))}[: ]*$\s+^[ ]*(\d{1,5}[A-Z])$/m", $textDetails, $m)
            || preg_match("/^[ ]*{$this->opt($this->t('Seat'))}[: ]*$\s+^[ ]*(\d{1,5}[A-Z])$/m", $textArrival, $m)
            || preg_match("/^.*\bSeat[: ]*$\s+^.*\b(\d{1,5}[A-Z])$/m", $text, $m) // doesn't matter which lang
        ) {
            $s->extra()->seat($m[1], false, false, $traveller);
        }

        // Boarding Pass
        $bp = $email->createBoardingPass();
        $bp->setAttachmentName($fileName);
        $bp->setDepCode($s->getDepCode());
        $bp->setFlightNumber($s->getFlightNumber());
        $bp->setDepDate($s->getDepDate());

        if (!empty($f->getConfirmationNumbers()[0])) {
            $bp->setRecordLocator($f->getConfirmationNumbers()[0][0]);
        }

        if (!empty($f->getTravellers()[0])) {
            $bp->setTraveller($f->getTravellers()[0][0]);
        }
    }

    private function detectBody($text): bool
    {
        if (empty($text) || !isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if (strpos($text, $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Flight number']) || empty($phrases['Gate closes'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['Flight number']) !== false
                && $this->strposArray($text, $phrases['Gate closes']) !== false
            ) {
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

    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 8 / APR / 2019
            '/^(\d{1,2})[ ]*\/[ ]*([[:alpha:]]{3,})[ ]*\/[ ]*(\d{4})$/u',
        ];
        $out = [
            '$1 $2 $3',
        ];

        return preg_replace($in, $out, $text);
    }

    private function getAttachmentName(\PlancakeEmailParser $parser, $pdf): ?string
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $m)) {
            return $m[1];
        }

        return null;
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
