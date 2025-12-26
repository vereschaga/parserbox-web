<?php

namespace AwardWallet\Engine\sevenrooms\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationPlain extends \TAccountChecker
{
    public $mailFiles = "sevenrooms/it-444857272.eml, sevenrooms/it-446481870.eml";
    public $subjects = [
        'Reservation Update Alert:',
        'Your Reservation at',
        'Reservation Alert:',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@sevenrooms.com') !== false) {
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
        $text = $parser->getPlainBody();
        $text = str_replace(["&amp;"], ["&"], $text);

        if (stripos($text, 'www.sevenrooms.com') !== false) {
            if (
                ((stripos($text, 'add to calendar') !== false && stripos($text, 'Contact') !== false)
             || (stripos($text, 'Party Size:') !== false && stripos($text, 'Venue:') !== false))
             && ($this->http->XPath->query("//a")->length === 0)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]sevenrooms\.com$/', $from) > 0;
    }

    public function ParseEvent(Email $email, string $text)
    {
        $reg = "/Name:\s*(?<traveller>.+)\nReservation No.:\s*(?<confNumber>[A-Z\d]+)\n"
                . "Venue:\s*(?<location>.+)\nDate\:\s*(?<date>.+\d{4})\n+Party Size:\s*(?<guests>\d+)/";

        if (preg_match($reg, $text, $m)) {
            $e = $email->add()->event();
            $e->setEventType(Event::TYPE_EVENT);

            $e->general()
                ->traveller($m['traveller'])
                ->confirmation($m['confNumber']);

            $e->booked()
                ->guests($m['guests'])
                ->start(strtotime($m['date']))
                ->noEnd();

            $e->setAddress($m['location']);

            $e->setName($this->re("/^\n*(.+)\s+{$this->opt($this->t('just updated this reservation.'))}/iu", $text));
        } elseif (stripos($text, 'Contact') !== false && stripos($text, 'Thank you booking your reservation at') !== false) {
            $e = $email->add()->event();
            $e->setEventType(Event::TYPE_EVENT);

            if (preg_match("/^\s*\n*(?<traveller>\D+)\n\w+\,\s*(?<date>\w+\s*\d{1,2}\,\s*\d{4})\s*\nfor\s*(?<guests>\d+)\s*guests\s*at\s*(?<time>[\d\:]+\s*A?P?M)/", $text, $m)) {
                $e->booked()
                    ->guests($m['guests'])
                    ->start(strtotime($m['date'] . ', ' . $m['time']))
                    ->noEnd();

                $e->general()
                    ->travellers(explode("&", $m['traveller']));
            }

            if (preg_match("/Contact\s*\n(?<address>(?:.+\n){1,3})(?<phone>[\(\d\s\)\-]{5,})\n/", $text, $m)) {
                $e->setPhone($m['phone']);
                $e->setAddress($m['address']);
            }

            if (preg_match("/Thank you booking your reservation at\s*(?<eventName>\D+)\.\s*\nYour reservation number is\s*(?<confirmation>[\dA-Z]{6,})/", $text, $m)) {
                $e->setName($m['eventName']);
                $e->general()
                    ->confirmation($m['confirmation']);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $text = strip_tags($parser->getPlainBody());
        $text = str_replace(["&amp;"], ["&"], $text);
        $this->logger->debug($text);

        $this->ParseEvent($email, $text);

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
        return 0;
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
}
