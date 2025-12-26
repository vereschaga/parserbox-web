<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: move examples it-55873727.eml, it-55873727.eml, it-94869910.eml in to parser It3688101

class ReservationLocationTwo extends \TAccountChecker
{
    public $mailFiles = "hertz/it-1.eml, hertz/it-11.eml, hertz/it-1548114.eml, hertz/it-1561832.eml, hertz/it-1570475.eml, hertz/it-1608151.eml, hertz/it-1617623.eml, hertz/it-1692683.eml, hertz/it-1892658.eml, hertz/it-1912655.eml, hertz/it-1965912.eml, hertz/it-2343231.eml, hertz/it-3227656.eml, hertz/it-5.eml, hertz/it-55873727.eml, hertz/it-55882021.eml, hertz/it-6.eml, hertz/it-8.eml, hertz/it-9.eml, hertz/it-94869910.eml";

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
                ". Your Reservation has been made.",
                "Thank you for your reservation,",
                ", You have successfully checked-in, and your booking confirmation",
            ],
            "Confirmation Number" => ["Your Confirmation Number is:", "Your reservation confirmation number is:", "Confirmation Number:"],
            "Account Number"      => "Your Hertz Gold Plus Rewards",
            //            "Your Itinerary" => "",
            "Pickup Time"     => ["Pickup Time", "Pick Up time", "Pick-Up Date", "Pick-up Time", "Pick-Up Time"],
            "Return Time"     => ["Return Time", "Return time", "Return Date", "Return Time"],
            "Pickup Location" => ["Pickup Location", "Pick Up Location", "Pick-Up Location", "Pick-up Location"],
            //            "Address" => "",
            //            "Location Type" => "",
            "Hours of Operation" => ["Hours of Operation", "Hours Of Operation"],
            "Phone Number"       => ["Phone Number", "Phone"],
            "Fax Number"         => ["Fax Number", "Fax"],
            //            "Return Location" => "",
            //            "Your Vehicle" => "",
            "Cost"  => ["Vehicle Subtotal"],
            "Tax"   => ["Taxes", "Extras Subtotal", "TAXES"],
            "Total" => ["Total Approximate Charge", "Total Estimated Charge", "Total"],
            //            "Driving Instructions" => "",
            "Day"                    => ["week", "Week", "day", "Days"],
            "What the rate includes" => ["What the rate includes", "Fees And Surcharges"],
        ],

        'pt' => [
            "Traveller" => [
                "Thanks for Traveling at the Speed of Hertz™,",
                "Thanks for Traveling at the Speed of Hertz,",
                "Obrigada por viajar a velocidade da Hertz,",
            ],
            "Confirmation Number" => ["Seu número de confirmação é:", "O seu número de Confirmação de Reserva é:"],
            //            "Account Number" => "",
            "Your Itinerary"     => ["Seu itinerário", "Your Itinerary"],
            "Pickup Time"        => ["Retirada", "Levantamento"],
            "Return Time"        => ["Devolução", "Devolução"],
            "Pickup Location"    => ["Loja de Retirada", "Estação de levantamento"],
            "Address"            => ["Endereço", "Morada"],
            "Location Type"      => ["Tipo de loja", "Tipo de estação"],
            "Hours of Operation" => ["Horário comercial", "Horário"],
            "Phone Number"       => ["Tel", "Número de Telefone"],
            "Fax Number"         => "Número de fax",
            "Return Location"    => ["Loja de Devolução", "Cidade de devolução"],
            "Your Vehicle"       => ["Veículo", "Your Vehicle"],
            "Cost"               => "Subtotal - Veículo",
            //            "Tax" => "",
            "Total"                => ["Total"],
            "Driving Instructions" => ["Indicações de percurso", "Como chegar"],
        ],

        'de' => [
            "Traveller" => [
                ". Ihre Reservierung wurde storniert.",
                "Thanks for Traveling at the Speed of Hertz,",
                "Vielen Dank für Ihre Reservierung,",
            ],
            "Confirmation Number" => ["Ihre Reservierungsnummer lautet:", "Reservierungsnummer:"],
            //"Account Number" => "",
            "Your Itinerary"     => "Ihr Reiseplan",
            "Pickup Time"        => ["Anmietung", "Anmietdatum"],
            "Return Time"        => ["Rückgabe", "Rückgabedatum"],
            "Pickup Location"    => ["Pickup Location", "Anmietstation"],
            "Address"            => "Adresse",
            "Location Type"      => ["Anmietstation", "Vermietstation"],
            "Hours of Operation" => "Öffnungszeiten",
            "Phone Number"       => ["Telefonnummer", "Telefon"],
            "Fax Number"         => ["Fax Nummer", "Fax"],
            "Return Location"    => ["Return Location", "Rückgabestation"],
            "Your Vehicle"       => "Ihr Fahrzeug",
            "or similar"         => "oder ähnlich",
            //"Cost" => "",
            //"Tax" => "",
            "Total" => ["Voraussichtliche Kosten", "Voraussichtlicher Mietpreis"],
        ],

        'es' => [
            "Traveller"           => ["Gracias por viajar a la velocidad de Hertz,", ", Usted se ha registrado con éxito, y su número de confirmación"],
            "Confirmation Number" => ["Su número de confirmación es el siguiente:", "Tu Número de Confirmación es el siguiente:"],
            //"Account Number" => "",
            "Your Itinerary"     => ["DATOS GENERALES", "Tu itinerario:"],
            "Pickup Time"        => ["Recogida", "Oficina de recogida"],
            "Return Time"        => ["Devolución", "Oficina de devolución"],
            "Pickup Location"    => ["Pickup Location", "Oficina de recogida"],
            "Address"            => "Dirección",
            "Location Type"      => ["Tipo de localidad", "Tipo de oficina"],
            "Hours of Operation" => ["Horarios de Atención", "Horario de la Oficina"],
            "Phone Number"       => ["Teléfono", "Número de telefono"],
            "Fax Number"         => ["Fax", "Número de fax"],
            "Return Location"    => ["Return Location", "Oficina de devolución"],
            "Your Vehicle"       => ["Tu vehículo:"],
            "or similar"         => "o similar",
            //"Cost" => "",
            //"Tax" => "",
            "Total" => ["Total", "Cargo Total Estimado:"],
        ],

        'nl' => [
            "Traveller"           => "Dank u voor uw reservering bij Hertz,",
            "Confirmation Number" => "Uw bevestigingsnummer is:",
            //"Account Number" => "",
            "Your Itinerary"     => "Uw reisschema",
            "Pickup Time"        => "Ophaalgegevens",
            "Return Time"        => "Inlevergegevens",
            "Pickup Location"    => "Ophalen - test",
            "Address"            => "Addres",
            "Location Type"      => "Locatietype",
            "Hours of Operation" => "Openingstijden",
            "Phone Number"       => "Telefoonnummer",
            "Fax Number"         => "Faxnummer",
            "Return Location"    => "Terugbrenglocatie - test",
            "Your Vehicle"       => "uw voertuig",
            "Cost"               => "Subtotaal",
            "Tax"                => "Subtotaal opties",
            "Total"              => ["Totaal", "Totaal *"],
        ],

        'da' => [
            "Traveller"           => "Tak fordi du valgte at bestille din billeje hos Hertz,",
            "Confirmation Number" => "Dit bestillingsnr. er:",
            //"Account Number" => "",
            "Your Itinerary" => "Din rejseplan",
            "Pickup Time"    => "Afhentning:",
            "Return Time"    => "Returnering:",
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
            "Total"        => ["Total"],
        ],

        'fr' => [
            "Traveller" => [
                "Merci d'avoir choisi Hertz",
            ],
            "Confirmation Number" => ["Votre numéro de confirmation est:"],
            //"Account Number" => "",
            "Your Itinerary"         => "Votre itinéraire",
            "Pickup Time"            => ["Date de départ"],
            "Return Time"            => ["Date de retour"],
            "Pickup Location"        => ["Lieu de départ"],
            "Address"                => "Adresse",
            "Location Type"          => ["Type d'agence"],
            "Hours of Operation"     => "Horaires d'ouverture",
            "Phone Number"           => ["Téléphone"],
            "Fax Number"             => ["Fax"],
            "Return Location"        => ["Lieu de restitution"],
            "Your Vehicle"           => "Votre Véhicule",
            "or similar"             => "ou similaire",
            "Cost"                   => "Détails du tarif",
            "What the rate includes" => "Ce que le tarif inclut",
            "Tax"                    => "Taxe de vente totale",
            "Total"                  => ["Total"],
            "Driving Instructions"   => "Directions à suivre",
        ],
    ];
    private $from = [
        'hertz.com',
    ];

    private $subject = [
        'en' => ['My Hertz Reservation', 'Your Hertz Reservation'],
        'pt' => ['A minha reserva Hertz'],
        'de' => ['Ihre Hertz Reservierung'],
        'es' => ['Mi Reserva de Hertz'],
        'nl' => ['Mijn Hertz Reservering'],
        'fr' => ['Ma réservation Hertz'],
        //'da' => [''],
    ];

    private $body = [
        'en' => ["Return Location"],
        'pt' => ["Loja de Devolução", "Cidade de devolução"],
        'de' => ["Rückgabedatum"],
        'es' => ["Oficina de devolución"],
        'nl' => ["Terugbrenglocatie - test"],
        'fr' => ["Lieu de restitution"],
        //'da' => [""],
    ];

    private $detectLang = [
        'fr' => ['Lieu de restitution'],
        'pt' => ['Veículo', 'Horário'],
        'de' => ['Ihr Reiseplan'],
        'es' => ['Tu vehículo'],
        'nl' => ['Uw reisschema'],
        //'da' => ['Din rejseplan'],
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
                if ($this->http->XPath->query("//text()[{$this->starts($body)}]")->length > 0) {
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
        } else {
            $r->general()->noConfirmation();
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

        //Account Number
        $accountNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Account Number'))}]/following::text()[normalize-space()][1]", null, true, "/(\d{7,})/");

        if (empty(trim($accountNumber)) && !empty($traveller)) {
            $accountNumber = $this->http->FindSingleNode("//text()[normalize-space(.)='Your Confirmation Number is:']/following::text()[normalize-space()][2]/ancestor::tr[1]", null, true, "/{$this->opt($this->t($traveller))}\s*[#]\s*(\d+)/is");
        }

        if (empty(trim($accountNumber)) && !empty($traveller)) {
            $accountNumber = $this->http->FindSingleNode("//text()[normalize-space(.)='Votre numéro de confirmation est:']/following::text()[normalize-space()][2]/ancestor::tr[1]", null, true, "/{$this->opt($this->t($traveller))}\s*[#]\s*(\d+)/is");
        }

        if (!empty($accountNumber)) {
            $r->ota()
                ->account($accountNumber, false);
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

        if (empty($pickupDate)) {
            $pickupDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Itinerary'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]/following-sibling::tr[1]/td[1]");
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

        if (empty($dropOffDate)) {
            $dropOffDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Itinerary'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]/following-sibling::tr[1]/td[2]");
        }

        $r->dropoff()
            ->date($this->normalizeDate($dropOffDate));

        //if - Pickup Location
        //     Return Location
        $rentalText = implode(' , ', $this->http->FindNodes("//text()[{$this->starts($this->t('Pickup Location'))} or {$this->starts($this->t('Return Location'))}]/ancestor::td[1]"));

        if (!empty($this->re("/({$this->opt($this->t('Pickup Location'))})/", $rentalText))
            && empty($this->re("/({$this->opt($this->t('Phone Number'))})/", $rentalText))) {
            $rentalText = implode(' , ', $this->http->FindNodes("//text()[{$this->starts($this->t('Pickup Location'))} or {$this->starts($this->t('Return Location'))}]/ancestor::td[2]"));

            if (!empty($this->re("/({$this->opt($this->t('Driving Instructions'))})/", $rentalText))) {
                $rentalText = str_replace(["\U003csup\U003e", "\U003c/Sup\U003e"], "", $rentalText);
                $rentalText = preg_replace("/({$this->opt($this->t('Driving Instructions'))}(?:\:?\s?Google\s?MapsMSN\s?MapsMapQuest|Google\s?MapsAAA\s?TripTik\(R\)NeverLost\s?Online\s?Trip\s?Planning|\s*Google\s+MapsAAA\s+\S+NeverLost\s+Online\s+Trip\s+Planning|\s*Google Maps AAA TripTik\(R\) NeverLost Online Trip Planning))/", '', $rentalText);
            }
        }

        if (!empty($this->re("/({$this->opt($this->t('Your Itinerary'))})/", $rentalText))) {
            $rentalText = implode(' , ', $this->http->FindNodes("//text()[{$this->starts($this->t('Phone Number'))}]/ancestor::td[1]"));
        }

        $this->logger->error($rentalText);

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

        //if - Pickup Location
        //     Return Location
        if (!empty($rentalText)) {
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
                . "{$this->opt($this->t('Fax Number'))}?\s?\:?\s?\:?\s?(?<PickupFax>[\d\-?\s?\(?\)?\+?]+)\s?\s?\,?\s?"

                . "{$this->opt($this->t('Return Location'))}\:?(?<DropOffLocation>.+)"
                . "{$this->opt($this->t('Hours of Operation'))}\s?\:?\s?(?<DropOffHours>.+)"
                . "{$this->opt($this->t('Location Type'))}\s?\:?\:?\s?(?<DropOffType>.+)"
                . "{$this->opt($this->t('Phone Number'))}\s?\:?\s?\:?\s?(?<DropOffPhone>[\d\-?\s?\(?\)?\+?]+)\s?"
                . "{$this->opt($this->t('Fax Number'))}?\s?\:?\s?\:?\s?(?<DropOffFax>[\d\-?\s?\(?\)?\+?]+)$/";

            $pattern7 = "/^(?<PickupLocation>[\S\s]+)\s?{$this->opt($this->t('Location Type'))}[:]+\D+"
                . "{$this->opt($this->t('Hours of Operation'))}[:]+\s(?<PickupHours>.+)\.?\s?"
                . "{$this->opt($this->t('Phone Number'))}[:]+\s(?<PickupPhone>[\d\s\-]+)"
                . "(?:{$this->opt($this->t('Fax Number'))}[:]+\s(?<PickupFax>[\d\s\-]+))?\s\,\s?"
                . "(?<DropOffLocation>[\S\s]+)\s?{$this->opt($this->t('Location Type'))}[:]+\D+"
                . "{$this->opt($this->t('Hours of Operation'))}[:]+\s(?<DropOffHours>.+)\.?\s?"
                . "{$this->opt($this->t('Phone Number'))}[:]+\s(?<DropOffPhone>[\d\s\-]+)"
                . "(?:{$this->opt($this->t('Fax Number'))}[:]+\s(?<DropOffFax>[\d\s\-]+))?$/";

            $this->logger->error($pattern6);

            $pattern8 = "/^(?<PickupLocation>[\S\s]+)\s"
                . "{$this->opt($this->t('Phone Number'))}[:]+\s(?<PickupPhone>[\d\s\-]+)"
                . "{$this->opt($this->t('Fax Number'))}[:]+\s(?<PickupFax>[\d\s\-]+)"
                . "{$this->opt($this->t('Hours of Operation'))}[:]+\s(?<PickupHours>.+)"
                . "{$this->opt($this->t('Location Type'))}[:]+\D+\,"
                . "(?<DropOffLocation>[\S\s]+)\s"
                . "{$this->opt($this->t('Phone Number'))}[:]+\s(?<DropOffPhone>[\d\s\-]+)"
                . "{$this->opt($this->t('Fax Number'))}[:]+\s(?<DropOffFax>[\d\s\-]+)"
                . "{$this->opt($this->t('Hours of Operation'))}[:]+\s(?<DropOffHours>.+)"
                . "{$this->opt($this->t('Location Type'))}[:]+\D+$/";

            if (preg_match($pattern4, $rentalText, $m)
                || preg_match($pattern5, $rentalText, $m)
                || preg_match($pattern6, $rentalText, $m)
                || preg_match($pattern7, $rentalText, $m)
                || preg_match($pattern8, $rentalText, $m)) {
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

                if (isset($m['PickupFax']) and !empty(trim($m['PickupFax']))) {
                    $r->pickup()
                        ->fax($m['PickupFax']);
                }

                $r->dropoff()
                    ->location(trim($dropOffLocation, ':'))
                    ->openingHours($m['DropOffHours'])
                    ->phone($m['DropOffPhone']);

                if (isset($m['DropOffFax']) and !empty(trim($m['DropOffFax']))) {
                    $r->dropoff()
                        ->fax($m['DropOffFax']);
                }
            }
        }

        //Car Type|Model|Image
        $type = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Vehicle'))}]/following::text()[normalize-space()][2][not({$this->eq($this->t('or similar'))})]");

        if (empty($type)) {
            $type = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Vehicle'))}]/following::text()[normalize-space()][3]");
        }

        if (!empty($type)) {
            $r->car()
                ->type($type);
        }

        $model = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Vehicle'))}]/following::text()[normalize-space()][1]");

        if (!empty($model)) {
            $r->car()
                ->model($model);
        }

        $image = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Vehicle'))}]/following::img[contains(@src, 'hertz.com')][1]/@src");

        if (!empty($image)) {
            $r->car()
                ->image($image);
        }

        //Price
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]/descendant::td[2]");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/following::text()[normalize-space()][1]");
        }

        if (empty($this->re('/(\d+)/', $total))) {
            $total = $this->re("/{$this->opt($this->t('Total'))}\s\s?([\d\.?\,?]+\s+[A-Z]{3})/u", $this->bodyText);
        }

        if (!empty($total)) {
            $r->price()
                ->total($this->normalizePrice($this->re('/([\d\.?\,?]+)/', $total)))
                ->currency($this->re('/([A-Z]{3})/', $total));
        }

        $cost = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cost'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]/descendant::text()[normalize-space()][last()]", null, true, "/([\d\.?\,?]+)/");

        if (empty($cost)) {
            $cost = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cost'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/([\d\.?\,?]+)/");
        }

        if (empty($cost)) {
            $cost = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cost'))}]/following::text()[normalize-space()][1]", null, true, "/([\d\.?\,?]+)/");
        }

        if (empty($cost)) {
            $cost = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}]/preceding::text()[{$this->eq($this->t('Cost'))}][1]/following::text()[normalize-space()][1]", null, true, "/([\d\.?\,?]+)/");
        }

        if (!empty($cost) & $cost > 0) {
            $r->price()
                ->cost($this->normalizePrice($cost));
        }

        //fees
        if (!empty($this->http->FindSingleNode("//text()[{$this->eq($this->t('What the rate includes'))}]"))) {
            $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('What the rate includes'))}]/ancestor::tr[1]/descendant::tr/td[string-length()>2][2]");

            if ($nodes->length == 0) {
                $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('What the rate includes'))}]/ancestor::tr[1]/following::tr[1]/descendant::tr/td[string-length()>2][2]");
            }

            if ($nodes->count() > 0) {
                foreach ($nodes as $root) {
                    $feeSum = $this->http->FindSingleNode(".", $root, true, "/^\s*([\d\.]+)/");
                    $feeName = $this->http->FindSingleNode("./preceding::td[1]", $root);

                    if (!empty($feeSum) && !empty($feeName)) {
                        $r->price()
                            ->fee($feeName, $feeSum);
                    }
                }

                $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Sales Tax'))}]/following::text()[string-length() > 2][1]", null, true, "/([\d\.?\,?]+)/");

                if (!empty($tax)) {
                    $r->price()
                        ->tax($tax);
                }
            } else {
                $tax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Tax'))}]/following::text()[normalize-space()][1]", null, true, "/([\d\.?\,?]+)/");

                if (empty($tax)) {
                    $tax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}]/preceding::text()[{$this->eq($this->t('Tax'))}][1]/following::text()[normalize-space()][1]", null, true, "/([\d\.?\,?]+)/");
                }

                if ((!empty($tax) & $tax > 0) && (!empty($cost) & $cost > 0)) {
                    $r->price()
                        ->tax($this->normalizePrice($tax));
                }
            }
        } else {
            $tax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Tax'))}]/following::text()[normalize-space()][1]", null, true, "/([\d\.?\,?]+)/");

            if (empty($tax)) {
                $tax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}]/preceding::text()[{$this->eq($this->t('Tax'))}][1]/following::text()[normalize-space()][1]", null, true, "/([\d\.?\,?]+)/");
            }

            if ((!empty($tax) & $tax > 0) && (!empty($cost) & $cost > 0)) {
                $r->price()
                    ->tax($this->normalizePrice($tax));
            }
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
        //$this->logger->debug($str);
        $in = [
            "#^\w+[,]\s+(\w+)\s+(\d+)[,]\s+(\d{4})\s+at\s+([\d\:]+\s+A?P?M)$#u", //Thu, Feb 06, 2014  at 05:30 PM
            "#^\w+[,]\s+(\d+)\s+(\w+)[,]\s+(\d{4})\s+at\s+([\d\:]+)$#", //Sat, 02 Nov, 2013 at 15:00
            "#^\w+[,]\s+(\d+)\s+(\w+)[,]\s+(\d{4})\s+(?:às|a)\s+([\d\:]+)$#u", //Qui, 06 Set, 2012 às 23:00 //pt
            "#^\w+[,]\s+(\d+)\s+(\w+)[,]\s+(\d{4})\s+um\s+([\d\:]+)$#u", //Fr, 05 Jul, 2013 um 17:30 //de
            "#^\w+[,]\s+(\d+)\s+(\w+)[,]\s+(\d{4})\s+a la\(s\)\s+([\d\:]+\s+A?P?M)$#u", //jue, 12 feb, 2015 a la(s) 02:00 PM //es
            "#^\w+[,]\s+(\d+)\s+(\w+)[,]\s+(\d{4})\s+om\s+([\d\:]+)$#u", //za, 28 nov, 2015 om 09:00 //nl
            "#^\w+[,]\s+(\d+)\s+(\w+)[,]\s+(\d{4})\s+for\s+([\d\:]+)$#u", //Sun, 28 Feb, 2016 for 11:00 //da
            "#^\w+[,]\s+(\d+)\s+(\w+)[,]\s+(\d{4})\s+a\s+la\(s\)\s+([\d\:]+)#u", //sáb, 19 nov, 2016 a la(s) 22:00 //es
            "#^\:?\s*\w+\.\,\s*(\d+)\s*(\w+)\.\,\s*(\d{4})\D+([\d\:]+)$#u", //fr lun., 19 juil., 2021 à 08:00
            "#^\:?\s*\w+\.\,\s*(\w+)\.\s*(\d+)\,\s*(\d{4})\D+([\d\:]+\s*A?P?M)$#u", //: mar., déc. 03, 2019 à 09:00 AM
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
            "$1 $2 $3, $4",
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        //$this->logger->error($str);
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
