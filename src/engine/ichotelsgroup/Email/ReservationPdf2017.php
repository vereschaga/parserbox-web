<?php

// bcdtravel
// Crowne Plaza Century Park Shanghai

namespace AwardWallet\Engine\ichotelsgroup\Email;

class ReservationPdf2017 extends \TAccountChecker
{
    public static $dict = [
        'en' => [],
    ];

    protected $subject = ['Reservation Confirmation'];
    protected $body = [
        'en' => ['your preferred choice of hotel'],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && isset($headers['from'])
                && $this->arripos($headers['from'], ['cpcpsh', 'ihg']) !== false && $this->arripos($headers['subject'], $this->subject) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('\d{4,}\.pdf');

        foreach ($pdf as $value) {
            $text = \PDF::convertToText($parser->getAttachmentBody($value));

            if (stripos($text, 'Call now and book your hotel worldwide!') !== false && $this->detect($text, $this->body)) {
                return true;
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arripos($from, ['cpcpsh', 'ihg']) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('\d{4,}\.pdf');
        $text = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));

        $its = [];

        if ($this->lang = $this->detect($text, $this->body)) {
            $its[] = $this->parseEmail($this->strCut($text, null, 'Call now and book your hotel worldwide!'));
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ReservationPdf2017' . ucfirst($this->lang),
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

    protected function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
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
            } elseif (stripos($haystack, $needles) !== false) {
                return $lang;
            }
        }
    }

    protected function parseEmail($text)
    {
        $i = [];
        $i['Kind'] = 'R';
        $i['HotelName'] = $this->match('/Thank you for making (.+?), /', $text);
        $i['Address'] = $this->match("/{$i['HotelName']}\s+([\w\s,.()-]+)(:?\n|Date)/", $text);
        $i['Phone'] = $this->match('/Tel\s*:\s*([\d\s()-]+)\n/', $text);
        $i['Fax'] = $this->match('/Fax\s*:\s*([\d\s()-]+)\n/', $text);

        $i['ConfirmationNumber'] = $this->match('/Confirmation Number\s*:\s*([\w-]+)\b/', $text);
        $i['GuestNames'][] = $this->match('/Guest Name\s*:\s*([\w\s]+)\n/', $text);

        $i += $this->matchSubpattern("/Check-in\s*:\s*(?<CheckInDate>.+?)"
                . "\s+Check-out\s*:\s*(?<CheckOutDate>.+?)"
                . "\s+Room Type\s*:\s*(?<RoomType>.+?)"
                . "\s+Number of Room\(s\)\s*:\s*(?<Rooms>\d+).*?"
                . "\s+Room Rate per Night\s*:\s*(?<Rate>.+?)\n/s", $text);

        if (isset($i['CheckInDate'])) {
            $i['CheckInDate'] = strtotime($i['CheckInDate'] . ', ' . $this->match('/check in time is ([\d:]+\s*[apmno]{2,4})\b/', $text), false);
            $i['CheckOutDate'] = strtotime($i['CheckOutDate'] . ', ' . $this->match('/check out time is ([\d:]+\s*[apmno]{2,4})\b/', $text), false);
        }

        $i['CancellationPolicy'] = $this->match("/CANCELLATION POLICY\s+(.+?)\s+Please don't hesitate/s", $text);

        return $i;
    }

    //========================================
    // Auxiliary methods
    //========================================

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
