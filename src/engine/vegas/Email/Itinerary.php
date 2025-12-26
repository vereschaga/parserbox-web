<?php

namespace AwardWallet\Engine\vegas\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "vegas/it-130846849.eml, vegas/it-131327327.eml";
    public $subjects = [
        'Your Vegas.com Purchase Confirmation',
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
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src, 'https://www.vegas.com')]")->length > 0
            || $this->http->XPath->query("//img[contains(@src, 'https://image.email.vegas.com')]")->length > 0) {
            return $this->http->XPath->query('//text()[contains(normalize-space(), "Here\'s confirmation of your recent purchase, including all the stuff you need to know. Save this email. It could come in handy.")]')->length > 0
                || $this->http->XPath->query('//text()[contains(normalize-space(), "The following information contains details of the specific items in your order. Please read each section carefully for specific pickup and other instructions.")]')->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]vegas\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[normalize-space()='Hotel Details']/ancestor::table[1]");

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $h->general()
                ->confirmation($this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Confirmation number:')]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Confirmation number:'))}\s*([A-Z\d\-]+)/"))
                ->traveller($this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Check-in name:')]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Check-in name:'))}\s*(.+)/"))
                ->cancellation($this->http->FindSingleNode("./descendant::text()[normalize-space()='cancellation policy']/following::text()[string-length()> 3][1]", $root));

            $h->hotel()
                ->name($this->http->FindSingleNode("./descendant::img[1]/following::text()[normalize-space()][1]", $root))
                ->address($this->http->FindSingleNode("./descendant::img[1]/following::text()[normalize-space()][1]/following::text()[normalize-space()][1]/ancestor::div[2]", $root));

            $h->booked()
                ->checkIn(strtotime($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Check-in date:')]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Check-in date:'))}\s*(.+)/")))
                ->checkOut(strtotime($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Check-out date:')]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Check-out date:'))}\s*(.+)/")))
                ->guests($this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Guests:')]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Guests:'))}\s*(\d+)\s*adult/"));

            $roomCount = count($this->http->FindNodes("./descendant::text()[contains(normalize-space(), 'Room #1:')]", $root));

            if ($roomCount > 0) {
                $h->booked()
                    ->rooms($roomCount);
            }

            $roomType = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Room type:')]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Room type:'))}\s*(.+)/");

            if (!empty($roomType)) {
                $room = $h->addRoom();

                $room->setType($roomType);
            }

            $h->price()
                ->cost($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Room subtotal:')]/ancestor::tr[normalize-space()][1]", $root, true, "/{$this->opt($this->t('Room subtotal:'))}\s*\D([\d\,\.]+)/"))
                ->tax($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Room taxes & fees:')]/ancestor::tr[normalize-space()][1]", $root, true, "/{$this->opt($this->t('Room taxes & fees:'))}\s*\D([\d\,\.]+)/"))
                ->total($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Total paid at booking:')]/ancestor::tr[normalize-space()][1]", $root, true, "/{$this->opt($this->t('Total paid at booking:'))}\s*\D([\d\,\.]+)/"))
                ->currency($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Total paid at booking:')]/ancestor::tr[normalize-space()][1]", $root, true, "/{$this->opt($this->t('Total paid at booking:'))}\s*(\D)/"));

            $this->detectDeadLine($h, $h->getCancellation());
        }
    }

    public function ParseEvent(Email $email)
    {
        $e = $email->add()->event();

        $e->general()
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Ticket holder:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Ticket holder:'))}\s*(.+)/"))
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation number:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation number:'))}\s*(.+)/"));

        $e->setName($this->http->FindSingleNode("//text()[normalize-space()='Map']/preceding::img[1]/following::text()[normalize-space()][1]/following::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()][1]"));

        $e->setEventType(4);

        $e->setAddress(implode(' ', $this->http->FindNodes("//text()[normalize-space()='Map']/preceding::img[1]/following::text()[normalize-space()][1]/following::text()[normalize-space()][1]/ancestor::td[1]/descendant::div[normalize-space()][1]/descendant::*")));

        $e->booked()
            ->seats($this->http->FindNodes("//text()[contains(normalize-space(), 'Seating information:')]/ancestor::tr[1]/descendant::text()[contains(normalize-space(), 'Seat')][not(contains(normalize-space(), 'information'))]/ancestor::span[1]"))
            ->guests($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Quantity:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Quantity:'))}\s*(.+)/"))
            ->start($this->normalizeDate($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Show time:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Show time:'))}\s*(.+)/")))
            ->noEnd();

        $e->price()
            ->cost($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Show ticket price:')]/ancestor::tr[normalize-space()][1]", null, true, "/{$this->opt($this->t('Show ticket price:'))}\s*\D([\d\,\.]+)/"))
            ->tax($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Ticket service fee:')]/ancestor::tr[normalize-space()][1]", null, true, "/{$this->opt($this->t('Ticket service fee:'))}\s*\D([\d\,\.]+)/"))
            ->total($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Show ticket total:')]/ancestor::tr[normalize-space()][1]", null, true, "/{$this->opt($this->t('Show ticket total:'))}\s*\D([\d\,\.]+)/"))
            ->currency($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Show ticket total:')]/ancestor::tr[normalize-space()][1]", null, true, "/{$this->opt($this->t('Show ticket total:'))}\s*(\D)/"));
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Ticket holder:')]")->length > 0) {
            $this->ParseEvent($email);
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Hotel Details')]")->length > 0) {
            $this->ParseHotel($email);
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
            '/^\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s[@]\s*([\d\:]+A?P?M)$/',
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
}
