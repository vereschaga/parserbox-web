<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationCancellation extends \TAccountChecker
{
    public $mailFiles = "hertz/it-1544877.eml, hertz/it-1876765.eml, hertz/it-1877241.eml, hertz/it-1882454.eml, hertz/it-1897015.eml, hertz/it-2122488.eml, hertz/it-46541727.eml, hertz/it-61219253.eml, hertz/it-83214312.eml, hertz/it-8695699.eml";

    public $lang = '';
    public $reHeaders;

    public static $dictionary = [
        'en' => [
            "Traveller" => [
                "We've cancelled your reservation.",
                ", We've cancelled your reservation.",
                "Your reservation for Confirmation Number",
            ],
            "Confirmation Number"        => ["Your Confirmation Number is:", "Your reservation confirmation number is:", "Confirmation Number:", "Your reservation for Confirmation Number"],
            "Cancellation Number"        => ["Please retain cancellation number", "has been cancelled"],
            "Account Number"             => "Your Hertz Gold Plus Rewards",
            "Your Itinerary"             => ["Your Itinerary", "Your Previous Itinerary"],
            "Pickup Time"                => ["Pickup Time", "Pick Up time", "Pick-Up Date", "Pickup Time"],
            "Return Time"                => ["Return Time", "Return time", "Return Date", "Return Time"],
            "Pickup and Return Location" => ["Pickup and Return Location", "Pickup Location & Return Location"],
            "Pickup Location"            => ["Pickup Location", "Pick Up Location", "Pick-Up Location"],
            //            "Address" => "",
            //            "Location Type" => "",
            //            "Hours of Operation" => "",
            "Phone Number" => ["Phone Number", "Phone"],
            "Fax Number"   => ["Fax Number", "Fax"],
            //            "Return Location" => "",
            //            "Your Vehicle" => "",
            "Cost"  => "Vehicle Subtotal",
            "Tax"   => ["Taxes", "Extras Subtotal"],
            "Total" => ["Total Approximate Charge", "Total Estimated Charge", "Total"],
            //            "Driving Instructions" => "",
            "Day" => ["week", "Week", "day", "Days"],
        ],

        'de' => [
            "Traveller" => [
                ". Ihre Reservierung wurde storniert.",
                "Thanks for Traveling at the Speed of Hertz,",
            ],
            "Confirmation Number"        => "Ihre Reservierungsnummer lautet:",
            "Cancellation Number"        => "Bitte bewahren Sie die Nummer Ihrer Stornierung",
            "Account Number"             => "",
            "Your Itinerary"             => "Ihr Reiseplan",
            "Pickup Time"                => "Anmietung",
            "Return Time"                => "Rückgabe",
            "Pickup and Return Location" => ["Anmietstation & Rückgabestation", "Ort der Anmietung und Ort der Rückgabe"],
            //"Pickup Location" => "",
            "Address"            => "Adresse",
            "Location Type"      => "Anmietstation",
            "Hours of Operation" => "Öffnungszeiten",
            "Phone Number"       => "Telefonnummer",
            "Fax Number"         => "Fax Nummer",
            //"Return Location" => "",
            "Your Vehicle" => "Ihr Fahrzeug",
            //"Cost" => "",
            //"Tax" => "",
            "Total" => "Voraussichtliche Kosten",
        ],

        'pt' => [
            "Traveller" => [
                ", Sua reserva foi cancelada.",
            ],
            //"Confirmation Number" => "Ihre Reservierungsnummer lautet:",
            "Cancellation Number" => "Por favor, guarde o seu número de cancelamento.",
            //"Account Number" => "",
            "Your Itinerary" => "Seu Itinerário",
            "Pickup Time"    => "Retirada",
            "Return Time"    => "Devolução",
            //"Pickup and Return Location" => ["Anmietstation & Rückgabestation", "Ort der Anmietung und Ort der Rückgabe"],
            "Pickup Location" => "Loja de Retirada",
            "Return Location" => "Loja de Devolução",
            //"Address" => "Adresse",
            "Location Type"      => "Tipo de loja",
            "Hours of Operation" => "Horário comercial",
            "Phone Number"       => "Tel",
            "Fax Number"         => "Número de fax",

            //"Your Vehicle" => "Ihr Fahrzeug",
            //"Cost" => "",
            //"Tax" => "",
            //"Total" => "Voraussichtliche Kosten",
        ],
    ];
    private $from = [
        'hertz.com',
    ];

    private $subject = [
        'en' => ['Hertz Reservation Cancellation'],
        'de' => ['Stornierung Ihrer Hertz Reservierung'],
        'pt' => ['Cancelamento da sua Reserva Hertz'],
    ];

    private $body = [
        'en' => ["We've cancelled your reservation", "has been cancelled", "Your cancellation details"],
        'de' => ["Ihre Reservierung wurde storniert"],
        'pt' => ["Sua reserva foi cancelada"],
    ];

    private $detectLang = [
        'de' => ['Ihr Reiseplan'],
        'en' => ['Your Vehicle', 'Your Itinerary', 'Your Previous Itinerary'],
        'pt' => ["Seu Itinerário"],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->alert("Can't determine a language!");

            return $email;
        }

        $this->reHeaders = $parser->getHeader('subject');

        $this->parseRental($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->from as $refrom) {
            if (stripos($from, $refrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $lang => $subjects) {
            foreach ($subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach ($this->body as $lang => $bodys) {
            foreach ($bodys as $body) {
                if ($this->http->XPath->query("//text()[{$this->contains($body)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function parseRental(Email $email)
    {
        if ($this->http->XPath->query("//script[@type='application/ld+json']")->length > 0) {
            $json = $this->http->FindSingleNode("//script[@type='application/ld+json']");
            $json = preg_replace("/^[^\{]+/", "", $json);
            $json = preg_replace("/[^\}]+$/", "", $json);
            $json = preg_replace("/(\"\w+\":\s*)(<a[^\a]+?>)(.+?)(\s*<\/a>)?(\s*,)/", "$1$3$5", $json);
            $json = preg_replace('/(\"name\"\:\s+\D+\")(\s+)("telephone\")/', "$1, $3", $json);

            if ($json && ($data = @json_decode($json, true))) {
                if ($data['@context'] == 'schema.org' && $data['@type'] == 'RentalCarReservation') {
                    $this->logger->warning("NOTE: email contain JSON format - go to parse by JsonIt.php");

                    return false;
                }
            }
        }

        $r = $email->add()->rental();
        //Confirmation Number

        $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation Number'))}]", null, true, "/([A-Z\d]{10,})/");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Cancellation Number'))}]/ancestor::td[1]", null, true, "/([A-Z\d]{10,})/");
        }

        if (!empty($cancellation)) {
            $r->general()
                ->confirmation($cancellation, 'cancellation number')
                ->cancellationNumber($cancellation)
                ->cancelled();
        }

        //Travellers
        $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Traveller'))}]", null, true, "/(?:Thanks\s?)?([A-Za-z\s\-?]+)\.?\s?{$this->opt($this->t('Traveller'))}/");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your reservation for Confirmation Number')]/preceding::text()[normalize-space()][1]");
        }

        if (!empty($traveller)) {
            $r->general()
                ->traveller($traveller, true);
        }

        //PickUp/DropOff

        $pickupDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Itinerary'))}]/following::text()[{$this->starts($this->t('Pickup Time'))}]/ancestor::td[1]/descendant::text()[normalize-space()][2]");

        if (empty($pickupDate)) {
            $pickupDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Itinerary'))}]/following::text()[{$this->starts($this->t('Pickup Time'))}]/ancestor::tr[1]/following-sibling::tr[1]/descendant::td[1]");
        }

        if (!empty($pickupDate)) {
            $r->pickup()
                ->date($this->normalizeDate($pickupDate));
        }

        $dropOffDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Itinerary'))}]/following::text()[{$this->starts($this->t('Return Time'))}]/ancestor::td[1]/descendant::text()[normalize-space()][4]");

        if (empty($dropOffDate)) {
            $dropOffDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Itinerary'))}]/following::text()[{$this->starts($this->t('Return Time'))}]/ancestor::tr[1]/following-sibling::tr[1]/descendant::td[2]");
        }

        if (!empty($dropOffDate)) {
            $r->dropoff()
                ->date($this->normalizeDate($dropOffDate));
        }

        // if - Pickup and Return Location | cancelled
        $rentalText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Pickup and Return Location'))}]/ancestor::tr[1]/descendant::td[normalize-space()][1]");

        if (!empty($this->re("/({$this->opt($this->t('Pickup and Return Location'))})/", $rentalText))
            && empty($this->re("/({$this->opt($this->t('Phone Number'))})/", $rentalText))) {
            $rentalText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Pickup and Return Location'))}]/ancestor::table[1]");
        }

        //if - Pickup Location
        //     Return Location
        if (empty($rentalText)) {
            $rentalText = implode(' , ', $this->http->FindNodes("//text()[{$this->starts($this->t('Pickup Location'))} or {$this->starts($this->t('Return Location'))}]/ancestor::td[1]"));
        }

        if (!empty($this->re("/({$this->opt($this->t('Pickup Location'))})/", $rentalText))
            && empty($this->re("/({$this->opt($this->t('Phone Number'))})/", $rentalText))) {
            $rentalText = implode(' , ', $this->http->FindNodes("//text()[{$this->starts($this->t('Pickup Location'))} or {$this->starts($this->t('Return Location'))}]/ancestor::td[2]"));

            if (!empty($this->re("/({$this->opt($this->t('Driving Instructions'))})/", $rentalText))) {
                $rentalText = preg_replace("/({$this->opt($this->t('Driving Instructions'))}(?:\:?\s?Google\s?MapsMSN\s?MapsMapQuest|Google\s?MapsAAA\s?TripTik\(R\)NeverLost\s?Online\s?Trip\s?Planning))/", '', $rentalText);
            }
        }

        if (!empty($this->re("/({$this->opt($this->t('Your Itinerary'))})/", $rentalText))) {
            $rentalText = implode(' , ', $this->http->FindNodes("//text()[{$this->starts($this->t('Phone Number'))}]/ancestor::td[1]"));
        }

        if (!empty($rentalText)) {
            $this->logger->error($rentalText);
            $pattern = "/(?:{$this->opt($this->t('Pickup and Return Location'))})?\.?\s?(?<location>.+)\s?"
                . "{$this->opt($this->t('Phone Number'))}\s?\:?\:?(?<phoneNumber>[\d\-?\s?\(?\)?]+)\s?"
                . "{$this->opt($this->t('Fax Number'))}\s?\:?\s?\:?(?<faxNumber>[\d\-?\s?\(?\)?]+)\s?"
                . "{$this->opt($this->t('Hours of Operation'))}\s?\:?(?<hours>.+)"
                . "{$this->opt($this->t('Location Type'))}\s?\:?\:?(?<type>.+)$/";

            $pattern2 = "/{$this->opt($this->t('Pickup Location'))}\:?\.?\s(?<PickupLocation>.+)\s?"
                . "{$this->opt($this->t('Phone Number'))}\s?\:?\s*\:?\s*(?<PickupPhone>[\+?\d\-?\s?\(?\)?]+)\s?"
                . "{$this->opt($this->t('Fax Number'))}?\s?\:?\s?\:?(?<PickupFax>[\+\d\-?\s?\(?\)?]+)?\s?\*?"
                . "{$this->opt($this->t('Hours of Operation'))}\s?\:?(?<PickupHours>.+)"
                . "{$this->opt($this->t('Location Type'))}\s?\:?\s*\:?(?<PickupType>.+)"

                . "{$this->opt($this->t('Return Location'))}\:?\.?\s(?<DropOffLocation>.+)\s?"
                . "{$this->opt($this->t('Phone Number'))}\s?\:?\s*\:?\s*(?<DropOffPhone>[\+?\d\-?\s?\(?\)?]+)\s?"
                . "{$this->opt($this->t('Fax Number'))}?\s?\:?\s?\:?(?<DropOffFax>[\+\d\-?\s?\(?\)?]+)?\s?\*?"
                . "{$this->opt($this->t('Hours of Operation'))}\s?\:?(?<DropOffHours>.+)"
                . "{$this->opt($this->t('Location Type'))}\s?\:?\s*\:?(?<DropOffType>.+)$/";

            $pattern3 = "/^(?<PickupLocation>[\S\s]+)\s"
                . "{$this->opt($this->t('Phone Number'))}[:]+\s(?<PickupPhone>[\d\s\-]+)"
                . "{$this->opt($this->t('Fax Number'))}[:]+\s(?<PickupFax>[\d\s\-]+)"
                . "{$this->opt($this->t('Hours of Operation'))}[:]+\s(?<PickupHours>.+)"
                . "{$this->opt($this->t('Location Type'))}[:]+\D+\,"
                . "(?<DropOffLocation>[\S\s]+)\s"
                . "{$this->opt($this->t('Phone Number'))}[:]+\s(?<DropOffPhone>[\d\s\-]+)"
                . "{$this->opt($this->t('Fax Number'))}[:]+\s(?<DropOffFax>[\d\s\-]+)"
                . "{$this->opt($this->t('Hours of Operation'))}[:]+\s(?<DropOffHours>.+)"
                . "{$this->opt($this->t('Location Type'))}[:]+\D+$/";

            if (preg_match($pattern, $rentalText, $m)) {
                $r->pickup()
                    ->location($m['location'])
                    ->openingHours($m['hours'])
                    ->phone($m['phoneNumber'])
                    ->fax($m['faxNumber']);

                $r->dropoff()
                    ->same();
            }

            $this->logger->warning($pattern2);

            if (preg_match($pattern2, $rentalText, $m) || preg_match($pattern3, $rentalText, $m)) {
                if (isset($m['PickupAddress']) & !empty($m['PickupAddress'])) {
                    $pickupLocation = trim($m['PickupLocation']) . ', ' . trim($m['PickupAddress']);
                } else {
                    $pickupLocation = trim($m['PickupLocation']);
                }

                if (isset($m['DropOffAddress']) & !empty($m['DropOffAddress'])) {
                    $dropOffLocation = trim($m['DropOffLocation']) . ', ' . trim($m['DropOffAddress']);
                } else {
                    $dropOffLocation = trim($m['DropOffLocation']);
                }

                $r->pickup()
                    ->location(trim($pickupLocation, ':'))
                    ->openingHours($m['PickupHours'])
                    ->phone($m['PickupPhone']);

                if (isset($m['PickupFax']) and !empty($m['PickupFax'])) {
                    $r->pickup()
                        ->fax($m['PickupFax']);
                }

                $r->dropoff()
                    ->location(trim($dropOffLocation, ':'))
                    ->openingHours($m['DropOffHours'])
                    ->phone($m['DropOffPhone']);

                if (isset($m['DropOffFax']) and !empty($m['DropOffFax'])) {
                    $r->dropoff()
                        ->fax($m['DropOffFax']);
                }
            }
        }

        //Car Type|Model|Image
        $model = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Vehicle'))}]/following::text()[normalize-space()][2]");

        if (!empty($model)) {
            $r->car()
                ->model($model);
        }

        $type = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Vehicle'))}]/following::text()[normalize-space()][1]");

        if (!empty($type)) {
            $r->car()
                ->type($type);
        }

        $image = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Vehicle'))}]/following::img[contains(@src, 'hertz.com')][1]/@src");

        if (!empty($image)) {
            $r->car()
                ->image($image);
        }

        return true;
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $detectLang) {
            foreach ($detectLang as $phrase) {
                if ($this->http->XPath->query("//text()[{$this->contains($phrase)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
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
        //$this->logger->debug('IN '.$str);
        $in = [
            "#^\w+[,]\s+(\w+)\s+(\d+)[,]\s+(\d{4})\s+at\s+([\d\:]+\s+A?P?M)$#u", //Thu, Feb 06, 2014  at 05:30 PM
            "#^\w+[,]\s+(\d+)\s+(\w+)[,]\s+(\d{4})\s+at\s+([\d\:]+)$#", //Sat, 02 Nov, 2013 at 15:00
            "#^\w+[,]\s+(\d+)\s+(\w+)[,]\s+(\d{4})\s+(?:às|a)\s+([\d\:]+)$#u", //Qui, 06 Set, 2012 às 23:00 //pt
            "#^\w+[,]\s+(\d+)\s+(\w+)[,]\s+(\d{4})\s+um\s+([\d\:]+)$#u", //Fr, 05 Jul, 2013 um 17:30 //de
            "#^\w+[,]\s+(\d+)\s+(\w+)[,]\s+(\d{4})\s+a la\(s\)\s+([\d\:]+\s+A?P?M)$#u", //jue, 12 feb, 2015 a la(s) 02:00 PM //es
            "#^\w+[,]\s+(\d+)\s+(\w+)[,]\s+(\d{4})\s+om\s+([\d\:]+)$#u", //za, 28 nov, 2015 om 09:00 //nl
            "#^\w+[,]\s+(\d+)\s+(\w+)[,]\s+(\d{4})\s+for\s+([\d\:]+)$#u", //Sun, 28 Feb, 2016 for 11:00 //da
            "#^\w+[,]\s+(\d+)\s+(\w+)[,]\s+(\d{4})\s+a\s+la\(s\)\s+([\d\:]+)#u", //sáb, 19 nov, 2016 a la(s) 22:00 //es
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
