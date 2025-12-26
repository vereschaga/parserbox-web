<?php

namespace AwardWallet\Engine\tablethotels\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationAt extends \TAccountChecker
{
    public $mailFiles = "tablethotels/it-149854661.eml";
    public $subjects = [
        'Your Upcoming Reservation at',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@tablethotels.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Tablet Hotels LLC')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Check-in after:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Time to get excited!'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tablethotels\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Confirmation #:')]/following::text()[normalize-space()][1]"))
            ->traveller($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Guest name:')]/following::text()[normalize-space()][1]"));

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Confirmation #:')]/preceding::text()[normalize-space()][1]/ancestor::td[1]/descendant::p[1]"))
            ->address($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Confirmation #:')]/preceding::text()[normalize-space()][1]/ancestor::td[1]/descendant::p[2]"));

        $roomType = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Room:')]/following::text()[normalize-space()][1]");

        if (!empty($roomType)) {
            $room = $h->addRoom();
            $room->setType($roomType);
        }

        $dateText = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Stay dates:')]/following::text()[normalize-space()][1]");
        $timeIn = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Check-in after:')]/following::text()[normalize-space()][1]", null, true, "/^(.+)\s+\(/");

        if (preg_match("/^(\w+\s*\d+\,\s*\d{4})[\s\-]+(\w+\s*\d+\,\s*\d{4})$/", $dateText, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1] . ', ' . $timeIn))
                ->checkOut(strtotime($m[2]));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotel($email);

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
}
