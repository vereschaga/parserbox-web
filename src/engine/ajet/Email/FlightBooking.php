<?php

namespace AwardWallet\Engine\ajet\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightBooking extends \TAccountChecker
{
    public $mailFiles = "ajet/it-712666762.eml, ajet/it-712848084.eml, ajet/it-715267461.eml, ajet/it-732892941.eml, ajet/it-809595193.eml";
    public $subjects = [
        'Ticket information',
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Direct'  => ['Direct', 'Connecting'],
            'hours'   => ['h'],
            'minutes' => ['m'],
        ],
        'tr' => [
            'Ticket Information'    => 'Bilet Bilgileri',
            'Passenger Information' => 'Yolcu Bilgileri',
            'Manage My Flights'     => 'Uçuşlarımı Yönet',

            'Reservation Code'                 => 'Rezervasyon Kodu',
            'Transaction Date'                 => 'İşlem Tarihi',
            'Dear'                             => 'Sayın',
            'Total Fare'                       => 'Toplam Ücret',
            'Ticket No'                        => 'Bilet No',
            'Seat'                             => 'Koltuk',
            'Flight and Passenger Information' => 'Uçuş ve Yolcu Bilgileri',
            'Direct'                           => 'Direkt',
            //'Meal:' => '',
            'hours'   => ['S'],
            'minutes' => ['DK'],
        ],
    ];
    public $detectLang = [
        "en" => ["Flight Information"],
        "tr" => ["Uçuş Bilgileri"],
    ];

    public function detectEmailByHeaders(array $headers): bool
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mail.ajet.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser): bool
    {
        $this->assignLang();

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('AJet Hava Taşımacılığı Anonim Şirketi'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Ticket Information'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Passenger Information'))}]")->length > 0
            && $this->http->XPath->query("//a[{$this->eq($this->t('Manage My Flights'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from): bool
    {
        return preg_match('/[@.]ajet\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->assignLang();
        $this->Flight($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation Code'))}]/ancestor::td[normalize-space()][1]", null, false, "/^{$this->opt($this->t('Reservation Code'))}\s*([A-Z\d]{6})\s+/"), $this->t('Reservation Code'))
            ->date(strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Transaction Date'))}]/ancestor::td[normalize-space()][1]", null, false, "/^{$this->opt($this->t('Transaction Date'))}\s*(\d+\.\d+\.\d{4})\s*{$this->opt($this->t('Dear'))}/")));

        $priceInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Fare'))}]/ancestor::td[normalize-space()][1]", null, false, "/^{$this->opt($this->t('Total Fare'))}\s*(.+)\s*$/");

        if (preg_match("/^(?<price>[\d\.\,\']+)\s*(?<currency>[A-Z]{3})$/", $priceInfo, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['price'], $m['currency']))
                ->currency($m['currency']);
        }

        $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Passenger Information'))}]/ancestor::tr[normalize-space(.)][1]/following-sibling::tr[normalize-space(.)]/descendant::tr[normalize-space(.)]/td[normalize-space(.)][1]", null, "/^{$this->opt($this->t('Dear'))}\s+(?<passName>\D+)$/");
        $f->setTravellers(array_unique($travellers), true);

        $tickets = $this->http->FindNodes("//text()[{$this->eq($this->t('Passenger Information'))}]/ancestor::tr[normalize-space(.)][1]/following-sibling::tr[normalize-space(.)]/descendant::tr[normalize-space(.)]/td[normalize-space(.)][3]", null, "/^{$this->opt($this->t('Ticket No'))}\s+(?<ticketNumber>\d+)\s+{$this->opt($this->t('Seat'))}/");

        foreach (array_unique($tickets) as $ticket) {
            $traveller = $this->http->FindNodes("//text()[{$this->eq($this->t('Passenger Information'))}]/ancestor::tr[normalize-space(.)][1]/following-sibling::tr[normalize-space(.)]/descendant::tr[{$this->contains($this->t($ticket))}]/td[normalize-space(.)][1]", null, "/^{$this->opt($this->t('Dear'))}\s+(?<passName>\D+)$/")[0];
            $f->addTicketNumber($ticket, false, $traveller);
        }

        $segmentNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Flight and Passenger Information'))}]/ancestor::tr[normalize-space(.)][1]");

        foreach ($segmentNodes as $root) {
            $s = $f->addSegment();

            $ticketInfo = $this->http->XPath->query("./following-sibling::tr[normalize-space(.)][1][{$this->contains($this->t('Ticket Information'))}]/descendant::table[normalize-space(.)][2]", $root)[0];

            $depDate = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $ticketInfo, true, "/^\d+\s+\w+\s+\d{4}$/u");

            $airInfo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Direct'))}]/ancestor::tr[normalize-space()][1]", $ticketInfo, true, "/^{$this->opt($this->t('Direct'))}\s+(.+?)\s*{$this->opt($this->t('Class'))}$/");

            if (preg_match("/^(?<duration>(?:\d+{$this->opt($this->t('hours'))})?\s*(?:\d+{$this->opt($this->t('minutes'))})?)\s+(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})\s+(?<cabin>.+?)\s*\/\s*(?<bookingCode>[A-Z\d]{1,2})$/i", $airInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $s->extra()
                    ->duration($m['duration'])
                    ->cabin($m['cabin'] . ' Class')
                    ->bookingCode($m['bookingCode']);
            }

            $depInfo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Direct'))}]/ancestor::tr[normalize-space(.)][1]/preceding-sibling::tr[normalize-space(.)][1]/td[normalize-space(.)][1]", $ticketInfo);

            if (preg_match("/^(?<depName>.+?)\s+(?<depCode>[A-Z]{3})\s+(?<depTime>\d+:\d+)$/", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($depDate . ' ' . $m['depTime']));
            }

            $arrInfo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Direct'))}]/ancestor::tr[normalize-space(.)][1]/preceding-sibling::tr[normalize-space(.)][1]/td[normalize-space(.)][2]", $ticketInfo);

            if (preg_match("/^(?<arrName>.+?)\s+(?<arrCode>[A-Z]{3})\s+(?<arrTime>\d+\:\d+)$/", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->date($this->normalizeDate($depDate . ' ' . $m['arrTime']));
            }

            $seatNodes = $this->http->XPath->query("./following-sibling::tr[normalize-space(.)][2][{$this->contains($this->t('Passenger Information'))}]/descendant::tr[{$this->eq($this->t('Passenger Information'))}]/following-sibling::tr[normalize-space(.)]", $root);

            foreach ($seatNodes as $seatNode) {
                $traveller = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Dear'))}]/ancestor::td[normalize-space(.)][1]", $seatNode, true, "/^{$this->opt($this->t('Dear'))}\s+(?<passName>\D+)$/");
                $seat = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Ticket No'))}]/ancestor::td[normalize-space(.)][1]", $seatNode);

                if (preg_match("/^{$this->opt($this->t('Ticket No'))}.+?{$this->opt($this->t('Seat'))}\s*(?<seatNumber>\d+[A-Z])?$/", $seat, $m)) {
                    if (!empty($m['seatNumber'])) {
                        $s->addSeat($m['seatNumber'], false, false, $traveller);
                    }
                }
            }

            $meals = $this->http->FindNodes("//text()[{$this->eq(mb_strtoupper($s->getDepName()) . ' - ' . mb_strtoupper($s->getArrName()))}]/ancestor::tr[normalize-space(.)]/descendant::text()[{$this->eq($this->t('Meal:'))}]/ancestor::tr[normalize-space(.)][1]/descendant::td[normalize-space(.)][2]");
            $meals = array_unique(array_filter($meals));

            if (!empty($meals)) {
                $s->setMeals($meals);
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $array) {
            foreach ($array as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^[\w\-]+\,\s*(\d+)\.?\s*(?:de\s+)?(\w+)(?:\s+de)?\s*(\d{4})$#u", //Miércoles, 19 de mayo de 2021
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
