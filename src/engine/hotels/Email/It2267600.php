<?php

namespace AwardWallet\Engine\hotels\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It2267600 extends \TAccountChecker
{
    public $mailFiles = "hotels/it-2267600.eml, hotels/it-2891245.eml, hotels/it-6900359.eml";

    public static $dictionary = [
        "en" => [
            //            "Dear " => "",
            //            "Your Hotels.com Confirmation Number is" => "",
            //            "Check in:" => "",
            //            "Check out:" => "",
            //            "Room type:" => "",
            //            "Total rooms:" => "",
            //            "Cancellation Policy" => "",
            //            "View / Print / Manage booking" => "",
        ],
        "es" => [
            "Dear "                                  => "Estimado/a",
            "Your Hotels.com Confirmation Number is" => "Tu número de confirmación de Hoteles.com es ",
            "Check in:"                              => "Entrada:",
            "Check out:"                             => "Salida:",
            "Room type:"                             => "Tipo de habitación:",
            "Total rooms:"                           => "Total de habitaciones:",
            "Cancellation Policy"                    => "Política de cancelación",
            "View / Print / Manage booking"          => "Ver / Imprimir / Gestionar la reserva",
        ],
        "pt" => [
            "Dear "                                  => "Prezado(a)",
            "Your Hotels.com Confirmation Number is" => "Seu número de confirmação da Hoteis.com é",
            "Check in:"                              => "Check-in:",
            "Check out:"                             => "Check-out:",
            "Room type:"                             => "Tipo de quarto:",
            "Total rooms:"                           => "Total de quartos:",
            "Cancellation Policy"                    => "Política de cancelamento",
            "View / Print / Manage booking"          => "Ver/Imprimir/Gerenciar reserva",
        ],
    ];

    private $detectFrom = "hotels.com";

    private $detectSubject = [
        "en" => "Booking for ",
        "es" => "Reserva para ",
        "pt" => "Reserva para ",
    ];
    private $detectCompany = [".hotels.com", ".hoteis.com", ".hoteles.com"];
    private $detectBody = [
        "en" => ["View / Print / Manage booking"],
        "es" => ["Ver / Imprimir / Gestionar la reserva"],
        "pt" => ["Ver/Imprimir/Gerenciar reserva"],
    ];

    private $lang = "en";
    private $body;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->body = $parser->getPlainBody();

        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0
                || $this->strposAll($this->body, $detectBody) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseHotel($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["subject"]) || empty($headers["from"])) {
            return false;
        }

        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if ($this->http->XPath->query("//a[" . $this->contains($this->detectCompany, '@href') . "]")->length === 0
            && $this->strposAll($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0
                || $this->strposAll($body, $detectBody) !== false) {
                return true;
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

    private function parseHotel(Email $email)
    {
        $text = implode("\n", $this->http->FindNodes("//text()[" . $this->starts($this->t("Check in:")) . "]/ancestor::*[1]//text()[normalize-space()]"));

        if (empty($text)) {
            $text = $this->body;
        }

        // Travel Agency
        $email->obtainTravelAgency();

        $email->ota()
            ->confirmation($this->re("#" . $this->preg_implode($this->t("Your Hotels.com Confirmation Number is")) . "\s*(\d{5,})(\.|\s)#", $text));

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->traveller($this->re("#" . $this->preg_implode($this->t("Dear ")) . "[ ]*(.+?)\s*(?:\n| :)#", $text))
            ->cancellation($this->re("#" . $this->preg_implode($this->t("Cancellation Policy")) . "\s+([\s\S]+?)\s+" . $this->preg_implode($this->t("View / Print / Manage booking")) . "#", $text))
        ;

        // Hotel
        $hotel = $this->re("#" . $this->preg_implode($this->t("Your Hotels.com Confirmation Number is")) . ".+\s+([\s\S]+?)\n\s*" . $this->preg_implode($this->t("Check in:")) . "#", $text);

        if (preg_match("#^(?<name>.+?)\n(?<address>[\s\S]+?)(?:\n(?<phone>[\d- \(\)\+]{5,}))?\s*$#", $hotel, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address(preg_replace("#\s*\n\s*#", ', ', trim($m['address'])))
                ->phone($m['phone'] ?? null, true, true)
            ;
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->re("#\n\s*" . $this->preg_implode($this->t("Check in:")) . "(.+)\n#", $text)))
            ->checkOut($this->normalizeDate($this->re("#\n\s*" . $this->preg_implode($this->t("Check out:")) . "(.+)\n#", $text)))
            ->rooms($this->re("#\n\s*" . $this->preg_implode($this->t("Total rooms:")) . "[ ]*(\d+)#", $text))
        ;

        // Rooms
        $h->addRoom()
            ->setType($this->re("#\n\s*" . $this->preg_implode($this->t("Room type:")) . "[ ]*(.+)\n#", $text));

        $this->detectDeadLine($h);

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return false;
        }

        if (
            preg_match("#Free cancellation until (?<date>\d+/\d+/\d+)#ui", $cancellationText, $m)
            || preg_match("#Cancelamento grátis até (?<date>\d+/\d+/\d+)#ui", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline($this->normalizeDate($m['date']));
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug($date);
        $in = [
            "#^\s*(\d{1,2})/(\d{2})/(\d{4})\s*$#", // 07/22/2013
        ];

        if ($this->lang === 'en') {
            $out = [
                "$2.$1.$3",
            ];
        } else {
            $out = [
                "$1.$2.$3",
            ];
        }

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug($date);
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function normalizeTime($time)
    {
        $in = [
            "#^\s*(\d{1,2})\s*([ap]\.?m)\.?\s*$#i", //4 p.m.
            "#^\s*(\d{2})(\d{2})\s*$#i", //1200
        ];
        $out = [
            "$1:00 $2",
            "$1:$2",
        ];
        $time = str_replace('.', '', preg_replace($in, $out, $time));

        if (!preg_match("#^\d{1,2}:\d{2}( [ap]m)?$#i", $time)) {
            return null;
        }

        return $time;
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
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function strposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (strpos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && strpos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains(" . $text . ", \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    /*
    public $mailFiles = "hotels/it-2267600.eml, hotels/it-2891245.eml, hotels/it-6900359.eml";

    private $detects = [
        'Obrigado por reservar com a Hoteis.com',
        'Tu número de confirmación de Hoteles.com',
    ];

    function processors()
    {
        return array(


            "#.*?#" => array(

                "ItinerariesSplitter" => function($text = '', $node = null, $it = null){
                    if( empty($text) )
                        $text = $this->parser->getPlainBody();
                    return [$text];
                },

                "#.*?#" => array(

                    "Kind" => function($text = '', $node = null, $it = null){
                        return "R";
                    },

                    "ConfirmationNumber" => function($text = '', $node = null, $it = null){
                        return re_white('(?:de confirmación de Hoteles\.com es|de confirmação da Hoteis\.com é) (\w+)');
                    },

                    "HotelName" => function($text = '', $node = null, $it = null){
                        $q = '/de\s+(?:confirmación|confirmação)\s+.+\s+(?<HotelName>\w+.+)\n(?<Address>[\w\D]+)\s+(?<Phone>[\+\d]+)\s+(?:Entrada|Check-in):/iu';
                        if( preg_match($q, $text, $m) )
                            return [
                                'HotelName' => $m['HotelName'],
                                'Address' => preg_replace('/\s+/', ' ', $m['Address']),
                                'Phone' => $m['Phone']
                            ];
                        return '';
                    },

                    "CheckInDate" => function($text = '', $node = null, $it = null){
                        $date = re_white('(?:Entrada|Check-in): (.+? \d{4})');
                        return totime($date);
                    },

                    "CheckOutDate" => function($text = '', $node = null, $it = null){
                        $date = re_white('(?:Salida|Check-out): (.+? \d{4})');
                        return totime($date);
                    },

                    "GuestNames" => function($text = '', $node = null, $it = null){
                        if( preg_match('/(?:Estimado\/a\s+(.+?):|Prezado\(a\)\s*(.+))/i', $text, $m) )
                            return !empty($m[1]) ? [$m[1]] : [$m[2]];
                        return [];
                    },

                    "Rooms" => function($text = '', $node = null, $it = null){
                        return re_white('(\d+) (?:habitación|quarto)');
                    },

                    "CancellationPolicy" => function($text = '', $node = null, $it = null){
                        return between('(?:Política de cancelación|Política de cancelamento)', '(?:Ver /|Ver/)');
                    },

                    "RoomType" => function($text = '', $node = null, $it = null){
                        $s = re_white('Tipo de (?:habitación|quarto): (.+?) \n');
                        return nice($s);
                    },

                    "Status" => function($text = '', $node = null, $it = null){
                        if (re_white('(?:La reserva está confirmada|Sua reserva está confirmada)'))
                            return 'confirmed';
                    },
                ),
            ),

        );
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], '@hotels.com') !== false
            && stripos($headers['subject'], 'Reserva para') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@hotels.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();
        foreach ($this->detects as $detect) {
            if( stripos($body, $detect) !== false )
                return true;
        }
        return false;
    }

    public static function getEmailTypesCount(){
        return 2;
    }

    public static function getEmailLanguages(){
        return ['es', 'pt'];
    }

    public function IsEmailAggregator(){
    return true;
    }
    */
}
