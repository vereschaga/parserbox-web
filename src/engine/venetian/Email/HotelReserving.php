<?php

namespace AwardWallet\Engine\venetian\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelReserving extends \TAccountChecker
{
    public $mailFiles = "venetian/it-121903378.eml";
    public $subjects = [
        'Thank you for reserving!',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@cvent.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Thank you for choosing The Venetian')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Hotel Reservation Acknowledgement')]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]cvent\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Hotel Reservation Acknowledgement')]", null, true, "/{$this->opt($this->t('Hotel Reservation Acknowledgement'))}\s*([A-Z\d]+)/"))
            ->travellers($this->http->FindNodes("//text()[contains(normalize-space(), 'Share-With')]/following::span[1]/descendant::text()[normalize-space()]"), true)
            ->date(strtotime($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Date Booked:')]/following::text()[normalize-space()][1]")))
            ->cancellation($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Cancel Policy:')]/following::text()[normalize-space()][1]"));

        $hotelNameText = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Hotel Reservation Acknowledgement')]");

        if (preg_match("/{$this->opt($this->t('Thank you for choosing'))}\s*(.+)\s*{$this->opt($this->t('for your upcoming stay'))}/", $hotelNameText, $m)) {
            $h->hotel()
                ->name($m[1])
                ->address($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Home')]/preceding::text()[string-length()>3][1]"))
                ->phone($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Reservations')]/following::text()[string-length()>3][1]"));
        }

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Arrival:')]/following::text()[normalize-space()][1]")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Departure:')]/following::text()[normalize-space()][1]")));

        $roomType = $this->http->FindSingleNode("//text()[normalize-space()='Suite:']/following::text()[normalize-space()][1]");
        $rateArray = array_filter($this->http->FindNodes("//text()[normalize-space()='Hotel Rates:']/following::span[normalize-space()][1]/descendant::text()[contains(normalize-space(), 'Confirmed')]", null, "/Confirmed\s*([\d\,\.]+)/"));

        if (!empty($roomType) || count($rateArray) > 0) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (count($rateArray) > 0) {
                $room->setRates($rateArray);
            }
        }

        $guests = $this->http->FindSingleNode("//text()[normalize-space()='Hotel Rates:']/following::span[normalize-space()][1]/descendant::text()[contains(normalize-space(), 'Confirmed')][1]", null, true, "/\s+(\d+)\s*Confirmed/u");

        if (!empty($guests)) {
            $h->booked()
                ->guests($guests);
        }

        $this->detectDeadLine($h);
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

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/The cancellation policy for your reservation is (\d+\s*\w+) prior to arrival/', $cancellationText, $m)) {
            $this->logger->error($m[1]);
            $h->booked()->deadlineRelative($m[1]);
        }
    }
}
