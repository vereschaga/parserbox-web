<?php

namespace AwardWallet\Engine\uniglobe\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ItineraryText extends \TAccountChecker
{
    public $mailFiles = "uniglobe/it-785318018.eml, uniglobe/it-786691415.eml, uniglobe/it-786698706.eml, uniglobe/it-787290675.eml, uniglobe/it-788172318.eml, uniglobe/it-866287731.eml, uniglobe/it-867171530.eml";
    public $lang = '';

    public $year;
    public $subject;

    public $detectLang = [
        'pt' => ['Período', 'Valores', 'abaixo da solicitação', 'Localizador:'],
        'en' => ['Period', 'Prices'],
    ];

    public static $dictionary = [
        "pt" => [
            'A Solicitação número' => ['A Solicitação número', 'abaixo da solicitação #'],
        ],
        "en" => [
            'HOSPEDAGEM'  => 'HOTELS',
            'Período...:' => 'Period...:',
            'Valores...:' => 'Prices...:',
            //'Reserva...:' => '',
            'A Solicitação número' => 'Request number',
            'Limite Cancelamento:' => ['Cancellation threshold:'],
            'Diária:'              => 'Rate:',
            'Total:'               => 'Total:',
            'Descrição.:'          => 'Description.:',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $text = $parser->getHTMLBody();
        $text = str_replace("<br/>", "\n", $text);

        $this->AssignLang($text);

        //Detect for Car
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('LOCAÇÃO DE VEÍCULO'))}]")->length > 0
            && ($this->http->XPath->query("//text()[{$this->contains($this->t('Período...:'))}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('Motivo Cancelamento:'))}]")->length > 0)
            && $this->http->XPath->query("//text()[{$this->contains($this->t('EMAIL AUTOMÁTICO, NÃO RESPONDA ESSA MENSAGEM'))}]")->length > 0) {
            return true;
        }

        //Detect for Hotel
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('HOSPEDAGEM'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Período...:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Valores...:'))}]")->length > 0) {
            return true;
        }

        //Detect for Hotel
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('HOSPEDAGEM'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Descrição.:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Período...:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Pagamento.:'))}]")->length > 0) {
            return true;
        }

        //Detect for Hotel
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Localizador:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Partida'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Chegada'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Voo'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Assento:'))}]")->length > 0) {
            return true;
        }

        //it-867171530.eml
        if (empty($text)) {
            $text = $parser->getBody();

            if (stripos($text, $this->t('HOSPEDAGEM')) !== false
                && stripos($text, $this->t('Descrição.:')) !== false
                && stripos($text, $this->t('Período...:')) !== false
                && stripos($text, $this->t('Pagamento.:')) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]argoit\.com\.br$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (preg_match("/^Fwd\:/", $parser->getSubject())) {
            $email->setIsJunk(true);

            return $email;
        }

        $this->year = date("Y", strtotime($parser->getHeader('date')));
        $this->subject = $parser->getSubject();
        $text = $parser->getHTMLBody();
        $text = str_replace("<br/>", "\n", $text);
        $text = str_replace("<br>", "", $text);

        if (empty($text)) {
            $text = $parser->getBody();
        }

        $this->AssignLang($text);

        if (preg_match("/{$this->opt($this->t('LOCAÇÃO DE VEÍCULO'))}/", $text)) {
            $this->ParseCar($email, $text);
        }

        if (preg_match("/{$this->opt($this->t('HOSPEDAGEM'))}/", $text)) {
            $this->ParseHotel($email, $text);
        }

        if (stripos($text, $this->t('Localizador:')) !== false && stripos($text, $this->t('Voo')) !== false) {
            $this->ParseFlight($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseCar(Email $email, $text)
    {
        $r = $email->add()->rental();

        if (stripos($text, 'Motivo Cancelamento:') !== false) {
            $r->general()
                ->cancelled();
        }

        if (preg_match_all("/\s(?<pax>[[:alpha:]][-\/\'’[:alpha:] ]*[[:alpha:]])\s+\(?\S*@\S*/mu", $text, $match)) {
            $r->general()
                ->travellers(array_unique(array_filter($match['pax'])));
        }

        $confirmation = $this->re("/{$this->opt($this->t('A Solicitação número'))}\s*(\d+)/iu", $text);

        if (empty($confirmation)) {
            $confirmation = $this->re("/Solicitação #\s*(\d+)/u", $this->subject);
        }

        $r->general()
            ->confirmation($confirmation);

        if (preg_match("/Empresa:\s+(?<company>[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])/", $text, $m)) {
            $r->setCompany($m['company']);
        }

        if (preg_match("/Diária:.+Total:\s*(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)/", $text, $matches)) {
            $r->price()
                ->total(PriceHelper::parse($matches['total'], $matches['currency']))
                ->currency($matches['currency']);
        }

        if (preg_match("/Retirada\s*(?<pickUpDate>\d+\/\w+\s+[\d\:]+)\s+\-\s+(?<pickUpLocation>.+)\s+Devolução\s+(?<dropOffDate>\d+\/\w+\s+[\d\:]+)\s+\-\s+(?<dropOffLocation>.+\([A-Z]{3}\))\s+\*LOCALIZ/", $text, $m)
            || preg_match("/Retirada\s*(?<pickUpDate>\d+\/\w+\s+[\d\:]+)\s+\-?\s*(?<pickUpLocation>.+)\s*\n*Devolução\s+(?<dropOffDate>\d+\/\w+\s+[\d\:]+)\s+(?<dropOffLocation>.+)\-\s+(?<carType>.+)\s+(?:\*?LOCALIZ)/", $text, $m)
            || preg_match("/Retirada\s*(?<pickUpDate>\d+\/\w+\s+[\d\:]+)\s+\-\s+(?<pickUpLocation>.+)\s+Devolução\s+(?<dropOffDate>\d+\/\w+\s+[\d\:]+)\s+\-\s+(?<dropOffLocation>.+)\-\s+(?<carType>.+)\s+(?:\*?LOCALIZ)/", $text, $m)
            ) {
            $r->pickup()
                ->location($m['pickUpLocation'])
                ->date($this->normalizeDate($m['pickUpDate'] . ', ' . $this->year));

            $r->dropoff()
                ->location($m['dropOffLocation'])
                ->date($this->normalizeDate($m['dropOffDate'] . ', ' . $this->year));

            if (isset($m['carType'])) {
                $r->setCarType($m['carType']);
            }
        } elseif (preg_match("/LOCAÇÃO DE VEÍCULO\s+(?<pickUpDate>\d+\/\d+\s+[\d\:]+)\s+(?<pickUpLocation>.+)\s+(?<dropOffDate>\d+\/\d+\s+[\d\:]+)\s+(?<dropOffLocation>.+)\s+Motivo Cancelamento:/", $text, $m)) {
            $r->pickup()
                ->location($m['pickUpLocation'])
                ->date($this->normalizeDate($m['pickUpDate'] . ', ' . $this->year));

            $r->dropoff()
                ->location($m['dropOffLocation'])
                ->date($this->normalizeDate($m['dropOffDate'] . ', ' . $this->year));
        }
    }

    public function ParseHotel(Email $email, $text)
    {
        $hotelText = $this->re("/([>]*\s*{$this->t('HOSPEDAGEM')}.+)/su", $text);

        $h = $email->add()->hotel();

        $conf = $this->re("/{$this->opt($this->t('Reserva...:'))}\s*([A-Z\d]+)\s*(?:\n|Pagamento)/u", $text);

        if (empty($conf)) {
            $conf = $this->re("/{$this->opt($this->t('A Solicitação número'))}\s*(\d+)\s+/i", $text);
        }

        if (empty($conf)) {
            $conf = $this->re("/Solicitação #\s*(\d+)/u", $this->subject);
        }

        $h->general()
            ->confirmation($conf);

        if (preg_match_all("/(?:^|SOLICITANTE\s*)([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s+\(\S+@\S+\)/mu", $text, $m)
        ) {
            $h->general()
                ->travellers(array_unique(array_filter($m[1])));
        }

        $cancellation = $this->re("/({$this->opt($this->t('Limite Cancelamento:'))}\s+(?:\d+\/\w+|\w+\/\d+)\s*[\d\:]+\s*A?P?M?)(?:\n|{$this->t('Valores...:')})/", $hotelText);

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        if (preg_match("/{$this->opt($this->t('Diária:'))}.+{$this->opt($this->t('Total:'))}\s*(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)/", $hotelText, $matches)) {
            $h->price()
                ->total(PriceHelper::parse($matches['total'], $matches['currency']))
                ->currency($matches['currency']);
        }

        if (preg_match("/{$this->opt($this->t('Descrição.:'))}\s+(?<hotelName>.+)\s+\(.*(?:CAMA|Individual|Finder|\s*).*\)\s*(?<address>[A-Z\d].+)\s+\(Fone(?:\s+(?<phone>[\(\)\_\d\s\-]+)\))?\s+/u", $hotelText, $m)
            || preg_match("/{$this->opt($this->t('Descrição.:'))}\s+(?<hotelName>.+)\s+\((?:CAMA|Individual|Finder|)\)\s*(?<address>[A-Z\d].+)\s+\(Fone\s+(?<phone>[\(\)\_\d\s\-]+)\)\s+/u", $hotelText, $m)
            || preg_match("/{$this->opt($this->t('Descrição.:'))}\s+(?<hotelName>.+[A-z])\s+\-\s+(?<address>[A-Z\d].+)\s+\((?:Fone)?\s*\)/", $hotelText, $m)
            || preg_match("/{$this->opt($this->t('Descrição.:'))}\s+(?<hotelName>.+HOTEL)\s+\-\s*(?<address>[A-Z\d].+)\s+\(Fone(?:\s+(?<phone>[\(\)\d\s\-]+)\))?\s+/", $hotelText, $m)
            || preg_match("/{$this->opt($this->t('Descrição.:'))}\s+(?<hotelName>.+)\s+\-\s+(?<address>[A-Z].+)\s+\(Fone\s+(?<phone>[\(\)\d\-\s]+)\)/", $hotelText, $m)
        ) {
            $h->hotel()
                ->name($m['hotelName'])
                ->address(str_replace(['<', '/'], '', $m['address']));

            if (isset($m['phone'])) {
                $h->hotel()
                    ->phone(str_replace('_', ' ', $m['phone']));
            }
        }

        if (preg_match("/{$this->opt($this->t('Período...:'))}\s+(?<checkIn>(?:\d+\/\w+|\w+\/\d+)\s+[\d\:]+\s*A?P?M?)\s+a\s+(?<checkOut>(?:\d+\/\w+|\w+\/\d+)\s+[\d\:]+\s*A?P?M?)(?:\s+|{$this->opt($this->t('Pagamento.:'))})/", $hotelText, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate(trim($m['checkIn']) . ', ' . $this->year))
                ->checkOut($this->normalizeDate(trim($m['checkOut']) . ', ' . $this->year));
        }

        $this->detectDeadLine($h);
    }

    public function ParseFlight(Email $email, $text)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/{$this->opt($this->t('Localizador:'))}\s*([A-Z\d]{6})\s*\n*/u", $text));

        $segText = $this->re("/{$this->opt($this->t('Localizador:'))}\s*[A-Z\d]{6}\n(?:\({$this->opt($this->t('Data de Expiração'))}\s+(?:\d+\/\w+\s*[\d\:]+\s*A?P?M?)\)\n)?(.+)\n\n{$this->opt($this->t('Até o momento do envio deste email'))}/su", $text);

        if (empty($segText)) {
            $segText = $this->re("/{$this->opt($this->t('PASSAGEM AÉREA'))}(.+)[>]*{$this->opt($this->t('HOSPEDAGEM'))}/su", $text);
        }

        $price = trim($this->re("/{$this->opt($this->t('Total:'))}\s+(.+)/u", $segText));

        if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>[\d\.\'\,]+)$/u", $price, $m)) {
            $currency = $m['currency'];
            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($m['currency']);

            if (preg_match("/{$this->opt($this->t('Taxas:'))}\s+(?:[A-Z]{3})\s*(?<tax>[\d\.\,\']+)\s+/", $segText, $m)) {
                $f->price()
                    ->tax(PriceHelper::parse($m['tax'], $currency));
            }

            if (preg_match("/{$this->opt($this->t('Tarifa:'))}\s+(?:[A-Z]{3})\s*(?<cost>[\d\.\,\']+)\s+/", $segText, $m)) {
                $f->price()
                    ->cost(PriceHelper::parse($m['cost'], $currency));
            }
        }

        $segments = $this->splitText($segText, "/(\([A-Z]{3}\).+\/\s*\([A-Z]{3}\).+(?:\n|$))/u", true);

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            if (preg_match("/\((?<depCode>[A-Z]{3})\)\s+(?<depName>.+)\s+\/\s+\((?<arrCode>[A-Z]{3})\)\s+(?<arrName>.+)\n*{$this->opt($this->t('Partida'))}\s+(?<depDate>\d+\/\w+\/\d{4}\s*[\d\:]+\s*A?P?M?)\s+\-\s+{$this->opt($this->t('Chegada'))}\s+(?<arrDate>\d+\/\w+\/\d{4}\s*[\d\:]+\s*A?P?M?)\s+(?<aName>.+)\s+\n*Voo\s+(?<fNumber>\d{1,4})\s+\((?<cabin>\w+)\)/u", $segment, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($m['depDate']));

                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->date($this->normalizeDate($m['arrDate']));

                $s->extra()
                    ->cabin($m['cabin']);
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

    public function AssignLang($text)
    {
        foreach ($this->detectLang as $lang => $array) {
            foreach ($array as $word) {
                if (stripos($text, $word) !== false) {
                    $this->lang = $lang;
                }
            }
        }

        return false;
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            //06/11 22:00
            "#^(\d+)\/(\d+)\s+([\d\:]+)\,\s*(\d{4})$#u",
            //24/Nov 14:00, 2024
            "#^(\d+)\/(\w+)\s+([\d\:]+\s*A?P?M?)\,\s*(\d{4})$#u",
            //Nov/24 4:00 PM, 2024
            "#^(\w+)\/(\d+)\s+([\d\:]+\s*A?P?M?)\,\s*(\d{4})$#u",
            //17/Nov/2024 08:30
            "#^(\d+)\/(\w+)\/(\d{4})\s+([\d\:]+)$#u",
        ];
        $out = [
            "$1.$2.$4, $3",
            "$1 $2 $4, $3",
            "$2 $1 $4, $3",
            "$1 $2 $3, $4",
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

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/{$this->opt($this->t('Limite Cancelamento:'))}\s+((?:\d+\/\w+|\w+\/\d+)\s*[\d\:]+\s*A?P?M?)/", $cancellationText, $m)) {
            $h->booked()
                ->deadline($this->normalizeDate($m[1] . ', ' . $this->year));
        }
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }
}
