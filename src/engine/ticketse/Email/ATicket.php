<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\ticketse\Email;

use AwardWallet\Engine\MonthTranslate;

class ATicket extends \TAccountChecker
{
    public $mailFiles = "ticketse/it-7405351.eml";

    private static $bodyDetects = [
        'sv' => 'Tack för din beställning hos Ticket',
    ];

    private $lang = '';

    private $provider = 'ticket.se';

    private $year = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->year = date('Y', strtotime($parser->getDate()));
        $this->detectBody($parser);

        return [
            'emailType'  => 'ATicket' . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $this->parseEmail()],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'ticket.se') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'ticket.se') !== false
        && (
            stripos($headers['subject'], 'Betalningsbekräftelse Order') !== false
            || stripos($headers['subject'], 'Information om Order') !== false
            || stripos($headers['subject'], 'Tack för din beställning av order') !== false
        );
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$bodyDetects);
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->http->FindSingleNode("//tr[contains(., 'Bokningsnummer')]/following-sibling::tr[normalize-space(.)][1]", null, true, '/\b([A-Z\d]{5,8})\b/');

        $total = $this->http->FindSingleNode("(//td[contains(., 'Totalt') and not(.//td)])[last()]");

        if (preg_match('/([\d\s]+)\s+([A-Z]{3})/', $total, $m)) {
            $it['TotalCharge'] = $m[1];
            $it['Currency'] = $m[2];
        }

        $it['Passengers'] = $this->http->FindNodes("//td[descendant::img[contains(@src, 'ticket/mail-bullet')] and not(.//td)]/following-sibling::td[1]");

        $xpath = "//text()[contains(normalize-space(.), 'FLIGHT')]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return false;
        }
        $days = 0;
        $lastDate = '';

        foreach ($roots as $i => $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $date = $this->getDate($this->http->FindSingleNode("preceding-sibling::text()[normalize-space(.)][not(contains(., 'FLIGHT'))][1]", $root));

            $re = '/flight\s*:\s*([A-Z\d]{2})\s*(\d+)\s+(.+)\s*-\s*(.+)\s+(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/i';

            if (preg_match($re, $root->nodeValue, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $seg['DepName'] = $m[3];
                $seg['ArrName'] = $m[4];
                $depDate = strtotime($date . ', ' . $m[5]);
                $arrDate = strtotime($date . ', ' . $m[6]);
            }

            if (!empty($depDate) && !empty($arrDate)) {
                $dDate = new \DateTime(date('d M y', $depDate));
                $aDate = new \DateTime(date('d M y', $arrDate));

                if ($arrDate < $depDate) {
                    $aDate->modify('+1 day');
                }
                $diff = $dDate->diff($aDate);

                // was found new date in letter
                if (strtotime($date) !== strtotime($lastDate)) {
                    $days = 0;
                }

                if (!empty($days)) {
                    $depDate = strtotime("+{$days} days", $depDate);
                }
                $days += $diff->days;

                if (!empty($days) && $arrDate < $depDate) {
                    $arrDate = strtotime("+{$days} days", $arrDate);
                }

                if (isset($it['TripSegments'][$i - 1]['ArrDate']) && ($depDate < $it['TripSegments'][$i - 1]['ArrDate'])) {
                    $days++;
                    $depDate = strtotime("+1 days", $depDate);
                    $arrDate = strtotime("+1 days", $arrDate);
                }

                $seg['DepDate'] = $depDate;
                $seg['ArrDate'] = $arrDate;
            }
            $lastDate = $date;

            if (!empty($seg['FlightNumber']) && !empty($seg['DepDate']) && !empty($seg['ArrDate'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getDate($str)
    {
        $re = [
            '/\w+\s+(\d{1,2})\s*(\w+)/', // UTRESA 4 JULI
        ];

        foreach ($re as $r) {
            if (preg_match($r, $str, $m)) {
                return $m[1] . ' ' . MonthTranslate::translate($m[2], $this->lang) . ' ' . $this->year;
            }
        }

        return $str;
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach (self::$bodyDetects as $lang => $bodyDetect) {
            if (is_string($bodyDetect) && stripos($body, $bodyDetect) !== false && stripos($body, $this->provider) !== false) {
                $this->lang = $lang;

                return true;
            } elseif (is_array($bodyDetect)) {
                foreach ($bodyDetect as $detect) {
                    if (stripos($body, $detect) !== false && stripos($body, $this->provider) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }
}
