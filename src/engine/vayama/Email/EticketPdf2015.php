<?php

namespace AwardWallet\Engine\vayama\Email;

// similar budgetair/EticketPdf2015
class EticketPdf2015 extends \TAccountChecker
{
    public $mailFiles = "vayama/it-5958678.eml";

    public $lang = '';
    public $body = [
        'en' => ['We would advise you to print this e-ticket'],
        //'fr' => ['Nous vous recommandons d\'imprimer ce courriel ainsi'],
        //'nl' => ['Wij adviseren je de e-mail uit'],
        //'es' => ['Le recomendamos que imprima este correo electr'],
    ];
    public static $dict = [
        'en' => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && isset($headers['subject']) && stripos($headers['from'], '@vayama') !== false
                && $this->stripos($headers['subject'], ['E-Ticket for ']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'vayama.') !== false && $this->detect($parser->getHTMLBody(), $this->body) != false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@vayama') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!($this->lang = $this->detect($parser->getHTMLBody(), $this->body))) {
            $this->http->Log('file not recognized, check detectEmailByHeaders or detectEmailByBody method');

            return;
        }

        $pdf = $parser->searchAttachmentByName('E-?ticket.*?.pdf');

        if (empty($pdf)) {
            $this->http->Log('No Pdf file.');

            return false;
        }

        $pdfText = str_replace(chr(194) . chr(160), ' ', \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf))));
        $text = $this->findCutSection($pdfText, null, $this->t('local and subject to change'));

        $it['Kind'] = 'T';
        $it['Passengers'][] = trim(str_replace(':', '', $this->findCutSection($pdfText, $this->t('Passenger name'), $this->t('Reservation number'))));

        if (preg_match("#{$this->t('Date issued')}:?\s*(\d+\/\d+\/\d+)#", $text, $m)) {
            $it['ReservationDate'] = strtotime($this->normalizeDate(trim($m[1])));
        }

        $it += $this->parseSegments($this->findCutSection($text, $this->t('Flight details'), null));

        $it = $this->groupBySegments([$it]);

        return [
            'parsedData' => ['Itineraries' => $it],
            'emailType'  => "reservation",
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    //========================================
    // Auxiliary methods
    //========================================

    public function normalizeDate($str)
    {
        $str = str_replace('/', '-', $str);

        return $str;
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

    protected function parseSegments($pdfText)
    {
        $i = [];

        foreach (preg_split("/{$this->t('Flight details')}\s+/", $pdfText, -1, PREG_SPLIT_NO_EMPTY) as $value) {
            if (stripos($value, $this->t('Arrival')) !== false && mb_strlen($value) > 300) {
                $i['TripSegments'][] = $this->parseSegment($value);
            }
        }

        return $i;
    }

    protected function parseSegment($text)
    {
        $i = [];

        if (preg_match("/(?:{$this->t('booking code')}):?\s+([A-Z\d]{5,6})/", $text, $matches)) {
            $i['RecordLocator'] = $matches[1];
        }

        if (preg_match("/{$this->t('Aircraft')}\s+(.*?)\s{2,}/", $text, $matches)) {
            $i['Aircraft'] = $matches[1];
        }

        if (preg_match("/{$this->t('Flight number')}:?.*?([A-Z\d+]{2})\s*(\d{2,4})/", $text, $matches)) {
            $i['FlightNumber'] = $matches[2];
            $i['AirlineName'] = $matches[1];
        }

        if (preg_match("#(\d+\/\d+\/\d{4}).+?\(([A-Z]{3})\).+?(\d+\/\d+\/\d{4}).+?\(([A-Z]{3})\)#s", $text, $matches)) {
            $i['DepCode'] = $matches[2];

            if (preg_match("/{$this->t('Departure')}:?\s+(\d+:\d+)/", $text, $m)) {
                $i['DepDate'] = strtotime($this->normalizeDate("{$matches[1]} {$m[1]}"));
            }

            $i['ArrCode'] = $matches[4];

            if (preg_match("/{$this->t('Arrival')}:?\s+(\d+:\d+)/", $text, $m)) {
                $i['ArrDate'] = strtotime($this->normalizeDate("{$matches[3]} {$m[1]}"));
            }
        }

        if (preg_match('/Duration(.*)/', $text, $matches)) {
            $i['Duration'] = trim($matches[1]);
        }

        if (preg_match("/{$this->t('Class')}:?\s+([A-Z])/", $text, $matches)) {
            $i['BookingClass'] = $matches[1];
        }

        return $i;
    }

    protected function stripos($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Are case sensitive. Example:
     * <pre>
     * var $reSubject = [
     * 'en' => ['Reservation Modify'],
     * ];
     * </pre>.
     *
     * @param type $haystack
     * @param type $arrayNeedle
     *
     * @return type
     */
    protected function detect($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $lang => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $lang;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $lang;
                }
            }
        }
    }

    /**
     * TODO: Beta!
     *
     * @version v1.2
     *
     * @param type $reservations
     *
     * @return array
     */
    protected function groupBySegments($reservations)
    {
        $newReservations = [];

        foreach ($reservations as $reservation) {
            $newSegments = [];

            foreach ($reservation['TripSegments'] as $segment) {
                if (empty($segment['RecordLocator']) && isset($reservation['TripNumber'])) {
                    // when there is no locator in the segment
                    $newSegments[$reservation['TripNumber']][] = $segment;
                } elseif (isset($segment['RecordLocator'])) {
                    $r = $segment['RecordLocator'];
                    unset($segment['RecordLocator']);
                    $newSegments[$r][] = $segment;
                }
            }

            foreach ($newSegments as $key => $segment) {
                $reservation['RecordLocator'] = $key;
                $reservation['TripSegments'] = $segment;
                $newReservations[] = $reservation;
            }
        }

        return $newReservations;
    }

    private function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }
}
