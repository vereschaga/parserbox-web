<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class RentalCar extends \TAccountChecker
{
    public $mailFiles = "hertz/it-83468517.eml";

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
                "We've cancelled your reservation.",
                "Thanks for Traveling at the Speed of Hertz®",
                ", We've cancelled your reservation.",
                ", you have successfully checked-in, and your booking confirmation number is below.",
            ],
            "Confirmation Number" => ["Your Confirmation Number is:", "Your reservation confirmation number is:", "Confirmation Number:"],
            "Cancellation Number" => "Please retain cancellation number",
            "Account Number"      => "Your Hertz Gold Plus Rewards",
            //            "Your Itinerary" => "",
            "Pickup Time"                => ["Pickup Time", "Pick Up time"],
            "Return Time"                => ["Return Time", "Return time"],
            "Pickup and Return Location" => ["Pickup and Return Location", "Pickup Location & Return Location"],
            "Pickup Location"            => ["Pickup Location", "Pick Up Location"],
            //            "Address" => "",
            //            "Location Type" => "",
            //            "Hours of Operation" => "",
            //            "Phone Number" => "",
            //            "Fax Number" => "",
            //            "Return Location" => "",
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
            "Cancellation Number" => "Por favor, guarde o seu número de cancelamento.",
            //            "Account Number" => "",
            "Your Itinerary"             => ["Seu itinerário", "Your Itinerary"],
            "Pickup Time"                => ["Retirada", "Levantamento"],
            "Return Time"                => ["Devolução", "Devolução"],
            "Pickup and Return Location" => "Pickup and Return Location",
            "Pickup Location"            => ["Loja de Retirada", "Estação de levantamento"],
            "Address"                    => ["Endereço", "Morada"],
            "Location Type"              => ["Tipo de loja", "Tipo de estação"],
            "Hours of Operation"         => ["Horário comercial", "Horário"],
            "Phone Number"               => ["Tel", "Número de Telefone"],
            "Fax Number"                 => "Número de fax",
            "Return Location"            => ["Loja de Devolução", "Cidade de devolução"],
            "Your Vehicle"               => ["Veículo", "Your Vehicle"],
            "Cost"                       => "Subtotal - Veículo",
            //            "Tax" => "",
            "Total"                => "Total",
            "Driving Instructions" => ["Indicações de percurso", "Como chegar"],
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

        'es' => [
            "Traveller"                  => ["Gracias por viajar a la velocidad de Hertz,", ", Su reserva ha sido cancelada."],
            "Confirmation Number"        => "Su número de confirmación es el siguiente:",
            "Cancellation Number"        => "Por favor guarde su número de cancelación",
            "Account Number"             => "",
            "Your Itinerary"             => ["DATOS GENERALES", "Datos Generales"],
            "Pickup Time"                => "Recogida",
            "Return Time"                => "Devolución",
            "Pickup and Return Location" => ["Localidad de Recogida y Devolución", "Localidad de Recogida & Localidad de Devolución"],
            //"Pickup Location" => "",
            "Address"            => "Dirección",
            "Location Type"      => "Tipo de localidad",
            "Hours of Operation" => "Horarios de Atención",
            "Phone Number"       => "Teléfono",
            "Fax Number"         => "Fax",
            //"Return Location" => "",
            "Your Vehicle" => "Vehículo",
            //"Cost" => "",
            //"Tax" => "",
            "Total" => "Total",
        ],

        'nl' => [
            "Traveller"           => "Dank u voor uw reservering bij Hertz,",
            "Confirmation Number" => "Uw bevestigingsnummer is:",
            //"Cancellation Number" => "",
            //"Account Number" => "",
            "Your Itinerary"             => "Uw reisschema",
            "Pickup Time"                => "Ophaalgegevens",
            "Return Time"                => "Inlevergegevens",
            "Pickup and Return Location" => "Ophaal- en inleverlocatie",
            "Pickup Location"            => "Ophalen - test",
            "Address"                    => "Addres",
            "Location Type"              => "Locatietype",
            "Hours of Operation"         => "Openingstijden",
            "Phone Number"               => "Telefoonnummer",
            "Fax Number"                 => "Faxnummer",
            "Return Location"            => "Terugbrenglocatie - test",
            "Your Vehicle"               => "uw voertuig",
            "Cost"                       => "Subtotaal",
            "Tax"                        => "Subtotaal opties",
            "Total"                      => ["Totaal", "Totaal *"],
        ],

        'da' => [
            "Traveller"           => "Tak fordi du valgte at bestille din billeje hos Hertz,",
            "Confirmation Number" => "Dit bestillingsnr. er:",
            //"Cancellation Number" => "",
            //"Account Number" => "",
            "Your Itinerary"             => "Din rejseplan",
            "Pickup Time"                => "Afhentning:",
            "Return Time"                => "Returnering:",
            "Pickup and Return Location" => "Afhentnings- og returneringskontor",
            //"Pickup Location" => "",
            "Address"            => "Adresse",
            "Location Type"      => "Hertz kontor",
            "Hours of Operation" => "Åbningstider",
            "Phone Number"       => "Telefon",
            "Fax Number"         => "Fax",
            //"Return Location" => "",
            "Your Vehicle" => "Din bil",
            "Cost"         => "Subtotal",
            "Tax"          => "Tilvalg subtotal",
            "Total"        => "Total",
        ],
    ];
    private $from = [
        'hertz.com',
    ];
    private $subject = [
        'en' => ['Hertz Reservation', 'HNL HZ', 'Hertz Reservation Cancellation'],
        'pt' => ['A minha reserva Hertz'],
        'de' => ['Ihre Reservierung wurde storniert'],
        'es' => ['Mi Reserva Hertz'],
        'nl' => ['Mijn Hertz Reservering'],
        'da' => ['Min Hertz billeje bestilling'],
    ];
    private $body = [
        'en' => [
            "Thanks for Traveling at the Speed of Hertz",
            "The Hertz Corporation",
            "www.hertz-ebilling.com",
        ],
        'pt' => ["Thanks for Traveling at the Speed of Hertz"],
        //'de' => [""]
        'es' => ["Gracias por viajar a la velocidad de Hertz"],
        'nl' => ["Dank u voor uw reservering bij Hertz"],
        'da' => ["Tak fordi du valgte at bestille din billeje hos Hertz"],
    ];

    private $detectLang = [
        'pt' => ['Veículo', 'Horário'],
        'de' => ['Ihr Reiseplan'],
        'es' => ['Vehículo', 'Datos Generales'],
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
                    return true;
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
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    private function parseRental(Email $email)
    {
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

        if (!empty($confirmation)) {
            $r->general()
                ->confirmation($confirmation);
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation Number'))}]/ancestor::*[1]", null, true, "/([A-Z\d]{10,})/");

        if (!empty($cancellation)) {
            $r->general()
                ->confirmation($cancellation, 'cancellation number')
                ->cancellationNumber($cancellation)
                ->cancelled();
        }

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

        if (!empty($pickupDate)) {
            $r->pickup()
                ->date($this->normalizeDate($pickupDate));
        }

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

        // If email text only
        if (empty($rentalText)) {
            $rentalText = $this->re("/({$this->opt($this->t('Pickup and Return Location'))}.+)Discounts/", $this->bodyText);
        }

        if (empty($rentalText)) {
            $rentalText = $this->re("/({$this->opt($this->t('Pickup Location'))}.+){$this->opt($this->t('Return Time'))}/", $this->bodyText);
        }

        if (empty($rentalText)) {
            $rentalText = $this->re("/({$this->opt($this->t('Pickup Location'))}.+)Discounts/", $this->bodyText);
        }

        if (!empty($pickupTimeText = $this->re("/\S({$this->opt($this->t('Pickup Time'))}.+){$this->opt($this->t('Return Location'))}/", $rentalText))) {
            $rentalText = str_replace($pickupTimeText, '', $rentalText);
        }

        $this->logger->warning($rentalText);
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

            if (preg_match($pattern, $rentalText, $m) or preg_match($pattern2, $rentalText, $m)) {
                $r->pickup()
                    ->location(trim($m['name']) . ', ' . trim($m['address']))
                    ->openingHours($m['hours'])
                    ->phone($m['phoneNumber'])
                    ->fax($m['faxNumber']);

                $r->dropoff()
                    ->same();
            }

            // if cancelled
            $pattern3 = "/{$this->opt($this->t('Pickup and Return Location'))}\.?\s(?<location>.+)\s?"
                         . "{$this->opt($this->t('Phone Number'))}\s?\:?\:?(?<phoneNumber>[\d\-?\s?\(?\)?]+)\s?"
                         . "{$this->opt($this->t('Fax Number'))}\s?\:?\s?\:?(?<faxNumber>[\d\-?\s?\(?\)?]+)\s?"
                         . "{$this->opt($this->t('Hours of Operation'))}\s?\:?(?<hours>.+)"
                         . "{$this->opt($this->t('Location Type'))}\s?\:?\:?(?<type>.+)$/";

            $pattern8 = "/{$this->opt($this->t('Pickup and Return Location'))}\.?\s(?<location>.+)\s?"
                . "{$this->opt($this->t('Phone Number'))}\s?\:?\s*\:?\s*(?<phoneNumber>[\d\-?\s?\(?\)?]+)\s?"
                . "{$this->opt($this->t('Hours of Operation'))}\s?\:?(?<hours>.+)"
                . "{$this->opt($this->t('Location Type'))}\s?\:?\:?(?<type>.+)$/";

            $pattern7 = "/{$this->opt($this->t('Pickup Location'))}\.?\s(?<PickupLocation>.+)\s?"
                        . "{$this->opt($this->t('Phone Number'))}\s?\:?\:?(?<PickupPhone>[\d\-?\s?\(?\)?]+)\s?"
                        . "{$this->opt($this->t('Fax Number'))}\s?\:?\s?\:?(?<PickupFax>[\d\-?\s?\(?\)?]+)\s?"
                        . "{$this->opt($this->t('Hours of Operation'))}\s?\:?(?<PickupHours>.+)"
                        . "{$this->opt($this->t('Location Type'))}\s?\:?\:?(?<PickupType>.+)"

                        . "{$this->opt($this->t('Return Location'))}\.?\s(?<DropOffLocation>.+)\s?"
                        . "{$this->opt($this->t('Phone Number'))}\s?\:?\:?(?<DropOffPhone>[\d\-?\s?\(?\)?]+)\s?"
                        . "{$this->opt($this->t('Fax Number'))}\s?\:?\s?\:?(?<DropOffFax>[\d\-?\s?\(?\)?]+)\s?"
                        . "{$this->opt($this->t('Hours of Operation'))}\s?\:?(?<DropOffHours>.+)"
                        . "{$this->opt($this->t('Location Type'))}\s?\:?\:?(?<DropOffType>.+)$/";

            $this->logger->error($pattern8);

            if (preg_match($pattern3, $rentalText, $m) or preg_match($pattern8, $rentalText, $m)) {
                $r->pickup()
                    ->location($m['location'])
                    ->openingHours($m['hours'])
                    ->phone($m['phoneNumber']);

                if (isset($m['faxNumber'])) {
                    $r->pickup()
                        ->fax($m['faxNumber']);
                }

                $r->dropoff()
                    ->same();
            }

            //if - Pickup Location
            //     Return Location

            $pattern4 = "/^{$this->opt($this->t('Pickup Location'))}(?<PickupLocation>.+)"
                         . "{$this->opt($this->t('Address'))}(?<PickupAddress>.+)"
                         . "{$this->opt($this->t('Location Type'))}\s?\:?\s?(?<PickupType>.+)"
                         . "{$this->opt($this->t('Hours of Operation'))}\s?\:?\s?(?<PickupHours>.+)"
                         . "{$this->opt($this->t('Phone Number'))}\s?\:?\s?\:?\s?(?<PickupPhone>[\d\-?\s?\(?\)?]+)"
                         . "{$this->opt($this->t('Fax Number'))}?\s?\:?\:?\s?(?<PickupFax>[\d\-?\s?\(?\)?]+)?\s+[,]\s+"

                         . "{$this->opt($this->t('Return Location'))}(?<DropOffLocation>.+)"
                         . "{$this->opt($this->t('Address'))}(?<DropOffAddress>.+)"
                         . "{$this->opt($this->t('Location Type'))}\s?\:\s?(?<DropOffType>.+)"
                         . "{$this->opt($this->t('Hours of Operation'))}\s?\:?\s?(?<DropOffHours>.+)"
                         . "{$this->opt($this->t('Phone Number'))}\s?\:?\s?\:?\s?(?<DropOffPhone>[\d\-?\s?\(?\)?]+)\s?"
                         . "{$this->opt($this->t('Fax Number'))}\s?\:?\:?\s?(?<DropOffFax>[\d\-?\s?\(?\)?]+)$/";

            $pattern5 = "/^{$this->opt($this->t('Pickup Location'))}\:?(?<PickupLocation>.+)"
                         . "{$this->opt($this->t('Location Type'))}\s?\:?\s?(?<PickupType>.+)"
                         . "{$this->opt($this->t('Hours of Operation'))}\s?\:?\s?(?<PickupHours>.+)"
                         . "{$this->opt($this->t('Phone Number'))}\s?\:?\s?\:?\s?(?<PickupPhone>[\d\-?\s?\(?\)?]+)"
                         . "{$this->opt($this->t('Fax Number'))}\s?\:?\:?\s?(?<PickupFax>[\d\-?\s?\(?\)?]+)\s?\,?\s?"

                         . "{$this->opt($this->t('Return Location'))}\:?(?<DropOffLocation>.+)"
                         . "{$this->opt($this->t('Location Type'))}\s?\:\s?(?<DropOffType>.+)"
                         . "{$this->opt($this->t('Hours of Operation'))}\s?\:?\s?(?<DropOffHours>.+)"
                         . "{$this->opt($this->t('Phone Number'))}\s?\:?\s?\:?\s?(?<DropOffPhone>[\d\-?\s?\(?\)?]+)\s?"
                         . "{$this->opt($this->t('Fax Number'))}\s?\:?\:?\s?(?<DropOffFax>[\d\-?\s?\(?\)?]+)$/";

            $pattern6 = "/^{$this->opt($this->t('Pickup Location'))}\:?(?<PickupLocation>.+)"
                         . "{$this->opt($this->t('Hours of Operation'))}\s?\:?\s?(?<PickupHours>.+)"
                         . "{$this->opt($this->t('Location Type'))}\s?\:?\:?\s?(?<PickupType>.+)"
                         . "{$this->opt($this->t('Phone Number'))}\s?\:?\s?\:?\s?(?<PickupPhone>[\d\-?\s?\(?\)?\+?]+)"
                         . "{$this->opt($this->t('Fax Number'))}\s?\:?\s?\:?\s?(?<PickupFax>[\d\-?\s?\(?\)?\+?]+)\s?\s?\,?\s?"

                         . "{$this->opt($this->t('Return Location'))}\:?(?<DropOffLocation>.+)"
                         . "{$this->opt($this->t('Hours of Operation'))}\s?\:?\s?(?<DropOffHours>.+)"
                         . "{$this->opt($this->t('Location Type'))}\s?\:?\:?\s?(?<DropOffType>.+)"
                         . "{$this->opt($this->t('Phone Number'))}\s?\:?\s?\:?\s?(?<DropOffPhone>[\d\-?\s?\(?\)?\+?]+)\s?"
                         . "{$this->opt($this->t('Fax Number'))}\s?\:?\s?\:?\s?(?<DropOffFax>[\d\-?\s?\(?\)?\+?]+)$/";

            if (preg_match($pattern4, $rentalText, $m)
                or preg_match($pattern5, $rentalText, $m)
                or preg_match($pattern6, $rentalText, $m)
                or preg_match($pattern7, $rentalText, $m)) {
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
                    ->phone($m['DropOffPhone'])
                    ->fax($m['DropOffFax']);
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

        //Price
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

        $discount = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Vehicle'))}]/following::text()[{$this->contains($this->t('Day'))}][1]/following::text()[normalize-space()][1]", null, true, "/([\d\.?\,?]+)/");

        if (!empty($discount)) {
            $r->price()
                ->discount($this->normalizePrice($discount));
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
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
            "#^\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*\D+([\d\:]+\s*a)?p?\.m?\.?\s*$#u", // mié, mar 17, 2021 a la(s) 10:00 a.m.
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
            "$2 $1 $3, $4m",
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
