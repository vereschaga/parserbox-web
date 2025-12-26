<?php

namespace AwardWallet\Engine\shangrila\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationAt extends \TAccountChecker
{
    public $mailFiles = "shangrila/it-440701390.eml";
    public $subjects = [
        'Your Reservation at',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@shangri-la.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Shangri-La International Hotel Management')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('need to know for your reservation at'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('CONFIRMATION NO.'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]shangri\-la\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space() = 'CONFIRMATION NO.']/following::text()[string-length()>5][1]", null, true, "/([\d]{5,})/"), 'CONFIRMATION NO.')
            ->traveller($this->http->FindSingleNode("//text()[normalize-space() = 'CONFIRMATION NO.']/preceding::text()[string-length()>3][1]"), true)
            ->cancellation($this->http->FindSingleNode("//text()[normalize-space()='CANCELLATION POLICY']/following::text()[string-length()>2][1]"));

        $account = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'SHANGRI-LA CIRCLE')]/following::text()[string-length()>3][1]", null, true, "/^\s*(\d+[x]+)/");

        if (!empty($account)) {
            $h->program()
                ->account($account, true);
        }

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[normalize-space()='ARRIVAL']/following::text()[string-length()>2][1]", null, true, "/^(.+)\s*\(/")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[normalize-space()='DEPARTURE']/following::text()[string-length()>2][1]", null, true, "/^(.+)\s*\(/")));

        $guestsText = $this->http->FindSingleNode("//text()[normalize-space()='NO. OF GUEST(S)']/following::text()[string-length()>2][1]");

        if (preg_match("/^(?<adults>\d+)\s*Adults\,\s*(?<kids>\d+)\s*Childs?$/", $guestsText, $m)) {
            $h->booked()
                ->guests($m['adults'])
                ->kids($m['kids']);
        }

        $roomType = $this->http->FindSingleNode("//text()[normalize-space()='ROOM TYPE']/following::text()[string-length()>2][1]/ancestor::tr[1]");
        $h->addRoom()->setType($roomType);

        if ($this->http->XPath->query("//text()[normalize-space()='TOTAL CASH TO PAY']")->length > 0) {
            $total = $this->http->FindSingleNode("//text()[normalize-space()='TOTAL CASH TO PAY']/following::text()[string-length()>2][1]");

            if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>[\d\,\.]+)/", $total, $m)) {
                $h->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);
            }

            $spentAwards = $this->http->FindSingleNode("//text()[normalize-space()='TOTAL POINTS REDEEMED']/following::text()[normalize-space()][1]");

            if (!empty($spentAwards)) {
                $h->price()
                    ->spentAwards($spentAwards);
            }
        } else {
            $total = $this->http->FindSingleNode("//text()[normalize-space()='TOTAL COST']/following::text()[string-length()>2][1]/ancestor::tr[1]/following::text()[normalize-space()][1]");

            if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>[\d\,\.]+)/", $total, $m)) {
                $h->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);
            }
        }

        $hotelName = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Here is what you')]", null, true, "/{$this->opt($this->t('your reservation at'))}\s*(.+)\:/");
        $h->hotel()
            ->name($hotelName)
            ->address($this->http->FindSingleNode("//img[contains(@src, 'address')]/ancestor::tr[2]"))
            ->phone($this->http->FindSingleNode("//img[contains(@src, 'tel')]/ancestor::tr[2]"));

        $this->detectDeadLine($h);
    }

    public function detectDeadLine($h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Room cancelled after (?<time>[\d\:]+) local hotel time on (?<date>\d+\s*\w+\s*\d{4}) will be/", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m['date'] . ', ' . $m['time']));
        }
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
}
