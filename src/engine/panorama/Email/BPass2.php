<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\panorama\Email;

class BPass2 extends \TAccountChecker
{
    public $mailFiles = "panorama/it-7156455.eml";

    private $detects = [
        'Спасибо Вам за то, что выбираете авиакомпанию «Международные Авиалинии Украины» и нашу услугу онлайн-регистрации',
    ];

    /** @var \HttpBrowser */
    private $pdf;

    private $filename = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) === 0) {
            return [];
        }
        $body = \PDF::convertToHtml($parser->getAttachmentBody(array_shift($pdfs)), \PDF::MODE_COMPLEX);
        $pdfDetects = [
            'Ukraine International Airlines',
        ];
        $nbsp = chr(194) . chr(160);
        $body = str_replace([$nbsp, '&#160;'], [' ', ' '], $body);

        foreach ($pdfDetects as $detect) {
            if (stripos($body, $detect) !== false) {
                $this->pdf = clone $this->http;
                $this->pdf->SetEmailBody($body);
            } else {
                return [];
            }
        }

        if (preg_match('/name\=\"(.+\.pdf)\"/', $parser->getAttachmentHeader(0, 'Content-Type'), $m)) {
            $this->filename = $m[1];
        }

        return [
            'emailType'  => 'BoardingPass2En',
            'parsedData' => [
                'Itineraries'  => $this->parseEmail(),
                'BoardingPass' => $this->parseEmail() ? $this->parseBP() : false,
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'flyuia.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'flyuia.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody() ? $parser->getHTMLBody() : $parser->getPlainBody();

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                return true;
            }
        }
//        $pdfs = $parser->searchAttachmentByName('.*pdf');
//        if( count($pdfs) > 0 ){
//            $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));
//            if( stripos($body, 'Operated by Ukraine International Airlines') !== false )
//                return true;
//        }
        return false;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];
        $it['RecordLocator'] = CONFNO_UNKNOWN;
        $it['Passengers'] = $this->getPassengers();
        $it['TicketNumbers'] = $this->getPassengers();

        $xpath = "//p[normalize-space(.) = 'Flight no.']";
        $roots = $this->pdf->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return false;
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $seg['DepName'] = $this->getNode($root);
            $seg['ArrName'] = $this->getNode($root, 2);
            $flight = $this->getNode($root, 3, '/([A-Z\d]{2}\s*\d+)/');

            if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $date = $this->getNode($root, 4, '/(\d{1,2}\s*\D+\s*\d{2,4})/');
            $time = $this->getNode($root, 5, '/(\d{1,2}:\d{2})/');
            $seg['DepDate'] = $this->normalizeDate($date . ', ' . $time);
            $seg['BookingClass'] = $this->getNode($root, 6, '/([A-Z])/');

            if (!empty($seg['FlightNumber']) && !empty($seg['DepDate']) && !empty($seg['DepName']) && !empty($seg['ArrName'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrDate'] = MISSING_DATE;
            }
            $it['TripSegments'][] = $seg;
            $it['TripSegments'] = array_map("unserialize", array_unique(array_map("serialize", $it['TripSegments'])));
        }

        return [$it];
    }

    private function getNode(\DOMNode $root, $p = 1, $re = null)
    {
        return $this->pdf->FindSingleNode("following-sibling::p[contains(., 'Class')]/following-sibling::p[" . $p . "]", $root, true, $re);
    }

    private function getPassengers()
    {
        return array_values(array_unique($this->pdf->FindNodes("//p[contains(normalize-space(.), 'Name of passenger')]/preceding-sibling::p[contains(., 'MRS') or contains(., 'MR') or contains(., 'MISS')]")));
    }

    private function getTicketNumbers()
    {
        return array_values(array_unique($this->pdf->FindNodes("//p[contains(normalize-space(.), 'Ticket No.')]/following-sibling::p[1]")));
    }

    private function parseBP()
    {
        $it = [];

        if (!empty($this->filename)) {
            $it['AttachmentFileName'] = $this->filename;
        } else {
            return null;
        }
        $it['RecordLocator'] = CONFNO_UNKNOWN;
        $it['Passengers'] = $this->getPassengers();
        $it['TicketNumbers'] = $this->getTicketNumbers();
        $xpath = "//p[normalize-space(.) = 'Flight no.']";
        $roots = $this->pdf->XPath->query($xpath);

        if ($roots->length > 0) {
            $it['DepName'] = $this->getNode($roots->item(0));
            $it['ArrName'] = $this->getNode($roots->item(0), 2);
            $flight = $this->getNode($roots->item(0), 3, '/([A-Z\d]{2}\s*\d+)/');

            if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $flight, $m)) {
                $it['AirlineName'] = $m[1];
                $it['FlightNumber'] = $m[2];
            }
            $date = $this->getNode($roots->item(0), 4, '/(\d{1,2}\s*\D+\s*\d{2,4})/');
            $time = $this->getNode($roots->item(0), 5, '/(\d{1,2}:\d{2})/');
            $it['DepDate'] = $this->normalizeDate($date . ', ' . $time);
            $it['BookingClass'] = $this->getNode($roots->item(0), 6, '/([A-Z])/');

            if (!empty($seg['FlightNumber']) && !empty($seg['DepDate']) && !empty($seg['DepName']) && !empty($seg['ArrName'])) {
                $it['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $it['ArrDate'] = MISSING_DATE;
            }
        }

        return [$it];
    }

    private function normalizeDate($str)
    {
        $in = [
            '/(\d{1,2})\s*(\D+)\s*(\d{2,4}), (\d{1,2}:\d{2})/',
        ];
        $out = [
            '$1 $2 $3, $4',
        ];

        return strtotime(preg_replace($in, $out, $str));
    }
}
