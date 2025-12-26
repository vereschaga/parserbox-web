<?php

namespace AwardWallet\Engine\hoggrob\Email;

class ReminderText2014En extends \TAccountChecker
{
    public $mailFiles = "hoggrob/it-5738327.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $text = empty($parser->getPlainBody()) ? $parser->getHTMLBody() : $parser->getPlainBody();
        $text = $this->htmlToText($this->findCutSection($text, null, 'Trip must be approved in Community :'));

        $this->result['Kind'] = 'T';
        $this->result['RecordLocator'] = $this->match('/Reservation number\s+:\s+([A-Z\d]{5,6})\n/', $text);
        $this->result['Passengers'][] = $this->match('/Trip Plan for\s+:\s+([\w\s]+)\n/', $text);
        $total = $this->match('/Total fare for flight reservations\s+:\s+([\d.]+)\s*(Nigerian Naira)/', $text, true);

        if (!empty($total)) {
            $this->result['TotalCharge'] = (float) $total[0];
            $this->result['Currency'] = str_replace('Nigerian Naira', 'NGN', $total[1]);
        }

        $this->parseSegments($text);

        return [
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@ng.hrgworldwide.com') !== false
                && isset($headers['subject']) && (
                stripos($headers['subject'], 'Reminder: Approval needed for reservation :') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = empty($parser->getPlainBody()) ? $parser->getHTMLBody() : $parser->getPlainBody();

        return stripos($text, '@ng.hrgworldwide.com') !== false
                && stripos($text, 'Total fare for flight reservations :') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@ng.hrgworldwide.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
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
        foreach ($this->splitter('/(Status\s+:\s+\w+\s+Flight|Flight\s+:\s+[\w\s]+Aircraft)/', $text) as $text) {
            $i = [];

            $flight = $this->match('/Flight\s+:\s+([\w\s]+)\s+([A-Z]{2}\s*(\d+))/', $text, true);

            if (!empty($flight)) {
                $i['Operator'] = $flight[0];
                $i['AirlineName'] = $flight[1];
                $i['FlightNumber'] = $flight[2];
            }

            $i['Aircraft'] = $this->match('/Aircraft\s+:\s+([\w\s-]+)From/', $text);

            $from = $this->match('/From\s+:\s+(.+?)\s+\(([A-Z]{3})\)/', $text, true);

            if (!empty($from)) {
                $i['DepName'] = $from[0];
                $i['DepCode'] = $from[1];
            }

            $i['DepDate'] = strtotime($this->match('/Departing\s+:\s+(.+?)To/s', $text));

            $to = $this->match('/To\s+:\s+(.+?)\s+\(([A-Z]{3})\)/', $text, true);

            if (!empty($to)) {
                $i['ArrName'] = $to[0];
                $i['ArrCode'] = $to[1];
            }

            $i['ArrDate'] = strtotime($this->match('/Arriving\s+:\s+(.+?)(?:Number|\n)/s', $text));

            $i['Cabin'] = $this->match('/Cabin\s+:\s+(\w+)\n/', $text);

            $this->result['TripSegments'][] = $i;
        }
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

    protected function htmlToText($string, $view = false)
    {
        $text = str_replace(' ', ' ', preg_replace('/<[^>]+>/', "\n", html_entity_decode($string)));

        if ($view) {
            return preg_replace('/\n{2,}/', "\n", $text);
        }

        return $text;
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
