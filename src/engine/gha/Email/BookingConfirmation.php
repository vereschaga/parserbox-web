<?php

namespace AwardWallet\Engine\gha\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "gha/it-81652233.eml";
    public $subjects = [
        '/.+\:\s*Booking Confirmation/u',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@theoaksgroup.com.au') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Oaks Hotels & Resorts')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Booking Confirmation'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Guest Details'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]theoaksgroup\.com\.au$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Confirmation Number']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation Number'))}\s*([A-Z\d]+)\s*$/"), 'Confirmation Number')
            ->travellers($this->http->FindNodes("//text()[normalize-space()='Guest Details']/following::text()[normalize-space()='Name:']/following::text()[normalize-space()][1]"), true);

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Address:')]/preceding::text()[starts-with(normalize-space(), 'About')][1]", null, true, "/{$this->opt($this->t('About'))}\s*(.+)/"))
            ->address($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Address:')]/following::text()[normalize-space()][1]"))
            ->phone($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Address:')]/following::text()[starts-with(normalize-space(), 'Phone:')][1]/following::text()[normalize-space()][1]"));

        $dateCheckIn = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Arrival Date')]/following::text()[normalize-space()][1]", null, true, "/^(.+)\s*\(/");
        $timeCheckIn = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check in from:')]", null, true, "/{$this->opt($this->t('Check in from:'))}\s*([\d\:]+)/");

        $dateCheckOut = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Departure Date')]/following::text()[normalize-space()][1]");
        $timeCheckOut = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check out from:')]", null, true, "/{$this->opt($this->t('Check out from:'))}\s*([\d\:]+)/");

        $h->booked()
            ->guests($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Number of Guests')]/following::text()[normalize-space()][1]"))
            ->rooms(count($this->http->FindNodes("//text()[starts-with(normalize-space(), 'About Your ')]")))
            ->checkIn(strtotime($dateCheckIn . ', ' . $timeCheckIn))
            ->checkOut(strtotime($dateCheckOut . ', ' . $timeCheckOut));

        $h->price()
            ->total(PriceHelper::cost($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Price')]/following::text()[normalize-space()][1]", null, true, "/^[A-Z]{3}\s*(.+)/")))
            ->currency($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Price')]/following::text()[normalize-space()][1]", null, true, "/^([A-Z]{3})/"));

        $room = $h->addRoom();
        $room->setType($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Room Type')]/following::text()[normalize-space()][1]"))
            ->setRateType($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Rate')]/following::text()[normalize-space()][1]"));
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
