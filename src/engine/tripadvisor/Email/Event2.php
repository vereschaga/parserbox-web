<?php

namespace AwardWallet\Engine\tripadvisor\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Event2 extends \TAccountChecker
{
    public $mailFiles = "tripadvisor/it-108068045.eml";
    public $subjects = [
        '/Confirmed:/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'thousands' => ',',
            'decimals'  => '.',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@t1.tripadvisor.com') !== false) {
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
        if ($this->http->XPath->query("//a[contains(@href, 'viator.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'TripAdvisor LLC') or contains(normalize-space(), 'Tripadvisor LLC')]")->length > 0
                && $this->http->XPath->query("//text()[contains(normalize-space(), 'Get ticket')]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]t1\.tripadvisor\.com$/', $from) > 0;
    }

    public function ParseEvent(Email $email)
    {
        $link = $this->http->FindSingleNode("//a[contains(normalize-space(), 'Get ticket')]/@href");
        $http2 = clone $this->http;
        $http2->GetURL($link);

        $e = $email->add()->event();

        $e->setEventType(4);

        $e->place()
            ->name($this->http->FindSingleNode("//text()[normalize-space()='Booking confirmed']/following::text()[normalize-space()][1]"));

        $address = $http2->FindSingleNode("//text()[normalize-space()='Departure Point']/following::text()[normalize-space()][1]", null, true, "/{$this->opt($this->t('Ports'))}\s*(.+)/");

        if (empty($address)) {
            $address = $http2->FindSingleNode("//text()[normalize-space()='Departure Point']/following::text()[normalize-space()][1]");
        }
        $e->place()
            ->address(strip_tags($address));

        $e->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Confirmation number:']/ancestor::tr[1]", null, true, "/\:\s*(\d{5,})/"), 'Confirmation number')
            ->status($this->http->FindSingleNode("//text()[normalize-space()='Status']/following::text()[normalize-space()][1]"));

        $traveller = $http2->FindSingleNode("//text()[normalize-space()='{$e->getName()}']/preceding::text()[normalize-space()][1]");

        if (!empty($traveller)) {
            $e->general()
                ->traveller($traveller, true);
        }

        $dateStart = $this->http->FindSingleNode("//text()[normalize-space()='Date']/following::text()[normalize-space()][1]");
        $timeStart = $this->http->FindSingleNode("//text()[normalize-space()='Time']/following::text()[normalize-space()][1]", null, true, "/\s*([\d\:]+)\s*$/");

        if (!empty($dateStart) && !empty($timeStart)) {
            $e->booked()
                ->guests($this->http->FindSingleNode("//text()[normalize-space()='Guests']/following::text()[normalize-space()][1]", null, true, "/(\d+)\s*Adults?/"))
                ->start(strtotime($dateStart . ' ' . $timeStart))
                ->noEnd();
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Paid']/following::text()[normalize-space()][1]", null, true, "/\D([\d\.\,]+)$/");
        $currency = $this->http->FindSingleNode("//text()[normalize-space()='Paid']/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z]{3})\s+/");

        if (!empty($total) && !empty($currency)) {
            if ($currency == 'EUR') {
                $e->price()
                    ->total(PriceHelper::cost($total, $this->t('decimals'), $this->t('thousands')))
                    ->currency($currency);
            } else {
                $e->price()
                    ->total(PriceHelper::cost($total, $this->t('thousands'), $this->t('decimals')))
                    ->currency($currency);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEvent($email);

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
