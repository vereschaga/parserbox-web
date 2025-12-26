<?php

namespace AwardWallet\Engine\vegas\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ItineraryCancelled extends \TAccountChecker
{
    public $mailFiles = "vegas/it-128959068.eml, vegas/it-131391494.eml";
    public $subjects = [
        'Your Vegas.com Cancellation Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@vegas.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Vegas.com purchase has been canceled')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'DETAILS OF YOUR CANCELLATION')]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]vegas\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email, $text)
    {
        //$this->logger->debug($text);
        $h = $email->add()->hotel();

        $h->general()
            ->traveller($this->re("/Dear\s*([A-z\s]+)\,/", $text))
            ->confirmation($this->re("/Cancellation confirmation No.:\s*([A-Z\d\-]+)/", $text))
            ->status('canceled')
            ->cancelled();

        $hotelInfo = $this->re("/Hotel Cancelled:(.+)Nights Cancelled:/su", $text);

        if (preg_match("/(.+)\n+(.+)/u", trim($hotelInfo), $m)) {
            $h->hotel()
                ->name($m[1])
                ->address($m[2]);
        }

        if (preg_match_all("/(Cancelled Room\s*\d)/", $text, $match)) {
            $h->booked()
                ->rooms(count($match[1]));
        }

        $h->booked()
            ->checkIn(strtotime($this->re("/Check-In Date:\s*(.+)/", $text)))
            ->checkOut(strtotime($this->re("/Check-out Date:\s*(.+)/", $text)))
            ->guests($this->re("/Adults:\s*(\d+)/", $text));

        $kids = $this->re("/Children:\s*(\d+)/", $text);

        if ($kids !== null) {
            $h->booked()
                ->kids($kids);
        }
    }

    public function ParseEvent(Email $email, $text)
    {
        $e = $email->add()->event();

        $e->general()
            ->traveller($this->re("/Ticket Holder:\s*(.+)/", $text))
            ->confirmation($this->re("/Cancellation Confirmation No:\s*([A-Z\d\-]+)/", $text))
            ->cancelled();

        $e->setName($this->re("/Show Canceled:\s*(.+)/", $text));

        $e->setEventType(4);

        $e->booked()
            ->guests($this->re("/Cancellation Quantity:\s*(\d+)/", $text))
            ->start($this->normalizeDate($this->re("/Date & Time of Canceled Show:\s*(.+)/", $text)))
            ->noEnd();
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $text = $parser->getBodyStr();

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Ticket Holder:')]")->length > 0) {
            $this->ParseEvent($email, $text);
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'HOTEL CANCELLATION DETAILS')]")->length > 0) {
            $this->ParseHotel($email, $text);
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

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = ' . print_r($str, true));
        $in = [
            // Wednesday, December 29, 2021 @ 9:30PM
            '/^\s*\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s[@]\s*([\d\:]+A?P?M)\s*$/u',
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str, false);
    }

    private function detectDeadLine(Hotel $h, $cancellationText)
    {
        if (preg_match("#Cancellations or changes after\s*(?<month>\w+)\s*(?<day>\d+)[a-z]+ at\s*(?<time>[\d\:]+A?P?M)\s*#i", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m['day'] . ' ' . $m['month'] . ' ' . date('Y', $h->getCheckInDate()) . ', ' . $m['time']));
        }
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
