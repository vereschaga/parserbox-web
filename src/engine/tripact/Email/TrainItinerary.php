<?php

namespace AwardWallet\Engine\tripact\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TrainItinerary extends \TAccountChecker
{
    public $mailFiles = "tripact/it-45266091.eml, tripact/it-45693119.eml";

    public $reFrom = "@tripactions.com";
    public $reSubject = [
        'en' => 'Train Booking',
    ];
    public $reBody = 'TripActions';
    public $reBody2 = [
        'en' => ['Train Confirmation:'],
    ];

    public static $dictionary = [
        'en' => [
            //            'confirmation' => ['Train Confirmation:'],
            //            'traveller' => ['Passenger:'],
        ],
    ];

    public $lang = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        //$this->http->FilterHTML = false;
        foreach ($this->reBody2 as $lang => $re) {
            if ($this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $text = $this->htmlToText($parser->getHTMLBody());
        // Travel Agency
        $email->obtainTravelAgency();
        // Record locator
        if (preg_match('/Record locator\s+([A-Z\d]{5,6})/', $text, $m)) {
            $email->ota()->confirmation($m[1], 'Record locator');
        }
        // Total Charge
        if (preg_match('/Total Charge:\s+([\d.,\s]+)\s*([A-Z]{3})/', $text, $m)) {
            $email->price()
                ->total($m[1])
                ->currency($m[2]);
        }

        $this->parseTrain($email, $text);
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers["from"], $headers["subject"])) {
            return false;
        }

        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[{$this->contains($this->reBody)}]")->length > 0) {
            foreach ($this->reBody2 as $re) {
                if ($this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function htmlToText($string)
    {
        $NBSP = chr(194) . chr(160);
        $string = str_replace($NBSP, ' ', html_entity_decode($string));
        $string = str_replace('-->', '', html_entity_decode($string));
        $string = preg_replace('/<[^>]+>/', "\n", $string);
        $string = preg_replace(['/\n{2,}\s{2,}/'], "\n", $string);

        return $string;
    }

    private function parseTrain(Email $email, $text)
    {
        //$this->logger->debug($text);

        $trains = $this->splitter('/(Train Confirmation:\s+[A-z\d]{5,6})/', $text);
        $this->logger->notice("Found " . count($trains) . " trains");
        $confirmations = [];

        foreach ($trains as $train) {
            //$this->logger->debug(var_export($train, true));

            if (preg_match('/Confirmation:\s*([A-z\d]{5,6})/', $train, $m)) {
                if (isset($confirmations) && in_array($m[1], $confirmations)) {
                } else {
                    $confirmations[] = $m[1];
                    $t = $email->add()->train();
                    $t->general()->confirmation($m[1], 'Train Confirmation');

                    if (preg_match('/Passenger:\s*(.+?)\n/', $train, $m)) {
                        $t->general()->traveller($m[1], true);
                    }
                }
            }

            if (!isset($t)) {
                $this->logger->alert('Item bug');

                return;
            }

            // Thursday October 3, 2019
            $segments = $this->splitter('/(\w+ \w+ \d{1,2}, \d{4}\b)|(Change at .+?\n)/', $train);
            $this->logger->notice("Found " . count($segments) . " segments");

            foreach ($segments as $segment) {
                //$this->logger->debug(var_export($segment, true));
                if (preg_match('/^(\w+ \w+ \d{1,2}, \d{4})\s+/', $segment, $m)) {
                    $date = $m[1];
                }

                if (preg_match('/^.+?\n+([A-z\s]+)\s+(\d+)/', $segment, $m)) {
                    $s = $t->addSegment();
                    $s->extra()->service($m[1]);
                    $s->extra()->number($m[2]);
                }

                if (isset($date) && preg_match_all('/(\d+:\d+\s*(?:[ap]m)?)\s+(.+)/i', $segment, $m)) {
                    $s->departure()->date2("{$date}, {$m[1][0]}");
                    $s->departure()->name($m[2][0]);
                    $s->arrival()->date2("{$date}, {$m[1][1]}");
                    $s->arrival()->name($m[2][1]);
                } else {
                    $this->logger->alert('Date bug');
                }
                // Seat: 1A
                if (preg_match('/Seat:\s*([\w,\s]+)\n/', $segment, $m)) {
                    $s->extra()->seats(explode(',', $m[1]));
                }
                // Class: Business Class Seat
                if (preg_match('/Class:\s*([\w\s]+)\n/', $segment, $m)) {
                    $s->extra()->cabin($m[1]);
                }
                // Duration: 2h 24m
                if (preg_match('/Duration:\s*(.+?)\n/', $segment, $m)) {
                    $s->extra()->duration($m[1]);
                }
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
    private function splitter($pattern, $text)
    {
        $result = [];

        $array = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return null;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'normalize-space(' . $node . ')="' . $s . '"';
                }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'contains(normalize-space(' . $node . '),"' . $s . '")';
                }, $field))
            . ')';
    }

    private function normalizeDate($str)
    {
//        $in = [
//            "#^(\w+)\s+(\d+),\s+(\d{4})$#",
//            "#^(\w+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+\s*[ap]m)$#i",
//        ];
//        $out = [
//            "$2 $1 $3",
//            "$2 $1 $3, $4",
//        ];
//        return strtotime($this->dateStringToEnglish(preg_replace($in, $out, $str)));
        return strtotime($str, false);
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
