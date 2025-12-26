<?php

namespace AwardWallet\Engine\marriott\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationReceived extends \TAccountChecker
{
    public $mailFiles = "marriott/it-111364808.eml, marriott/it-95762430.eml";
    public $subjects = [
        'Reservation Received',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Check-In:' => ['Check-In:', 'Check In:'],
            'Check-Out:' => ['Check-Out:', 'Check Out:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email-marriott.com') !== false) {
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
        return $this->http->XPath->query("//a[contains(@href, 'email-marriott.com')]")->length > 0
            && $this->http->XPath->query("//td[contains(normalize-space(), 'RESERVATION') and contains(normalize-space(), 'RECEIVED')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains('Your Payment Details')}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@email-marriott.com') !== false;
    }

    public function ParseHotel1(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reservation Number:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Reservation Number:'))}\s*([A-Z\d]+)\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Primary Guest')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Primary Guest'))}\s*(.+)/"))
            ->cancellation(implode(", ", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Cancellation Policy:')]/ancestor::tr[1]/following-sibling::tr[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), '•'))]")));

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thank you for booking with')]", null, true, "/{$this->opt($this->t('Thank you for booking with'))}\s*(\D+)\.\s/"))
            ->noAddress()
        ;

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-In:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Check-In:'))}\s*(.+)/")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-Out:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Check-Out:'))}\s*(.+)/")))
            ->guests($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Number of Guests')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Number of Guests'))}\s*(\d+)/"));

        $this->detectDeadLine($h);

        if ($this->http->XPath->query("//text()[normalize-space()='Total for Stay']/ancestor::tr[1]/descendant::text()[contains(normalize-space(), 'PTS')]")->length > 0) {
            $h->price()->spentAwards($this->http->FindSingleNode("//text()[normalize-space()='Total for Stay']/ancestor::tr[1]/descendant::text()[contains(normalize-space(), 'PTS')]"));
        } else {
            $h->price()
                ->total($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total for Stay')]/ancestor::tr[1]", null, true, "/([\d\,\.]+)\s*[A-Z]{3}/u"))
                ->currency($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total for Stay')]/ancestor::tr[1]", null, true, "/([A-Z]{3}$)/u"));
        }

        $count = count($h->toArray());
        if ($count >= 8) {
            $email->removeItinerary($h);
            $email->setIsJunk(true);
        }

    }

    public function ParseHotel2(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Reservation #:')]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//td[not(.//td)][".$this->starts('Primary Guest')." and following::td[normalize-space()][1][".$this->starts('Number of Guests')."]]"
                , null, true, "/{$this->opt($this->t('Primary Guest'))}\s*(.+)/"))
        ;

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Reservation #:')]/preceding::text()[normalize-space()][1][ancestor::a]"))
        ;

        $propertyUrl = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Reservation #:')]/preceding::text()[normalize-space()][1]/ancestor::a/@href[contains(., 'email-marriott.com')]");
        if (!empty($propertyUrl)) {
//            $this->logger->debug($propertyUrl);

            $http1 = clone $this->http;
            $http1->GetURL($propertyUrl);
            $address = $http1->FindSingleNode("//text()[normalize-space() = 'DESTINATION']/following::text()[normalize-space()][1]");
        }
        if (!empty($address)){
            $h->hotel()
                ->address($address);
        } else {
            $h->hotel()->noAddress();
        }


        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//td[not(.//td)][".$this->starts($this->t('Check-In:'))."]", null, true, "/{$this->opt($this->t('Check-In:'))}\s*(.+)/")))
            ->checkOut(strtotime($this->http->FindSingleNode("//td[not(.//td)][".$this->starts($this->t('Check-Out:'))."]", null, true, "/{$this->opt($this->t('Check-Out:'))}\s*(.+)/")))
            ->guests($this->http->FindSingleNode("//td[not(.//td)][".$this->starts('Number of Guests')." and preceding::td[normalize-space()][1][".$this->starts('Primary Guest')."]]"
                , null, true, "/{$this->opt($this->t('Number of Guests'))}\s*(\d+)\s*$/"));

        $this->detectDeadLine($h);

        if ($this->http->XPath->query("//text()[normalize-space()='Total for Stay']/ancestor::tr[1]/descendant::text()[contains(normalize-space(), 'PTS')]")->length > 0) {
            $h->price()->spentAwards($this->http->FindSingleNode("//text()[normalize-space()='Total for Stay']/ancestor::tr[1]/descendant::text()[contains(normalize-space(), 'PTS')]"));
        } else {
            $h->price()
                ->total($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total for Stay')]/ancestor::tr[1]", null, true, "/([\d\,\.]+)\s*[A-Z]{3}/u"))
                ->currency($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total for Stay')]/ancestor::tr[1]", null, true, "/([A-Z]{3}$)/u"));
        }

    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $type = '';
        $path = "//td[not(.//td)][".$this->starts($this->t('Check-In:'))."]/following::td[not(.//td)][normalize-space()][1][".$this->starts($this->t('Check-Out:'))."]";
        if (!empty($this->http->FindSingleNode($path))) {
            // Check In:                        Check Out:
            // Tuesday, December 7, 2021        Saturday, December 11, 2021
            $type = '2';
            $this->ParseHotel2($email);
        } else {
            // Check-In:                   Sunday, July 18, 2021
            // Check-Out:                Thursday, July 22, 2021
            $type = '1';
            $this->ParseHotel1($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
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
        if (preg_match('/More than (\d+ days?) before arrival/', $h->getCancellation(), $m)) {
            $h->booked()->deadlineRelative($m[1]);
        }
    }
}
