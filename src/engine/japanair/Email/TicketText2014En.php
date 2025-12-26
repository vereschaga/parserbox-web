<?php

namespace AwardWallet\Engine\japanair\Email;

class TicketText2014En extends \TAccountChecker
{
    public $mailFiles = "japanair/it-5239113.eml";
    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->result['Kind'] = 'T';

        $this->parseReservation($parser->getHTMLBody());
        $this->parseSegments($parser->getHTMLBody());

        return [
            'emailType'  => 'TicketText2014En',
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'confirmation@amadeus.com') !== false
                && isset($headers['subject']) && (
                stripos($headers['subject'], 'Your Electronic Ticket Receipt') !== false
                );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'Your e-ticket is stored in our Computer Reservations System.') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
//        return stripos($from, '@amadeus.com') !== false;
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT\n(.*)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * </p>
     * <pre>
     * findСutSection($plainText, 'LEFT', 'RIGHT')
     * findСutSection($plainText, 'LEFT', ['RIGHT', PHP_EOL])
     * </pre>.
     */
    public function findСutSection($input, $searchStart, $searchFinish = null)
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

    protected function parseReservation($text)
    {
        $resv = $this->htmlToText($this->findСutSection($text, 'WEBSITE:', ['Your e-ticket is stored in our']));

        if (preg_match('/PASSENGER NAME:\s*(.+?)\s*ISSUED BY:/s', $resv, $matches)) {
            $this->result['Passengers'][] = $matches[1];
        }

        if (preg_match('/FREQUENT FLYER NUMBER:\s*(\d+)/s', $resv, $matches)) {
            $this->result['AccountNumbers'][] = $matches[1];
        }

        if (preg_match('/TICKET NUMBER:\s*([\d+\s-]+)\s*PHONE/s', $resv, $matches)) {
            $this->result['TicketNumbers'][] = trim($matches[1]);
        }

        if (preg_match('/BOOKING REFERENCE:\s*([A-Z\d]+)\s*ISSUE DATE:\s*(\d+\w+\d+)/s', $resv, $matches)) {
            $this->result['RecordLocator'] = trim($matches[1]);
            $this->result['ReservationDate'] = strtotime($matches[2]);
        }

        if (preg_match('/TOTAL:\s*([A-Z]{3})\s*(\d+)/', $this->htmlToText($this->findСutSection($text, 'RECEIPT DETAILS', ['NOTICE:'])), $matches)) {
            $this->result['Currency'] = $matches[1];
            $this->result['TotalCharge'] = (float) $matches[2];
        }
    }

    protected function parseSegments($text)
    {
        $text = $this->htmlToText($this->findСutSection($text, 'security and check-in personnel.', ['RECEIPT DETAILS']));

        foreach ($this->splitter('/(.+?\s*\([A-Z]{3}\)\s+.+?\s*\([A-Z]{3}\)\s+[A-Z]{2}\s*\d+)/', $text) as $value) {
            $this->result['TripSegments'][] = $this->parseSegment($value);
        }
    }

    protected function parseSegment($text)
    {
        $segment = [];
        $reg = '(.+?)\s*\(([A-Z]{3})\)\s+(.+?)\s*\(([A-Z]{3})\)\s+([A-Z]{2})\s*(\d+)\s+';
        $reg .= '([A-Z])\s+(\d+\w+)\s+(\d+:\d+)\s+(\d+\w+)\s+(\d+:\d+)\s+\w+\s+(\d+[A-Z])';

        if (preg_match("/{$reg}/", $text, $matches)) {
            $segment['DepName'] = $matches[1];
            $segment['DepCode'] = $matches[2];
            $segment['ArrName'] = $matches[3];
            $segment['ArrCode'] = $matches[4];
            $segment['AirlineName'] = $matches[5];
            $segment['FlightNumber'] = $matches[6];
            $segment['BookingClass'] = $matches[7];
            $segment += $this->increaseDate($this->result['ReservationDate'], $matches[8] . ', ' . $matches[9], $matches[10] . ', ' . $matches[11]);
            $segment['Seats'] = $matches[12];
        }

        return $segment;
    }

    //========================================
    // Auxiliary methods
    //========================================

    protected function htmlToText($string)
    {
        return preg_replace('/<[^>]+>/', "\n", $string);
    }

    protected function increaseDate($dateLetter, $depTime, $arrTime)
    {
        $depDate = strtotime($depTime, $dateLetter);

        if ($dateLetter > $depDate) {
            $depDate = strtotime('+1 year', $depDate);
        }

        return [
            'DepDate' => $depDate,
            'ArrDate' => strtotime($arrTime, $depDate),
        ];
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
