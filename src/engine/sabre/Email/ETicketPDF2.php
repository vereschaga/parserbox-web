<?php

namespace AwardWallet\Engine\sabre\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicketPDF2 extends \TAccountChecker
{
    public $mailFiles = "sabre/it-8023299.eml";

    private $year = '';

    private $langDetectors = [
        'en' => ['FLIGHT/CLASS', 'FLIGHT / CLASS'],
    ];

    private static $providers = [
        'sabre' => [
            'Sabre',
        ],
        'jtb' => [
            'JTB Business Travel',
            'jtbap.com',
        ],
    ];
    private $code;
    private $lang = '';

    private static $dict = [
        'en' => [],
    ];

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;°\"'’<>«»?~`!@\#№$%^&*\[\]=\(\)\-–‑{}£¥₣₤₧€\$\|]#imsu", ' ',
                $textPdf);

            if (stripos($textPdf, 'Sabre Asia') === false && stripos($textPdf, '"Sabre"') === false && strpos($textPdf,
                    'Sabre shall not be') === false && strpos($textPdf,
                    ' Please verify flight times prior to departure') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;°\"'’<>«»?~`!@\#№$%^&*\[\]=\(\)\-–‑{}£¥₣₤₧€\$\|]#imsu", ' ',
                $textPdf);
            $this->assignLang($textPdf);
            $this->year = getdate(strtotime($parser->getHeader('date')))['year'];
            $this->parsePdf($textPdf, $email);
            $this->assignProvider($textPdf);
        }

        if (!empty($this->code)) {
            $email->setProviderCode($this->code);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function parsePdf($textPdf, Email $email)
    {
        $text = $this->sliceText($textPdf, 'PASSENGER ITINERARY', 'POSITIVE IDENTIFICATION');

        if (!$text) {
            return false;
        }

        $email->obtainTravelAgency();

        $f = $email->add()->flight();

        $f->general()->noConfirmation();

        if (preg_match('/FREQUENT FLYER\s?:\s?([A-Z\d]+?)\s{2,}/m', $text, $matches)) {
            $f->ota()->account($matches[1], false);
        }

        if (preg_match('/RESERVATION CODE[ ]*:[ ]*([A-Z\d]{5,})$/m', $text, $matches)) {
            $f->ota()->confirmation($matches[1]);
        }

        if (preg_match('/^[ ]*ISSUED DATE[ ]*:[ ]*(\d{1,2}[ ]*[^,.\d ]{3,}[ ]*\d{2,4})/m', $text, $matches)) {
            $f->general()->date(strtotime($matches[1]));
            $this->year = getdate($f->getReservationDate())['year'];
        }

        if (preg_match('/TOTAL\s*:\s*([A-Z]{3})\s?(\d+[\d.,]+)/m', $text, $matches)) {
            $f->price()->total($matches[2])->currency($matches[1]);
        }

        if (preg_match('/FARE\s*:\s*[A-Z]{3}\s?(\d+[\d.,]+)/m', $text, $matches)) {
            $f->price()->cost($matches[1]);
        }

        if (preg_match('/TAXES\/FEES\/CARRIER-IMPOSED CHARGES \(YR\/YQ\) : [A-Z]{3}\s(.+)$/m', $text, $matches)) {
            if (preg_match_all("/(\d+[\d.]+)([A-Z\d]{2})[,]?/", $matches[1], $m)) {
                foreach ($m[1] as $key => $fess) {
                    $f->price()->fee($m[2][$key], $fess);
                }
            }
        }

        $headText = $this->sliceText($text, 'PREPARED FOR', 'ISSUED DATE');

        $headRows = explode("\n", $headText);

        if (preg_match('/^[ ]*(\w+\/\w+[\w ]+?)(?:[ ]{2,}|$)/m', $text, $matches)) {
            $f->general()->traveller($matches[1]);
        }

        foreach ($headRows as $key => $headRow) {
            if (strpos($headRow, 'TICKET NUMBER') !== false) {
                if (preg_match('/^[ ]*([-A-Z\d\/ ]+\d{5}[-\d\/ ]+)/m', $headRows[$key + 1],
                        $matches) || preg_match('/^[ ]*([-A-Z\d\/ ]+\d{5}[-\d\/ ]+)/m', $headRows[$key + 2],
                        $matches)) {
                    $f->issued()->ticket($matches[1], false);
                }

                break;
            }
        }

        // Segments
        $segments = $this->splitText($text, '/^[ ]*[^\d ]{2,} (\d{1,2} [^\d ]{3,})/m', true);

        foreach ($segments as $segText) {
            if (stripos($segText, 'FLIGHT/CLASS') === false) {
                continue;
            }

            $patterns = [
                'timeAirport'  => '/^(?<time>\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?)[ ]{2,38}(?<airport>[^ ].+)/',
                'timeAirport2' => '/^(?<time>\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?)\n\s*(?<airport>[^ ].+)/',
                'timeAirport3' => '/^(?<time>\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?)[ ]*(?:\((?<day>\d{1,2}) ?(?<month>[^\W\d]+)\))[ ]+(?<airport>[^ ].+)/',
                'timeAirport4' => '/(?<time>\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?)[\n]?\s*(?<airport>.+?)\s{2}/',
                'terminal'     => '/[ ]{2,}(TERMINAL [^ ].*)/',
            ];
            $s = $f->addSegment();

            if (preg_match('/^(\d{1,2} [^\d ]{3,})/', $segText, $matches)) {
                $dayMonth = $matches[1];
            }

            // Airline
            if (preg_match('/AIRLINE RES CODE[ ]*:[ ]*([A-Z\d]{5,})$/m', $segText, $matches)) {
                $s->airline()->confirmation($matches[1]);
            }

            if (preg_match('/^[ ]*([A-Z\d]{2})\s*(\d+)(.*?)[ ]{2,}/m', $segText, $matches)) {
                // MI 134/ECONOMY/W
                $s->airline()
                    ->name($matches[1])
                    ->number($matches[2]);

                if (!empty($matches[3])) {
                    $classParts = explode('/', $matches[3]);

                    if (!empty($classParts[1])) {
                        $s->extra()->cabin($classParts[1]);
                    }

                    if (!empty($classParts[2])) {
                        $s->extra()->bookingCode($classParts[2]);
                    }
                }
            }

            // Extra
            if (preg_match('/\/[ ]*(\d{1,3}HR\d{1,2}MIN)/', $segText, $matches)) {
                // 2HR15MIN
                $s->extra()->duration($matches[1]);
            }

            if (preg_match('/SEAT ?: ?(\d{1,2}[A-Z])\b/', $segText, $matches)) {
                // SEAT :48H
                $s->extra()->seat($matches[1]);
            }

            if (preg_match('/\/[ ]*(\d+MILES)/', $segText, $matches)) {
                // 911MILES
                $s->extra()->miles($matches[1]);
            }

            if (preg_match('/\/[ ]*\d+MILES[ ]*\/[ ]*\n.{70,}[ ]{2,}(\S+(?: \S+){0,5}) \/ (\S+(?: \S+){0,5})\n/',
                $segText, $matches)) {
                $s->extra()
                    ->meal($matches[1])
                    ->aircraft($matches[2]);
            }

            unset($newDepDayMonth, $newArrDayMonth);
            $segParts = $this->splitText($segText,
                '/[ ]{2,}(\d{1,2}:\d{2}(?:[ ]*[ap]m)?( \(\d{1,2}[^\W\d]+\))?(?:[ ]{2,}| \(\d{1,2}[^\W\d]+\)|\n|))/i',
                true);

            // Daparture
            // 11:10        SINGAPORE CHANGI                                       MEALS / AIRBUS 320
            if (preg_match($patterns['timeAirport'], $segParts[0], $matches)
                || preg_match($patterns['timeAirport2'], $segParts[0], $matches)
                || preg_match($patterns['timeAirport3'], $segParts[0], $matches)
                || preg_match($patterns['timeAirport4'], $segParts[0], $matches)) {
                $timeDep = $matches['time'];
                $airportDepParts = $this->splitText($matches['airport'], '/[ ]{2,}/');
                $s->departure()
                    ->name($airportDepParts[0]);

                if (!empty($matches['day']) && !empty($matches['month'])) {
                    $newDepDayMonth = $matches['day'] . ' ' . $matches['month'];
                }
            }
            $s->departure()->noCode();

            if (preg_match($patterns['terminal'], $segParts[0], $matches)) {
                $terminalDepParts = $this->splitText($matches[1], '/[ ]{2,}/');
                $s->departure()
                    ->terminal(preg_replace("#\s*terminal\s*#i", '', $terminalDepParts[0]));
            }

            // Arrival
            if (preg_match($patterns['timeAirport'], $segParts[1], $matches)
                || preg_match($patterns['timeAirport2'], $segParts[1], $matches)
                || preg_match($patterns['timeAirport3'], $segParts[1], $matches)
                || preg_match($patterns['timeAirport4'], $segParts[1], $matches)) {
                $timeArr = $matches['time'];
                $airportArrParts = $this->splitText($matches['airport'], '/[ ]{2,}/');
                $s->arrival()
                    ->name($airportArrParts[0]);

                if (!empty($matches['day']) && !empty($matches['month'])) {
                    $newArrDayMonth = $matches['day'] . ' ' . $matches['month'];
                }
            }
            $s->arrival()->noCode();

            if (preg_match($patterns['terminal'], $segParts[1], $matches)) {
                $terminalArrParts = $this->splitText($matches[1], '/[ ]{2,}/');
                $s->arrival()
                    ->terminal(preg_replace("#\s*terminal\s*#i", '', $terminalArrParts[0]));
            }

            if ($dayMonth && $timeDep && $timeArr) {
                $date = $dayMonth . ' ' . $this->year;

                $depDate = (!empty($newDepDayMonth)) ? $newDepDayMonth : $dayMonth;
                $depDate .= ' ' . $this->year . ', ' . $timeDep;
                $s->departure()->date(strtotime($depDate));

                $arrDate = (!empty($newArrDayMonth)) ? $newArrDayMonth : $dayMonth;
                $arrDate .= ' ' . $this->year . ', ' . $timeArr;
                $s->arrival()->date(strtotime($arrDate));
            }
        }

        return $email;
    }

    private function assignProvider($text)
    {
        foreach (self::$providers as $code => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->code = $code;
                }
            }
        }
    }

    private function assignLang($text)
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function sliceText($textSource = '', $textStart = '', $textEnd = '')
    {
        if (empty($textSource) || empty($textStart)) {
            return false;
        }
        $start = strpos($textSource, $textStart);

        if (empty($textEnd)) {
            return substr($textSource, $start);
        }
        $end = strpos($textSource, $textEnd, $start);

        if ($start === false || $end === false) {
            return false;
        }

        return substr($textSource, $start, $end - $start);
    }

    private function splitText($textSource = '', $pattern = '', $saveDelimiter = false)
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
        }

        return $result;
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }
}
