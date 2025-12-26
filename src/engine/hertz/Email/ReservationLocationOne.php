<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationLocationOne extends \TAccountChecker
{
    public $mailFiles = "hertz/it-1547912.eml, hertz/it-1603227.eml, hertz/it-1747571.eml, hertz/it-1766198.eml, hertz/it-1766338.eml, hertz/it-1766340.eml, hertz/it-1903973.eml, hertz/it-1989313.eml, hertz/it-2.eml, hertz/it-2311293.eml, hertz/it-2468609.eml, hertz/it-3.eml, hertz/it-3050008.eml, hertz/it-3209463.eml, hertz/it-3321802.eml, hertz/it-4.eml, hertz/it-4981377.eml, hertz/it-4993208.eml, hertz/it-4993278.eml, hertz/it-7.eml, hertz/it-83681809.eml";

    public $lang = '';
    public $reHeaders;
    public $bodyText;

    public static $dictionary = [
        'en' => [
            "Traveller" => [
                "Thanks for Traveling at the Speed of Hertz™,",
                "Thanks for Traveling at the speed of Hertz™,",
                "Thanks for Travelling at the speed of Hertz,",
                "Thanks for Travelling at the Speed of Hertz,",
                "Thanks for Traveling at the Speed of Hertz®",
                ", you have successfully checked-in, and your booking confirmation number is below.",
            ],
            "Confirmation Number" => ["Your Confirmation Number is:", "Your reservation confirmation number is:", "Confirmation Number:"],
            "Account Number"      => "Your Hertz Gold Plus Rewards",
            //            "Your Itinerary" => "",
            "Pickup Time"                => ["Pickup Time", "Pick Up time"],
            "Return Time"                => ["Return Time", "Return time"],
            "Pickup and Return Location" => ["Pickup and Return Location", "Pickup Location & Return Location"],
            //            "Address" => "",
            //            "Location Type" => "",
            //            "Hours of Operation" => "",
            //            "Phone Number" => "",
            //            "Fax Number" => "",
            //            "Your Vehicle" => "",
            "Cost"  => "Vehicle Subtotal",
            "Tax"   => ["Taxes", "Extras Subtotal"],
            "Total" => ["Total Approximate Charge", "Total Estimated Charge", "Total"],
            //            "Driving Instructions" => "",
            "Day" => ["week", "Week", "day", "Days"],
        ],

        'pt' => [
            "Traveller" => [
                "Thanks for Traveling at the Speed of Hertz™,",
                "Thanks for Traveling at the Speed of Hertz,",
                "Obrigada por viajar a velocidade da Hertz,",
            ],
            "Confirmation Number" => ["Seu número de confirmação é:", "O seu número de Confirmação de Reserva é:"],
            //            "Account Number" => "",
            "Your Itinerary"             => ["Seu itinerário", "Your Itinerary"],
            "Pickup Time"                => ["Retirada", "Levantamento"],
            "Return Time"                => ["Devolução", "Devolução"],
            "Pickup and Return Location" => "Pickup and Return Location",
            "Address"                    => ["Endereço", "Morada"],
            "Location Type"              => ["Tipo de loja", "Tipo de estação"],
            "Hours of Operation"         => ["Horário comercial", "Horário"],
            "Phone Number"               => ["Tel", "Número de Telefone"],
            "Fax Number"                 => "Número de fax",
            "Your Vehicle"               => ["Veículo", "Your Vehicle"],
            "Cost"                       => "Subtotal - Veículo",
            //            "Tax" => "",
            "Total"                => ["Total"],
            "Driving Instructions" => ["Indicações de percurso", "Como chegar"],
        ],

        'de' => [
            "Traveller" => [
                ". Ihre Reservierung wurde storniert.",
                "Thanks for Traveling at the Speed of Hertz,",
            ],
            "Confirmation Number"        => "Ihre Reservierungsnummer lautet:",
            "Account Number"             => "",
            "Your Itinerary"             => "Ihr Reiseplan",
            "Pickup Time"                => "Anmietung",
            "Return Time"                => "Rückgabe",
            "Pickup and Return Location" => ["Anmietstation & Rückgabestation", "Ort der Anmietung und Ort der Rückgabe"],
            "Address"                    => "Adresse",
            "Location Type"              => "Anmietstation",
            "Hours of Operation"         => "Öffnungszeiten",
            "Phone Number"               => "Telefonnummer",
            "Fax Number"                 => "Fax Nummer",
            "Your Vehicle"               => "Ihr Fahrzeug",
            //"Cost" => "",
            //"Tax" => "",
            "Total" => ["Voraussichtliche Kosten"],
        ],

        'es' => [
            "Traveller"           => "Gracias por viajar a la velocidad de Hertz,",
            "Confirmation Number" => ["Su número de confirmación es el siguiente:", "Tu Número de Confirmación es el siguiente:"],
            //"Account Number" => "",
            "Your Itinerary"             => ["DATOS GENERALES", "Tu itinerario:"],
            "Pickup Time"                => ["Recogida", "Oficina de recogida:"],
            "Return Time"                => ["Devolución", "Oficina de devolución:"],
            "Pickup and Return Location" => ["Localidad de Recogida y Devolución", "Oficina de recogida y de devolución", "Localidad de Recogida y Devolución"],
            "Address"                    => "Dirección",
            "Location Type"              => ["Tipo de localidad", "Tipo de oficina", "Tipo De Localidad"],
            "Hours of Operation"         => ["Horarios de Atención", "Horario de la Oficina", "Horarios De Atención"],
            "Phone Number"               => ["Teléfono", "Número de telefono"],
            "Fax Number"                 => ["Fax", "Número de fax"],
            "Your Vehicle"               => ["Vehículo", "Tu vehículo:"],
            //"Cost" => "",
            //"Tax" => "",
            "Total" => ["Total", "Cantidad total"],
        ],

        'nl' => [
            "Traveller"           => "Dank u voor uw reservering bij Hertz,",
            "Confirmation Number" => "Uw bevestigingsnummer is:",
            //"Account Number" => "",
            "Your Itinerary"             => "Uw reisschema",
            "Pickup Time"                => "Ophaalgegevens",
            "Return Time"                => "Inlevergegevens",
            "Pickup and Return Location" => "Ophaal- en inleverlocatie",
            "Address"                    => "Addres",
            "Location Type"              => "Locatietype",
            "Hours of Operation"         => "Openingstijden",
            "Phone Number"               => "Telefoonnummer",
            "Fax Number"                 => "Faxnummer",
            "Your Vehicle"               => "uw voertuig",
            "Cost"                       => "Subtotaal",
            "Tax"                        => "Subtotaal opties",
            "Total"                      => ["Totaal", "Totaal *"],
        ],

        'da' => [
            "Traveller"           => "Tak fordi du valgte at bestille din billeje hos Hertz,",
            "Confirmation Number" => "Dit bestillingsnr. er:",
            //"Account Number" => "",
            "Your Itinerary"             => "Din rejseplan",
            "Pickup Time"                => "Afhentning:",
            "Return Time"                => "Returnering:",
            "Pickup and Return Location" => "Afhentnings- og returneringskontor",
            "Address"                    => "Adresse",
            "Location Type"              => "Hertz kontor",
            "Hours of Operation"         => "Åbningstider",
            "Phone Number"               => "Telefon",
            "Fax Number"                 => "Fax",
            "Your Vehicle"               => "Din bil",
            "Cost"                       => "Subtotal",
            "Tax"                        => "Tilvalg subtotal",
            "Total"                      => ["Total"],
        ],
    ];
    private $from = [
        'hertz.com',
    ];
    private $subject = [
        'en' => ['Your Hertz Reservation', 'My Hertz Reservation', 'Hertz Reservation', 'HNL HZ'],
        'pt' => ['A minha reserva Hertz'],
        'de' => ['Ihre Reservierung wurde storniert', 'Meine Hertz Reservierung'],
        'es' => ['Mi Reserva Hertz'],
        'nl' => ['Mijn Hertz Reservering'],
        'da' => ['Min Hertz billeje bestilling'],
    ];
    private $body = [
        'en' => ["Pickup and Return Location"],
        'pt' => ["Pickup and Return Location"],
        'de' => ["Ort der Anmietung und Ort der Rückgabe"],
        'es' => ["Localidad de Recogida y Devolución", "Oficina de recogida y de devolución"],
        'nl' => ["Ophaal- en inleverlocatie"],
        'da' => ["Afhentnings- og returneringskontor"],
    ];

    private $detectLang = [
        'pt' => ['Veículo', 'Horário'],
        'de' => ['Ihr Reiseplan'],
        'es' => ['Vehículo', 'Tu itinerario'],
        'nl' => ['Uw reisschema'],
        'da' => ['Din rejseplan'],
        'en' => ['Your Vehicle', 'Your Itinerary'],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->alert("Can't determine a language!");

            return $email;
        }

        $this->reHeaders = $parser->getHeader('subject');
        $this->bodyText = strip_tags($parser->getHTMLBody());

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
                    foreach (self::$dictionary as $lang => $words) {
                        foreach ($words['Total'] as $word) {
                            if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    protected function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);            // 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);    // 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);    // 18800,00		->	18800.00

        return $string;
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
        //Account Number
        $accountNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Account Number'))}]/following::text()[normalize-space()][1]", null, true, "/(\d{7,})/");

        if (!empty($accountNumber)) {
            $r->ota()
                ->account($accountNumber, false);
        }

        //Confirmation Number
        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number'))}]/following::text()[normalize-space()][1]", null, true, "/([A-Z\d]{10,})/");

        if (empty($confirmation)) {
            $confirmation = $this->re('/Hertz\s+Reservation\s+([A-Z\d]{10,})/', $this->reHeaders);
        }

        if (empty($confirmation)) {
            $confirmation = $this->re("/{$this->opt($this->t('Confirmation Number'))}\s+([A-Z\d]{10,})/", $this->bodyText);
        }

        $r->general()
            ->confirmation($confirmation);

        //Travellers
        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Traveller'))}]", null, true, "/{$this->opt($this->t('Traveller'))}\s?(\D+)/");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Traveller'))}]", null, true, "/(?:Thanks\s?)?([A-Za-z\s\-?]+)\.?\s?{$this->opt($this->t('Traveller'))}/");
        }

        if (!empty($traveller)) {
            $r->general()
                ->traveller($traveller, true);
        }

        //PickUp/DropOff
        $pickupDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Itinerary'))}]/following::text()[{$this->starts($this->t('Pickup Time'))}]/ancestor::*[2]", null, true, "/{$this->opt($this->t('Pickup Time'))}\s+(.+)/");

        if (empty($pickupDate)) {
            $pickupDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Itinerary'))}]/following::text()[{$this->starts($this->t('Pickup Time'))}]/ancestor::td[1]/descendant::text()[normalize-space()][2]");
        }

        if (empty($pickupDate)) {
            $pickupDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Itinerary'))}]/following::text()[{$this->starts($this->t('Pickup Time'))}]/ancestor::tr[1]/following-sibling::tr[1]/descendant::td[1]");
        }

        if (empty($pickupDate)) {
            $pickupDate = $this->re("/{$this->opt($this->t('Pickup Time'))}\:?\s?(\w+[,]\s+\w+\s+\d+[,]\s+\d{4}\s+at\s+[\d\:]+\s+A?P?M)/u", $this->bodyText);
        }

        $r->pickup()
            ->date($this->normalizeDate($pickupDate));

        $dropOffDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Itinerary'))}]/following::text()[{$this->starts($this->t('Return Time'))}]/ancestor::*[2]", null, true, "/{$this->opt($this->t('Return Time'))}\s+(.+)/");

        if (empty($dropOffDate)) {
            $dropOffDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Itinerary'))}]/following::text()[{$this->starts($this->t('Return Time'))}]/ancestor::td[1]/descendant::text()[normalize-space()][2]");
        }

        if (empty($dropOffDate)) {
            $dropOffDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Itinerary'))}]/following::text()[{$this->starts($this->t('Return Time'))}]/ancestor::tr[1]/following-sibling::tr[1]/descendant::td[2]");
        }

        if (empty($dropOffDate)) {
            $dropOffDate = $this->re("/{$this->opt($this->t('Return Time'))}\:?\s?(\w+[,]\s+\w+\s+\d+[,]\s+\d{4}\s+at\s+[\d\:]+\s+A?P?M)/u", $this->bodyText);
        }

        $r->dropoff()
            ->date($this->normalizeDate($dropOffDate));

        // if - Pickup and Return Location
        $rentalText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Pickup and Return Location'))}]/ancestor::tr[1]/descendant::td[normalize-space()][1]");

        if (!empty($this->re("/({$this->opt($this->t('Pickup and Return Location'))})/", $rentalText))
            && empty($this->re("/({$this->opt($this->t('Phone Number'))})/", $rentalText))) {
            $rentalText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Pickup and Return Location'))}]/ancestor::table[1]");
        }

        // If email text only
        if (empty($rentalText)) {
            $rentalText = $this->re("/({$this->opt($this->t('Pickup and Return Location'))}.+)Discounts/", $this->bodyText);
        }

        if (!empty($pickupTimeText = $this->re("/\S({$this->opt($this->t('Pickup Time'))}.+){$this->opt($this->t('Return Location'))}/", $rentalText))) {
            $rentalText = str_replace($pickupTimeText, '', $rentalText);
        }

        // if - Pickup and Return Location
        if (!empty($rentalText)) {
            $pattern = "/^{$this->opt($this->t('Pickup and Return Location'))}\.?\:?\s?(?<name>.+)"
                . "{$this->opt($this->t('Address'))}?(?<address>.+)?"
                . "{$this->opt($this->t('Location Type'))}\s?\:?(?<type>.+)"
                . "{$this->opt($this->t('Hours of Operation'))}\s?\:?\s?(?<hours>.+)"
                . "{$this->opt($this->t('Phone Number'))}\s?\:?\s?\:?(?<phoneNumber>[\d\-?\s?\(?\)?]+)"
                . "{$this->opt($this->t('Fax Number'))}\s?\:?\s?\:?\s?(?<faxNumber>[\d\-?\s?\(?\)?]+)$/";

            $pattern2 = "/^{$this->opt($this->t('Pickup and Return Location'))}\.?\:?\s?(?<name>.+)"
                . "{$this->opt($this->t('Address'))}?(?<address>.+)?"
                . "{$this->opt($this->t('Hours of Operation'))}\s?\:?\s?(?<hours>.+)"
                . "{$this->opt($this->t('Location Type'))}\s?\:?(?<type>.+)"
                . "{$this->opt($this->t('Phone Number'))}\s?\:?\s?\:?(?<phoneNumber>[\d\-?\s?\(?\)?]+)"
                . "{$this->opt($this->t('Fax Number'))}\s?\:?\s?\:?\s?(?<faxNumber>[\d\-?\s?\(?\)?]+)$/";

            $pattern3 = "/^{$this->opt($this->t('Pickup and Return Location'))}\.?\:?\s?(?<name>.+)"
                . "{$this->opt($this->t('Address'))}?(?<address>.+)?"
                . "{$this->opt($this->t('Hours of Operation'))}\s?\:?\s?(?<hours>.+)"
                . "{$this->opt($this->t('Location Type'))}\s?\:?(?<type>.+)"
                . "{$this->opt($this->t('Phone Number'))}\s?\:?\s?\:?(?<phoneNumber>[\d\-?\s?\(?\)?]+)/ui";

            if (preg_match($pattern, $rentalText, $m) or preg_match($pattern2, $rentalText, $m) or preg_match($pattern3, $rentalText, $m)) {
                $address = trim($m['name']) . ', ' . trim($m['address']);
                $address = preg_replace("/{$this->opt($this->t('Address'))}/", ",", $address);

                $r->pickup()
                    ->location($address)
                    ->openingHours($m['hours'])
                    ->phone($m['phoneNumber']);

                if (isset($m['faxNumber'])) {
                    $r->pickup()
                        ->fax($m['faxNumber']);
                }

                $r->dropoff()
                    ->same();
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

        $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}]/ancestor::tr[1]/descendant::td[2]");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}]/following::text()[normalize-space()][1]");
        }

        if (empty($this->re('/(\d+)/', $total))) {
            $total = $this->re("/{$this->opt($this->t('Total'))}\s\s?([\d\.?\,?]+\s+[A-Z]{3})/u", $this->bodyText);
        }

        if (!empty($total)) {
            $r->price()
                ->total($this->normalizePrice($this->re('/([\d\.?\,?]+)/', $total)))
                ->currency($this->re('/([A-Z]{3})/', $total));
        }

        $cost = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cost'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/([\d\.?\,?]+)/");

        if (empty($cost)) {
            $cost = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cost'))}]/following::text()[normalize-space()][1]", null, true, "/([\d\.?\,?]+)/");
        }

        if (empty($cost)) {
            $cost = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}]/preceding::text()[{$this->eq($this->t('Cost'))}][1]/following::text()[normalize-space()][1]", null, true, "/([\d\.?\,?]+)/");
        }

        $tax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Tax'))}]/following::text()[normalize-space()][1]", null, true, "/([\d\.?\,?]+)/");

        if (empty($tax)) {
            $tax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}]/preceding::text()[{$this->eq($this->t('Tax'))}][1]/following::text()[normalize-space()][1]", null, true, "/([\d\.?\,?]+)/");
        }

        if (!empty($cost) & $cost > 0 & !empty($tax) & $tax > 0) {
            $r->price()
                ->cost($this->normalizePrice($cost))
                ->tax($this->normalizePrice($tax));
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
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
