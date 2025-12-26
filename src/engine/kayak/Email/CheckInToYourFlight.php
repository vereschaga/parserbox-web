<?php

namespace AwardWallet\Engine\kayak\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CheckInToYourFlight extends \TAccountChecker
{
    public $mailFiles = "kayak/it-10008817.eml, kayak/it-10094001.eml, kayak/it-37770244.eml, kayak/it-38424367.eml, kayak/it-38796616.eml, kayak/it-41858846.eml, kayak/it-41973517.eml";

    public static $dictionary = [
        "en" => [
            "Flight" => ["Flight", "Flight:"],
            //            "Confirmation" => "",
            //            "Departure" => "",
            //            "Arrival" => "",
            //            "Time" => "",
        ],
        "pt" => [
            "Flight"       => ["Voo:"],
            "Confirmation" => "Nº da Reserva",
            "Departure"    => "Partida",
            "Arrival"      => "Chegada",
            "Time"         => "Horário",
        ],
        "es" => [
            "Flight"       => ["Vuelo:"],
            "Confirmation" => "Confirmación",
            "Departure"    => "Salida",
            "Arrival"      => "Llegada",
            "Time"         => "Hora",
        ],
        "ru" => [
            "Flight"       => ["Рейс:"],
            "Confirmation" => "Подтверждение",
            "Departure"    => "Откуда",
            "Arrival"      => "Куда",
            "Time"         => "Время",
        ],
    ];

    public $lang = "en";

    private $reFrom = "noreply-trips@message.kayak.com";
    private $reSubject = [
        "en" => "Check in to your",
        "pt" => "Faça agora o check-in do seu voo",
        "es" => "Realiza ahora la facturación para tu vuelo",
        "ru" => "Зарегистрируйтесь на рейс",
    ];
    private $reBody = 'KAYAK';
    private $reBody2 = [
        "en" => "Your upcoming flight",
        "pt" => "Seu próximo voo",
        "es" => "Tu próximo vuelo",
        "ru" => "Ваш следующий перелет",
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        // Travel Agency
        $email->obtainTravelAgency();

        $this->parseHtml($email);

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

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $rls = array_filter(array_map('trim', explode(',', trim($this->nextText($this->t("Confirmation")), ": "))));

        if (empty($rls)) {
            $f->general()->noConfirmation();
        } else {
            foreach ($rls as $rl) {
                $f->general()->confirmation($rl);
            }
        }

        // Segments
        $s = $f->addSegment();

        // Airline
        $s->airline()
            ->name($this->re("#^(.+?)\s+\d{1,5}$#", $this->nextText($this->t("Flight"))))
            ->number($this->re("#^.+?\s+(\d{1,5})$#", $this->nextText($this->t("Flight"))));

        $operator = $this->nextText("Operated by");

        if (!empty($operator)) {
            $s->airline()->operator($operator);
        }

        // Departure
        $s->departure()
            ->code($this->re("#\(([A-Z]{3})\)#", $this->nextText($this->t("Departure"))))
            ->name($this->re("#:\s*(.*?)\s+\([A-Z]{3}\)#", $this->nextText($this->t("Departure"))))
            ->date($this->normalizeDate($this->re("#:\s*(.*?) - #", $this->nextText($this->t("Time")))))
        ;

        // Arrival
        $s->arrival()
            ->code($this->re("#\(([A-Z]{3})\)#", $this->nextText($this->t("Arrival"))))
            ->name($this->re("#:\s*(.*?)\s+\([A-Z]{3}\)#", $this->nextText($this->t("Arrival"))))
            ->date($this->normalizeDate($this->re("#:\s*.*? - (.+)#", $this->nextText($this->t("Time")))))
        ;

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        //		$this->logger->debug('normalizeDate $str = '.print_r( $str,true));
        $in = [
            "#^[^\s\d]+ ([^\s\d]+) (\d+) (\d{4}) (\d+)[:.](\d+(?: [ap]\.?m))\.? [A-Z]+$#", //Wed Nov 22 2017 8:25 am MST
            "#^[^\s\d]+ (\d+) ([^\s\d]+) (\d{4}) (\d+)[:.](\d+(?: [ap]\.?m)?)\.? [A-Z]+$#", //Sun. 19 May 2019 8:30 p.m. EDT;  Fri 7 Jun 2019 10.50 ICT
        ];
        $out = [
            "$2 $1 $3, $4:$5",
            "$1 $2 $3, $4:$5",
        ];
        $str = preg_replace($in, $out, $str);
        $str = str_replace('.', '', $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][not(" . $this->eq(':') . ")][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }
}
