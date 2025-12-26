<?php
/**
 * Created by PhpStorm.
 * User: rshakirov.
 */

namespace AwardWallet\Engine\norwegian\Email;

use PlancakeEmailParser;

class Api extends \TAccountChecker
{
    public $mailFiles = "norwegian/it-11847771.eml";

    private $from = '/[@.]air\.norwegian\.com/i';

    private $detects = [
        'no'  => 'Sørg for at du har alt du behøver før reisen',
        'no2' => 'Gjør det enkelt. Spar tid på flyplassen. God tur!',
        'no3' => 'Takk for at du velger å reise med oss',
        'es'  => 'No te compliques. Ahorra tiempo en el aeropuerto y que tengas un buen vuelo',
    ];

    private $lang = 'no';

    private $xpath = "(//a[normalize-space(.)='Gå til booking' or normalize-space(.)='Se din reservasjon' or normalize-space(.)='Ir a reservas'])[1]/@href";

    public function ParsePlanEmail(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $lang => $detect) {
            if (false !== stripos($body, $detect)) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $class = explode('\\', __CLASS__);

        return [
            'emailType'  => end($class) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (0 === $this->http->XPath->query($this->xpath)->length) {
            return false;
        }

        $body = $parser->getHTMLBody();

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    public static function getEmailLanguages()
    {
        return ['no', 'es'];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    private function parseEmail(): array
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $res = $this->http->GetURL($this->http->FindSingleNode($this->xpath));

        if (empty($res)) {
            return [];
        }

        $pnr = '';
        $pnrLocal = '';

        if (preg_match('/pnr=(\w+)\&pnrLocal=(\w+)/', $this->http->currentUrl(), $m)) {
            $pnr = $m[1];
            $pnrLocal = $m[2];
        }
        $data = [
            'culture=nb-NO',
            'marketCode=no',
            "pnr={$pnr}",
            "pnrLocal={$pnrLocal}",
        ];
        $headers = [
            'Accept'          => '*/*',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            'Connection'      => 'keep-alive',
            'Host'            => 'www.norwegian.no',
            'Referer'         => 'https://www.norwegian.no/ipr/mynorwegian',
        ];
        $res = $this->http->GetURL("https://www.norwegian.no/resourceipr/api/mynorwegian/reservationDetails?" . implode('&', $data), $headers);

        if (empty($res)) {
            return [];
        }

        $response = json_decode($this->http->Response['body'], true);

        if (!isset($response['booking']['pnr'])) {
            return [];
        }

        $it['RecordLocator'] = $response['booking']['pnr'];

        foreach ($response['booking']['travellerList'] as $traveller) {
            $it['Passengers'][] = $traveller['title'] . ' ' . $traveller['fullName'];
        }

        if (isset($response['ticketNumbers'][1])) {
            $it['TicketNumbers'] = explode(',', $response['ticketNumbers'][1]);
        }

        $roots = $response['booking']['routeList'];

        foreach ($roots as $root) {
            $root = $root['flights'][0];
            /** @vwar \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $seg['DepName'] = $root['originName'];

            $seg['ArrName'] = $root['destinationName'];

            $seg['DepCode'] = $root['origin'];

            $seg['ArrCode'] = $root['destination'];

            $seg['DepDate'] = strtotime($root['localDepartureTime']);

            $seg['ArrDate'] = strtotime($root['localArrivalTime']);

            $seg['Duration'] = $root['durationText'];

            if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $root['flightCode'], $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }
}
