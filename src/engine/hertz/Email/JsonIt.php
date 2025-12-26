<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;

class JsonIt extends \TAccountChecker
{
    public $mailFiles = "hertz/it-10.eml, hertz/it-1617628.eml, hertz/it-1700146.eml, hertz/it-1879579.eml, hertz/it-1879580.eml";

    public static $detectProvider = [
        // don't add provider if there is more information in html than json (like in hotels, avis, aplus)
        'hertz' => [
            'from' => ['@hertz.com'],
            'subject' => ['My Hertz Reservation'],
            'body' => ['The Hertz Corporation', 'www.hertz.com/']
        ],
        'airbnb' => [
            'from' => ['@airbnb.com'],
            'subject' => ['is canceled'],
            'body' => ['Airbnb, Inc', 'www.airbnb.com']
        ],
        'booking' => [
            'from' => ['@cars.booking.com'],
            'subject' => ['Cancellation of your Booking.com Itinerary for'],
            'body' => ['your Booking.com Itinerary', '//cars.booking.com']
        ],
        'ebrite' => [
//            'from' => [''],
            'subject' => ['Order CANCELLED for '],
            'body' => ['The Eventbrite Team', 'www.eventbrite.']
        ],
        'rentalcars' => [
            'from' => ['@reservations.rentalcars.com'],
            'subject' => ['Cancellation of your Rentalcars.com Itinerary for'],
            'body' => ['as Rentalcars.com', 'reservations.rentalcars.com']
        ],
        'tock' => [
            'from' => ['@exploretock.com'],
            'subject' => ['Cancellation confirmation for'],
            'body' => ['TOCK LLC', 'info@tockhq.com']
        ],
    ];
    public $providerCode;

    public function detectEmailFromProvider($from)
    {
        return preg_match("/@hertz\.com/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectProvider as $code => $detects) {
            if (isset($headers['from'], $detects['from']) && $this->isContains($headers['from'], $detects['from']) == true
                    || isset($headers['subject'], $detects['subject']) && $this->isContains($headers['subject'], $detects['subject']) == true) {
                $this->providerCode = $code;
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//script[@type='application/ld+json']")->length === 0) {
            return false;
        }
        foreach (self::$detectProvider as $code => $detects) {
            if (isset($detects['body'])
                && $this->http->XPath->query("//*[".$this->contains($detects['body'])."]")->length > 0) {
                $this->providerCode = $code;
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        $email->setProviderCode($this->providerCode);


        $json = $this->http->FindSingleNode("//script[@type='application/ld+json']");
//        $this->logger->debug(var_export($json, true));
        $json = preg_replace("/^[^\{]+/", "", $json);
        $json = preg_replace("/[^\}]+$/", "", $json);
        $json = preg_replace("/(\"\w+\":\s*)(<a[^\a]+?>)(.+?)(\s*<\/a>)?(\s*,)/", "$1$3$5", $json);
        $json = preg_replace('/(\"name\"\:\s+\D+\")(\s+)("telephone\")/', "$1, $3", $json);

        if (!$json || !($data = @json_decode($json, true))) {
            return false;
        }

        if ($this->providerCode !== 'hertz' && !preg_match("#http://schema.org/(Cancelled|Canceled)#i", $data["reservationStatus"] ?? '', $m)) {
            $this->logger->debug('there is more information in html than json');
            return false;
        }

//        $this->logger->debug(var_export($data, true));


        if (isset($data["@type"]) && $data["@type"] == 'RentalCarReservation') {
            $this->rental($email, $data);
        }
        if (isset($data["@type"]) && $data["@type"] == 'FoodEstablishmentReservation') {
            $this->eventRestaurant($email, $data);
        }
        if (isset($data["@type"]) && $data["@type"] == 'EventReservation') {
            $this->eventEvent($email, $data);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 3; // rentals, event restaurant, event event
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    private function rental(Email $email, array $data)
    {
        $r = $email->add()->rental();

        // General
        if (isset($data["reservationNumber"])) {
            $r->general()
                ->confirmation($data["reservationNumber"]);
        }

        if (isset($data["underName"]["name"])) {
            $r->general()
                ->traveller($data["underName"]["name"]);
        }

        if (isset($data["reservationStatus"]) && preg_match("#http://schema.org/(\w+)#", $data["reservationStatus"], $m)) {
            $r->general()
                ->status($m[1]);

            if (in_array($r->getStatus(), ['Cancelled', 'Canceled'])) {
                $r->general()
                    ->cancelled();
            }
        }

        // Car
        if (isset($data["reservationFor"]["name"])) {
            $r->car()
                ->model($data["reservationFor"]["name"]);
        }

        if (isset($data["reservationFor"]["model"])) {
            $r->car()
                ->type($data["reservationFor"]["model"]);
        }

        $carImage = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Vehicle')]/following::img[1]/@src");

        if (!empty($carImage)) {
            $r->car()
                ->image($carImage);
        }

        // Pick Up, Drop Off
        foreach (["pickup", "dropoff"] as $field) {
            if (isset($data[strtolower($field) . "Location"]["address"])) {
                $address = $data[strtolower($field) . "Location"]["address"];
                $location  = implode(', ', array_filter(
                    [
                        $data[strtolower($field) . "Location"]["name"] ?? '',
                        $address['streetAddress'] ?? '',
                        $address['addressLocality'] ?? '',
                        $address['addressRegion'] ?? '',
                        $address['postalCode'] ?? '',
                        $address['addressCountry'] ?? '',
                    ]
                ));

                switch ($field) {
                    case "pickup":
                        $r->pickup()->location($location);

                        break;

                    case "dropoff":
                        $r->dropoff()->location($location);

                        break;
                }
            }

            if (isset($data[strtolower($field) . "Time"])) {
                switch ($field) {
                    case "pickup":
                        $r->pickup()->date(strtotime($this->re("/^([\d\-]+T\d+\:\d+)/", $data[strtolower($field) . "Time"])));
                        break;

                    case "dropoff":
                        $r->dropoff()->date(strtotime($this->re("/^([\d\-]+T\d+\:\d+)/", $data[strtolower($field) . "Time"])));
                        break;
                }
            }

            if (isset($data[strtolower($field) . "Location"]["telephone"])) {
                switch ($field) {
                    case "pickup":
                        $r->pickup()->phone($data[strtolower($field) . "Location"]["telephone"]);
                        break;
                    case "dropoff":
                        $r->dropoff()->phone($data[strtolower($field) . "Location"]["telephone"]);
                        break;
                }
            }
        }

        $hours = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Pickup and Return Location')]/ancestor::div[1]/following-sibling::table[1]/descendant::text()[starts-with(normalize-space(), 'Hours of Operation')]/following::text()[normalize-space()!=''][1]");

        if (!empty($hours)) {
            $r->pickup()->openingHours($hours);
            $r->dropoff()->openingHours($hours);
        }
        $fax = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Pickup and Return Location')]/ancestor::div[1]/following-sibling::table[1]/descendant::text()[starts-with(normalize-space(), 'Fax Number')]/following::text()[normalize-space()!=''][1]");

        if (!empty($fax)) {
            $r->pickup()->fax($fax);
            $r->dropoff()->fax($fax);
        }

        // Price
        if (isset($data["price"])) {
            $r->price()
                ->total($data["price"]);
        }

        if (isset($data["priceCurrency"])) {
            $r->price()
                ->currency($data["priceCurrency"]);
        }

        return $email;
    }

    private function eventRestaurant(Email $email, array $data)
    {
        $ev = $email->add()->event();
        $ev->setEventType(EVENT_RESTAURANT);

        // General
        if (isset($data["reservationNumber"])) {
            $ev->general()
                ->confirmation($data["reservationNumber"]);
        }
        if (isset($data["reservationStatus"]) && preg_match("#http://schema.org/(\w+)$#", $data["reservationStatus"], $m)) {
            $ev->general()
                ->status($m[1]);

            if (in_array($ev->getStatus(), ['Cancelled', 'Canceled'])) {
                $ev->general()
                    ->cancelled();
            }
        }
        if (isset($data["underName"]["name"])) {
            $ev->general()
                ->traveller($data["underName"]["name"]);
        }

        // Place
        if (isset($data["reservationFor"])) {
            $ev->place()
                ->name($data["reservationFor"]['name'] ?? '')
                ->phone(str_replace('N/A', '', $data["reservationFor"]['telephone'] ?? ''), true, true)
            ;
            if (isset($data['reservationFor']['address'])) {
                $address = $data['reservationFor']['address'];
                $ev->place()
                    ->address(implode(', ', array_filter(
                        [
                            $address['streetAddress'] ?? '',
                            $address['addressLocality'] ?? '',
                            $address['addressRegion'] ?? '',
                            $address['postalCode'] ?? '',
                            $address['addressCountry'] ?? '',
                        ]
                    )));
            }
        }

        // Booked
        if (preg_match("/^(?<date>[\d\-]+)T(?<time>\d{2}:\d{2})\b[\d:]*[\-\+]\d{2}:\d{2}[\d:]*$/", $data['startTime'] ?? '', $m)) {
            $ev->booked()
                ->start2($m['date'] . '' . $m['time'])
                ->noEnd()
            ;
        }

        if (isset($data["partySize"])) {
            $ev->booked()
                ->guests($data["partySize"]);
        }

        return $email;
    }

    private function eventEvent(Email $email, array $data)
    {
        $ev = $email->add()->event();

        $ev->setEventType(EVENT_EVENT);

        // General
        if (isset($data["reservationNumber"])) {
            $ev->general()
                ->confirmation($data["reservationNumber"]);
        }
        if (isset($data["reservationStatus"]) && preg_match("#http://schema.org/(\w+)$#", $data["reservationStatus"], $m)) {
            $ev->general()
                ->status($m[1]);

            if (in_array($ev->getStatus(), ['Cancelled', 'Canceled'])) {
                $ev->general()
                    ->cancelled();
            }
        }
        if (isset($data["underName"]["name"])) {
            $ev->general()
                ->traveller($data["underName"]["name"]);
        }

        // Place
        if (isset($data["reservationFor"])) {
            $ev->place()
                ->name($data["reservationFor"]['name'] ?? '')
                ->phone(str_replace('N/A', '', $data["reservationFor"]['telephone'] ?? ''), true, true)
            ;

            if (isset($data["reservationFor"]['startDate'])) {
                $ev->booked()
                    ->start2($data["reservationFor"]['startDate'])
                    ->noEnd()
                ;
            }
            if (isset($data["reservationFor"]['location'], $data["reservationFor"]['location']['address'])) {

                $address = $data["reservationFor"]['location']['address'];
                $ev->place()
                    ->address(implode(', ', array_filter(
                        [
                            $data['reservationFor']['location']['name'] ?? '',
                            $address['streetAddress'] ?? '',
                            $address['addressLocality'] ?? '',
                            $address['addressRegion'] ?? '',
                            $address['postalCode'] ?? '',
                            $address['addressCountry'] ?? '',
                        ]
                    )));
            }
        }

        // Booked

        if (empty($ev->getStartDate()) && !empty($ev->getName())) {
            // airbnb
            $dates = $this->http->FindSingleNode("//text()[".$this->eq($ev->getName())."]/following::text()[normalize-space()][1]");
            if (preg_match("/^(?<date>.+?) (?<start>\d{1,2}:\d{2}(?: +[ap]m)?)\s*-\s*(?<end>\d{1,2}:\d{2}(?: +[ap]m)?)$/i", $dates, $m)) {
                $date = EmailDateHelper::parseDateRelative($m['date'], strtotime(preg_replace('/^([\d\-]+)T(\d{2}:\d{2}:\d{2})/', '$1 $2', $data['modifiedTime'] ?? '')));
                if (!empty($date)) {
                    $ev->booked()
                        ->start(strtotime($m['start'], $date))
                        ->end(strtotime($m['end'], $date))
                    ;
                }
            }

        }

        return $email;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function isContains($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }
        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }
        return false;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }
}
