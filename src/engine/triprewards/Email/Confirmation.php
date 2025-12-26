<?php

namespace AwardWallet\Engine\triprewards\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "triprewards/it-75899634.eml";
    public $subjects = [
        '/Club Wyndham SP Confirmation [A-Z\d]+$/u',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Member Number:' => ['Member Number:', 'Ownership Number:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@hitachivantara.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Wyndham')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('We are looking forward to welcoming you soon'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your booking at'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]hitachivantara\.com$/', $from) > 0;
    }

    public function ParseEmail(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//tr[starts-with(normalize-space(), 'Confirmation number:')]/descendant::td[last()]"))
            ->cancellation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Cancel by')]/ancestor::*[1]"))
            ->travellers(explode('&', $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'We are looking forward')]/following::text()[normalize-space()][1]")), true);

        $h->program()
            ->account($this->http->FindSingleNode("//text()[{$this->eq($this->t('Member Number:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Member Number:'))}\s*(\d+)/"), false);

        $hotelNameText = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Your booking at')]/ancestor::td[1]");

        if (preg_match("/^{$this->opt($this->t('Your booking at'))}\s*(.+)\sis\s(\w+)$/", $hotelNameText, $m)) {
            $h->hotel()->name($m[1]);
            $h->general()->status($m[2]);
        }

        $h->hotel()
            ->address($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Address:')]/ancestor::tr[1]/descendant::td[last()]"))
            ->phone($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Telephone:')]/ancestor::tr[1]/descendant::td[last()]"));

        $fax = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Fax:')]/ancestor::tr[1]/descendant::td[last()]");

        if (!empty($fax)) {
            $h->hotel()->fax($fax);
        }

        $checkInDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'We will welcome you on:')]/ancestor::tr[1]/descendant::td[last()]");
        $checkInTime = str_replace('noon', 'pm', $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-in Time:')]/ancestor::tr[1]/descendant::td[last()]"));

        $checkOutDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'We will say goodbye on:')]/ancestor::tr[1]/descendant::td[last()]");
        $checkOutTime = str_replace('noon', 'pm', $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-out Time:')]/ancestor::tr[1]/descendant::td[last()]"));

        $h->booked()
            ->checkIn(strtotime($checkInDate . ', ' . $checkInTime))
            ->checkOut(strtotime($checkOutDate . ', ' . $checkOutTime));

        $roomType = $this->http->FindSingleNode("//tr[starts-with(normalize-space(), 'We have booked you into:')]/descendant::td[last()]");

        if (!empty($roomType)) {
            $room = $h->addRoom();
            $room->setType($roomType);

            $roomDesc = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Room Amenities:')]/following::text()[normalize-space()][1]");

            if (!empty($roomDesc)) {
                $room->setDescription($roomDesc);
            }
        }

        $this->detectDeadLine($h, $h->getCancellation());

        return true;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function normalizeDate($date)
    {
        $this->logger->warning($date);

        $in = [
            '/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}:\d{1,2})\s*$/', // 31/12/2018 14:05
            '/^(\d{1,2})\/(\d{1,2})$/', // 28/12
        ];
        $out = [
            '$3-$2-$1 $4',
            '$2/$1',
        ];

        $date = preg_replace($in, $out, $date);

        return strtotime($date);
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
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

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, $cancellationText)
    {
        //Here we describe various variations in the definition of dates deadLine
        if (preg_match("#Cancel by (\d+\s*\w+\s*\d{4}) to avoid any penalties#u", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m[1]));
        }
    }
}
