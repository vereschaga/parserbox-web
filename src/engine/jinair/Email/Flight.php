<?php

namespace AwardWallet\Engine\jinair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "jinair/it-752789327.eml, jinair/it-753277922.eml, jinair/it-754350342.eml, jinair/it-754576288.eml, jinair/it-759356490.eml";

    public $subjects = [
        'E-Ticket Itinerary / Receipt for',
        '님의 E-Ticket Itinerary/Receipt입니다',
    ];

    public $lang = '';

    public $detectLang = [
        'en' => ['Passenger Information'],
        'kr' => ['승객정보'],
    ];

    public static $dictionary = [
        'en' => [
            'e-Ticket Itinerary & Receipt' => 'e-Ticket Itinerary & Receipt',
            'Passenger Information'        => 'Passenger Information',
            'Itinerary'                    => 'Itinerary',
            'Fare Information'             => 'Fare Information',
            'Passenger Name'               => 'Passenger Name :',
            'Reservation No.'              => 'Reservation No. :',
            'Ticket No.'                   => 'Ticket No. :',
            'Fare Amount'                  => 'Fare Amount',
            'Total Amount'                 => 'Total Amount',
            'Flight No.'                   => 'Flight No.',
            'Class'                        => 'Class',
            'Departure'                    => 'Departure',
            'Arrival'                      => 'Arrival',
            'Seat Number'                  => 'Seat Number',
        ],
        'kr' => [
            'e-Ticket Itinerary & Receipt' => 'e-티켓 확인증 / e-Ticket Itinerary & Receipt',
            'Passenger Information'        => '승객정보',
            'Itinerary'                    => '여정',
            'Fare Information'             => '항공권 운임 정보',
            'Passenger Name'               => '승객 성명 Passenger Name :',
            'Reservation No.'              => '예약 번호 Reservation No. :',
            'Ticket No.'                   => '티켓 번호 Ticket No. :',
            'Fare Amount'                  => '운임합계 Fare Amount',
            'Total Amount'                 => '총금액 Total Amount',
            'Flight No.'                   => '편명 Flight No.',
            'Class'                        => '예약등급 Class',
            'Departure'                    => '출발 Departure',
            'Arrival'                      => '도착 Arrival',
            'Seat Number'                  => '좌석번호 Seat Number',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@jinair.com') !== false) {
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

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('www.jinair.com'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('e-Ticket Itinerary & Receipt'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Passenger Information'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Itinerary'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Fare Information'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]jinair\.com$/', $from) > 0;
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
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation No.'))}]", null, true, "/^\D+\.\s*\:\s*([A-Z\d]{6})$/"));

        $reservationDate = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation Date'))}]/ancestor::tr[1]", null, false, "/(\d+[\.\-]\d+[\.\-]\d+)$/");

        if ($reservationDate !== null) {
            $f->general()
                ->date(strtotime(str_replace('.', '', $reservationDate)));
        }

        $priceInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Amount'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Total Amount'))}\s+(\D{1,3}\s*[\d\.\,\`]+)$/");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>[\d\.\,\']+)$/", $priceInfo, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['price'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Fare Amount'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Fare Amount'))}\s+\D{1,3}\s*([\d\.\,\`]+)$/");

            if ($cost !== null) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $feesNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Fare Amount'))}]/ancestor::tr[1]/following-sibling::tr[not(contains(normalize-space(), 'Total Amount'))][position() >= 1 and not(position() > 4)]");

            if ($feesNodes !== null) {
                foreach ($feesNodes as $root) {
                    $feeName = $this->http->FindSingleNode("./descendant::td[1]", $root);
                    $feeSum = $this->http->FindSingleNode("./descendant::td[2]", $root, true, '/^\D{1,3}\s*([\d\.\,\`]+)/');

                    if ($feeName !== null && $feeSum !== null) {
                        $f->price()
                            ->fee($feeName, PriceHelper::parse($feeSum, $m['currency']));
                    }
                }
            }
        }

        $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Passenger Name'))}]", null, false, "/\:\s+([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/u");
        $f->addTraveller(preg_replace("/(?:MS|MSTR|MR)$/", "", $traveller), true);

        $tickets = $this->http->FindNodes("//text()[{$this->contains($this->t('Ticket No.'))}]", null, "/{$this->opt($this->t('Ticket No.'))}\s+([\-\d\s]+)$/");

        foreach ($tickets as $ticket) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Passenger Name'))}]/ancestor::table[{$this->contains($this->t($ticket))}][1]/descendant::tr[1]", null, false, "/\:\s+([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/u");
            $f->addTicketNumber($ticket, false, preg_replace("/(?:MS|MSTR|MR)$/", "", $traveller));
        }

        $segmentNodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Flight No.'))}]/ancestor::table[1]");

        foreach ($segmentNodes as $root) {
            $s = $f->addSegment();

            $airInfo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight No.'))}]/ancestor::td[1]/following-sibling::td[1]", $root, true, '/((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,4})$/');

            if (preg_match("/(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})$/", $airInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $cabinInfo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Class'))}]/ancestor::td[1]/following-sibling::td[1]", $root, true, '/^([A-Z]{1,2})$/');

            if (!empty($cabinInfo)) {
                $s->extra()
                    ->bookingCode($cabinInfo);
            }

            $depInfo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure'))}]/ancestor::tr[1]", $root);

            if (preg_match("/^{$this->t('Departure')}\s+(?<depName>.*)\((?<depCode>[A-Z]{3})\)\s*(?<depDate>\d+\w+\s*\d+)\s+(?<depTime>\d+\:\d+)/u", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->date(strtotime($m['depDate'] . ' ' . $m['depTime']))
                    ->code($m['depCode']);
            }

            $depTerminal = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure'))}]/ancestor::tr[1]/descendant::td[{$this->contains($this->t('Terminal'))}]", $root, true, "/^Terminal\s*(.+)$/");

            if ($depTerminal !== null) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            $arrInfo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Arrival'))}]/ancestor::tr[1]", $root);

            if (preg_match("/^{$this->t('Arrival')}\s+(?<arrName>.*)\((?<arrCode>[A-Z]{3})\)\s*(?<arrDate>\d+\w+\s*\d+)\s+(?<arrTime>\d+\:\d+)/u", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->date(strtotime($m['arrDate'] . ' ' . $m['arrTime']))
                    ->code($m['arrCode']);
            }

            $arrTerminal = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Arrival'))}]/ancestor::tr[1]/descendant::td[{$this->contains($this->t('Terminal'))}]", $root, true, "/^Terminal\s*(.+)$/");

            if ($arrTerminal !== null) {
                $s->arrival()
                    ->terminal($arrTerminal);
            }

            $seatInfo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Seat Number'))}]/ancestor::td[1]/following-sibling::td[1]", $root, true, '/^(\d+[A-Z])$/');

            if (!empty($seatInfo)) {
                $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Passenger Name'))}]", null, false, "/\:\s+([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/u");

                if (!empty($traveller)) {
                    $s->extra()
                        ->seat($seatInfo, true, true, preg_replace("/(?:MS|MSTR|MR)$/", "", $traveller));
                } else {
                    $s->extra()
                        ->seat($seatInfo);
                }
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

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $dBody) {
            foreach ($dBody as $word) {
                if ($this->http->XPath->query("//*[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
}
