<?php

namespace AwardWallet\Engine\homeaway\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "homeaway/it-190308854.eml, homeaway/it-49532684.eml, homeaway/it-49561286.eml, homeaway/it-49649200.eml, homeaway/it-57952879.eml, homeaway/it-58011137.eml, homeaway/it-58031272.eml, homeaway/it-58048930.eml, homeaway/it-58107823.eml, homeaway/it-61124544.eml, homeaway/it-61300231.eml, homeaway/it-61385007.eml, homeaway/it-61385008.eml, homeaway/it-617727965.eml, homeaway/it-618107427.eml, homeaway/it-62244225.eml, homeaway/it-63041814.eml, homeaway/it-70769098.eml";

    private static $detectors = [
        'en' => [
            "Property manager has replied to your message",
            "Your card has been charged",
            "Pay now to secure your reservation",
            "Book now before someone else does",
            "has replied to your message",
            "Message from the property manager",
            "Your reservation has been confirmed",
            "Thanks for your payment",
            "Your request has been sent",
            "Payment Request",
            "Reservation cancelled",
            "Your booking was updated",
            "you've canceled.",
            'Your reservation was canceled',
        ],

        'de' => [
            "Ihre Reservierung wurde bestätigt",
            "Buchungsnummer",
        ],

        'fr' => [
            "Votre réservation a été confirmée",
        ],
    ];

    private static $dictionary = [
        'en' => [
            "firstDetect"                       => "Property",
            "lastDetect"                        => ["Property manager", "Owner name", "Reservation ID"],
            "Property"                          => ["Property", "Property:", "Property no."],
            "Property manager"                  => ["Property manager", "Owner name"],
            "Reservation ID"                    => ["Reservation ID", "Reservation ID:"],
            "Reservation confirmed"             => "Reservation confirmed",
            "Reservation cancelled"             => ["Reservation cancelled", 'Your reservation has been canceled', 'Your reservation was canceled'],
            "statusVariants"                    => "confirmed",
            "get ready for your trip to"        => ["get ready for your trip to"],
            "Arrive"                            => "Arrive",
            "Depart"                            => "Depart",
            "Time arrival"                      => ["Time arrival", "Check-in time"],
            "Time depart"                       => ["Time depart", "Check-out time"],
            "Guests"                            => "Guests",
            "adult"                             => "adult",
            "children"                          => ["children", "kid"],
            "Payment schedule"                  => "Payment schedule",
            "Total"                             => ["Total", "Total traveler payment"],
            "Message from the property manager" => ["Message from the property manager"],
            "The address to the property is"    => "The address to the property is",
            "make up for your trip"             => ["make up for your trip", "get ready for your trip to", "get ready for your trip"],
            "cancellation"                      => ["you can cancell for a 100% refund until", "you can cancel for a 100% refund until"],
            "Taxes and Fees"                    => ["Taxes and Fees", "Tax", 'Total Taxes', 'Taxes you collect (10%)'],
            "fees"                              => ["Reservations Fee", "Departure Clean", "Service Fee", "Lodging Tax", "Cleaning Fee", "Refundable Damage Deposit", "Property Damage Protection", "Owner Fees", 'Host Fees'],
            "Offer"                             => ["Offer", "Charges", 'Traveler payment'],
            "Nights"                            => ["Nights", "nights"],
            "Night"                             => ["Nights", "night"],
            "house"                             => [
                "House", "Hotel", "Townhome", "Farmhouse", "Villa", "Cottage", "Condo",
                "Bungalow", "Apartment", "Cabin", "Chalet", "Studio", "campground", "Lodge",
                "Townhouse", "Resort", "Place", "Estate", "Country house/chateau", "Building",
                "Mas", "Guest house", "Chalet", "Camp ground", "Hotel suite", "House boat", "Bed & breakfast",
                "Recreational Vehicle", "Cabin", "Entire home",
            ],

            // Junk
            "Book Now"                           => ["Book Now", "Request to book", "Book now", "Add trip dates"],
            "Your request has been sent"         => ["Your request has been sent", "Your request expired"],
            'Pay now to secure your reservation' => ['Pay now to secure your reservation', 'Your payment has been refunded'],
        ],

        'de' => [
            "firstDetect"           => ["Buchungsnummer", "Objekt"],
            "lastDetect"            => ["Objektnummer", "Reservierungsnummer"],
            "Reservation ID"        => ["Buchungsnummer", "Reservierungsnummer"],
            "Property"              => ["Objektnummer", "Objekt"],
            "Reservation confirmed" => "Reservierung bestätigt",
            "statusVariants"        => "bestätigt",
            "Arrive"                => "Anreise",
            "Depart"                => "Abreise",
            "Time arrival"          => "Uhrzeit Anreise",
            "Time depart"           => "Uhrzeit Abreise",
            "Guests"                => "Gäste",
            "adult"                 => "Erwachsene",
            "children"              => "Kinder",
            "Taxes and Fees"        => ["Steuern und Gebühren", "Servicegebühr"],
            "fees"                  => ["Reinigungsgebühr"],
            "Total"                 => ["Gesamt"],
            "Nights"                => "Nächte",
            "Night"                 => "Nächt",
            "Offer"                 => ["Angebot", "Gebühren"],
            "make up for your trip" => "machen Sie sich für Ihre Reise nach",
            //            "cancellation" => "",
            "house" => [
                "Kleines Landhaus", "House", "Townhome", "Villa", "Cottage", "Condo",
                "Bungalow", "Apartment", "Cabin", "Hotel", "Wohnung in einer Anlage", "Unterkunft",
                "Ferienhaus", "Bed & Breakfast", "Ferienhütte", "Hostel",
            ],
            "Your request has been sent" => ["Ihre Buchungsanfrage wurde gesendet."],
        ],

        'fr' => [
            "firstDetect"           => "Arrivée",
            "lastDetect"            => ["Nom du propriétaire", "Numéro de réservation"],
            "Property"              => "Location",
            "Property manager"      => ["Nom du propriétaire"],
            "Reservation ID"        => "Numéro de réservation",
            "Reservation confirmed" => "Votre réservation a été confirmée",
            "statusVariants"        => "confirmée",
            //            "get ready for your trip to" => "get ready for your trip to",
            "Arrive" => "Arrivée",
            "Depart" => "Départ",
            //            "Time arrival" => ["Time arrival", "Check-in time"],
            //            "Time depart" => ["Time depart", "Check-out time"],
            "Guests"   => "Vacanciers",
            "adult"    => "adult",
            "children" => ["enfant"],
            //            "Payment schedule" => "Payment schedule",
            //            "Total" => "Total",
            //            "Message from the property manager" => ["Message from the property manager"],
            //            "The address to the property is" => "The address to the property is",
            //            "make up for your trip" => ["make up for your trip", "get ready for your trip to"],
            "cancellation"   => ["you can cancell for a 100% refund until", "you can cancel for a 100% refund until"],
            "Taxes and Fees" => ["Taxe de séjour"],
            "fees"           => ["Frais de service"],
            "Nights"         => "nuitées",
            "Night"          => "nuitée", // ?
            "Offer"          => "Tarif",
            "house"          => [
                "Maison", "Villa", "Appartement", "Location", "Mobile home", "Bungalow",
                "Hutte/pavillon", "Chalet", "Cottage", "Chambres d'hôtes", "Maison de ville", "Manoir/château",
                "Studio",
            ],

            // Junk
            //            "Book Now" => ["Book Now", "Request to book", "Book now", "Add trip dates"],
            //            "Your request has been sent" => ["Your request has been sent", "Your request expired"],
        ],
    ];

    private $from = "messages.homeaway.com";

    private $body = ["Vrbo", "homeaway.com", "FeWo-direkt"];

    private $subject = [
        "Reservation confirmation",
        "Your inquiry",
        "Ihre Reservierung wurde bestätigt",
        "Payment confirmation ",
        "Your reservation has been confirmed",
        "Your reservation was cancelled",
        " Your reservation:",
    ];

    private $lang = 'en';

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $this->http->Response['body'];

        foreach ($this->body as $body) {
            if (stripos($text, $body) !== false) {
                return true;
            }
        }

        if ($this->detectBody()) {
            return $this->assignLang();
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

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $email->setType('BookingConfirmation');

        $this->assignLang();

        if (
            (stripos($parser->getCleanFrom(), $this->from) !== false && $this->isJunk() === true)
            || preg_match("#^(\w{1,5}\W?:)?\s*Your inquiry: #u", $parser->getSubject())
            || strpos($parser->getSubject(), 'Your cancellation request was sent') !== false
        ) {
            $email->setIsJunk(true);

            return $email;
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $email->setType($email->getType() . ucfirst($this->lang));

        $this->parseHotel($email);

        return $email;
    }

    private function parseHotel(Email $email): void
    {
        if (!$this->detectBody()) {
            return;
        }
//        if ($this->isJunk() === true) {
//            $email->setIsJunk(true);
//            return;
//        }

        $r = $email->add()->hotel();
        $r->hotel()->house();

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Payment processing fees will be applied after the traveler has paid')]/preceding::text()[normalize-space()='Total payout']/following::text()[contains(normalize-space(), 'Estimated payout**')]")->length > 0
            || $this->http->XPath->query("//text()[normalize-space()='Your payment receipt']/following::text()[contains(normalize-space(), 'Traveler Payment')]/following::text()[contains(normalize-space(), 'Payment to you:')]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains($this->t('Pay now to secure your reservation'))}]/following::text()[string-length()>5][1][contains(normalize-space(), 'Below is a copy of the email you sent, for your records')]")->length > 0) {
            $r->setHost(true);
        }

        $status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your booking was')]", null, true, "/{$this->opt($this->t('Your booking was'))}\s*(\w+)/u");

        if (!empty($status)) {
            $r->general()
                ->status($status);
        }

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Reservation cancelled")) . "])[1]"))) {
            $r->general()
                ->status('Cancelled')
                ->cancelled();
        }

        $cancellation = $this->http->FindSingleNode("//text()[normalize-space()='Cancellation policy:']/following::text()[normalize-space()][string-length()>3][1]/ancestor::tr[1]");

        if (!$cancellation) {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->contains($this->t("cancellation"))}]", null, true, "/\(\s*([^)(]+{$this->opt($this->t("cancellation"))}[^)(]+?)\s*\)/");
        }

        if (!$cancellation) {
            $cancellation = $this->http->FindSingleNode("//*[{$this->eq($this->t("Cancellation Policy"))}]/following-sibling::*[normalize-space()][1]");
        }

        if (!empty($cancellation)) {
            $r->general()
                ->cancellation($cancellation);
        }

        //address
        $address = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('The address to the property is')) . "]",
            null, true, "/" . $this->opt($this->t('The address to the property is')) . "\s(.+)$/");

        //hotelName
        $hotelName = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Property')) . "]/following-sibling::th[starts-with(normalize-space(.),'#')]");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Property')) . "]/following-sibling::td[starts-with(normalize-space(.),'#')]");
        }

        if (empty($confNo)) {
            $hotelName = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Property')) . "]/following::text()[normalize-space()][not(normalize-space() = ': #')][1]", null, true, "/^\s*([\d]{5,})\s*$/");
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Property')) . "]/following::tr[1]/descendant::a[1]");
        }

        if (!empty($hotelName)) {
            $hotelName = str_replace(['<', '>'], '', $hotelName);
            $r->hotel()->name($hotelName);
        }

        //pax
        /*
         * don't add a passengers from the message text
        $pax = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Message from the property manager')) . "]/following::text()[" . $this->contains($this->t('Hello')) . "]",
            null, true, '/^[\s]?' . $this->opt($this->t('Hello')) . '\s(.+)\!/');

        if (empty($pax)) {
            $pax = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Hello')) . "]", null, true,
                '/' . $this->opt($this->t('Hello')) . '\s(.+?)[.,!-]/');
        }
*/
        // TODO: delete if pax is taken from the text of the message
        $pax = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('make up for your trip')) . "]",
            null, true,
            '/^([A-z\s]+)\,[\s]?' . $this->opt($this->t('make up for your trip')) . '/');

        if (!empty($pax)) {
            $r->general()->traveller($pax, false);
        }

        //confNo
        $confNo = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Reservation ID')) . "]/following-sibling::th[position() = 1 and not(not(./*[contains(normalize-space(.),'')]))][not(starts-with(normalize-space(.),'Property ID'))]", null, true, "/^\s*[:#]?\s*(\d+[\d\-A-Z]+)\s*$/");

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Reservation ID')) . "]/following-sibling::td[position() = 1 and not(starts-with(normalize-space(.),'Property ID')) and not(not(./*[contains(normalize-space(.),'')]))]", null, true, "/^\s*[:#]?\s*(\d+[\d\-A-Z]+)\s*$/");
        }

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Reservation ID')) . "]/following::tr[1]/descendant::th[1]", null, true, "/^\s*[:#]?\s*(\d+[\d\-A-Z]+)\s*$/");
        }

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Reservation ID')) . "]/ancestor::td[1]/following::td[2]", null, true, "/^\s*[:#]?\s*(\d+[\d\-A-Z]+)\s*$/");
        }

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Reservation ID')) . "]/following::text()[normalize-space()][not(normalize-space()=':') and not(normalize-space()='#')][1]", null, true, "/^\s*([\dA-Z\-]{5,})\s*$/");
        }

        if (!empty($confNo) && $this->http->FindSingleNode("(//text()[starts-with(normalize-space(), 'Reservation ID')]/preceding::tr[normalize-space() and not(.//tr)][position()<=5] [contains(., 'look for a response within 24 hours') or .//text() = 'Your request has been sent'])[1]")) {
            $email->removeItinerary($r);
            $email->setIsJunk(true);

            return;
        }

        if (!empty($confNo)) {
            $r->general()->confirmation($confNo, trim($this->http->FindSingleNode("(//text()[" . $this->starts($this->t('Reservation ID')) . "])[1]"), ':'));
        } else {
            $r->general()->noConfirmation();
        }

        //status
        $status = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Reservation confirmed'))}][1]", null, true, "/\s+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/");

        if ($status) {
            $r->general()->status($status);
        }

        $dateFormat = '/\b([[:alpha:]]{3,}\s\d{1,2},\s\d{4}|\d{1,2}[.]? [[:alpha:]]{3,4}\.?\s\d{4})\b/u';
        $timeIn = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Time arrival')) . "]/following::tr[1]/descendant::th[1]");

        if (empty($timeIn)) {
            $timeIn = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Check in')) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*after (\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/i");
        }
        $checkIn = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Arrive')) . "]/following-sibling::th[position() = 1 and not(not(./*[contains(normalize-space(.),'')]))]",
            null, false, $dateFormat);

        if (empty($checkIn)) {
            $checkIn = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Arrive')) . "]/following-sibling::td[position() = 1 and not(not(./*[contains(normalize-space(.),'')]))]",
                null, false, $dateFormat);
        }

        if (empty($checkIn)) {
            $checkIn = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Arrive:')) . "]/following::text()[normalize-space()][1]", null, true, "/.*\d{4}.*/");
        }

        if (empty($checkIn)) {
            $checkIn = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Arrive')) . "]/following::text()[normalize-space()][1]", null, true, "/^\s*:\s*(.*\d{4}.*)/");
        }

        if (empty($checkIn)) {
            $checkIn = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t('Arrive')) . "]/following::tr[1]/descendant::th[1])[1]");
        }

        if (empty($checkIn)) {
            $checkIn = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Arrive')) . "]/ancestor::tr[contains(translate(normalize-space(), '0123456789', 'dddddddddd'), ', dddd')][1]/descendant::td[contains(translate(normalize-space(), '0123456789', 'dddddddddd'), ', dddd') and not(" . $this->starts($this->t('Arrive')) . ")][1]");
        }

        if (!empty($checkIn)) {
            $checkIn = str_replace('/', '.', $checkIn);
            $r->booked()->checkIn($this->normalizeDate($checkIn . ' ' . $timeIn));
        }

        $timeOut = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Time depart')) . "]/following::tr[1]/descendant::th[1]");

        if (empty($timeOut)) {
            $timeOut = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Check out')) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*after (\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/i");
        }
        $checkOut = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Depart')) . "]/following-sibling::th[position() = 1 and not(not(./*[contains(normalize-space(.),'')]))]",
            null, false, $dateFormat);

        if (empty($checkOut)) {
            $checkOut = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Depart')) . "]/following-sibling::td[position() = 1 and not(not(./*[contains(normalize-space(.),'')]))]",
                null, false, $dateFormat);
        }

        if (empty($checkOut)) {
            $checkOut = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Depart:')) . "]/following::text()[normalize-space()][1]", null, true, "/.*\d{4}.*/");
        }

        if (empty($checkOut)) {
            $checkOut = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Depart')) . "]/following::text()[normalize-space()][1]", null, true, "/^\s*:\s*(.*\d{4}.*)/");
        }

        if (empty($checkOut)) {
            $checkOut = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t('Depart')) . "]/following::tr[1]/descendant::th[1])[1]");
        }

        if (empty($checkOut)) {
            $checkOut = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Depart')) . "]/ancestor::tr[contains(translate(normalize-space(), '0123456789', 'dddddddddd'), ', dddd')][1]/descendant::td[contains(translate(normalize-space(), '0123456789', 'dddddddddd'), ', dddd') and not(" . $this->starts($this->t('Depart')) . ")][1]");
        }

        if (!empty($checkOut)) {
            $checkOut = str_replace('/', '.', $checkOut);
            $r->booked()->checkOut($this->normalizeDate($checkOut . ' ' . $timeOut));
        }

        if (empty($checkIn) && empty($checkOut)) {
            $checkDates = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Dates')) . "]/following-sibling::td[position() = 1 and not(not(./*[contains(normalize-space(.),'')]))]");

            if (empty($checkDates)) {
                $checkDates = $this->http->FindSingleNode("(//*[" . $this->starts($this->t('Dates')) . "]/following::text()[normalize-space(.)])[1]");
            }

            if (!empty($checkDates)) {
                if (preg_match('/([A-z]{3})\s(\d{1,2})\-(\d{1,2}),[\s]?(\d{4})/', $checkDates, $m)) {
                    $r->booked()->checkIn(strtotime($m[1] . ' ' . $m[2] . ' ' . $m[4]));
                    $r->booked()->checkOut(strtotime($m[1] . ' ' . $m[3] . ' ' . $m[4]));
                } elseif (preg_match('/^\s*([A-z]{3})\s(\d{1,2})\s+(\d{4})\-([A-z]{3})\s(\d{1,2})\s+(\d{4}),/', $checkDates, $m)) {
                    // Sep 19 2019-Sep 21 2019
                    $r->booked()->checkIn(strtotime($m[2] . ' ' . $m[1] . ' ' . $m[3]));
                    $r->booked()->checkOut(strtotime($m[5] . ' ' . $m[4] . ' ' . $m[6]));
                } elseif (preg_match('/^\s*(.+?)\s*-\s*(.+?)$/', $checkDates, $m)) {
                    // Apr 11 - Apr 12, 2020
                    $r->booked()->checkOut2($m[2]);
                    $r->booked()->checkIn(strtotime($m[1], $r->getCheckOutDate()));
                }
            }
        }

        // Hotel Addres
        $expiredLink = false;

        if (empty($address)) {
            if (!empty($r->getCheckInDate()) && !empty($r->getCheckOutDate())) {
                $hotel = $this->getHotelAddress();

                if (!empty($hotel) && !empty($hotel['errorJunk'])) {
                    $expiredLink = $hotel['errorJunk'];

                    return;
                }

                if (!empty($hotel)) {
                    $hotel['name'] = str_replace(['<', '>', '{', '}'], '', $hotel['name']);
                    $address = $hotel['address'];
                    $r->hotel()->name($hotel['name']);
                }
            } else {
                $this->logger->debug('address was not searched by url because check in or check out dates are empty');
            }
        }

        if (empty($address)) {
            $text = join("\n", $this->http->FindNodes("(//text()[" . $this->contains($this->t('Property Address:')) . "])[1]/following-sibling::text()"));
            /*
             Kahana Outrigger
            4521 Lower Honoapiilani Rd.
            Unit 1A1
            Lahaina, HI 96761
            Check-in: 3p
             */

            if (preg_match('/^(.+?)\s*\n+(.+?)\s+Check-in:/s', $text, $m)) {
                $m[1] = str_replace(['<', '>'], '', $m[1]);
                $r->hotel()->name($m[1]);
                $address = preg_replace("/\n/", ', ', $m[2]);
            }
        }

        if (empty($address) && !empty($expiredLink)) {
            $email->removeItinerary($r);
            $email->setIsJunk(true, $expiredLink);

            return;
        }

        if (!empty($address)) {
            $address = str_replace(['<', '>', '{', '}'], '', $address);
            $r->hotel()->address($address);
        }

        //guests
        $guests = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Guests')) . "]/following-sibling::th[position() = 1 and not(not(./*[contains(normalize-space(.),'')]))]",
            null, true, "/(\d+)\s" . $this->opt($this->t('adult')) . "/");

        if (empty($guests)) {
            $guests = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Guests')) . "]/following-sibling::td[position() = 1 and not(not(./*[contains(normalize-space(.),'')]))]",
                null, true, "/(\d+)\s" . $this->opt($this->t('adult')) . "/");
        }

        if (empty($guests)) {
            $text = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Guests')) . "]/following::tr[1]/descendant::th[1]|(//text()[" . $this->starts($this->t('Guests')) . "]/following::td[" . $this->contains($this->t('adult')) . "])[1]");

            if (preg_match("/(\d+)\s" . $this->opt($this->t('adult')) . "/", $text, $m)) {
                $guests = $m[1];
            } elseif (preg_match("/^\s*(\d+)\s*$/", $text, $m)) {
                $guests = $m[1];
            }
        }

        if (empty($guests)) {
            $guests = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Guests:')) . "]/following::text()[normalize-space(.)][1]",
                null, true, "/(\d+)\s" . $this->opt($this->t('adult')) . "/");
        }

        if (empty($guests)) {
            $guests = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Guests')) . "]/following::text()[normalize-space(.)][1]",
                null, true, "/^\s*:\s*(\d+)\s" . $this->opt($this->t('adult')) . "/");
        }

        if (!empty($guests)) {
            $r->booked()->guests($guests);
        }

        //kids
        $kids = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Guests')) . "]/following-sibling::th[position() = 1 and not(not(./*[contains(normalize-space(.),'')]))]",
            null, true, "/(\d+)\s" . $this->opt($this->t('children')) . "/");

        if (!is_numeric($kids)) {
            $kids = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Guests')) . "]/following-sibling::td[position() = 1 and not(not(./*[contains(normalize-space(.),'')]))]",
                null, true, "/(\d+)\s" . $this->opt($this->t('children')) . "/");
        }

        if (empty($kids)) {
            $text = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Guests')) . "]/following::tr[1]/descendant::th[1]|(//text()[" . $this->starts($this->t('Guests')) . "]/following::td[" . $this->contains($this->t('adult')) . "])[1]");

            if (preg_match("/(\d+)\s" . $this->opt($this->t('children')) . "/", $text, $m)) {
                $kids = $m[1];
            } elseif (preg_match("/^\s*(\d+)\s*$/", $text, $m)) {
                $kids = $m[1];
            }
        }

        if (empty($kids)) {
            $kids = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Guests:')) . "]/following::text()[normalize-space(.)][1]",
                null, true, "/(\d+)\s" . $this->opt($this->t('children')) . "/");
        }

        if (empty($kids)) {
            $kids = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Guests')) . "]/following::text()[normalize-space(.)][1]",
                null, true, "/^\s*:\s*.*\b(\d+)\s" . $this->opt($this->t('children')) . "/");
        }

        if (is_numeric($kids)) {
            $r->booked()->kids($kids);
        }

        //tax
        $tax = $this->http->FindSingleNode("//*[{$this->starts($this->t('Taxes and Fees'))}]/following-sibling::th[1]", null, true, '/\d[,.\'\d]*/');

        if (empty($tax)) {
            $tax = $this->http->FindSingleNode("//div[count(*) = 2 and *[1][{$this->eq($this->t('Taxes and Fees'))}]]/div[2]", null, true, '/\d[,.\'\d]*/');
        }

        if (empty($tax)) {
            $tax = $this->http->FindSingleNode("//td[" . $this->eq($this->t('Taxes and Fees')) . "]/following-sibling::td[1]", null, true, '/^\D*(\d[,.\'\d ]*)\D*$/');
        }

        if ($tax !== null) {
            $r->price()
                    ->tax($this->priceNorm($tax));
        }
        //fees
        foreach (self::$dictionary as $dict) {
            if (!isset($dict['fees'])) {
                continue;
            }

            foreach ($dict['fees'] as $fee) {
                $f = $this->http->FindSingleNode("(//*[{$this->contains($fee)}]/following-sibling::th[1])[1]", null, true, '/\d[,.\'\d]*/');

                if (empty($f)) {
                    $f = $this->http->FindSingleNode("//div[count(*) = 2 and *[1][{$this->eq($fee)}]]/div[2]", null, true, '/\d[,.\'\d]*/');
                }

                if (empty($f)) {
                    $f = $this->http->FindSingleNode("//td[" . $this->eq($fee) . "]/following-sibling::td[1]", null, true, '/^\D*(\d[,.\'\d ]*)\D*$/');
                }

                if ($f !== null) {
                    $r->price()
                        ->fee($fee, $this->priceNorm($f));
                }
            }
        }

        //total & currency
        $total = $this->http->FindSingleNode("//*[" . $this->eq($this->t('Total')) . "]/following-sibling::th[1][not(following::*[self::td or self::th][" . $this->starts($this->t('fees')) . "])]");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("(//*[" . $this->eq($this->t('Total')) . "]/following-sibling::td[1])[1]");
        }

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//div[count(*) = 2 and *[1][{$this->eq($this->t('Total'))}]]/div[2]", null, true, '/.*\d.*/');
        }

        if (!empty($total)) {
            if (preg_match("/^\s*(?<amount>\d[,.\'\d]*)[\s]?(?<currency>[^\d\s]{1,5})\s*$/", $total, $m)
                || preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[,.\'\d ]*)\s*$/u", $total, $m)
            ) {
                $r->price()
                        ->total($this->priceNorm($m['amount']))
                        ->currency($this->normalizeCurrency($m['currency']));
            }
        }

        //cost
        $cost = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Offer')) . "]/following::th[" . $this->contains($this->t('Nights')) . "]/following-sibling::th[1]");

        if (empty($cost)) {
            $cost = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Charges')) . "]/following::div[count(*) = 2 and *[1][not(.//div)][{$this->contains($this->t('Nights'))}]]/div[2][not(.//div)]", null, true, '/.*\d.*/');
        }

        if (empty($cost)) {
            $cost = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Charges')) . "]/following::td[not(.//td)][normalize-space()][1][" . $this->contains($this->t('Nights')) . "]/following-sibling::td[normalize-space()][1]",
                null, false, '/^.*\d+.*/');
        }

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $cost, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $cost, $m)) {
            $r->price()
                ->cost($this->priceNorm($m['amount']))
            ;
        }

        //costNight
        $costNight = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Offer')) . "]/following::th[" . $this->contains($this->t('Nights')) . "]",
            null, false, '/^(.+?)[\s]?x[\s]?\d+\s' . $this->opt($this->t('Nights')) . '/');

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $costNight, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $costNight, $m)) {
            if (!empty($costNight)) {
                $r->addRoom()->setRate($costNight);
            }
        }

        if (!empty($r->getCancellation())) {
            $this->detectDeadLine($r, $r->getCancellation());
        }
    }

    private function isJunk(): bool
    {
        $mainWords = ["Reservation ID", "Arrive", "Dates", "Guests"];

        foreach ($mainWords as $w) {
            if ($this->http->XPath->query("//text()[{$this->eq($this->t($w))}]")->length > 1) {
                return false;
            }
        }

        $aUrl = ['vrbo.', 'homeaway.', '.fewo-direkt.de'];

        if ($this->http->FindSingleNode("//a[" . $this->eq($this->t("Respond to this Inquiry")) . " and " . $this->contains($aUrl, '@href') . "]")) {
            return true;
        }

        if ($this->http->FindSingleNode("//text()[" . $this->starts($this->t("You have been invited to book and will be confirmed instantly if payment is made")) . "]")) {
            return true;
        }

        if ($this->http->FindSingleNode("//a[" . $this->eq($this->t("Reply")) . " and " . $this->contains($aUrl, '@href') . "]/following::text()[normalize-space()][1]/ancestor::a[1][" . $this->eq($this->t("Book Now")) . " and " . $this->contains($aUrl, '@href') . "]")) {
            return true;
        }

        if ($this->http->FindSingleNode("//text()[" . $this->starts($this->t("You should hear back from the property manager soon.")) . "]/following::text()[normalize-space()][1]/ancestor::" .
            "a[1][(" . $this->eq($this->t("Book Now")) . " or " . $this->eq($this->t("Add trip dates")) . ") and " . $this->contains($aUrl, '@href') . "]")) {
            return true;
        }

        if ($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Message from the property manager")) . "]/following::a[normalize-space()][1]" .
            "[" . $this->eq($this->t("Reply")) . " and " . $this->contains($aUrl, '@href') . "]/following::a[normalize-space()][1][" . $this->eq($this->t("Add trip dates")) . " and " . $this->contains($aUrl, '@href') . "]")) {
            return true;
        }

        if ($this->http->FindSingleNode("//text()[" . $this->eq($this->t("My property is available for your dates")) . "]/following::a[normalize-space()]" .
            "[" . $this->eq($this->t("Request to book")) . " and " . $this->contains($aUrl, '@href') . "]")) {
            return true;
        }

        if ($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Your request has been sent")) . "])[1]")) {
            return true;
        }

        if ($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Available when booked")) . "])[1]")) {
            return true;
        }

        if ($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Your request was not accepted")) . "])[1]")) {
            return true;
        }

        return false;
    }

    private function detectBody(): bool
    {
        foreach (self::$detectors as $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getHotelAddress(): array
    {
        $propertyUrl = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Property')) . "]/following-sibling::th[position() = 1 and not(not(./*[contains(normalize-space(.),'')]))]//@href");

        if (empty($propertyUrl)) {
            $propertyUrl = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Property')) . "]/following-sibling::td[position() = 1 and not(not(./*[contains(normalize-space(.),'')]))]//@href");
        }

        if (empty($propertyUrl)) {
            $propertyUrl = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Property')) . "]/following::tr[1]/descendant::a/@href");
        }

        if (empty($propertyUrl)) {
            $propertyUrl = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Property')) . "]/following::text()[normalize-space()][not(normalize-space() = ':')][1]/ancestor::a/@href");
        }

        if (empty($propertyUrl)) {
            $propertyUrl = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Property')) . "]/following::text()[normalize-space()][not(normalize-space() = '#')][1]/ancestor::a/@href");
        }

        if (empty($propertyUrl)) {
            $propertyUrl = $this->http->FindSingleNode("//strong[" . $this->eq($this->t('Property')) . "]/following::text()[normalize-space()][1][contains(normalize-space(), '#')]/following::text()[normalize-space()][1]/ancestor::a[1]/@href");
        }

        if (!empty($propertyUrl)) {
            //it-190308854
            if (stripos($propertyUrl, 'link.edgepilot.com') !== false) {
                if (preg_match("#(https://www.vrbo.com.+arrival\=[\d\-]+)#u", $propertyUrl, $m)) {
                    $propertyUrl = str_replace('%', '&', $m[1]);
                }
            }

            if (stripos($propertyUrl, 'vrbo.com') !== false || stripos($propertyUrl, 'homeaway.com') !== false
                || stripos($propertyUrl, 't.vrbo.io') !== false || stripos($propertyUrl, 't.hmwy.io') !== false
                || stripos($propertyUrl, 'vacationrentals.com') !== false
                || stripos($propertyUrl, 'fewo-direkt.de') !== false
                || stripos($propertyUrl, 'stayz.com.au') !== false
                || stripos($propertyUrl, 'www.abritel.fr') !== false
                || stripos($propertyUrl, 'www.bookabach.co.nz') !== false
                || stripos($propertyUrl, '.expedia.com/') !== false
            ) {
                $http1 = clone $this->http;
                $http1->GetURL($propertyUrl);

                if ($http1->XPath->query("//text()[normalize-space() = 'This link has expired. Please contact the sender of the email for more information.']")->length > 0) {
                    return [
                        'errorJunk' => 'Link has expired',
                    ];
                }

                $address = $http1->FindSingleNode("//input[@id='react-destination-typeahead']/@value");

                if (empty($address)) {
                    $address = $http1->FindSingleNode("//button[normalize-space() = 'View in a map' and .//*[name()='svg']]/preceding::text()[normalize-space()][1]");
                    $address = preg_replace("/^\s*(.+?)\s*\(\s*Full address shared after booking.*/", '$1', $address);
                }

                if (empty($address)) {
                    $address = $http1->FindSingleNode("//*[@data-stid = 'content-hotel-address']");
                    $address = preg_replace("/^\s*(.+?)\s*\(\s*Full address shared after booking.*/", '$1', $address);
                }
                //old collection
                //$name = $http1->FindSingleNode("//h1[contains(@class,'property-headline__headline')]");
                $keywords = [];

                foreach ((array) $this->t('house') as $phrase) {
                    $keywords[] = $phrase;
                    $keywords[] = mb_strtolower($phrase);
                    $keywords[] = mb_strtoupper($phrase);
                }

                if (count($keywords) === 0) {
                    return [];
                }
                $name = $http1->FindSingleNode("//text()[{$this->eq($keywords)} and ancestor::li]/preceding::h1");

                if (empty($name)) {
                    $name = $http1->FindSingleNode("//text()[{$this->eq($keywords)}]/following::text()[normalize-space()][1]/ancestor::h1");
                }

                if (empty($name)) {
                    $names = array_unique($http1->FindNodes("//h1"));

                    if (count($names) == 1) {
                        $name = $names[0];
                    }
                }
                $name = str_replace('}', '', $name);
                $this->logger->debug('address: ' . $address . '; hotelName: ' . $name);

                if (!empty($address) && !empty($name)) {
                    return [
                        'address' => $address,
                        'name'    => $name,
                    ];
                }
            }
        }

        return [];
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["firstDetect"], $words["lastDetect"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['firstDetect'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['lastDetect'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
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

    private function priceNorm($price)
    {
//         $this->logger->debug('$price = '.print_r( $price,true));
        if (preg_match("/^(\d[\d., ']*)[,.](\d{2})$/", trim($price), $m)) {
            $price = preg_replace("/\D/", "", $m[1]) . '.' . $m[2];
        }

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText): void
    {
        if (preg_match("#Cancellation policy: 100% refund for cancellations requested by (\d+\/\d+\/\d{4})\s*at\s*([\d\:]+\s*A?P?M) \(#u",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($m[1] . ', ' . $m[2]));
        } elseif (
            preg_match("/you can cancell? for a 100% refund until (?<date>[[:alpha:]]{3,} \d{1,2}|\d{1,2} [[:alpha:]]{3,})/i", $cancellationText, $m) // en
        ) {
            if (!preg_match('/\d{4}$/', $m['date']) && !empty($h->getCheckInDate())) {
                $deadline = EmailDateHelper::parseDateRelative($m['date'], $h->getCheckInDate(), false, '%D%, %Y%');
            } else {
                $deadline = strtotime($m['date']);
            }
            $h->booked()->deadline($deadline);
        } elseif (
            preg_match("/^\s*100% refund of amount paid if you cancel by (?<date>[[:alpha:]]{3,} \d{1,2}, \d{4})\./i", $cancellationText, $m) // en
            || preg_match("/100[%] refund of amount payable if you cancel by (?<date>\w+\s*\d+\,\s*\d{4})/i", $cancellationText, $m) // en
        ) {
            // 100% refund of amount paid if you cancel by Mar 7, 2024.
            $h->booked()->deadline(strtotime($m['date']));
        }

        if (preg_match("/Your booking no longer qualifies for a refund./", $cancellationText)) {
            $h->booked()
                ->nonRefundable();
        }
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$'],
            'CAD' => ['C$'],
            'AUD' => ['AU$'],
            'NZD' => ['NZ$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$str = '.print_r( $str,true));

        $in = [
            // 3 juil. 2021
            "/^\s*(\d{1,2})[.]?\s+([[:alpha:]]+)[.]?\s+(\d{4})\s*$/",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        // $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
