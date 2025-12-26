<?php

namespace AwardWallet\Engine\rwvegas\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ResortConfirmation extends \TAccountChecker
{
    public $mailFiles = "rwvegas/it-644351856.eml, rwvegas/it-645585789.eml";
    public $subjects = [
        '/^Resort.*Confirmation Number\:\d+$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@rwlasvegas.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Resorts World Las Vegas')]")->length > 0
            && ($this->http->XPath->query("//img[contains(@src, 'reservation-details')]")->length > 0
             || $this->http->XPath->query("//text()[normalize-space()='RESERVATION DETAILS']")->length > 0)
            && $this->http->XPath->query("//text()[{$this->contains($this->t('NO. OF GUESTS:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]rwlasvegas\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[normalize-space()='GUEST NAME:']/ancestor::td[1]/following::td[1]"))
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='CONFIRMATION NUMBER:']/ancestor::td[1]/following::td[1]"));

        $cancellation = $this->http->FindSingleNode("//text()[normalize-space()='Cancellation Policy']/following::text()[normalize-space()][1]");

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        $guestInfo = $this->http->FindSingleNode("//text()[normalize-space()='NO. OF GUESTS:']/ancestor::td[1]/following::td[1]");

        if (preg_match("/Adults\:\s*(?<adults>\d+)\s*\/\s*Children\:\s*(?<kids>\d+)/", $guestInfo, $m)) {
            $h->booked()
                ->guests($m['adults'])
                ->kids($m['kids']);
        }

        $roomType = $this->http->FindSingleNode("//text()[normalize-space()='ROOM TYPE:']/ancestor::td[1]/following::td[1]");

        if (!empty($roomType)) {
            $h->addRoom()->setType($roomType);
        }

        $hotelName = trim($this->http->FindSingleNode("//text()[normalize-space()='MAP IT']/ancestor::tr[1]/descendant::p[1]"));

        if (empty($hotelName)) {
            $hotelName = trim($this->http->FindSingleNode("//text()[normalize-space()='See you soon,']/following::text()[contains(normalize-space(), 'Team')][1]", null, true, "/^The\s*(.+)\s*Team/"));
        }

        $h->hotel()
            ->name($hotelName);

        $addressInfo = $this->http->FindSingleNode("//text()[normalize-space()='MAP IT']/ancestor::tr[1]/descendant::p[2]");

        if (preg_match("/(?<address>.+)\s+(?<phone>[\(\)\d\-\s]{10,})/", $addressInfo, $m)) {
            $h->hotel()
                ->address($m['address'])
                ->phone($m['phone']);
        }

        //Resorts World Las Vegas
        if (empty($addressInfo) && !empty($hotelName)) {
            if (stripos($hotelName, 'Resorts World Las Vegas') !== false) {
                $h->hotel()
                    ->address('999 Resorts World Avenue')
                    ->phone('(702) 676-7000');
            }
        }

        $arrivalDate = $this->http->FindSingleNode("//text()[normalize-space()='ARRIVAL DATE:']/ancestor::td[1]/following::td[1]");
        $arrivalTime = $this->http->FindSingleNode("//text()[normalize-space()='CHECK-IN TIME:']/ancestor::td[1]/following::td[1]", null, true, "/^([\d\:]+\s*a?\.?p?\.?m\.?)$/i");
        $h->booked()
            ->checkIn(strtotime($arrivalDate . ', ' . $arrivalTime));

        $departuteDate = $this->http->FindSingleNode("//text()[normalize-space()='DEPARTURE DATE:']/ancestor::td[1]/following::td[1]");
        $departuteTime = $this->http->FindSingleNode("//text()[normalize-space()='CHECK-OUT TIME:']/ancestor::td[1]/following::td[1]", null, true, "/^([\d\:]+\s*a?\.?p?\.?m\.?)$/i");
        $h->booked()
            ->checkOut(strtotime($departuteDate . ', ' . $departuteTime));

        $this->detectDeadLine($h);

        $nodes = $this->http->XPath->query("//text()[normalize-space()='ROOM TYPE:']/following::text()[normalize-space()='PROMOTIONAL OFFER:']/ancestor::table[1]/following::table[contains(normalize-space(), '/')]/descendant::tr");
        $i = 0;

        foreach ($nodes as $root) {
            $day = $this->http->FindSingleNode("./td[normalize-space()][1]", $root);
            $price = $this->http->FindSingleNode("./td[normalize-space()][2]", $root, true, "/^(\D{1,3}\s*0.00)$/");

            if (!empty($day) && !empty($price)) {
                $i++;
            }
        }

        if (!empty($i)) {
            $h->setFreeNights($i);
        }

        $price = $this->http->FindSingleNode("//text()[normalize-space()='ROOM TYPE:']/following::text()[normalize-space()='GRAND TOTAL:']/ancestor::td[1]/following::td[1]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)$/", $price, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='ROOM TYPE:']/following::text()[normalize-space()='ROOM TAXES & FEES:']/ancestor::td[1]/following::td[1]", null, true, "/^\D{1,3}\s*([\d\,\.]+)/");

            if ($tax !== null) {
                $h->price()
                    ->tax($tax);
            }
        }
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

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }
        // you can cancel or modify your booking free of charge by 3PM, 24 hours prior to your arrival
        if (preg_match('/Cancel by\s*(?<hours>\d+\s*A?P?M)\s*local hotel time at least\s*(?<prior>\d+\s*days?)\s*prior to arrival to avoid in the full deposit being forfeited./',
                $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'], $m['hours']);
        }
    }
}
