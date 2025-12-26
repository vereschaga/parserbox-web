<?php

namespace AwardWallet\Engine\lotpair\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parsers malaysia/FlightRetiming(object), aviancataca/Air(object), flyerbonus/TripReminder(object), thaiair/Cancellation(object), rapidrewards/Changes(object), mabuhay/FlightChange(object) (in favor of malaysia/FlightRetiming)

class FlightDelay extends \TAccountChecker
{
    public $mailFiles = "lotpair/it-99527785.eml";
    public $subjects = [
        '/Important information for travellers/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@lot.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $text = $this->htmlToText($parser->getHTMLBody());

        $this->logger->debug($text);

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('LOT Polish'))}]")->length > 0
            && ((stripos($text, 'YOUR NEW FLIGHT') !== false or stripos($text, 'NEW DEPARTURE TIME') !== false)
            && stripos($text, 'ADDITIONAL INFORMATIONS') !== false)
            || ((stripos($text, 'FLIGHT INFORMATION') !== false)
                && stripos($text, 'ADDITIONAL INFORMATION') !== false)) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]lot\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking reference:')]/following::text()[normalize-space()][1]"));

        $text = $this->htmlToText($parser->getHTMLBody());

        $text = $this->re("/(From.+)Best/s", $text);
        /*
        Phnom Penh PNH International
        Bangkok BKK Suvarnabhumi Intl
        21:15
        22:20
        TG585
        I
        May 20, 2020
        May 20, 2020
         */

        $q1 = '/\n(?<dName>.*[A-Z]{3}(?:.*\n){1,5})\n*(?<aName>.*[A-Z]{3}(?:.*\n){1,5})' .
            '\s*(?<dTime>\d+:\d+)\n\s*(?<aTime>\d+:\d+|Formula Error)' .
            '\n\s*(?<fName>[A-Z]{2})\s*(?<fNum>\d{1,4})\n\s*(?<bCode>[A-Z])' .
            '\n\s*(?<dDate>.+?\d{4})\n\s*(?<aDate>.+?\d{4}|Formula Error)/';

        $q2 = '/\n(?<dName>.+?)\n(?<aName>.+?)' .
            '\n\s*(?<dTime>\d+:\d+)\n\s*(?<aTime>\d+:\d+|Formula Error)' .
            '\n\s*(?<fName>[A-Z]{2})\s*(?<fNum>\d{1,4})\n\s*(?<bCode>[A-Z])' .
            '\n\s*(?<dDate>.+?\d{4})\n\s*(?<aDate>.+?\d{4}|Formula Error)/';

        if (preg_match_all($q1, $text, $segments, PREG_SET_ORDER) || preg_match_all($q2, $text, $segments, PREG_SET_ORDER)) {
            foreach ($segments as $segment) {
                $s = $f->addSegment();
                $s->airline()->name($segment['fName']);
                $s->airline()->number($segment['fNum']);

                $s->departure()->code($this->http->FindPreg('/^.+?\b([A-Z]{3})\b/', false, $segment['dName']));
                $s->departure()->name(str_replace("\n", " ", $segment['dName']));
                $s->departure()->date2("{$segment['dDate']},{$segment['dTime']}");

                $s->arrival()->code($this->http->FindPreg('/^.+?\b([A-Z]{3})\b/', false, $segment['aName']));
                $s->arrival()->name(str_replace("\n", " ", $segment['aName']));
                $s->extra()->bookingCode($segment['bCode']);

                if (preg_match("/Formula Error/", "{$segment['aDate']},{$segment['aTime']}")) {
                    $s->arrival()
                        ->noDate();
                } else {
                    $s->arrival()->date2("{$segment['aDate']},{$segment['aTime']}");
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

    protected function htmlToText($text)
    {
        $NBSP = chr(194) . chr(160);
        $text = str_replace($NBSP, ' ', html_entity_decode($text));
        $text = preg_replace('/<[^>]+>/', "\n", $text);
        $text = preg_replace('/\n{2,}/', "\n", $text);

        return $text;
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
}
