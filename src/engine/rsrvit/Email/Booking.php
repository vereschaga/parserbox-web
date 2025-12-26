<?php

namespace AwardWallet\Engine\rsrvit\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "rsrvit/it-431036356.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Thank you for your booking request on' => 'Thank you for your booking request on',
            'at'                                    => 'at',
            'Your request has been submitted'       => 'Your request has been submitted',
        ],
    ];

    private $detectFrom = "@rsrvit.com";
    private $detectSubject = [
        // en
        // Thank you for your booking - Not Only Fish
        'Thank you for your booking - ',
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
            if (!empty($dict["Thank you for your booking request on"]) && !empty($dict["at"]) && !empty($dict["Your request has been submitted"])) {
                if ($this->http->XPath->query("//text()[{$this->contains($dict['Thank you for your booking request on'])}]/ancestor::*[position() < 3][{$this->contains($dict['at'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->starts($dict['Your request has been submitted'])}]")->length > 0
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
        $event = $email->add()->event();

        // Type
        $event->type()->restaurant();

        // General
        $event->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Hello'))}]/following::text()[normalize-space()][1][following::text()[normalize-space()][1][normalize-space()=',']]"))
        ;

        // Place
        // Booked
        $text = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Thank you for your booking request on'))}]/ancestor::*[position() < 3][{$this->contains($this->t('at'))}]");

        if (preg_match("/{$this->opt($this->t('Thank you for your booking request on'))}\s+\W?(.+?)\W?\s+{$this->opt($this->t('at'))}\s+(.+\d{4}.+)\./", $text, $m)) {
            $name = $m[1];
            $event->place()
                ->name($name);

            $event->booked()
                ->start($this->normalizeDate($m[2]))
                ->noEnd();

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
        } else {
            $event->place()
                ->name(null);
        }

        if (empty($event->getAddress())) {
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
            '/^\s*(\d+)\\/(\d+)\\/(\d{4})\s+at\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1.$2.$3, $4',
        ];

        $date = preg_replace($in, $out, $date);
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
