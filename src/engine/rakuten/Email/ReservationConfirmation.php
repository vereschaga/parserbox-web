<?php

namespace AwardWallet\Engine\rakuten\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "rakuten/it-640508156.eml, rakuten/it-641416036.eml";
    public $subjects = [
        '「楽天トラベル」予約完了メール ',
    ];

    public $lang = 'ja';

    public static $dictionary = [
        "ja" => [
            'Rakuten Travel'      => '楽天トラベル',
            'Reservation details' => 'ご予約内容',
            //'Canceled reservation details' => '',
            'Price summary' => '宿泊料金合計',

            'Reservation number'                      => ['予約受付番号', '予約番号'],
            'Guest information'                       => ['宿泊者氏名', '宿泊代表者氏名'],
            'Cancellation policy'                     => 'キャンセルについて',
            'Total Price'                             => '総合計',
            'Rakuten points (scheduled to be earned)' => '楽天ポイント（獲得予定）',
            'points'                                  => 'ポイント',
            'Address'                                 => ['住所', '宿泊施設住所'],
            'Phone'                                   => '宿泊施設電話番号',
            'Check-in'                                => ['チェックイン', 'チェックイン日時'],
            'Check-out'                               => ['チェックアウト', 'チェックアウト日'],
            'Number of rooms'                         => '申込部屋数',
            'Number of guests (per room)'             => '申込人数',
            'adult'                                   => '大人',
            'child'                                   => '子供',
            'Room'                                    => ['部屋タイプ'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mail.travel.rakuten.co.jp') !== false) {
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
        return $this->http->XPath->query("//text()[{$this->contains($this->t('Rakuten Travel'))}]")->length > 0
            && ($this->http->XPath->query("//text()[{$this->contains($this->t('Reservation details'))}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('Canceled reservation details'))}]")->length > 0)
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Price summary'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mail\.travel\.rakuten\.co\.jp$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->hotelHTML($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function hotelHTML(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation number'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-z\d]+)(?:$|\,)/"))
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Guest information'))}]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()]"));

        $cancellation = implode(", ", $this->http->FindNodes("//text()[{$this->eq($this->t('Cancellation policy'))}]/following::table[1]/descendant::tr[normalize-space()]"));

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Price'))}]/following::text()[normalize-space()][1]", null, true, "/(?:\＝|\:)([\d\.\,]+\s*\D{1,3})$/u");

        if (preg_match("/^(?<total>[\d\.\,]+)\s*(?<currency>\D{1,3})\s*/", $price, $m)) {
            $m['currency'] = preg_replace("/(円)/u", 'JPY', $m['currency']);
            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $earned = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rakuten points (scheduled to be earned)'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+\s*{$this->opt($this->t('points'))})/");

            if (!empty($earned)) {
                $h->setEarnedAwards($earned);
            }
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/preceding::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/following::text()[normalize-space()][1]"));

        $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Phone'))}]/following::text()[normalize-space()][1]");

        if (!empty($phone)) {
            $h->hotel()
                ->phone($phone);
        }

        $checkIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in'))}]/following::text()[normalize-space()][1]");
        $checkOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out'))}]/following::text()[normalize-space()][1]");

        $h->booked()
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut));

        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room'))}]/following::text()[normalize-space()][1]/ancestor::td[1]");

        if (!empty($roomType)) {
            $h->addRoom()->setType($roomType);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Canceled reservation details'))}]")->length > 0) {
            $h->general()
                ->cancelled()
                ->status('cancelled');

            return $email;
        }

        $h->booked()
            ->rooms($this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of rooms'))}]/following::text()[normalize-space()][1]", null, true, "/^\d+/"))
            ->guests($this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of guests (per room)'))}]/ancestor::td[1]/following::text()[normalize-space()][1]", null, true, "/{$this->opt($this->t('adult'))}[\：\s]*(\d+)/u"));

        $kids = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of guests (per room)'))}]/ancestor::td[1]/following::text()[normalize-space()][1]", null, true, "/{$this->opt($this->t('child'))}[\：\s]*(\d+)/u");

        if ($kids !== null) {
            $h->booked()
                ->kids($kids);
        }

        $this->detectDeadLine($h);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^(\d{4})\-(\d+)\-(\d+)\s*\(\D{1,2}\)\s*([\d\:]+)$#u", //2024-02-09(金) 22:00
            "#^(\d{4})\-(\d+)\-(\d+)\s*\(\D{1,2}\)\s*$#u", //2024-02-10(土)
        ];
        $out = [
            "$3.$2.$1, $4",
            "$3.$2.$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Free cancellation until\s*(\w+\s*\d+\,.*A?P?M?)\(/", $cancellationText, $m)) {
            $h->booked()
                ->deadline($this->normalizeDate($m[1]));
        }

        if (preg_match("/^(\d+\s*day)\(s\)\s*before your stay\s*\(from\s*([\d\:]+)\)/u", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1], $m[2]);
        }
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }
}
