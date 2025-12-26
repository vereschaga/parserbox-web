<?php

namespace AwardWallet\Engine\goldcrown\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmation17V2 extends \TAccountChecker
{
    public $mailFiles = "goldcrown/it-62776321.eml";

    private $lang = '';
    private $reFrom = ['.bestwestern.com'];
    private $reProvider = ['Best Western'];
    private $reSubject = [
        'Your recent stay at Best Western Plus ',
    ];
    private $reBody = [
        'en' => [
            ['Thank you for choosing our hotel for your recent stay.', 'Room Number:'],
        ],
        'fr' => [
            ['Nous vous remercions davoir choisi notre hôtel pour votre séjour récent.', 'Numéro de chambre:'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'PAYMENT' => ['PAYMENT', 'AMERICAN EXPRESS', 'PYMNT'],
        ],
        'fr' => [
            'Confirmation Number'  => 'Numéro de confirmation',
            'Hotel Information'    => 'Informations sur lhôtel',
            'Guest'                => 'Clients',
            'Room'                 => 'Chambre',
            'PAYMENT'              => ['PAYMENT', 'AMERICAN EXPRESS', 'PYMNT'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $this->parseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseHotel(Email $email): void
    {
        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1]/p[1][{$this->contains($this->t('Confirmation Number'))}] ]/*[normalize-space()][2]/p[1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//*[count(*[normalize-space()])=2]/*[normalize-space()][1]/p[1][{$this->contains($this->t('Confirmation Number'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $checkIn = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1]/p[2][{$this->contains($this->t('Check-In'))}] ]/*[normalize-space()][2]/p[2]", null, true, '/^.*\d.*$/');
        $checkOut = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1]/p[3][{$this->contains($this->t('Check-Out'))}] ]/*[normalize-space()][2]/p[3]", null, true, '/^.*\d.*$/');
        $h->booked()
            ->checkIn2($checkIn)
            ->checkOut2($checkOut)
        ;

        if ($name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Information'))}]/../preceding-sibling::div[last()]")) {
            $h->general()->traveller($name);
        }

        $account = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Best Western Rewards #'))}]/following::text()[normalize-space()][1]", null, true, '/^(?:\W{3,}\d{4}|[-A-Z\d]{5,})$/');

        if (!empty($account)) {
            $h->program()->account($account, preg_match('/^[-\w]+$/', $account) !== 1);
        }

        if ($name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Information'))}]/../following-sibling::span[1]",
            null, false, '/^.*?(?:Best Western|\bBW\b).+?$/i')) {
            $h->hotel()->name($name);

            if ($address = $this->http->FindNodes("//text()[{$this->eq($this->t('Hotel Information'))}]/../following-sibling::span[1]/following-sibling::span")) {
                $h->hotel()->address(array_shift($address) . ', ' . join(' ', $address));
            }

            if ($phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Information'))}]/../following-sibling::a[contains(@href,'tel:')]")) {
                $h->hotel()->phone($phone);
            }
        }

        $h->booked()
            ->guests($this->http->FindSingleNode("//text()[{$this->starts($this->t('Guest'))}]/../preceding-sibling::*[normalize-space(.)][1]"), false, true)
            ->rooms($this->http->FindSingleNode("//text()[{$this->starts($this->t('Room'))}]/../preceding-sibling::*[normalize-space(.)][1]"), false, true);

        $roomNumberTitle = $this->http->FindSingleNode("//*[count(*[normalize-space()])=2]/*[normalize-space()][1]/p[position()>3 and last()][{$this->contains($this->t('Room Number'))}]", null, true, '/^(.+?)[\s:：]*$/u');
        $roomNumber = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1]/p[position()>3 and last()][{$this->contains($this->t('Room Number'))}] ]/*[normalize-space()][2]/p[position()>3 and last()]", null, true, '/^\d{1,6}$/');

        if ($h->getRoomsCount() === 1 && $roomNumberTitle && $roomNumber) {
            $room = $h->addRoom();
            $room->setDescription($roomNumberTitle . ': ' . $roomNumber);
        }

        $price = $this->http->FindSingleNode("//text()[{$this->contains($this->t('PAYMENT'))}]/ancestor::td[1]/following-sibling::td[last()]", null, false, '/\((.+?)\)/');

        if (preg_match('/^(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $price), $matches)) {
            $h->price()->total(PriceHelper::parse($matches['amount']));
        }
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
