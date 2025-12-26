<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\wagonlit\Email;

use PlancakeEmailParser;

class ItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-10596719.eml, wagonlit/it-10597253.eml, wagonlit/it-15063631.eml";

    private $from = '/[.@]carlsonwagonlit[.]com/i';

    private $detects = [
        'The following Frequent Flyer Numbers have been advised',
        'DUPLICATE RECEIPT CANNOT BE PROVIDED AFTER THE ABOVE DATE',
        'PLEASE REVIEW YOUR TRAVEL ITINERARY AND',
    ];

    private $needle = 'AIRLINE PASSENGER RECEIPT';

    private $lang = 'en';

    /** @var \HttpBrowser */
    private $pdf;

    private $text = '';

    public function ParsePlanEmail(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdfs[0]) && 0 < count($pdfs)) {
            $pdf = array_shift($pdfs);
            $body = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
            $this->text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($body)) {
                return false;
            }
            $body = str_replace(chr(194) . chr(160), ' ', $body);

            foreach ($this->detects as $detect) {
                if (false !== stripos($body, $detect)) {
                    $this->pdf = clone $this->http;
                    $this->pdf->SetEmailBody($body);
                }
            }

            if (empty($this->pdf)) {
                return false;
            }
        } else {
            return null;
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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdfs[0]) && 0 < count($pdfs)) {
            $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

            if (false === stripos($body, $this->needle) && false === stripos($body, 'Invoice Information') && false === stripos($body, 'CWT/Carlson Global') && false === stripos($body, '@contactcwt.com')) {
                return false;
            }

            foreach ($this->detects as $detect) {
                if (false !== stripos($body, $detect)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    private function parseEmail(): array
    {
        $its = [];
        $locators = [];
        $traveler = [];

        $totalAirs = $this->pdf->FindNodes("//p[contains(normalize-space(.), 'Total Ticket Amount')]/following-sibling::p[normalize-space(.)][1]", null, '/([\d\.\,]+)/');

        $pax = $this->pdf->FindSingleNode("//p[contains(normalize-space(.), 'Traveler:')]/following-sibling::p[contains(normalize-space(.), 'Billing Code:')]/following-sibling::p[normalize-space(.)][1]");

        if (empty($pax)) {
            $tripNum = $this->pdf->FindSingleNode("//p[contains(normalize-space(.), 'Traveler:')]/following-sibling::p[2][contains(.,'Record Locator')]/following-sibling::p[string-length()=6][1]",
                null, false, "#([A-Z\d]{6})#");

            if (!empty($tripNum)) {
                $pax = $this->pdf->FindSingleNode("//p[contains(normalize-space(.), 'Traveler:')]/following-sibling::p[normalize-space(.)][1]");
            }
        }
        $traveler[] = $pax;

        $cnt1 = $this->pdf->XPath->query("//p[contains(normalize-space(.), 'Airline Record Locators:')]/following-sibling::p[contains(., 'Carrier')]/preceding-sibling::p")->length;
        $cnt2 = $this->pdf->XPath->query("//p[contains(normalize-space(.), 'Airline Record Locators:')]/following-sibling::p[contains(., 'Carrier')]/following-sibling::p[contains(., 'Remarks')]/preceding-sibling::p")->length;

        if ($cnt2 > 0 && $cnt1 > 0) {
            $cnt = $cnt2 - $cnt1;
            $airReferenceAndCarrier = $this->pdf->FindNodes("//p[contains(normalize-space(.), 'Airline Record Locators:')]/following-sibling::p[contains(., 'Carrier')]/following-sibling::p[position()<{$cnt}]");

            foreach ($airReferenceAndCarrier as $i => $node) {
                if (($i % 2 === 0) && isset($airReferenceAndCarrier[$i + 1])) {
                    $locators[$airReferenceAndCarrier[$i + 1]] = $node;
                }
            }
        } elseif (!empty($this->text) && ($str = strstr(strstr($this->text, 'Airline Record Locators:'), 'Remarks', true))) {
            preg_match_all('/([A-Z\d]{5,9})\s+([A-Z\s\-]+)/', $str, $m);

            foreach ($m[1] as $i => $locator) {
                if (isset($m[2][$i])) {
                    $locators[$m[2][$i]] = $locator;
                }
            }
        }

        $cnt1 = $this->pdf->XPath->query("//p[contains(normalize-space(.), 'Frequent Flyer #:')]/following-sibling::p[contains(., 'Carrier')]/preceding-sibling::p")->length;
        $cnt2 = $this->pdf->XPath->query("//p[contains(normalize-space(.), 'Frequent Flyer #:')]/following-sibling::p[contains(., 'Carrier')]/following-sibling::p[contains(., 'Invoice Information')]/preceding-sibling::p")->length;

        if ($cnt2 > 0 && $cnt1 > 0) {
            $cnt = $cnt2 - $cnt1;
            $accNumAndCarrier = $this->pdf->FindNodes("//p[contains(normalize-space(.), 'Frequent Flyer #:')]/following-sibling::p[contains(., 'Carrier')]/following-sibling::p[position()<{$cnt}]");

            foreach ($accNumAndCarrier as $i => $node) {
                if (($i % 2 === 0) && isset($accNumAndCarrier[$i + 1])) {
                    $accNum[$accNumAndCarrier[$i + 1]] = $node;
                }
            }
        }

        // AIRs

        $xpathAir = "//p[contains(normalize-space(.), 'From:')]";

        if (($roots = $this->pdf->XPath->query($xpathAir)) && $roots->length > 0) {
            $airs = [];

            foreach ($roots as $root) {
                $carrier = $this->pdf->FindSingleNode("preceding::b[1]", $root, true, '/([A-Z\s]+)/');
                //				$carrier = $this->pdf->FindSingleNode("preceding-sibling::p[string-length(normalize-space(.))>2][1]", $root, true, '/([A-Z\s]+)/');
                //				$this->logger->info($carrier);
                if (isset($locators[$carrier])) {
                    $airs[$locators[$carrier]][] = $root;
                } else {
                    $rl = $this->pdf->FindSingleNode("following::p[position()<30][contains(.,'LOCATOR')]", $root, true, '/([A-Z\d]{5,})$/');

                    if (!empty($rl)) {
                        $airs[$rl][] = $root;
                    } else {
                        $airs[CONFNO_UNKNOWN][] = $root;
                    }
                }
            }

            foreach ($airs as $rl => $roots) {
                /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
                $it = ['Kind' => 'T'];

                if (0 < count($totalAirs)) {
                    $it['TotalCharge'] = str_replace(",", "", array_shift($totalAirs));
                }

                $it['RecordLocator'] = $rl;

                if (isset($tripNum)) {
                    $it['TripNumber'] = $tripNum;
                }

                $it['Passengers'] = $traveler;

                foreach ($roots as $root) {
                    /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                    $seg = [];

                    $nodes = $this->pdf->FindNodes("preceding::p[contains(., ',') and contains(translate(., '0123456789', 'dddddddddd'), 'dddd')][1]", $root);
                    $date = '';

                    foreach ($nodes as $node) {
                        if (preg_match('/\w+\s+\w+\s+\d{1,2},\s+\d{4}/', $node)) {
                            $date = $node;
                        }
                    }

                    $seg['DepName'] = $this->pdf->FindSingleNode("(following-sibling::p[descendant::a][1])[1]", $root);

                    $depTime = $this->pdf->FindSingleNode("(following-sibling::p[descendant::a and contains(., 'Departing')][1])[1]", $root, true, '/at\s+(.+)/');

                    $seg['DepDate'] = $this->normalizeDate($date . ' ' . $depTime);

                    $seg['ArrName'] = $this->pdf->FindSingleNode("(following::p[contains(., 'To:')][1]/following-sibling::p[descendant::a][1])[1]", $root);

                    $arrTime = $this->pdf->FindSingleNode("(following::p[contains(., 'To:')][1]/following-sibling::p[descendant::a and contains(., 'Arriving')][1])[1]", $root, true, '/at\s+(.+)/');

                    $seg['ArrDate'] = $this->normalizeDate($date . ' ' . $arrTime);

                    if ($depTerm = $this->pdf->FindSingleNode("(following-sibling::p[contains(., 'Dep.Terminal')][1]/following-sibling::p[1][not(contains(., 'Information not available'))])[1]", $root)) {
                        $seg['DepartureTerminal'] = str_ireplace('terminal ', '', $depTerm);
                    }

                    if ($arrTerm = $this->pdf->FindSingleNode("(following-sibling::p[contains(., 'Arr.Terminal')][1]/following-sibling::p[1][not(contains(., 'Information not available'))])[1]", $root)) {
                        $seg['ArrivalTerminal'] = str_ireplace('terminal ', '', $arrTerm);
                    }

                    if ($stops = $this->getNode($root, 'Stops', 'Non stop')) {
                        $seg['Stops'] = $stops;
                    }

                    $seg['FlightNumber'] = $this->getNode($root, 'Flight', '', '/^\s*(\d+)\s*$/');

                    $seg['AirlineName'] = $this->pdf->FindSingleNode("./preceding::text()[normalize-space()][1]", $root);

                    if (isset($accNum[$seg['AirlineName']])) {
                        $it['AccountNumbers'][] = $accNum[$seg['AirlineName']];
                    }

                    if ($seats = $this->getNode($root, 'Seat', 'Unassigned')) {
                        $seg['Seats'][] = $seats;
                    }

                    if ($class = $this->getNode($root, 'Class:')) {
                        $seg['Cabin'] = $class;
                    }

                    if ($duration = $this->getNode($root, 'Flight Duration')) {
                        $seg['Duration'] = $duration;
                    }

                    if (!empty($seg['DepName']) && !empty($seg['ArrName']) && !empty($seg['FlightNumber'])) {
                        $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                    }

                    $it['TripSegments'][] = $seg;
                }

                if (isset($it['AccountNumbers'])) {
                    $it['AccountNumbers'] = array_values(array_unique($it['AccountNumbers']));
                }
                $its[] = $it;
            }
        } else {
            $this->logger->info("Segments didn't found by xpath: {$xpathAir} for air trip");
        }

        // HOTELs

        $xpathHotel = "//p[contains(., 'Address:')]";

        if (($hotels = $this->pdf->XPath->query($xpathHotel)) && $hotels->length > 0) {
            foreach ($hotels as $root) {
                /** @var \AwardWallet\ItineraryArrays\Hotel $it */
                $it = ['Kind' => 'R'];

                $it['GuestNames'] = $traveler;

                if (isset($tripNum)) {
                    $it['TripNumber'] = $tripNum;
                }

                $it['Address'] = implode(', ', $this->pdf->FindNodes("(preceding-sibling::p[1])[1] | (following-sibling::p[3])[1]", $root));

                $it['Fax'] = $this->getNode($root, 'Fax:');

                $it['Phone'] = $this->getNode($root, 'Phone:');

                $it['CheckInDate'] = $this->normalizeDate($this->getNode($root, 'Check in:', '', null, false));

                $it['CheckOutDate'] = $this->normalizeDate($this->getNode($root, 'Check out:', '', null, false));

                $it['RoomTypeDescription'] = $this->getNode($root, 'Special info:');

                $it['AccountNumbers'][] = $this->getNode($root, 'Frequent Guest:');

                $it['ConfirmationNumber'] = $this->getNode($root, 'Confirmation #:');

                $it['Rate'] = $this->getNode($root, 'Rate per Night:', '', null, false);

                $it['HotelName'] = $this->pdf->FindSingleNode("(preceding-sibling::p[2]/b/text()[last()])[1]", $root);

                $its[] = $it;
            }
        } else {
            $this->logger->info("Segments didn't found by xpath: {$xpathHotel} for hotel reservation");
        }

        // CARs

        $xpathCar = "//p[contains(normalize-space(.), 'Pick Up') and contains(normalize-space(.), 'Drop Off')]";

        if (($cars = $this->pdf->XPath->query($xpathCar)) && $cars->length > 0) {
            foreach ($cars as $root) {
                /** @var \AwardWallet\ItineraryArrays\CarRental $it */
                $it = ['Kind' => 'L'];
                /** @var \DOMNode $root */
                $nodes = $this->pdf->FindNodes("preceding::p[contains(., ',') and contains(translate(.,'0123456789', 'dddddddddd'), 'dddd')][1]", $root);
                $date = '';

                foreach ($nodes as $node) {
                    if (preg_match('/\w+\s+\w+\s+\d{1,2},\s+\d{4}/', $node)) {
                        $date = $node;
                    }
                }

                $re = '/(?<rcomp>.+)\s*(?:Phone\s*\-|Tel)\s*(?<phone>[\d\- ]+)\s*Pick Up\s*\-\s*(?<pickup>.+)\s*Drop Off\s*\-\s*(?<dropoff>.+)\s*Rate\s*\-\s*(?<rate>.+)\s*Confirmation\s*\-\s*(?<conf>\d+)/i';

                if (preg_match($re, $root->nodeValue, $m)) {
                    $it['RentalCompany'] = trim($m['rcomp'], " -");

                    $it['PickupDatetime'] = strtotime($date);

                    if (preg_match("#^\s*(.+)\s+AT\s+(\d{2})(\d{2})\s*$#", $m['pickup'], $v)) {
                        $it['PickupLocation'] = $v[1];
                        $it['PickupDatetime'] = strtotime($v[2] . ':' . $v[3], $it['PickupDatetime']);
                    } else {
                        $it['PickupLocation'] = $m['pickup'];
                    }

                    $it['DropoffLocation'] = $m['dropoff'];

                    $it['Number'] = $m['conf'];

                    $it['DropoffDatetime'] = MISSING_DATE;

                    $it['PickupPhone'] = $it['DropoffPhone'] = $m['phone'];

                    if (preg_match('/([A-Z]{3})\s*([\d\.]+)/', $m['rate'], $total)) {
                        $it['TotalCharge'] = $total[2];
                        $it['Currency'] = $total[1];
                    } elseif (preg_match('/(\S)\s*([\d\.]+)/', $m['rate'], $total)) {
                        $it['TotalCharge'] = $total[2];
                        $it['Currency'] = str_replace(['$'], ['USD'], $total[1]);
                    }
                }

                if (0 < count($traveler)) {
                    $it['RenterName'] = $traveler[0];
                }

                if (isset($tripNum)) {
                    $it['TripNumber'] = $tripNum;
                }

                $its[] = $it;
            }
        } else {
            $this->logger->info("Segments didn't found by xpath: {$xpathCar} for car reservation");
        }

        $xpathCar2 = "//p[normalize-space(.)='Pick Up:']";

        if (($cars = $this->pdf->XPath->query($xpathCar2)) && $cars->length > 0) {
            foreach ($cars as $root) {
                /** @var \AwardWallet\ItineraryArrays\CarRental $it */
                $it = ['Kind' => 'L'];

                if (0 < count($traveler)) {
                    $it['RenterName'] = $traveler[0];
                }

                if (isset($tripNum)) {
                    $it['TripNumber'] = $tripNum;
                }

                $it['AccountNumbers'][] = $this->getNode($root, 'Membership Number:');

                $it['Number'] = $this->getNode($root, 'Confirmation #:', '', null, true);

                $it['CarType'] = $this->getNode($root, 'Category:');

                $dates = $this->getNode($root, 'Reserved:');
                $pickupDate = '';
                $dropoffDate = '';

                if (preg_match('/(.+)\s*through\s*(.+)/', $dates, $m)) {
                    $pickupDate = $this->normalizeDate($m[1]);
                    $dropoffDate = $this->normalizeDate($m[2]);
                }

                $it['PickupDatetime'] = strtotime($this->getNode($root, 'Pick Up Time:', '', null, false), $pickupDate);

                $it['DropoffDatetime'] = strtotime($this->getNode($root, 'Drop Off Time', '', '/(\d{1,2}:\d{2}\s*[amp]{0,2})/', false), $dropoffDate);

                $it['PickupLocation'] = $it['DropoffLocation'] = $this->pdf->FindSingleNode('preceding-sibling::p[1]', $root);

                $its[] = $it;
            }
        } else {
            $this->logger->info("Segments didn't found by xpath: {$xpathCar2} for car reservation type 2");
        }

        return $its;
    }

    private function getNode(\DOMNode $root, string $str, string $notContains = '', $re = null, $preceding = true)
    {
        $os = '';

        if ($preceding) {
            $os = 'preceding';
        } else {
            $os = 'following';
        }

        if (empty($notContains)) {
            return $this->pdf->FindSingleNode("(following::p[contains(normalize-space(.), '{$str}')]/{$os}-sibling::p[1])[1]", $root, true, $re);
        } else {
            return $this->pdf->FindSingleNode("(following::p[contains(normalize-space(.), '{$str}')]/{$os}-sibling::p[1][not(contains(normalize-space(.), '{$notContains}'))])[1]", $root, true, $re);
        }
    }

    private function normalizeDate($str)
    {
        $res = '';
        $regs = [
            '/(\w+)\s+(\d{1,2}),\s+(\d{2,4})\s+(\d+:\d+\s*[ap]m)\s*(?:(\w+)\s+(\d{1,2}))?/i', // Friday  July 28, 2017 Arriving at  9:20 AM(?: (Jul) (17))?
            '/\w+\s+(\d{1,2})\/(\d{1,2})\/(\d{2,4})/i', // Friday 07/21/2017
            '/(\d{1,2})\/(\d{1,2})\/(\d{2,4})/',
        ];

        foreach ($regs as $reg) {
            if (preg_match($reg, $str, $m)) {
                if (preg_match('/^[a-z]+$/i', $m[1])) {
                    $res = strtotime($m[2] . ' ' . $m[1] . ' ' . $m[3]);
                } else {
                    $res = strtotime($m[2] . '.' . $m[1] . '.' . $m[3]);
                }

                if (!empty($m[5]) && !empty($m[5])) {
                    $res = strtotime($m[6] . ' ' . $m[5], $res);
                }

                if (!empty($m[4])) {
                    $res = strtotime($m[4], $res);
                }
            }
        }

        return $res;
    }
}
