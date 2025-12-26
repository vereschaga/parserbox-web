<?php

namespace AwardWallet\Engine\rsrvit\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmed extends \TAccountChecker
{
    public $mailFiles = "rsrvit/it-431035803.eml, rsrvit/it-440229231.eml, rsrvit/it-442615425.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Your booking at' => ['Your booking at', 'Just to remind you about reservation at', 'The booking for', 'Your reservation at'],
            'is Confirmed'    => ['is Confirmed', 'with the following details', 'cancelled', 'has been updated to the following details'],
            'Date&Time'       => ['Date&Time', 'Date & Time'],
        ],
    ];

    private $detectFrom = "@rsrvit.com";
    private $detectSubject = [
        // en
        // Your booking is Confirmed - Mostaccioli Brothers Aka Mo Bros
        'Your booking is Confirmed - ',
        // Mura Dubai - Reservations Reminder
        ' - Reservations Reminder',
        'Booking has been cancelled for ',
        // Your reservation at Trove Dubai has been modified
        'has been modified',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]rsrvit\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['/rsrvit.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['hello@rsrvit.com'])}]")->length === 0
        ) {
            return false;
        }

        return $this->detectFormat();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->detectFormat();

        if (!$this->lang) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

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

    private function detectFormat()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Your booking at"]) && !empty($dict["is Confirmed"]) && !empty($dict["Date&Time"])) {
                if ($this->http->XPath->query("//text()[{$this->contains($dict['Your booking at'])}]/ancestor::*[position() < 3][{$this->contains($dict['is Confirmed'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->eq($dict['Date&Time'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        // $url = 'https://rsrvit.com/listings/?keyword_search=Mostaccioli+Brothers&location_search=&tax-listing_category=0&action=listeo_get_listings';
        // $http2 = clone $this->http;
        //
        // $http2->getUrl($url);
        // $name = $http2->FindSingleNode("//a[@data-title = 'Mostaccioli Brothers Aka Mo Bros']/@data-friendly-address");
        // $this->logger->debug('$name = '.print_r( $name,true));
        // $this->logger->debug('$date = '.print_r( $http2->Response,true));

        $event = $email->add()->event();

        // Type
        $event->type()->restaurant();

        // General
        $event->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Ref#'))}]/following::text()[normalize-space()][not({$this->eq(':')})][1]",
                null, true, "/^\s*:?\s*(\d{3,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Name'))}]/following::text()[normalize-space()][not({$this->eq(':')})][1]",
                null, true, "/^\s*:?\s*(.+)\s*$/"))
        ;

        if ($this->http->XPath->query("//node()[{$this->contains($this->t('has been cancelled'))}]")->length > 0) {
            $event->general()
                ->cancelled()
                ->status('Cancelled');
        }

        // Place
        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your booking at'))}]/ancestor::*[position() < 3][{$this->contains($this->t('is Confirmed'))}][1]",
            null, true, "/{$this->opt($this->t('Your booking at'))}\s+\W?(.+?)\W?\s+{$this->opt($this->t('is Confirmed'))}/u");
        $event->place()
            ->name($name)
        ;

        if (!empty($name)) {
            $url = 'https://rsrvit.com/listings/?keyword_search='
                . trim(preg_replace(['/\W+/', '/\s+/'], [' ', '+'], $name), '+')
                . '&location_search=&tax-listing_category=0&action=listeo_get_listings';

            $http2 = clone $this->http;

            $http2->getUrl($url);
            $address = $http2->FindSingleNode("//a[@data-title = '" . $name . "']/@data-friendly-address");
            $city = $http2->FindSingleNode("//a[@data-title = '" . $name . "']/@href", null, true,
                    "/rsrvit\.com\\/listing\\/([^\/]+)\\//u");

            if (!empty($address)) {
                if (!empty($city) && !preg_match("/\b{$city}\b/i", $address)) {
                    $address .= ', ' . $city;
                }
                $event->place()
                        ->address($address);
            }
        }

        // Booked
        $event->booked()
            ->start($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Date&Time'))}]/following::text()[normalize-space()][not({$this->eq(':')})][1]",
                null, true, "/^\s*:?\s*(.+)\s*$/")))
            ->noEnd()
        ;

        if ($event->getCancelled() !== true) {
            $event->booked()
                ->guests($this->http->FindSingleNode("//text()[{$this->eq($this->t('Covers'))}]/following::text()[normalize-space()][not({$this->eq(':')})][1]",
                    null, true, "/^\s*:?\s*(\d+) Guest/"));
        }

        if ($event->getCancelled() !== true && empty($event->getAddress())) {
            // go to junk if all fields will be collected without errors
            $email->removeItinerary($event);
            $email->setIsJunk(true);
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            // 20/07/2023 at 20:00
            // 21/06/2023 18:00 pm
            '/^\s*(\d+)\\/(\d+)\\/(\d{4})\s+(?:at\s+)?(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1.$2.$3, $4',
        ];

        $date = preg_replace($in, $out, $date);
        // 18:00 pm -> 18:00
        $date = preg_replace("/(, (?:1[3-9]|\d+):\d{2})\s*pm\s*$/i", '$1', $date);
        // $this->logger->debug('date replace = ' . print_r( $date, true));

        if (preg_match('/^\d{1,2}\.\d{2}\.\d{4}, (\d{1,2}:\d{2}(?:\s*[ap]m)?)$/ui', $date)) {
            return strtotime($date);
        }

        return null;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
