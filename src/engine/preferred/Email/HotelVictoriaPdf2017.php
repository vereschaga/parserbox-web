<?php

// bcdtravel

namespace AwardWallet\Engine\preferred\Email;

class HotelVictoriaPdf2017 extends \TAccountChecker
{
    private $lang = '';
    private $subject = ['Reservation - Grand Victoria Hotel'];
    private $body = [
        'en' => [
            'Reservation Department - Grand Victoria Hotel',
            'Thank you for choosing The Imperial New Delhi',
        ],
    ];

    private static $dict = [
        'en' => [],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && isset($headers['from'])
                && $this->arripos($headers['from'], ['victoria', 'preferred']) !== false && $this->arripos($headers['subject'], $this->subject) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('[A-Z\d]{5,}.+?\.pdf');
        $text = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));

        return $this->detect($parser->getHTMLBody(), $this->body) || $this->detect($text, $this->body);
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arripos($from, ['victoria', 'preferred']) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('[A-Z\d]{5,}.+?\.pdf');
        $text = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));
        $its = [];

        if (!empty($text) && (
            ($this->lang = $this->detect($parser->getHTMLBody(), $this->body))
            || ($this->lang = $this->detect($text, $this->body))
                )
            ) {
            $its[] = $this->parseEmail($this->strCut($text, null, ['@grandvictoria.com', 'visit us at: www.theimperialindia.com']));
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'HotelVictoriaPdf2017' . ucfirst($this->lang),
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

    private function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }

    private function detect($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $lang => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $lang;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $lang;
            }
        }

        return null;
    }

    private function parseEmail($text)
    {
        /** @var \AwardWallet\ItineraryArrays\Hotel $i */
        $i = [];
        $i['Kind'] = 'R';

        $i['HotelName'] = $this->match('/Thank you for choosing (.+?)\./', $text);

        $i['Guests'] = $this->match('/ No\. of Adults \/ Child\s*:\s*(\d+)\/\d+/', $text);

        $i['Kids'] = $this->match('/ No\. of Adults \/ Child\s*:\s*\d+\/(\d+)/', $text);

        $i['CancellationPolicy'] = $this->match('/Cancellation Policy\s+(.+)/', $text);

        $i['ReservationDate'] = $this->normalizeDate($this->match('/Date\s*:\s*(\d+\/\d+\/\d{2,4})/', $text));

        $i += $this->matchSubpattern('/\n(?<Address>.+?)\s+(?:Tel|TELEPHONE):\s*(?<Phone>[\d\s()-\.,]+)\s+(?:Fax|FACSIMILE):\s*(?<Fax>[\d\s()-]+)\s+/i', $text);

        $i['ConfirmationNumber'] = $this->match('/(?:Reservation Number|Confirmation No\.)\s*:\s*([\w-]+)\b/', $text);

        if ($math = $this->match('/Guest Name\(s\)\s*:\s*([\w.\s]+)/', $text)) {
            $i['GuestNames'][] = $math;
        }

        if (count($i['GuestNames']) === 0) {
            $i['GuestNames'][] = $this->match('/Name\s*:\s*([\w.\s]+)\s+Arrival/', $text);
        }

        $i['RoomType'] = $this->match('/Room Type\s*:\s*(?<RoomType>[a-z\s]+)\s+Pickup/i', $text);

        $i['Rooms'] = $this->match('/Number of Rooms\s*:\s*(?<Rooms>\d+)\s+/i', $text);

        $i['Rate'] = $this->match('/Room Rate(?:\s+Per Night)?\s*:(?<Rate>.+)\s+Departure/i', $text);

        $i['CheckInDate'] = strtotime($this->match('/Arrival Date\s*:\s*(?<CheckInDate>.+)/i', $text));

        $i['CheckOutDate'] = strtotime($this->match('/Departure Date\s*:\s*(?<CheckOutDate>.+)/i', $text));

        if (!isset($i['CheckInDate'])) {
            $i['CheckInDate'] = strtotime($i['CheckInDate'], false);
            $i['CheckOutDate'] = strtotime($i['CheckOutDate'], false);
        }

        return $i;
    }

    //========================================
    // Auxiliary methods
    //========================================

    private function normalizeDate($s)
    {
        $in = [
            '/(\d+)\/(\d+)\/(\d{2,4})/',
        ];
        $out = [
            '$2/$1/$3',
        ];

        return strtotime(preg_replace($in, $out, $s));
    }

    private function arripos($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needle) {
            if (stripos($haystack, $needle) !== false) {
                return $key;
            }
        }

        return false;
    }

    /**
     * TODO: The experimental method.
     * If several groupings need to be used
     * Named subpatterns not accept the syntax (?<Name>) and (?'Name').
     *
     * @version v0.1
     *
     * @param type $pattern
     * @param type $text
     *
     * @return type
     */
    private function matchSubpattern($pattern, $text)
    {
        if (preg_match($pattern, $text, $matches)) {
            foreach ($matches as $key => $value) {
                if (is_int($key)) {
                    unset($matches[$key]);
                }
            }

            if (!empty($matches)) {
                return array_map([$this, 'normalizeText'], $matches);
            }
        }

        return [];
    }

    private function match($pattern, $text, $allMatches = false)
    {
        if (preg_match($pattern, $text, $matches)) {
            if ($allMatches) {
                array_shift($matches);

                return array_map([$this, 'normalizeText'], $matches);
            } else {
                return $this->normalizeText(count($matches) > 1 ? $matches[1] : $matches[0]);
            }
        } elseif ($allMatches) {
            return [];
        }
    }

    private function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
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
    private function strCut($input, $searchStart, $searchFinish = null)
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
}
