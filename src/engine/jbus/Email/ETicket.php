<?php

namespace AwardWallet\Engine\jbus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Bus;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "jbus/it-547595630.eml, jbus/it-548227663.eml";
    public $subjects = [
        'E-ticket from JapanBusOnline',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mail.j-bus.co.jp') !== false) {
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
        $text = preg_replace("/^\s*[>]/m", "", $parser->getPlainBody());

        if (stripos($text, 'https://JapanBusOnline.com/') !== false
            && stripos($text, 'Ride Date') !== false
            && stripos($text, 'Number of passengers') !== false
        ) {
            return true;
        }

        //it-547595630.eml
        if (stripos($text, 'https://JapanBusOnline.com/') !== false
            && stripos($text, 'Single trip') !== false
            && stripos($text, 'E-ticket/Confirmation e-mail') !== false
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mail\.j\-bus\.co\.jp$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $text = preg_replace("/^\s*[>]/m", "", $parser->getPlainBody());
        $this->ParseBus($email, $text);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseBus(Email $email, string $text)
    {
        $b = $email->add()->bus();

        $b->general()
            ->confirmation($this->re("/Reservation number\s*\(.*\n*\s*[<]\s*(\d+)\s*[>]/", $text))
            ->traveller($this->re("/Name\s*\(.*\n*\s*[<]\s*(\D+)\s*[>]/", $text));

        if (preg_match("/Fare\s*\(.*\n*\s*[<]\s*(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)\s*[>]/u", $text, $m)) {
            $b->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['total'], $m['currency']));
        }

        //it-547595630.eml
        if (stripos($text, "Single trip") !== false) {
            $this->ParseSegment2($b, $text);
        } else {
            $this->ParseSegment1($b, $text);
        }
    }

    public function ParseSegment1(Bus $b, string $text)
    {
        $s = $b->addSegment();

        $date = $this->re("/Ride Date.*\n\s*[<]\s*.*[【](\d{4}.*)[】]/u", $text);
        $depTime = $this->re("/Departure.*\n\s*[<]\s*(?<depTime>[\d\:]+)\(/u", $text);
        $arrTime = $this->re("/Arrival.*\n\s*[<]\s*(?<depTime>[\d\:]+)\(/u", $text);

        $s->departure()
            ->name($this->re("/Boarding spot.*\n\s*[<]\s*(.+)\n/", $text))
            ->date($this->normalizeDate($date . ', ' . $depTime));

        $s->arrival()
            ->name($this->re("/Arrival spot.*\n\s*[<]\s*(.+)\n/", $text))
            ->date($this->normalizeDate($date . ', ' . $arrTime));

        if (preg_match("/Seat No\s*\(.+\n\s*[<]\s*(?<number>[\dA-Z\s*]+)\s+\/\s*(?<seats>[A-Z\d\s\/]+)/u", $text, $m)) {
            $s->setSeats(explode(" / ", $m['seats']));
            $s->setNumber($m['number']);
        }
    }

    public function ParseSegment2(Bus $b, string $text)
    {
        $s = $b->addSegment();

        if (preg_match("/Departure:(?<depDate>\d+\/\d+\/\d{4}\s*[\d\:]+).*From\:(?<depName>.+)/", $text, $m)) {
            $s->departure()
                ->date($this->normalizeDate($m['depDate']))
                ->name($m['depName']);
        }

        if (preg_match("/Arrival:(?<arrDate>\d+\/\d+\/\d{4}\s*[\d\:]+).*To\:(?<arrName>.+)/", $text, $m)) {
            $s->arrival()
                ->date($this->normalizeDate($m['arrDate']))
                ->name($m['arrName']);
        }

        if (preg_match("/Seat Number\s*\(.+\n\s*(?<seats>[A-Z\d\s\/]+)/u", $text, $m)) {
            $s->setSeats(explode(" / ", $m['seats']));
            $s->setNoNumber(true);
        }
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

    private function normalizeDate($str)
    {
        //$this->logger->error('$str = ' . print_r($str, true));

        $in = [
            "#^(\d{4})[年](\d+)[月](\d+)[日]\,\s*([\d\:]+)$#u", //2023年11月02日, 15:20
        ];
        $out = [
            "$3.$2.$1, $4",
        ];
        $str = preg_replace($in, $out, $str);
        //$this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
