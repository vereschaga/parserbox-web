<?php

namespace AwardWallet\Engine\prestigia\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelCancellation extends \TAccountChecker
{
    public $mailFiles = "prestigia/it-777387180.eml";
    public $subjects = [
        'Cancellation of your booking in',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Night from' => ['Night from', 'Nights from'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@prestigia.com') !== false) {
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
        if (stripos($parser->getHeader('from'), '@prestigia.com') !== false
            && $this->http->XPath->query("//text()[{$this->contains($this->t('has now been cancelled'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('Your reservation reference'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]prestigia\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->HotelCancellation($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function HotelCancellation(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your reservation reference'))}]", null, true, "/^Your reservation reference\s*([A-Z\d\-]+)\s*at/"));

        if ($this->http->FindSingleNode("//text()[{$this->contains($this->t('has now been cancelled.'))}]") !== null) {
            $h->general()
                ->cancelled();
        }

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]/following::text()[1]", null, false, "/^[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]]$/u");
        $h->addTraveller(preg_replace("/\s{2,}/", " ", $traveller), true);

        $h->hotel()
            ->noAddress()
            ->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('hotel'))}]/preceding::text()[1]"));

        $reservationInfo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('from'))}]");

        if (preg_match("/^\s*from\s*(?<checkIn>\d{4}\-\d+\-\d+)\s*to\s*(?<checkOut>\d{4}\-\d+\-\d+)\s*has/", $reservationInfo, $m)) {
            $h->booked()
                ->checkIn(strtotime($m['checkIn']))
                ->checkOut(strtotime($m['checkOut']));
        }

        $roomInfo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation conditions:'))}]/following::text()[3]", null, false, '/^(.+\|.+)\s*\|/');

        $r = $h->addRoom();

        $r->setType($roomInfo);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
