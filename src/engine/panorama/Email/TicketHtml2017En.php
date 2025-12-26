<?php

namespace AwardWallet\Engine\panorama\Email;

// Such a parser in PDF format "TicketPdf2015"
class TicketHtml2017En extends \TAccountChecker
{
    public $mailFiles = "panorama/it-5537033.eml, panorama/it-6247783.eml, panorama/it-6381083.eml, panorama/it-6485969.eml";

    public $lang = "en";

    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!$this->detectEmailByBody($parser)) {
            return false;
        }

        $this->result['Kind'] = 'T';

        if (!$this->result['RecordLocator'] = $this->http->FindSingleNode('//text()[contains(., "PNR LOCATOR")]/ancestor::td[1]/following-sibling::td[1]', null, false, '/[A-Z\d]{5,6}/')) {
            $this->result['RecordLocator'] = TRIP_CODE_UNKNOWN;
        }
        $this->result['ReservationDate'] = strtotime($this->http->FindSingleNode('//text()[contains(., "SELLING DATE")]/ancestor::td[1]/following-sibling::td[1]'));
        $this->result['Passengers'] = $this->http->FindNodes('//text()[contains(., "PASSENGER")]/ancestor::td[1]/following-sibling::td[1]');
        $this->result['AccountNumbers'] = $this->http->FindNodes('//text()[contains(., "FREQUENT FLYER CARD")]/ancestor::td[1]/following-sibling::td[1]');
        $this->result['TicketNumbers'] = $this->http->FindNodes('//text()[contains(., "TICKET NUMBER")]/ancestor::td[1]/following-sibling::td[1]');

        $this->parseTotal();
        $this->parseSegments($this->htmlToText($this->findCutSection($parser->getHTMLBody(), 'FLIGHT DETAILS', ['CALCULATION'])));

        return [
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'link@bmp.viaamadeus.com') !== false
                && isset($headers['subject']) && (
                stripos($headers['subject'], 'This is your e-Ticket confirmation - ') !== false
                );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return (
            strpos($parser->getHTMLBody(), 'Ukraine International Airlines') !== false
            || strpos($parser->getHTMLBody(), 'link@bmp.viaamadeus.com') !== false
        ) && (
            strpos($parser->getHTMLBody(), 'PASSENGER ITINERARY RECEIPT') !== false
            || strpos($parser->getHTMLBody(), 'FLIGHT DETAILS') !== false
        );
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'link@bmp.viaamadeus.com') !== false;
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

    protected function parseSegments($text)
    {
        foreach ($this->splitter('/(\b[A-Z]{3} - [A-Z]{3}\b)/', $text) as $value) {
            $segment = [];

            // KBP - KUT РЕЙСFLIGHT PS 505 ПН, 16-СІЧ-2017
            // echo $value;die();
            if (preg_match('/([A-Z]{3})\s+-\s+([A-Z]{3})\s+(?<orig>.*)FLIGHT\s+([A-Z\d]{2})\s*(\d+)\s+([^\d\s]+, \d+-[^\d\s]+-\d+|[^\d\s]+\s+\d+ \w+ \d+|[^\d\s]+\s+\d+\s+[^\d\s]+\s+\d{4})/u', $value, $matches)) {
                $this->detectLang($matches['orig']);
                $segment['DepCode'] = $matches[1];
                $segment['ArrCode'] = $matches[2];
                $segment['AirlineName'] = $matches[4];
                $segment['FlightNumber'] = $matches[5];
                $date = $this->normalizeDate($matches[6]);
            }

            // DEPARTURE Ukraine, Kiev, Borispol (KBP) 20:35
            if (isset($date) && preg_match_all('/(?:DEPARTURE|ARRIVAL)\s+(.+?)\s*(\d+:\d+)/us', $value, $matches)) {
                $segment['DepName'] = $this->normalizeText($matches[1][0]);
                $segment['ArrName'] = $this->normalizeText($matches[1][1]);
                $segment['DepDate'] = strtotime($date . ', ' . $matches[2][0]);
                $segment['ArrDate'] = strtotime($date . ', ' . $matches[2][1]);
            }

            if (preg_match('/DURATION\s+(\d+-\d+)/', $value, $matches)) {
                $segment['Duration'] = $matches[1];
            }

            if (preg_match('/CLASS\s+([A-Z])/', $value, $matches)) {
                $segment['BookingClass'] = $matches[1];
            }

            if (preg_match('/MILEAGE\s+(\d+)/', $value, $matches)) {
                $segment['TraveledMiles'] = $matches[1];
            }

            $this->result['TripSegments'][] = $segment;
        }
    }

    protected function parseTotal()
    {
        foreach ($this->http->XPath->query('//text()[contains(., "EQUIVALENT")]/ancestor::tr[1]') as $root) {
            $posTotal = $this->http->XPath->query('.//text()[contains(., "TOTAL")]/ancestor::td[1]/preceding-sibling::td', $root);
            $total = $this->http->FindSingleNode("following-sibling::tr[1]/td[" . ($posTotal->length + 1) . "]", $root);

            if (preg_match('/([A-Z]{3})\s*([\d.,]+)/', $total, $matches)) {
                $this->result['Currency'] = $matches[1];
                $this->result['TotalCharge'] = (float) $matches[2];
            }

            $posFare = $this->http->XPath->query('.//text()[contains(., "FARE")]/ancestor::td[1]/preceding-sibling::td', $root);
            $fare = $this->http->FindSingleNode('following-sibling::tr[1]/td[' . ($posFare->length + 1) . ']', $root);

            if (preg_match('/[A-Z]{3}\s*([\d.,]+)/', $fare, $matches)) {
                $this->result['BaseFare'] = (float) $matches[1];
            }
        }
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

    protected function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    protected function htmlToText($string, $view = false)
    {
        $text = str_replace(' ', ' ', preg_replace('/<[^>]+>/', "\n", html_entity_decode($string)));

        if ($view) {
            return preg_replace(['/\s{2,}/'], ["\n"], $text);
        }

        return $text;
    }

    //========================================
    // Auxiliary methods
    //========================================

    private function normalizeDate($str)
    {
        $in = [
            "#^[^\d\s]+,\s+(\d+)-([^\d\s]+)-(\d{4})$#",
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#",
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function detectLang($str)
    {
        $d = ['de'=>'FLUG', 'uk'=>'РЕЙС'];

        foreach ($d as $lang=>$re) {
            if (strpos($str, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }
    }
}
