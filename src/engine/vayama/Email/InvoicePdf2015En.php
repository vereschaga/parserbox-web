<?php

namespace AwardWallet\Engine\vayama\Email;

class InvoicePdf2015En extends \TAccountChecker
{
    public $mailFiles = "vayama/it-5958764.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('\d+_\d+\.pdf');

        if (empty($pdf)) {
            $this->http->Log('Pdf is not found or is empty!');

            return false;
        }

        $text = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));
        $its[] = $this->parseAir($this->findCutSection($text, null, ['Additional information']));

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "reservation",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && isset($headers['subject'])
                && stripos($headers['from'], '@vayama') !== false && stripos($headers['subject'], 'Invoice ') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'vayama.') !== false && stripos($parser->getHTMLBody(), 'If passenger names (for flights)') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@vayama') !== false;
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
        $it['RecordLocator'] = $this->match('/Reservation number\s*:\s*([A-Z\d]{5,6})\b/', $text);
        $it['ReservationDate'] = strtotime(str_replace('/', '-', $this->match('#Invoice date\s*:\s*(\d+/\d+/\d+)\b#', $text)), false);

        $passengers = $this->findCutSection($text, 'Total', ['Flight ticket']);

        if (preg_match_all('/([[:alpha:]\s.]+)\s{2,}\d+.+?\n/', $passengers, $matches)) {
            $it['Passengers'] = array_map('trim', $matches[1]);
        }

        $total = $this->match('/Total\s+(.)\s+([\d.]+)/u', $text, true);

        if (!empty($total)) {
            $it['TotalCharge'] = (float) $total[1];
            $it['Currency'] = preg_replace(['/€/', '/^\$$/'], ['EUR', 'USD'], $total[0]);
        }

        $it['TripSegments'] = $this->parseSegments($this->findCutSection($text, 'Airline/flight number', ['Flight times and flight']));

        return $it;
    }

    protected function parseSegments($text)
    {
        $it = [];

        foreach ($this->splitter('/(Departure)/', $text) as $text) {
            $pattern = 'Departure\s+(\d+/\d+/\d+)\s+([\w\s.-]+)\s+(\d+:\d+)\s+([\w\s.-]+)\n';
            $pattern .= 'Arrival\s+(\d+/\d+/\d+)\s+([\w\s.]+)\s+(\d+:\d+)\s+([A-Z\d]{2})\s*(\d+)';

            if (preg_match("#{$pattern}#", $text, $matches)) {
                $i['DepDate'] = strtotime(str_replace('/', '-', $matches[1]) . ", {$matches[3]}", false);
                $i['DepName'] = trim($matches[2]);
                $i['Operator'] = $matches[4];
                $i['ArrDate'] = strtotime(str_replace('/', '-', $matches[5]) . ", {$matches[7]}", false);
                $i['ArrName'] = trim($matches[6]);
                $i['AirlineName'] = $matches[8];
                $i['FlightNumber'] = $matches[9];
                $i['DepCode'] = $i['ArrCode'] = TRIP_CODE_UNKNOWN;
                $it[] = $i;
            }
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
}
