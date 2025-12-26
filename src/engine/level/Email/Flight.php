<?php

namespace AwardWallet\Engine\level\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "level/it-773909916.eml, level/it-779708104.eml, level/it-781628710.eml, level/it-817518882.eml, level/it-822094437.eml, level/it-827412971.eml";

    public $subjects = [
        '/[A-Z\d]{5,7} reservation confirmed/',
        '/Information about your payment to LEVEL/',
        '/Reserva [A-Z\d]{5,7} confirmada/'
    ];

    public $lang = '';

    public $detectLang = [
        'es' => ['Resumen de tu vuelo'],
        'en' => ['Your flight summary'],
        'ca' => ['Resum del teu vol']
    ];

    public static $dictionary = [
        'en' => [
            'LEVEL booking code' => 'LEVEL booking code',
            'Your flight summary' => 'Your flight summary',
            "What's included in your booking with LEVEL?" => "What's included in your booking with LEVEL?",
        ],
        'es' => [
            'LEVEL booking code' => 'Código de reserva LEVEL',
            'Your flight summary' => 'Resumen de tu vuelo',
            'Total Price' => 'Precio total',
            'Denied' => 'Denegado',
            "What's included in your booking with LEVEL?" => '¿Qué incluye tu reserva con LEVEL?',
            'Outbound' => 'Ida',
            'Return' => 'Vuelta',
            'stop in' => 'parada en',
            'Seat' => 'Asiento'
        ],
        'ca' => [
            'LEVEL booking code' => 'Codi de reserva LEVEL',
            'Your flight summary' => 'Resum del teu vol',
            'Total Price' => '	Preu total',
            'Denied' => 'Denegat',
            'What\'s included in your booking with LEVEL?' => 'Què inclou la teva reserva amb LEVEL?',
            'Outbound' => 'Anada',
            'Return' => 'Tornada',
            'stop in' => 'parada a',
            'Seat' => 'Seient'
        ]
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'flylevel.com') !== false) {
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
        if (
            $this->http->XPath->query("//a/@href[{$this->contains(['flylevel.com'])}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Flylevel, S.L. (“LEVEL”)'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['LEVEL booking code']) && $this->http->XPath->query("//*[{$this->contains($dict['LEVEL booking code'])}]")->length > 0
                && !empty($dict['Your flight summary']) && $this->http->XPath->query("//*[{$this->contains($dict['Your flight summary'])}]")->length > 0
                && !empty($dict["What's included in your booking with LEVEL?"]) && $this->http->XPath->query("//*[{$this->contains($dict["What's included in your booking with LEVEL?"])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flylevel\.com$/', $from) > 0;
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
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('LEVEL booking code'))}]/ancestor::tr[1]", null, true, "/^{$this->t('LEVEL booking code')}\s*([A-Z\d]{5,7})$/"), $this->t('LEVEL booking code'));

        $priceInfo = $this->http->FindNodes("//text()[{$this->eq($this->t('Total Price'))}]/ancestor::table[2]/following-sibling::table[1]/descendant::table[not({$this->contains($this->t('Denied'))})][normalize-space()]");

        foreach ($priceInfo as $price) {
            if (preg_match("/\s+(?<currency>\D{1,3})\s*(?<price>[\d\.\,\`]+)(?: \d{1,2})?$/", $price, $m) or preg_match("/\s+(?<price>[\d\.\,\`]+)\s*(?<currency>\D{1,3})(?: \d{1,2})?$/", $price, $m)) {
                $amounts[] = PriceHelper::parse($m['price'], $m['currency']);
            }
        }

        if (isset($amounts) && $m['currency'] !== null){
            $f->price()
                ->total(array_sum($amounts))
                ->currency($this->normalizeCurrency($m['currency']));
        }

        $travellersNodes = $this->http->FindNodes("//text()[{$this->eq($this->t("What's included in your booking with LEVEL?"))}]/ancestor::table[3]/descendant::table[4]/descendant::table[{$this->contains($this->t('Outbound'))}]/preceding::table[1]");

        foreach ($travellersNodes as $traveller) {
            if (preg_match("/^(?<passengerName>[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*(?:E\-Ticket\:\s*(?<ticketNumber>\d+)|$)/", $traveller, $m)) {
                $f->addTraveller($m['passengerName'], true);

                if (isset($m['ticketNumber']) && $m['ticketNumber'] !== null) {
                    $f->addTicketNumber($m['ticketNumber'], false, $m['passengerName']);
                }
            }
        }

        $segmentNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Outbound'))} or {$this->eq($this->t('Return'))}]/ancestor::table[2]");

        foreach ($segmentNodes as $root) {
            unset($s2);
            if (preg_match("/^\d*\s*{$this->t('stop in')}\s*(.+)\s*\(/", $this->http->FindSingleNode("./descendant::table[2]/descendant::th[3]", $root), $btwCity)) {
                $s = $f->addSegment();
                $s2 = $f->addSegment();
                $airInfo = $this->http->FindSingleNode("./descendant::text()[normalize-space()][last()]", $root);

                if (preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})\s*\|\s*(?<aName2>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber2>\d{1,4})$/", $airInfo, $m)) {
                    $s->airline()
                        ->name($m['aName'])
                        ->number($m['fNumber']);

                    $s2->airline()
                        ->name($m['aName2'])
                        ->number($m['fNumber2']);
                }

                $flightDate = preg_replace("/\./",'', $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Outbound'))} or {$this->eq($this->t('Return'))}]/ancestor::tr[1]/descendant::text()[normalize-space()][last()]/ancestor::th[1]", $root, false, '/^(\w+\s*\d+\s*\D{3})/u'));

                $flightInfo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Outbound'))} or {$this->eq($this->t('Return'))}]/ancestor::tr[1]/following::tr[contains(normalize-space(), ':')][1]", $root);

                if (preg_match('/^(?<depTime>\d{2}\:\d{2})\s*(?<depCode>\D{3})\s*.+\s*(?<arrTime>\d{2}\:\d{2})\s*(?<arrCode>\D{3})$/u', $flightInfo, $m)) {
                    $s->departure()
                        ->code($m['depCode'])
                        ->date($this->normalizeDate($flightDate . ' ' . $m['depTime']));

                    $s->arrival()
                        ->name($btwCity[1])
                        ->noCode()
                        ->noDate();

                    $s2->departure()
                        ->name($btwCity[1])
                        ->noCode()
                        ->noDate();

                    $s2->arrival()
                        ->code($m['arrCode'])
                        ->date($this->normalizeDate($flightDate . ' ' . $m['arrTime']));
                }
            } else {
                $s = $f->addSegment();

                $airInfo = $this->http->FindSingleNode("./descendant::text()[normalize-space()][last()]", $root);

                if (preg_match("/(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})$/", $airInfo, $m)) {
                    $s->airline()
                        ->name($m['aName'])
                        ->number($m['fNumber']);
                }

                $flightDate = preg_replace("/\./",'',$this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Outbound'))} or {$this->eq($this->t('Return'))}]/ancestor::tr[1]/descendant::text()[normalize-space()][last()]/ancestor::th[1]", $root, false, '/^(\w+\s*\d+\s*\D{3})/u'));

                $flightInfo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Outbound'))} or {$this->eq($this->t('Return'))}]/ancestor::tr[1]/following::tr[contains(normalize-space(), ':')][1]", $root);

                if (preg_match('/^(?<depTime>\d{2}\:\d{2})\s*(?<depCode>\D{3})\s*(?<flightTime>.+)\s*(?<arrTime>\d{2}\:\d{2})\s*(?<arrCode>\D{3})$/u', $flightInfo, $m)) {
                    $s->departure()
                        ->code($m['depCode'])
                        ->date($this->normalizeDate($flightDate . ' ' . $m['depTime']));

                    $s->arrival()
                        ->code($m['arrCode'])
                        ->date($this->normalizeDate($flightDate . ' ' . $m['arrTime']));

                    $s->extra()
                        ->duration($m['flightTime']);
                }
            }

            $flightType = $this->http->FindSingleNode("./descendant::table[2][{$this->contains($this->t('Outbound'))} or {$this->contains($this->t('Return'))}]", $root, false, "/^\s*({$this->t('Outbound')}|{$this->t('Return')})/u");

            if (!empty($flightType)) {
                $seatsInfo = $this->http->XPath->query("//text()[{$this->eq($this->t("What's included in your booking with LEVEL?"))}]/ancestor::table[3]/descendant::table[4]/descendant::table[{$this->contains($flightType)}]/following-sibling::table[1]/descendant::tr[2]/descendant::th[3]");

                foreach ($seatsInfo as $sRoot) {
                    $seat = $this->http->FindSingleNode(".", $sRoot, false, "/^{$this->t('Seat')}\s*LEVEL\s*(\d+[A-Z])\*?$/");
                    if (!empty($seat)) {
                        $pos = preg_match("/^\s*{$this->t('Outbound')}/", $flightType)? 3:7;
                        $traveller = $this->http->FindSingleNode("./ancestor::table[1]/preceding::table[{$pos}]", $sRoot, false, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*(?:E\-Ticket\:|$)/u");

                        $s->extra()
                            ->seat($seat, true, true, $traveller);

                        if (isset($s2)){
                            $s2->extra()
                                ->seat($seat, true, true, $traveller);
                        }
                    }
                }
            }
        }
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

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function normalizeDate($date)
    {
        if (preg_match("/^(?<weekDay>\w+)\s*(?<date>\d+)\s*(?<month>\D+)\s*(?<time>\d{2}\:\d{2})$/u", $date, $x)) {
            $dayOfWeekInt = WeekTranslate::number1(WeekTranslate::translate($x['weekDay'], $this->lang));

            if ($en = MonthTranslate::translate($x['month'], $this->lang)){
                $date = EmailDateHelper::parseDateUsingWeekDay($x['time'] . ' ' . $x['date'] . ' ' . $en, $dayOfWeekInt);
            } else {
                $date = EmailDateHelper::parseDateUsingWeekDay($x['time'] . ' ' . $x['date'] . ' ' . $x['month'], $dayOfWeekInt);
            }
        }

        return $date;
    }

    private function normalizeCurrency($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
        ];
        $string = trim($string);

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
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
}
