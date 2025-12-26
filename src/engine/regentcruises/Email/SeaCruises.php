<?php

namespace AwardWallet\Engine\regentcruises\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class SeaCruises extends \TAccountChecker
{
    public $mailFiles = "regentcruises/it-583150775.eml, regentcruises/it-633925164.eml";
    public $subjects = [
        'Regent Seven Seas Cruises Final Cruise Vacation Summary',
    ];

    public $lang = 'en';
    public $aboard = false;

    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
            'In the sea' => [
                'Cruising The Atlantic Ocean',
                ' Cruising The Atlantic Ocean',
                'Cruising The Bahamian Waters',
                'Cruising The Gulf Of Mexico',
                'Cruising The Caribbean Sea',
                'Cruising The Pacific Ocean',
                'Panama Canal Transit',
                'Cruising The',
                'At Sea',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@rssc.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'Seven Seas Cruises') !== false
            && stripos($text, 'VACATION SUMMARY') !== false
            && stripos($text, 'DEPARTURE INFORMATION') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]rssc\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $text = '';

        foreach ($pdfs as $pdf) {
            $text .= \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        $this->ParseCruises($email, $text);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseCruises(Email $email, string $text)
    {
        $cruiseSegmnets = $this->re("/CRUISE ITINERARY\nDay\s*Date\s*Port.*\n*((?:\w+\s*\w+\s*.*(?:[\d\:]+\s*A?P?M)?\n){1,})/", $text);
        $cruiseRows = array_filter(explode("\n", $cruiseSegmnets));
        $travellers = [];

        if (preg_match_all("/Guest:\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\n/", $text, $m)) {
            $travellers = $m[1];
        }

        if (count($cruiseRows) > 0) {
            $c = $email->add()->cruise();

            $c->general()
                ->confirmation($this->re("/RESERVATION NUMBER:\s*(\d+)/", $text))
                ->travellers(preg_replace("/^(?:MRS|MR|MS)/", "", array_filter($travellers)))
                ->date(strtotime($this->re("/SAIL DATE:\s*(.+\d{4})/", $text)));

            $ship = $this->re("/SHIP:(.+)\b[ ]{10,}/", $text);

            if (empty($ship)) {
                $ship = $this->re("/SHIP:(.+)\b\n/", $text);
            }
            $c->setShip($ship);

            $deck = $this->re("/DECK:\s+(\d+)/", $text);

            if (!empty($deck)) {
                $c->setDeck($deck);
            }

            $suite = $this->re("/SUITE:\s+(\d+)/", $text);

            if (empty($suite)) {
                $suite = $this->re("/SUITE:\s+(.+)/", $text);
            }

            $c->setRoom($suite);
            $c->setClass($this->re("/CATEGORY:\s*(.+)/", $text));

            $account = $this->re("/Seven Seas Society Number:\s*(\d+)/", $text);

            if (!empty($account)) {
                $c->setAccountNumbers([$account], false);
            }

            foreach ($cruiseRows as $key => $row) {
                if (preg_match("/{$this->opt($this->t('In the sea'))}/", $row)) {
                    continue;
                }

                $s = $c->addSegment();

                if (preg_match("/^\w+\s*(?<day>\d+)(?<month>\w+)(?<year>\d{2})\s*(?<name>.+)\b[ ]{2,}(?<depTime>[\d\:]+\s*A?P?M)\s*(?<arrTime>[\d\:]+\s*A?P?M)(?:\s*[A-Z]\s*)?$/", $row, $m)) {
                    $s->setName($m['name'])
                    ->setAshore(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['depTime']))
                    ->setAboard(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['arrTime']));
                } elseif (preg_match("/^\w+\s*(?<day>\d+)(?<month>\w+)(?<year>\d{2})\s*(?<name>.+)\b[ ]{2,}(?<time>[\d\:]+\s*A?P?M)(?:\s*[A-Z]\s*)?$/", $row, $m)) {
                    if ($this->aboard === false) {
                        $s->setName($m['name'])
                        ->setAboard(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['time']));
                        $this->aboard = true;
                    } else {
                        $s->setName($m['name'])
                            ->setAshore(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['time']));
                        $this->aboard = false;
                    }
                }

                if (empty($s->getName()) && empty($s->getAboard() && empty($s->getAshore()))) {
                    $c->removeSegment($s);
                }
            }
        }

        $eventSegments = $this->re("/YOUR PRE\-PURCHASED TOURS\n\s*Name\s*Date\s*Port.*\n*((?:\s*.+\s+\d+\w+\d{2}\s+.+[ ]{2,}\s+[\d\:]+\s*A?P?M?\s+[\d\.]+\s*Hrs\n){1,})/", $text);
        $eventsRows = array_filter(explode("\n", $eventSegments));

        if (count($eventsRows) > 0) {
            foreach ($eventsRows as $row) {
                $e = $email->add()->event();

                if (preg_match("/\s*(?<name>.+)\b\s+(?<day>\d+)(?<month>\w+)(?<year>\d{4})\s+(?<address>.+)\b[ ]{2,}\s+(?<time>[\d\:]+\s*A?P?M?)\s+(?<duration>[\d\.]+\s*Hrs)/", $row, $m)) {
                    $e->general()
                        ->travellers(preg_replace("/^(?:MRS|MR|MS)/", "", $travellers))
                        ->noConfirmation();

                    $duration = $this->re("/^([\d\.]+)/", $m['duration']) * 60;

                    $e->setName($m['name'])
                        ->setAddress($m['address'])
                        ->setEventType(EVENT_SHOW)
                        ->setStartDate(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['time']))
                        ->setEndDate(strtotime($duration . ' minutes', $e->getStartDate()));
                }
            }
        }

        $flightSegText = $this->re("/Day\s*Date\s*Ref\s*[#]\s*Flight\s*City\s*Depart.*\n((?:.+\n){1,10})\n\n\n\n/", $text);
        $flightSegments = $this->splitText($flightSegText, "/([ ]\w+\s*\d+[A-Z]+\d{2}\s*[A-Z\d]{6}\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,4}.+)/", true);

        if (count($flightSegments) > 0) {
            $f = $email->add()->flight();

            $f->general()
                ->travellers(preg_replace("/^(?:MRS|MR|MS)/", "", $travellers));

            if (preg_match("#FLIGHT ARRANGEMENTS\n\D+\s+eTicket Numbers\s*Reservation Code\s*\D+\b\s+(?<tikets>[\d\S]+)\s+(?<confNumber>[A-Z\d]{6})#", $text, $m)) {
                $f->setTicketNumbers(explode('\\', $m['tikets']), false);
                $f->general()
                    ->confirmation($m['confNumber']);
            }

            foreach ($flightSegments as $segment) {
                $s = $f->addSegment();

                $regEx = "/[A-Z]{3}\s+(?<depDate>\d+[A-Z]*\d{2})\s*(?<conf>[A-Z\d]{6})\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})\s+(?<depName>.+)\b\s+(?<depTime>\d+\:\d+A?P?).*\n"
                    . "\s*[A-Z]{3}\s+(?<arrDate>\d+[A-Z]*\d{2})\s*\s+(?<arrName>.+)\b\s+(?<arrTime>\d+\:\d+A?P?)/";

                if (preg_match($regEx, $segment, $m)) {
                    $s->airline()
                        ->name($m['aName'])
                        ->number($m['fNumber']);

                    $s->departure()
                        ->date(strtotime($m['depDate'] . ', ' . $m['depTime'] . 'M'))
                        ->name($m['depName'])
                        ->noCode();

                    $s->arrival()
                        ->date(strtotime($m['arrDate'] . ', ' . $m['arrTime'] . 'M'))
                        ->name($m['arrName'])
                        ->noCode();

                    $s->setConfirmation($m['conf']);
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }
}
