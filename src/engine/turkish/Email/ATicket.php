<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\turkish\Email;

class ATicket extends \TAccountChecker
{
    public $mailFiles = "turkish/it-10039114.eml, turkish/it-7457234.eml, turkish/it-7501677.eml, turkish/it-8149772.eml, turkish/it-8862616.eml";

    private static $detectBody = [
        'en' => 'You can select your seat in advance without waiting in the line',
        'tr' => 'Koltuk seçimini sıra beklemeden yapın',
    ];

    private $dict = [
        'en' => [
            'Your e-Ticket Number'=> ['Your e-Ticket Number', 'Your E-Ticket Number'],
        ],
        'tr' => [
            'Your Reservation Code' => 'Rezervasyon kodunuz',
            'Your Flight Date'      => 'Uçuş Tarihiniz',
            'Flight Number'         => 'Uçuş Numarası',
            'Origin'                => 'Kalkış İstasyonu',
            'Your e-Ticket Number'  => 'E-Bilet Numaranız',
        ],
    ];

    private $lang = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->detectBody($parser);
        $name = explode('\\', __CLASS__);

        return [
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
            'emailType' => end($name) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], '@thy.com') !== false
                || preg_match('/Turkish\s+Airlines/i', $headers['subject']);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@thy.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$detectBody);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$detectBody);
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->getItNode('Your Reservation Code');
        $it['TicketNumbers'][] = $this->getItNode('Your e-Ticket Number');

        $it['ReservationDate'] = strtotime($this->getItNode('Your Flight Date'));

        $xpath = "//tr[contains(., '" . $this->t('Flight Number') . "') and contains(., '" . $this->t('Origin') . "') and not(descendant::tr)]/following-sibling::tr";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return false;
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $flight = $this->getNode($root);

            if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $date = $this->getNode($root, 2);
            $seg['DepDate'] = strtotime($date . ', ' . $this->getNode($root, 3));
            $seg['ArrDate'] = MISSING_DATE;
            $seg['DepCode'] = $this->getNode($root, 4, '/^\s*([A-Z]{3})\s*$/');

            if (empty($seg['DepCode'])) {
                $seg['DepName'] = $this->getNode($root, 4);
            }
            $seg['ArrCode'] = $this->getNode($root, 5, '/^\s*([A-Z]{3})\s*$/');

            if (empty($seg['ArrCode'])) {
                $seg['ArrName'] = $this->getNode($root, 5);
            }

            if (!empty($seg['FlightNumber']) && empty($seg['DepCode']) && empty($seg['ArrCode']) && !empty($seg['DepDate']) && !empty($seg['ArrDate'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getItNode($str, $re = null)
    {
        $str = (array) $this->t($str);

        if (count($str) == 0) {
            return null;
        }
        $rule = implode(" or ", array_map(function ($s) { return "contains(., \"{$s}\")"; }, $str));

        return $this->http->FindSingleNode("//td[(" . $rule . ") and not(descendant::td)]/following-sibling::td[1]", null, true, $re);
    }

    private function t($str)
    {
        if (!isset($this->dict[$this->lang]) || !isset($this->dict[$this->lang][$str])) {
            return $str;
        }

        return $this->dict[$this->lang][$str];
    }

    private function getNode(\DOMNode $root, $td = 1, $re = null)
    {
        return $this->http->FindSingleNode('descendant::td[' . $td . ']', $root, true, $re);
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@alt, 'Turkish Airlines')]")->length === 0) {
            return false;
        }
        $body = $parser->getHTMLBody();

        foreach (self::$detectBody as $lang => $detect) {
            if (is_string($detect) && stripos($body, $detect) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }
}
