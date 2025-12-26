<?php

namespace AwardWallet\Engine\hertz\Email;

class ReservationPdf2017_2 extends \TAccountChecker
{
    public $mailFiles = "";

    private $lang = '';
    private $subject = ['reservation'];
    private $body = [
        'en' => ['see your HERTZ reservation'],
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
        $pdf = $parser->searchAttachmentByName('.*\.pdf');

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
        $pdf = $parser->searchAttachmentByName('.*\.pdf');

        if (empty($pdf)) {
            return;
        }

        $text = \PDF::convertToHtml($parser->getAttachmentBody(array_shift($pdf)));
        $NBSP = chr(194) . chr(160);
        $text = str_replace($NBSP, ' ', html_entity_decode($text));
        $its = [];

        if (!empty($text) && $this->lang = $this->detect($text, $this->body)) {
            $its[] = $this->parseEmail($this->htmlToText($text, true));
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ReservationPdf2017_2' . ucfirst($this->lang),
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

        $i += $this->matchSubpattern('/Date:\s*(?<ReservationDate>\d+\/\d{2}\/\d{4})\s+Reservation Number:\s*(?<Number>[A-Z\d ]+)\s+Status:\s*(?<Status>.+)\n/', $text);
        $i['Number'] = preg_replace('#\D#', '', $i['Number']);
        $i['ReservationDate'] = strtotime(str_replace('/', '.', $i['ReservationDate']));

        if (preg_match("#Date of Rental:\s*(\d{2}\/\d{2}\/\d{4})\s+Time:\s*(\d{2}:\d{2})\s+Pick-up station:\s+([\s\S]+)(\d[\d\- \(\)]+)?\s+Date of Return#U", $text, $m)) {
            $i['PickupLocation'] = str_replace("\n", " ", trim($m[3]));
            $i['PickupDatetime'] = strtotime(str_replace('/', '.', $m[1] . ' ' . $m[2]));
            $i['PickupPhone'] = $m[4];
        }

        if (preg_match("#Date of Return:\s*(\d{2}\/\d{2}\/\d{4})\s+Time:\s*(\d{2}:\d{2})\s+Drop-off station:\s+([\s\S]+)\s+(No\. of Days\s*:\s*\d+)\s+(.+)\n#", $text, $m)) {
            $i['DropoffDatetime'] = strtotime(str_replace('/', '.', $m[1] . ' ' . $m[2]));

            if (preg_match("#([\s\S]+)\s+(\d[\d\- \(\)\+]+)$#", $m[3] . ' ' . $m[5], $mat)) {
                $i['DropoffLocation'] = str_replace("\n", " ", trim($mat[1]));
                $i['DropoffPhone'] = $mat[2];
            } else {
                $i['DropoffLocation'] = $m[3] . ' ' . $m[5];
            }
        }

        $i += $this->matchSubpattern('/Car Group\s*:\s*(?<CarType>[\w ]+)\s+\((?<CarModel>[^)]+)\)/', $text);

        $i += $this->matchSubpattern('/Dear Mr \/Mrs\.\s*(?<RenterName>.+)\n+/', $text);

        if (preg_match('/Values in ([A-Z]{3})\s+(?:.*\n)+Total Charges .*\n([\d.,]+)/', $text, $m)) {
            $i['TotalCharge'] = (float) str_replace(',', '', $m[2]);
            $i['Currency'] = $m[1];
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
