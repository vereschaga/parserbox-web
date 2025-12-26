<?php

namespace AwardWallet\Engine\mango\Email;

class BookingPdf2015En extends \TAccountChecker
{
    public $mailFiles = "mango/it-2320764.eml, mango/it-2510990.eml, mango/it-2510992.eml, mango/it-2510993.eml, mango/it-2510994.eml, mango/it-2511001.eml, mango/it-6070389.eml";

    private $pdfNamePattern = '(?:Confirmation_.+?|.*)\.pdf';
    private $reBody = ['Booking details', 'Flight Details', 'Passenger Details', 'Payment Details'];

    private $patterns = [
        'travellerName' => '[A-Z][-.\'A-Z ]*[A-Z]', // MR. HAO-LI HUANG
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (empty($pdf)) {
            $this->logger->debug('Pdf is not found or is empty!');

            return false;
        }

        $text = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));
        $tt = $this->findCutSection($text, null, 'Agent Details');

        if (empty($tt)) {
            $tt = $this->findCutSection($text, null, 'Important notices');
        }
        $its[] = $this->parseAir($tt);
        $name = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($name),
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Booking confirmation from Mango') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (strpos($text, 'flymango') !== false) {
                if (
                    stripos($text, $this->reBody[0]) !== false
                    && stripos($text, $this->reBody[1]) !== false
                    && stripos($text, $this->reBody[2]) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'flymango.') !== false;
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT\n(.*?)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * </p>
     * <pre>
     * findСutSection($plainText, 'LEFT', 'RIGHT')
     * findСutSection($plainText, 'LEFT', ['RIGHT', PHP_EOL])
     * </pre>.
     */
    public function findCutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;

        if (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($input, $searchFinish, true);
        } else {
            $inputResult = $input;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    protected function parseAir($text)
    {
        $it = ['Kind' => 'T'];

        $text2 = $this->findCutSection($text, $this->reBody[0], $this->reBody[1]);
        $it['RecordLocator'] = $this->match('/Reference Number:\s*([A-Z\d]{5,6})/', $text2);
        $it['TripNumber'] = $this->match('/Mango VAT Number\s+(\w+)/', $text2);

        $total = $this->match('/TOTAL\s+([\w\s.,]+)/', $text2);

        if (!empty($total)) {
            $it['TotalCharge'] = (float) preg_replace('/[^\d.]+/', '', $total);
            $it['Currency'] = preg_replace(['/[\d.,\s]+/', '/R\$?/', '/€/', '/^\$$/'], ['', 'BRL', 'EUR', 'USD'], $total);
        }

        $it['BaseFare'] = preg_replace('/[^\d.]+/', '', $this->match('/Airfare:\s+([\w\s.,]+)/', $text2));
        $it['Tax'] = preg_replace('/[^\d.]+/', '', $this->match('/Total Taxes\s+([\w\s.,]+)/', $text2));

        $passengerDetails = trim($this->findCutSection($text, $this->reBody[2], ['Important notices', $this->reBody[3]]));
        $passengersTable = $this->splitCols($passengerDetails);
        $passengersText = implode("\n\n", $passengersTable);

        if (preg_match_all('/[A-Z]{3,}\n(' . $this->patterns['travellerName'] . ')(?:\n|$)/', $passengersText, $matches)) {
            $it['Passengers'] = array_map('trim', $matches[1]);
        }

        $it['TripSegments'] = $this->parseSegments($this->findCutSection($text, $this->reBody[1], $this->reBody[2]));

        return $it;
    }

    protected function parseSegments($text)
    {
        $it = [];

        foreach ($this->splitter('/(\b[,.[:alpha:] ]+ - [,.[:alpha:] ]+\n)/u', $text) as $text) {
            $i = [];

            if (preg_match('/\b([,.[:alpha:] ]+) - ([,.[:alpha:] ]+)\n/u', $text, $matches)) {
                $i['DepName'] = $this->normalizeText($matches[1]);
                $i['ArrName'] = $this->normalizeText($matches[2]);
                $i['ArrCode'] = $i['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            if (preg_match('/\w+, (\d+ \w+ \d{4})\s+([\d:]+(?:\s*[ap.]m)?)\s+([\d:]+(?:\s*[ap.]m)?)/', $text, $matches)) {
                $i['DepDate'] = strtotime("{$matches[1]}, {$matches[2]}", false);
                $i['ArrDate'] = strtotime("{$matches[1]}, {$matches[3]}", false);

                if ($i['DepDate'] > $i['ArrDate']) {
                    $i['ArrDate'] = strtotime('+1 day', $i['ArrDate']);
                }
            }

            if (preg_match('/\bFlight +([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\b/', $text, $matches)) {
                $i['AirlineName'] = $matches[1];
                $i['FlightNumber'] = $matches[2];
            }
            $i['Stops'] = $this->match("#Non[\s-]*stop#i", $text) ? 0 : null;
            $it[] = $i;
        }

        return $it;
    }

    //========================================
    // Auxiliary methods
    //========================================

    protected function match($pattern, $text, $allMatches = false)
    {
        if (preg_match($pattern, $text, $matches)) {
            if ($allMatches) {
                array_shift($matches);

                return array_map([$this, 'normalizeText'], $matches);
            } else {
                return $this->normalizeText(count($matches) > 1 ? $matches[1] : $matches[0]);
            }
        }
    }

    protected function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
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
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }
}
