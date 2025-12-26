<?php

namespace AwardWallet\Engine\kiu\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
	public $mailFiles = "kiu/it-845146576.eml, kiu/it-851839605.eml, kiu/it-855602515.eml";
    public $subjects = [
        'E-TICKET ITINERARY RECEIPT - '
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'PASSENGER ITINERARY RECEIPT' => 'PASSENGER ITINERARY RECEIPT',
            'BOOKING REF' => 'BOOKING REF',
            'TICKET NUMBER' => ['TICKET NUMBER', "TICKET'S NUMBER'S"],
            'segHeader1' => 'FROM/TO',
            'segHeader2' => 'FLIGHT',
            'segHeader3' => 'CL',
            'segHeader4' => 'DATE',
            'segHeader5' => 'DEP',
            'segHeader6' => 'ARR',
            'detectReg' => 'FROM/TO FLIGHT CL DATE DEP ARR FARE BASIS NVB NVA'
        ],
        'es' => [
            'PASSENGER ITINERARY RECEIPT' => 'RECIBO DE ITINERARIO DE PASAJEROS',
            'BOOKING REF' => 'CODIGO DE RESERVA',
            'ISSUE DATE' => 'ISSUE DATE/FECHA DE EMISION',
            'BOOKING REF.' => 'BOOKING REF./CODIGO DE RESERVA',
            'NAME' => ['NAME/NOMBRE', 'NAME'],
            'TICKET NUMBER' => 'TICKET NUMBER/NRO DE BOLETO',
            'ISSUING AIRLINE' => 'ISSUING AIRLINE/LINEA AEREA EMISORA',
            'AIR FARE' => 'AIR FARE/TARIFA',
            'TAX' => 'TAX/IMPUESTOS',
            'segHeader1' => 'DESDE/HACIA',
            'segHeader2' => 'VUELO',
            'segHeader3' => 'CL',
            'segHeader4' => 'FECHA',
            'segHeader5' => 'HORA',
            'segHeader6' => ['HORA', 'BASE'],
            'detectReg' => ['DESDE/HACIA VUELO CL FECHA HORA HORA BASE TARIFARIA EQP. ESTATUS', 'DESDE/HACIA VUELO CL FECHA HORA BASE TARIFARIA EQP. ESTATUS']
        ],
    ];

    public $detectLang = [
        "es" => ['BOOKING REF./CODIGO DE RESERVA:'],
        "en" => ['BOOKING REF.:',],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'kiusys.com') !== false) {
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
        foreach (self::$dictionary as $dict) {
            if (!empty($dict['PASSENGER ITINERARY RECEIPT']) && $this->http->XPath->query("//*[{$this->contains($dict['PASSENGER ITINERARY RECEIPT'])}]")->length > 0
                && !empty($dict['BOOKING REF']) && $this->http->XPath->query("//*[{$this->contains($dict['BOOKING REF'])}]")->length > 0
                && preg_match("/{$this->opt($dict['detectReg'])}/", $this->http->FindSingleNode("//text()[{$this->contains($dict['segHeader1'])} and {$this->contains($dict['segHeader2'])} and {$this->contains($dict['segHeader3'])} and {$this->contains($dict['segHeader4'])} and {$this->contains($dict['segHeader5'])} and {$this->contains($dict['segHeader6'])}]"))
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]kiusys\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->assignLang();

        $body = preg_replace("/(\<[aA] href\=.+\>|[ ]style\=\"font-family\:monospace\"|\r)/u", '', html_entity_decode($parser->getHTMLBody(), ENT_QUOTES, 'UTF-8'));

        $this->Flight($email, $body);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight(Email $email, $body)
    {
        $f = $email->add()->flight();

        $this->date = strtotime($this->re("/{$this->opt($this->t('ISSUE DATE'))}[ ]*\:[ ]+(\d{1,2}[ ]+[[:alpha:]]+[ ]+\d{4}(?:[ ]+\d{1,2}\:\d{2})?)\n/u", $body));

        $f->general()
            ->date($this->date)
            ->confirmation($this->re("/{$this->opt($this->t('BOOKING REF.'))}[ ]*\:[ ]+\S+\/\<[bB]\>([A-Z\d]{5,7})\<\/[bB]\>\n/u", $body));

        $f->addTraveller($guestName = preg_replace("/(?:MR|MSTR|MS|MISS)\s*$/", "", $this->re("/{$this->opt($this->t('NAME'))}[ ]*\:[ ]+\<[bB]\>[ ]*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])[ ]*\<\/[bB]\>\n/u", $body)), true);

        $f->addTicketNumber($this->re("/{$this->opt($this->t('TICKET NUMBER'))}[ ]*\:[ ]*([0-9]{3}\-[0-9]+)\n/u", $body), false, $guestName);

        $airportFullNames = [];

        $segmentNodes = preg_split("/(?:\<\/[bB]\>(\n)|\n[ ]*x)/", $this->re("/[ ]*{$this->opt($this->t('segHeader1'))}[ ]+{$this->opt($this->t('segHeader2'))}[ ]+{$this->opt($this->t('segHeader3'))}[ ]+{$this->opt($this->t('segHeader4'))}[ ]+{$this->opt($this->t('segHeader5'))}[ ]+{$this->opt($this->t('segHeader6'))}[ ]+.+?\n+(.+?)\<\/(?:pre|PRE)\>/su", $body), null, PREG_SPLIT_NO_EMPTY);

        foreach ($segmentNodes as $node){
            $s = $f->addSegment();
            $node = preg_replace("/(?:\<[bB]\>|\<\/[bB]\>)/", "     ", $node);
            if (preg_match("/^[ ]*(x[ ]+)?(?<depCity>.+)[ ]{2,}(?<airName>[A-Z0-9]{2})[ ]*(?<airNum>[0-9]{1,4})[ ]+(?<class>[A-Z]{1,2})[ ]+(?<flightDate>\d{1,2}[[:alpha:]]+)[ ]*(?<depTime>\d{4})[ ]*(?<arrTime>\d{4})?[ ]*.+\n[ ]*(?<arrCity>.+)[ ]*$/u", $node, $m)){
                $s->airline()
                    ->name($m['airName'])
                    ->number($m['airNum']);

                $s->departure()
                    ->noCode()
                    ->date($this->normalizeDate($m['flightDate'] . ' ' . $m['depTime']))
                    ->name($m['depCity']);

                $s->arrival()
                    ->noCode()
                    ->name($m['arrCity']);

                if (!empty($m['arrTime'])){
                    $s->arrival()
                        ->date($this->normalizeDate($m['flightDate'] . ' ' . $m['arrTime']));
                } else {
                    $s->arrival()
                        ->noDate();
                }

                $s->extra()
                    ->bookingCode($m['class']);

                $airportFullNames[] = $m['arrCity'];
            }
        }

        //normalizeAirportNames
        foreach ($f->getSegments() as $seg) {
            foreach ($airportFullNames as $name){
                if (strpos($name, $seg->getDepName()) === 0){
                    $seg->setDepName($name);
                }
            }
        }

        $operator = $this->re("/{$this->opt($this->t('ISSUING AIRLINE'))}[ ]*\:[ ]*(.+)\n/u", $body);

        if ($operator !== null){
            $f->issued()
                ->name($operator);
        }

        $totalPrice = $this->re("/{$this->opt($this->t('GRAND TOTAL'))}[ ]*\:[ ]*(\D{1,3}[ ]*\d[,.\'\d ]*)[ ]*/u", $body);

        if ($totalPrice == null){
            $totalPrice = $this->re("/{$this->opt($this->t('TOTAL'))}[ ]*\:[ ]*(\D{1,3}[ ]*\d[,.\'\d ]*)[ ]*/u", $body);
        }

        if (preg_match('/^(?<currency>\D{1,3})[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)
            || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>\D{1,3})$/', $totalPrice, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);

            $f->price()
                ->currency($currency)
                ->cost(PriceHelper::parse($this->re("/{$this->opt($this->t('AIR FARE'))}[ ]*\:[ ]*\D{1,3}[ ]*(\d[,.\'\d ]*)\n/u", $body), $currency))
                ->total(PriceHelper::parse($m['amount'], $currency));

            $taxValues = preg_replace("/\n\s+/", "       ", $this->re("/{$this->opt($this->t('TAX'))}[ ]*\:[ ]*\D{1,3}[ ]*(.+)\n{$this->opt($this->t('TOTAL'))}/us", $body));

            preg_match_all("/(\d[,.\'\d ]*)\S{2,}/u", $taxValues, $taxValue);

            $f->price()
                ->tax(PriceHelper::parse(array_sum($taxValue[1]), $currency));

            $feeTotal = $this->re("/{$this->opt($this->t('FEE TOTAL'))}[ ]*\:[ ]*\D{1,3}[ ]*(\d[,.\'\d ]*)\n/u", $body);

            if ($feeTotal !== null){
                $f->price()
                    ->fee("FEE TOTAL", PriceHelper::parse($feeTotal, $currency));
            }
        }
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            '$'   => ['$'],
            'JPY' => ['¥'],
            'PLN' => ['zł'],
            'THB' => ['฿'],
            'CAD' => ['C$'],
            'COP' => ['COL$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function normalizeDate($date)
    {
        $in = [
            //21DEC 0721
            '#^(\d{1,2})([[:alpha:]]+)[ ]+(\d{1,2})(\d{2})$#u',
        ];
        $out = [
            '$1 $2 %year%, $3:$4',
        ];

        $string = preg_replace($in, $out, $date);

        if (!empty($this->date) && $this->date > strtotime('01.01.2000') && strpos($string, '%year%') !== false && preg_match('/^\s*(?<date>\d{1,2} [[:alpha:]]+) %year%(?:\s*,\s(?<time>\d{1,2}:\d{1,2}.*))?$/', $string, $m)) {
            $string = EmailDateHelper::parseDateRelative($m['date'], $this->date);

            if (!empty($string) && !empty($m['time'])) {
                return strtotime($m['time'], $string);
            }

            return $string;
        }

        return false;
    }

    private function    re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $dBody) {
            if ($this->http->XPath->query("//*[{$this->contains($dBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
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
