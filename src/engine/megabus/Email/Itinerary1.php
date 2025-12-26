<?php

namespace AwardWallet\Engine\megabus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "megabus/it-1.eml, megabus/it-1668772.eml";
    private $_emails = [
        'support@megabus.com',
    ];
    private $_subjects = [
        'From megabus.com: Your reservations have been made',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]megabus\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $from = $this->_checkInHeader($headers, 'from', $this->_emails);
        $subject = $this->_checkInHeader($headers, 'subject', $this->_subjects);

        return $from && $subject;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // If forwarded message
        $body = $parser->getPlainBody();
        $from = $this->_checkInBody($body, 'From:', $this->_emails);
        $subject = $this->_checkInBody($body, 'Subject:', $this->_subjects);

        return $from || $subject;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = preg_replace("/^\>?\s+/im", '', $parser->getBody());
        $text = text($parser->getBody());

        $it = [];
        $it['Kind'] = 'T';
        $it['TripCategory'] = TRIP_CATEGORY_BUS;

        if (preg_match('#Your\s+reservation\s+summary\s+for\s+order\s+([\w\-]+)#', $text, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        $totalPrice = $this->http->FindSingleNode("//*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][normalize-space()='Total Charge:'] ]/node()[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // GBP70.50
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $it['TotalCharge'] = PriceHelper::parse($matches['amount'], $currencyCode);
            $it['Currency'] = $matches['currency'];

            $baseFareAmounts = [];
            $baseFareValues = array_filter($this->http->FindNodes("//*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][normalize-space()='Price paid:'] ]/node()[normalize-space()][2]", null, '/^.*\d.*$/'));

            foreach ($baseFareValues as $bfVal) {
                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $bfVal, $m)) {
                    $baseFareAmounts[] = PriceHelper::parse($m['amount'], $currencyCode);
                }
            }

            if (count($baseFareAmounts) > 0) {
                $it['BaseFare'] = array_sum($baseFareAmounts);
            }

            $tax = $this->http->FindSingleNode("//*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][normalize-space()='Fee Total:'] ]/node()[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $tax, $m)) {
                $it['Tax'] = PriceHelper::parse($m['amount'], $currencyCode);
            }
        } elseif (preg_match('/Total\s+Charge:\s+(.*)/', $body, $m)) {
            $it['TotalCharge'] = cost($m[1]);
            $it['Currency'] = currency($m[1]);

            if (preg_match_all('/^Price paid:\s+(?:' . preg_quote($it['Currency'], '/') . ')?\s+(\d[,.‘\'\d ]*?)[ ]*$/im', $body, $costMatches)) {
                $it['BaseFare'] = array_sum($costMatches[1]);
            }

            if (preg_match('/^Fee Total:\s+(?:' . preg_quote($it['Currency'], '/') . ')?\s+(\d[,.‘\'\d ]*?)[ ]*$/im', $body, $m)) {
                $it['Tax'] = $m[1];
            }
        }

        // ---------------- Trip Segments -------------------

        preg_match("/^Trip information:$/im", $body, $start, PREG_OFFSET_CAPTURE);
        preg_match("/^Fees$/im", $body, $end, PREG_OFFSET_CAPTURE);

        if (!isset($start[0]) || !isset($start[0][1])) {
            return null;
        }
        $tripData = substr($body, $start[0][1], $end[0][1] - $start[0][1]);
        $tripData = preg_match($pattern = "/(?:[ ]*\*[ ]*)+Depart\s+/", $tripData) > 0
            ? preg_split($pattern, $tripData, -1, PREG_SPLIT_NO_EMPTY)
            : preg_split("/^[* ]*Depart\s+/m", $tripData, -1, PREG_SPLIT_NO_EMPTY)
        ;
        array_shift($tripData);

        $patterns = [
            'date' => '\b[[:alpha:]]+\s+\d{1,2}[,\s]+\d{4}\b', // May 18, 2014
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 9:30 PM
        ];

        foreach ($tripData as $data) {
            $segment = [];

            if (preg_match("/^(?<name>.{3,}?)\s+on\s+(?<date>{$patterns['date']})\s+(?<time>{$patterns['time']})\s+Arrive/iu", preg_replace('/\s+/', ' ', $data), $m)) {
                $segment['DepCode'] = TRIP_CODE_UNKNOWN;
                $segment['DepName'] = $m['name'];
                $segment['DepDate'] = strtotime($m['time'], strtotime($m['date']));
            }

            if (preg_match("/\sArrive\s+(?<name>.{3,}?)\s+at\s+(?<date>{$patterns['date']})\s+(?<time>{$patterns['time']})/iu", preg_replace('/\s+/', ' ', $data), $m)) {
                $segment['ArrCode'] = TRIP_CODE_UNKNOWN;
                $segment['ArrName'] = $m['name'];
                $segment['ArrDate'] = strtotime($m['time'], strtotime($m['date']));
            }

            $it['TripSegments'][] = $segment;
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$it],
            ],
        ];
    }

    private function _checkInHeader(&$headers, $field, $source): bool
    {
        if (isset($headers[$field])) {
            foreach ($source as $temp) {
                if (stripos($headers[$field], $temp) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function _checkInBody(&$body, $field, $source): bool
    {
        $end = 0;

        while ($start = strpos($body, $field, $end)) {
            $end = strpos($body, "\n", $start);

            if ($end === false) {
                break;
            }
            $header = substr($body, $start, $end - $start);

            foreach ($source as $temp) {
                if (stripos($header, $temp) !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}
