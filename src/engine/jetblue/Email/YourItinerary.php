<?php

namespace AwardWallet\Engine\jetblue\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourItinerary extends \TAccountChecker
{
    public $mailFiles = "jetblue/it-84689227.eml";
    public $subjects = [
        '/Your itinerary for your upcoming JetBlue Vacations trip\./',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'endTraveler' => [', DEPART', ', Flight confirmation code'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.jetblue.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'JetBlue Airways')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your flight confirmation code:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your vacations booking number:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.jetblue\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $xpath = "//text()[starts-with(normalize-space(), 'Flight confirmation code')]/preceding::text()[contains(normalize-space(), 'DEPART /')]/ancestor::table[1]/descendant::tr[last()][not(contains(normalize-space(), 'code'))]";
        $this->logger->warning($xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $number => $root) {
            $f = $email->add()->flight();

            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./following::table[2]/descendant::text()[normalize-space()][3]", $root))
                ->number($this->http->FindSingleNode("./following::table[2]/descendant::text()[normalize-space()][2]", $root));

            $codeInfo = $this->http->FindSingleNode("./following::table[1]", $root);

            if (preg_match("/([A-Z]{3})\s*to\s*([A-Z]{3})/", $codeInfo, $m)) {
                $s->departure()
                    ->date(strtotime($this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $root)))
                    ->code($m[1]);

                $s->arrival()
                    ->date(strtotime($this->http->FindSingleNode("./descendant::text()[normalize-space()][2]", $root)))
                    ->code($m[2]);
            }

            $travellerInfo = implode(', ', $this->http->FindNodes("./following::text()[normalize-space() = 'TRAVELER'][1]/ancestor::tr[1]/following-sibling::tr/td[normalize-space()][1]", $root));
            $f->general()
                ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'DEPART /')]/preceding::text()[starts-with(normalize-space(), 'Flight confirmation code:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Flight confirmation code:'))}\s*([A-Z\d]+)/u"))
                ->travellers(explode(',', $this->re("/^(\D+){$this->opt($this->t('endTraveler'))}/u", $travellerInfo)));

            $flightInfo = implode(', ', $this->http->FindNodes("./following::text()[normalize-space() = 'TRAVELER'][1]/ancestor::table[1]", $root));
            $accounts = [];

            foreach ($f->getTravellers() as $traveller) {
                $account = $this->re("/{$traveller[0]}\s*(\d+)/", $flightInfo);

                if (!empty($account)) {
                    $accounts[] = $account;
                }
            }

            if (count($accounts) > 0) {
                $f->setAccountNumbers($accounts, false);
            }
        }
    }

    public function ParseHotel(Email $email)
    {
        $xpath = "//text()[normalize-space()='ROOM TYPE']";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $h->general()
                ->traveller($this->http->FindSingleNode("./ancestor::tr[1]/following::tr[1]/descendant::td[normalize-space()][1]", $root))
                ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your vacations booking number:')]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Your vacations booking number:'))}\s*(\d+)/"));

            $h->hotel()
                ->name($this->http->FindSingleNode("./ancestor::tr[1]/preceding::tr[normalize-space()][1]/descendant::td[normalize-space()][3]/descendant::text()[normalize-space()][1]", $root))
                ->address($this->http->FindSingleNode("./ancestor::tr[1]/preceding::tr[normalize-space()][1]/descendant::td[normalize-space()][3]/descendant::text()[normalize-space()][2]", $root));

            $h->booked()
                ->checkIn(strtotime($this->http->FindSingleNode("./ancestor::tr[1]/preceding::tr[normalize-space()][1]/descendant::td[normalize-space()][1]", $root)))
                ->checkOut(strtotime($this->http->FindSingleNode("./ancestor::tr[1]/preceding::tr[normalize-space()][1]/descendant::td[normalize-space()][2]", $root)))
                ->guests($this->http->FindSingleNode("./ancestor::tr[1]/following::tr[1]/descendant::td[normalize-space()][4]", $root))
                ->kids($this->http->FindSingleNode("./ancestor::tr[1]/following::tr[1]/descendant::td[normalize-space()][5]", $root));

            if (!empty($type = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[1]/descendant::td[normalize-space()][2]", $root))) {
                $room = $h->addRoom();
                $room->setType($type);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Your flight confirmation code')]")->length > 0) {
            $this->ParseFlight($email);
        }

        if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Your vacations booking number')]")->length > 0) {
            $this->ParseHotel($email);
        }

        $otaPrice = array_unique(array_filter($this->http->FindNodes("//text()[normalize-space()='TOTAL']/ancestor::tr[1]/following::tr[1]/descendant::td[normalize-space()][2]", null, "/([\d\,\.]+\s*[A-Z]{3})$/")));

        if (count($otaPrice) == 1) {
            $email->price()
                ->total(cost($this->re("/^([\d\,\.]+)\s*[A-Z]{3}$/", $otaPrice[0])))
                ->currency($this->re("/^[\d\,\.]+\s*([A-Z]{3})$/", $otaPrice[0]));
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
        return 0;
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
