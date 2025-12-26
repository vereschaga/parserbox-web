<?php

namespace AwardWallet\Engine\trybooking\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Event extends \TAccountChecker
{
    public $mailFiles = "trybooking/it-488298473.eml, trybooking/it-488984194.eml, trybooking/it-513444471.eml, trybooking/it-751377273.eml";
    public $subjects = [
        'Your booking confirmation - ',
    ];

    public $lang = 'en';

    public $eventName;
    public $guestCount;
    public $event;

    public $pdfNamePattern = "Ticket.*pdf";

    public static $dictionary = [
        "en" => [
            'BOOKED BY' => ['BOOKED BY', 'ATTENDEE'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'mail.trybooking.com') !== false) {
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

            if (stripos($text, 'TryBooking.com') !== false
            && stripos($text, 'VENUE') !== false
            && stripos($text, 'WHEN') !== false) {
                return true;
            }
        }

        if (count($pdfs) === 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'TryBooking Pty Ltd')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Date booked:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Booking ID:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mail.trybooking.com$/', $from) > 0;
    }

    public function ParseEventPdf(Email $email, string $text)
    {
        $this->logger->debug(__METHOD__);

        $eventName = trim($this->re("/(?:^|\s+)(.+)\n+\s*VENUE/s", $text));

        if (empty($this->eventName) || (!empty($this->eventName) && $this->eventName !== $eventName)) {
            $this->eventName = $eventName;
            $this->guestCount = 1;

            $e = $email->add()->event();
            $this->event = $e;

            $e->setEventType(EVENT_EVENT);

            $e->setName(preg_replace("/\n\s+/", " ", $eventName));

            $e->setGuestCount($this->guestCount);

            $e->general()
                ->confirmation($this->http->FindSingleNode("//text()[normalize-space() = 'Booking ID:']/following::text()[normalize-space()][1]",
                    null, true, "/^\s*([a-z\d\-]{10,})\s*$/"))
                ->traveller($this->re("/{$this->opt($this->t('BOOKED BY'))}\s*(.+)/", $text));

            $e->setAddress(preg_replace("/(?:\s+|\n)/", " ", $this->re("/VENUE\s*(.+)\n\s*WHEN/su", $text)));

            if (preg_match("/WHEN\s*\w+\s*(?<date>\d+\s*\w+\s*\d{4})\s*(?<start>\d+\:\d+\s*A?P?M)\s*to\s*(?<end>\d+\:\d+\s*A?P?M)\n/", $text, $m)
            || preg_match("/WHEN\s*\w+\s*(?<date>\d+\s*\w+\s*\d{4})\s*(?<start>\d+\:\d+\s*A?P?M)\s*to\s*\s*\w+\s*(?<dateend>\d+\s*\w+\s*\d{4})\n*\s*(?<end>\d+\:\d+\s*A?P?M)\n/", $text, $m)) {
                $e->setStartDate(strtotime($m['date'] . ', ' . $m['start']));

                if (isset($m['dateend']) && !empty($m['dateend'])) {
                    $e->setEndDate(strtotime(str_replace("\n", "", $m['dateend']) . ', ' . $m['end']));
                } else {
                    $e->setEndDate(strtotime($m['date'] . ', ' . $m['end']));
                }
            }

            if (preg_match("/PRICE\s*.*for\s*(?<guestCount>\d+)[\s\-]+(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)/", $text, $m)
            || preg_match("/PRICE\s*.*\s+(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)/", $text, $m)) {
                if (isset($m['guestCount']) && !empty($m['guestCount'])) {
                    $e->setGuestCount($m['guestCount']);
                }

                $e->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);
            }

            $section = $this->re("/(SECTION\s+.+)/", $text);

            if (preg_match("/SECTION\s+(?<section>.+)\s+SEAT\s+(?<seat>[A-Z\d]+)/", $section, $m)) {
                $e->booked()
                    ->seat($m['seat']);
                $section = $m['section'];
            }

            if (!empty($section)) {
                $e->setNotes(preg_replace("/\s+/", " ", $section));
            }
        } else {
            ++$this->guestCount;
            $this->event->setGuestCount($this->guestCount);

            if (preg_match("/SECTION\s+(?<section>.+)\s+SEAT\s+(?<seat>[A-Z\d]+)/", $text, $m)) {
                $this->event->booked()
                    ->seat($m['seat']);
            }

            if (preg_match("/PRICE\s*.*for\s*(?<guestCount>\d+)[\s\-]+(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)/", $text, $m)
                || preg_match("/PRICE\s*.*\s+(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)/", $text, $m)) {
                $currentTotal = $this->event->getPrice()->getTotal();
                $total = array_sum([$currentTotal, PriceHelper::parse($m['total'], $m['currency'])]);
                $this->event->price()
                    ->total($total)
                    ->currency($m['currency']);
            }
        }
    }

    public function ParseEvent2Pdf(Email $email, string $text)
    {
        $this->logger->debug(__METHOD__);

        $eventName = trim($this->re("/(?:^|\s+)(.+)Table/", $text));

        if (empty($this->eventName) || (!empty($this->eventName) && $this->eventName !== $eventName)) {
            $this->eventName = $eventName;
            $this->guestCount = 1;

            $e = $email->add()->event();
            $this->event = $e;

            $e->setEventType(EVENT_EVENT);

            $e->setName(preg_replace("/\n\s+/", " ", $eventName));

            $e->setGuestCount($this->guestCount);

            $e->general()
                ->confirmation($this->re("/bookingUrlId=([a-z\d\-]{20,})\n/", $text))
                ->traveller($this->re("/{$this->opt($this->t('BOOKED BY'))}\s*(.+)/", $text));

            $e->setAddress(preg_replace("/(?:\s+|\n)/", " ", $this->re("/VENUE\s*((?:.+\n){1,3})\s*WHEN/u", $text)));

            if (preg_match("/WHEN\s*\w+\s*(?<day>\d+)\s*th\s*(?<monthYear>\w+\s*\d{4})\s*(?<start>\d+\:\d+\s*A?P?M)/", $text, $m)) {
                $e->setStartDate(strtotime($m['day'] . ' ' . $m['monthYear'] . ', ' . $m['start']));
                $e->setNoEndDate(true);
            }

            if (preg_match("/PRICE\s*.*for\s*(?<guestCount>\d+)[\s\-]+(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)/", $text, $m)
                || preg_match("/PRICE\s*.*\s+(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)/", $text, $m)) {
                if (isset($m['guestCount']) && !empty($m['guestCount'])) {
                    $e->setGuestCount($m['guestCount']);
                }

                $e->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);
            }

            $section = $this->re("/(SECTION\s+.+)/", $text);

            if (preg_match("/SECTION\s+(?<section>.+)\s+SEAT\s+(?<seat>[A-Z\d]+)/", $section, $m)) {
                $e->booked()
                    ->seat($m['seat']);
                $section = $m['section'];
            }

            if (!empty($section)) {
                $e->setNotes(preg_replace("/\s+/", " ", $section));
            }
        } else {
            ++$this->guestCount;
            $this->event->setGuestCount($this->guestCount);

            if (preg_match("/SECTION\s+(?<section>.+)\s+SEAT\s+(?<seat>[A-Z\d]+)/", $text, $m)) {
                $this->event->booked()
                    ->seat($m['seat']);
            }

            if (preg_match("/PRICE\s*.*for\s*(?<guestCount>\d+)[\s\-]+(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)/", $text, $m)
                || preg_match("/PRICE\s*.*\s+(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)/", $text, $m)) {
                $currentTotal = $this->event->getPrice()->getTotal();
                $total = array_sum([$currentTotal, PriceHelper::parse($m['total'], $m['currency'])]);
                $this->event->price()
                    ->total($total)
                    ->currency($m['currency']);
            }
        }
    }

    public function ParseEventHtml(Email $email)
    {
        $e = $email->add()->event();

        $e->setEventType(EVENT_EVENT);

        $e->general()
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hi')]", null, true, "/^({$this->opt($this->t('Hi'))})\s*(\D+)\,/"), false)
            ->confirmation($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Booking ID:')]/ancestor::tr[1]/descendant::td[normalize-space()][2]"))
            ->date(strtotime($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Date booked:')]/ancestor::tr[1]/descendant::td[normalize-space()][2]")));

        $dateTimeInfo = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Booking ID:')]/following::text()[contains(normalize-space(), ':')][1]");

        if (preg_match("/^\s*(?<date>\d+\s*\w+\s*\d{4})\s*(?<depTime>[\d\:]+\s*A?P?M)[\s\-]+(?<arrTime>[\d\:]+\s*A?P?M)$/", $dateTimeInfo, $m)
        || preg_match("/^(?<depDate>\d+\s+\w+\s*\d{4})\s*(?<depTime>[\d\:]+\s*A?P?M)[\-\s]+(?<arrDate>\d+\s+\w+\s*\d{4})\s*(?<arrTime>[\d\:]+\s*A?P?M)$/", $dateTimeInfo, $m)) {
            if (isset($m['depDate']) && !empty($m['depDate'])) {
                $e->booked()
                    ->start(strtotime($m['depDate'] . ', ' . $m['depTime']))
                    ->end(strtotime($m['arrDate'] . ', ' . $m['arrTime']));
            } else {
                $e->booked()
                    ->start(strtotime($m['date'] . ', ' . $m['depTime']))
                    ->end(strtotime($m['date'] . ', ' . $m['arrTime']));
            }
        } elseif (preg_match("/^\w+\s*(?<dateStart>\d+\s*\w+\s*\d{4})\:.*(?<timeStart>[\d\.]+a?p?m)$/", $dateTimeInfo, $m)) { //SUN 07 JAN 2024: Doors 2.30pm, Show 3pm
            $e->booked()
                ->start(strtotime($m['dateStart'] . ', ' . $m['timeStart']))
                ->noEnd();
        }

        $section = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Section:')]", null, true, "/{$this->opt($this->t('Section:'))}\s*(.+)/");

        if (!empty($section)) {
            $e->setNotes($section);
        }

        $e->setName($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Booking ID:')]/ancestor::tr[1]/following::text()[normalize-space()][not(contains(normalize-space(), 'Sold') or contains(normalize-space(), ':'))][1]"));

        $e->setAddress($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Section:')]/preceding::text()[normalize-space()][1]"));

        $total = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total:')]", null, true, "/{$this->opt($this->t('Total:'))}\s*(.+)/");

        if (preg_match("/^(?<currency>\D{1,3})\s+(?<total>[\d\.\,]+)$/", $total, $m)) {
            $e->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $guests = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'x ticket')]", null, true, "/^(\d+)\s*{$this->opt($this->t('x ticket'))}/");

        if (!empty($guests)) {
            $e->setGuestCount($guests);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $events = array_filter(preg_split("#^\s*Ticket#m", $text));

            foreach ($events as $event) {
                if (strpos($event, 'VENUE') !== false && strpos($event, 'SECTION') !== false && strpos($event, 'Table') === false) {
                    $this->ParseEventPdf($email, $event);
                } elseif (strpos($event, 'VENUE') !== false && strpos($event, 'SECTION') === false && strpos($event, 'Table') !== false) {
                    $this->ParseEvent2Pdf($email, $event);
                }
            }
        }

        if (count($pdfs) === 0 || count($email->getItineraries()) === 0) {
            $this->ParseEventHtml($email);
        }

        if (count($email->getItineraries()) > 0) {
            foreach ($email->getItineraries() as $it) {
                if ($it->getAddress() === 'Online Event'
                    || strpos($it->getAddress(), 'Meeting Password:') !== false
                ) {
                    $email->removeItinerary($it);
                }

                if (strlen($it->getAddress()) < 10) {
                    $email->removeItinerary($it);
                }
            }

            if (count($email->getItineraries()) === 0) {
                $email->setIsJunk(true, 'Online Event, no address');

                return;
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
}
