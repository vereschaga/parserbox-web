<?php

// bcdtravel, it-6397493.eml

namespace AwardWallet\Engine\hertz\Email;

class ReservationPdf2017 extends \TAccountChecker
{
    private $lang = '';
    private $subject = ['My Hertz Reservation'];
    private $body = [
        'en' => ['Thanks for Traveling at the Speed of Hertz'],
    ];

    private static $dict = [
        'en' => [],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && isset($headers['from'])
                && stripos($headers['from'], '@hertz.') !== false && $this->arripos($headers['subject'], $this->subject) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('My Hertz Reservation\.pdf');

        if (!empty($pdf)) {
            $text = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));

            return (bool) $this->detect(str_replace(' ', ' ', $text), $this->body);
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'hertz.') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('My Hertz Reservation\.pdf');

        if (empty($pdf)) {
            return;
        }

        $text = str_replace(' ', ' ', \PDF::convertToHtml($parser->getAttachmentBody(array_shift($pdf))));
        $its = [];

        if (!empty($text) && $this->lang = $this->detect($text, $this->body)) {
            $its[] = $this->parseEmail($this->htmlToText($this->strCut($text, null, 'Rental Terms and Conditions'), true));
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

    private function t($s)
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
    }

    private function parseEmail($text)
    {
        $i = ['Kind' => 'L'];

        $i += $this->matchSubpattern('/Thanks for Traveling at the (?<RentalCompany>.+?)\s+(?<RenterName>[A-Z\s]+)\n'
                . 'Your Conﬁrmation Number is:\s*(?<Number>\w+)\n/s', $text);

        $i['PickupLocation'] = $this->match('/Pickup Location\s+(.+?)Address/s', $text);
        $i['DropoffLocation'] = $this->match('/Return Location\s+(.+?)Address/s', $text);
        $i += $this->matchSubpattern('/Pickup Time(?<PickupDatetime>.+?)Return Time(?<DropoffDatetime>.+?)\n/s', $text);

        if (isset($i['PickupDatetime'])) {
            $i['PickupDatetime'] = strtotime(str_replace([',', ' at '], ['', ', '], $i['PickupDatetime']), false);
            $i['DropoffDatetime'] = strtotime(str_replace([',', ' at '], ['', ', '], $i['DropoffDatetime']), false);
        }

        $i['PickupHours'] = $this->match('/Hours of Operation:(.+?)\.\n/s', $text);
        $i['PickupPhone'] = $this->match('/Phone Number:([+\d\s()-]+)\n/s', $text);
        $i['PickupFax'] = $this->match('/Fax Number:([+\d\s()-]+)\n/s', $text);

        $i += $this->matchSubpattern('/YOUR VEHICLE(?<CarType>.+?)\n(?<CarModel>\([A-Z]\).+?)\n/s', $text);

        if (preg_match('/TOTAL\s+([\d.,]+)\s*([A-Z]{3})/', $text, $matches)) {
            $i['TotalCharge'] = (float) str_replace(',', '', $matches[1]);
            $i['Currency'] = $matches[2];
        }

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

    private function htmlToText($string, $view = false)
    {
        $text = preg_replace('/<[^>]+>/', "\n", html_entity_decode($string));

        if ($view) {
            return preg_replace('/\n{2,}/', "\n", $text);
        }

        return $text;
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
