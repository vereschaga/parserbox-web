<?php

namespace AwardWallet\Engine\thon\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "thon/it-521679961.eml, thon/it-525132053.eml";
    public $subjects = [
        'Booking Confirmation Thon Hotel',
    ];

    public $lang = '';

    public static $dictionary = [
        "en" => [
            'rooms' => ['rooms', 'room'],
        ],
        "de" => [
            'Download the Thon Hotels app'  => '',
            'Room '                         => 'Zimmer ',
            'Payment'                       => 'Zahlung',
            'Ref.'                          => 'Buchungsnr.',
            'Address'                       => 'Adresse',
            'Telephone'                     => 'Telefon',
            'adults'                        => 'Erwachsene',
            //'child' => '',
            'rooms'            => 'Zimmer',
            'Total'            => 'Gesamtsumme',
            'rate'             => 'pakke',
            'Check-in from'    => 'Check-in ab',
            'Check out before' => 'Check-out bis',
            'Your reservation' => 'Ihre Buchung',
        ],
    ];

    public $detectLang = [
        "en" => ['Room '],
        "de" => ['Zimmer '],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@e.thonhotels.no') !== false) {
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
        $this->assignLang();

        return $this->http->XPath->query("//text()[{$this->contains($this->t('Download the Thon Hotels app'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('Room '))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Payment'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]e\.thonhotels\.no$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Ref.'))}]", null, true, "/^{$this->opt($this->t('Ref.'))}\s*(\d+)$/"), 'Ref.')
            ->travellers(array_unique(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Room '))}]/following::text()[normalize-space()][1]/ancestor::table[1]/descendant::text()[normalize-space()][last()]"))), true);

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('Ref.'))}]/preceding::text()[{$this->eq($this->t('Your reservation'))}][1]/following::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]"))
            ->phone($this->http->FindSingleNode("//text()[{$this->eq($this->t('Telephone'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]"));

        $resInfo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Ref.'))}]/following::text()[{$this->contains($this->t('adults'))}][1]");

        if (preg_match("/^(?<rooms>\d+)\s*{$this->opt($this->t('rooms'))}\,\s*.*\,\s*(?<adults>\d+)\s*{$this->opt($this->t('adults'))}(?:\s*\,\s*(?<kids>\d+)\s*{$this->opt($this->t('child'))})?$/u", $resInfo, $m)) {
            $h->booked()
                ->rooms($m['rooms'])
                ->guests($m['adults']);

            if (isset($m['kids']) && !empty($m['kids'])) {
                $h->booked()
                    ->kids($m['kids']);
            }
        }

        $checkIn = $this->http->FindSingleNode("//img[contains(@src, 'TH_Arrow')]/preceding::div[1]");
        $checkOut = $this->http->FindSingleNode("//img[contains(@src, 'TH_Arrow')]/following::div[1]");

        if (preg_match("/^{$this->opt($this->t('Check-in from'))}/", $checkIn) || preg_match("/\d{4}$/", $checkOut)) {
            $checkIn = $this->http->FindSingleNode("//img[contains(@src, 'TH_Arrow')]/preceding::div[1]/ancestor::td[1]");
            $checkOut = $this->http->FindSingleNode("//img[contains(@src, 'TH_Arrow')]/following::div[1]/ancestor::td[1]");
        }

        $h->booked()
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut));

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,\s]+)/", $price, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $roomNodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Room '))}]");

        foreach ($roomNodes as $key => $roomRoot) {
            $room = $h->addRoom();
            $key++;

            $room->setType($this->http->FindSingleNode("./following::text()[normalize-space()][1]", $roomRoot));

            $roomTr = $this->t('Room ');
            $rate = $this->http->FindNodes("//text()[{$this->starts($roomTr . $key)}]/following::text()[normalize-space()][1]/ancestor::table[1]/following::table[{$this->contains($this->t('rate'))}][1]/descendant::tr[normalize-space()][not({$this->contains($this->t('rate'))})]/descendant::tr", null, "/^(.+[\d\s\.\,]+)$/");

            if (count($rate) == 0) {
                $rate = $this->http->FindNodes("//text()[{$this->starts($roomTr . $key)}]/following::text()[normalize-space()][1]/ancestor::table[1]/following::table[{$this->contains($this->t('rate'))}][1]/descendant::tr[normalize-space()][not({$this->contains($this->t('rate'))})]", null, "/^(.+[\d\s\.\,]+)$/");
            }

            if (count(array_filter(array_unique($rate))) > 0) {
                $room->setRates(array_filter(array_unique($rate)));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
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

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\s*(\d+)\.\s*(\w+\s*\d{4})\s*(?:{$this->opt($this->t('Check-in from'))}|{$this->opt($this->t('Check out before'))})\s*(\d+)[\.\:]*(\d+)$#u", //Wed 27. Sep 2023 Check-in from 15.00
        ];
        $out = [
            "$1 $2 $3:$4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
