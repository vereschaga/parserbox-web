<?php

namespace AwardWallet\Engine\travelocity\Email;

class Itinerary1 extends \TAccountChecker
{
    public const DATE_FORMAT = 'D, M d, Y';
    public const CAR_DATE_FORMAT = 'h:iA D, M d, Y';
    public const CAR_MIX_DATE_FORMAT = 'D, M d, Y, h:i A';
    public $mailFiles = "travelocity/it-2.eml, travelocity/it-3.eml, travelocity/it-4.eml, travelocity/it-5.eml, travelocity/it-6.eml, travelocity/it-7.eml, travelocity/it.eml";

    public $xInstance = null;
    public $lastRe = null;

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && $headers["from"] === "travelocity@travelocity.com"
                || stripos($headers['subject'], 'Travelocity Confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (stripos($parser->getPlainBody(), 'From: <info@travelocitycustomercare.com') !== false) {
            return true;
        }

        if (preg_match("#From:\s*\"*Travelocity#", $parser->getPlainBody())) {
            return true;
        }

        return stripos($this->http->Response['body'], 'Your Travelocity Trip ID') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        $type = $this->getType();

        switch ($type) {
            case 'hotel': $this->parseHotel($itineraries);

break;

            case 'car': $this->parseCar($itineraries);

break;

            case 'air_plain': $this->parseAirPlain($itineraries);

break;

            case 'air': $this->parseAir($itineraries);

break;

            case 'mix': $this->parseMix($itineraries);

break; //mix: when mail has hotel and car reservations
        }

        if ($type !== 'mix') {
            $itineraries = [$itineraries];
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 5;
    }

    public function getType()
    {
        if ($this->http->FindSingleNode('//strong[text()="Hotel"]', null, true, null, 0)) {
            return 'hotel';
        } elseif ($this->http->FindSingleNode('(//td[@class="what"])[1]') || $this->http->FindPreg("#Pick up Location#")) {
            return 'car';
        } elseif ($this->http->FindPreg('/Depart:/') && $this->http->FindPreg('/airport checkin/')) {
            return 'air_plain';
        } elseif ($this->http->FindPreg('/Depart:/') || $this->http->FindPreg('#<h\d+#i')) {
            return 'air';
        }

        return 'mix';
    }

    public function parseHotel(&$itineraries)
    {
        $itineraries['Kind'] = 'R';
        $itineraries['ConfirmationNumber'] = $this->http->FindPreg('/Confirmation number:<[^>]*>([^<]*)/');
        $itineraries['HotelName'] = $this->http->FindSingleNode('//a[contains(@href, "http://travel.travelocity.com/hotel/HotelDetail.do")]');
        $itineraries['CheckInDate'] = $this->stringDateToUnixtime($this->http->FindPreg('/Check in:\s*<[^>]*>([^<]*)/'));
        $itineraries['CheckOutDate'] = $this->stringDateToUnixtime($this->http->FindPreg('/Check out:\s*<[^>]*>([^<]*)/'));
        $hotelInfo = preg_split('/\n/', $this->http->XPath->query('//a[contains(@href, "http://travel.travelocity.com/hotel/HotelDetail.do")]/..')->item(0)->textContent);
        $itineraries['Address'] = trim($hotelInfo[2]) . ' ' . trim($hotelInfo[3]);
        $itineraries['Phone'] = preg_replace('/Hotel policies/', '', trim($hotelInfo[4]));
        $itineraries['GuestNames'] = [$this->http->FindPreg('/Contact:<[^>]*>([^<]*)/')];
        $matches = [];

        if (preg_match('/(\d+)\s*Rooms?,/', $this->http->Response['body'], $matches)) {
            $itineraries['Rooms'] = intval($matches[1]);
        }
        $itineraries['RoomType'] = $this->http->FindPreg('/Room 1:<[^>]*>([^<]*)/');
        $itineraries['Rate'] = $this->http->FindSingleNode('//strong[contains(text(), "Pricing")]/../../../following-sibling::tr[1]//tr[2]/td[3]');
    }

    public function parseCar(&$itineraries)
    {
        $itineraries['Kind'] = 'L';
        $itineraries['Number'] = $this->http->FindPreg('/Confirmation\s*\#\:\s*([^<]*)/');
        $itineraries['PickupDatetime'] = $this->stringDateToUnixtime($this->http->FindSingleNode('//p[contains(text(), "Pick-up")]/strong[1]'), $this::CAR_DATE_FORMAT);
        $itineraries['DropoffDatetime'] = $this->stringDateToUnixtime($this->http->FindSingleNode('//p[contains(text(), "Pick-up")]/strong[2]'), $this::CAR_DATE_FORMAT);
        $pickupLocation = preg_split('/\n/', $this->http->XPath->query('//strong[contains(text(), "Pick up Location")]/following-sibling::span[1]')->item(0)->textContent);
        $itineraries['PickupLocation'] = $itineraries['DropoffLocation'] = trim($pickupLocation[0]);
        $itineraries['PickupPhone'] = $this->http->FindPreg('/Telephone\s*\:\s*(?:<[^>]+>)([\d\-]*)/');
        //Telephone: <a href="tel:575-622-4866"
        //if (!$itineraries['PickupPhone'])
        //$itineraries['PickupPhone'] = $this->http->FindPreg('/Telephone\s*\:\s*([\d\-]*)/');

        $itineraries['PickupFax'] = $this->http->FindPreg('/Fax\s*\:\s*([\d\-]*)/');
        $itineraries['RentalCompany'] = $this->http->FindSingleNode('//td[@class="when"]//img/following-sibling::strong[1]');

        if (!$itineraries['RentalCompany']) {
            $itineraries['RentalCompany'] = $this->http->FindSingleNode("//img[contains(@alt,'Car Company Logo')]/../strong[1]");
        }

        $itineraries['CarType'] = $this->http->FindSingleNode('//td[@class="what"]/p[1]/strong');

        if (!$itineraries['CarType']) {
            $itineraries['CarType'] = $this->http->FindSingleNode("//p[contains(text(),'Driver:')]/ancestor::td[1]/p[1]/strong[1]/following-sibling::span[1]", null, true, "#^\((.*?)\)$#");
        }

        $itineraries['CarModel'] = $this->http->FindSingleNode('//td[@class="what"]/p[1]/span', null, true, '/\((.*)\)/');

        if (!$itineraries['CarModel']) {
            $itineraries['CarModel'] = $this->http->FindSingleNode("//p[contains(text(),'Driver:')]/ancestor::td[1]/p[1]/strong[1]");
        }

        $itineraries['RenterName'] = $this->http->FindPreg('/Driver\s*\:\s*([^<]*)/');
        $itineraries['TotalCharge'] = floatval($this->http->FindSingleNode('//font[contains(text(), "TotalPrice")]/../../following-sibling::td[1]', null, true, '/([\d\.]+)/'));
        $itineraries['TotalCharge'] = floatval($this->http->FindSingleNode('//td[contains(text(), "Taxes and Fees")]/following-sibling::td[1]', null, true, '/([\d\.]+)/'));
    }

    public function parseAir(&$it)
    {
        $it['Kind'] = 'T';

        $text = $this->mkText($this->http->Response['body']);
        $it['RecordLocator'] = $this->re("#\nOnline\s+check\-in\s+code:\s*([^\n]+)#ms", $text);
        echo 123;
        $names = [];
        $numbers = [];

        $nodes = $this->http->XPath->query("//tr//*[text() = 'Passengers']/ancestor::tr[1]/following-sibling::tr");

        for ($i = 0; $i < $nodes->length; $i += 2) {
            $tr = $nodes->item($i);
            $number = trim($this->http->FindSingleNode("td[3]", $tr));

            if (!$number) {
                break;
            } // done

            $names[beautifulName(trim($this->http->FindSingleNode("td[1]", $tr)))] = 1;
            $numbers[$number] = 1;
        }

        if ($names) {
            $it['Passengers'] = implode(', ', array_keys($names));
        }

        if ($numbers) {
            $it['AccountNumbers'] = implode(', ', array_keys($numbers));
        }

        $it['TotalCharge'] = $this->mkCost($this->re("#\nTotal:\s*([^\n]+)#", $text));
        $it['Currency'] = $this->mkCurrency($this->re(1));
        $it['Tax'] = $this->mkCost($this->re("#\nTaxes & Fees:\s*([^\n]+)#", $text));

        $nodes = $this->http->XPath->query("//h4/*[text() = 'Flights']/ancestor::tr[1]/following-sibling::tr");

        for ($i = 1; $i < $nodes->length; $i += 2) {
            $tr = $nodes->item($i);

            $this->re("#^([\w\d, ]+\d{4})\s+.*?Depart\s*:\s*(\d+:\d+\s*\w+)\s*Arrive\s*:\s*(\d+:\d+\s*\w+)\s*(.*?)\((\w{3})\)\s*(.*?)\((\w{3})\)\s*(.*?),\s*Flight\s*([^\s]+)\s+((?:Economy Class|))\s*((?:Non-stop)|)\s*(?:Total Travel Time:\s*(.*?\s+mins)|)\s*(?:Seat request:\s*([^\s]+)|)#ms",
                function ($m) use (&$it) {
                    $seg = [];

                    $seg['DepDate'] = $this->mkDate($m[1] . ', ' . $m[2]);
                    $seg['DepName'] = $m[4];
                    $seg['DepCode'] = $m[5];

                    $seg['ArrDate'] = $this->mkDate($m[1] . ', ' . $m[3]);
                    $seg['ArrName'] = $m[6];
                    $seg['ArrCode'] = $m[7];

                    $seg['AirlineName'] = $m[8];
                    $seg['FlightNumber'] = $m[9];

                    if (isset($m[12])) {
                        $seg['Duration'] = $m[12];
                    }

                    if (isset($m[13])) {
                        $seg['Seats'] = $m[13];
                    }

                    if (trim($m[11]) == 'Non-stop') {
                        $seg['Stops'] = 0;
                    }

                    $seg['Cabin'] = trim($m[10]);

                    $it['TripSegments'][] = $seg;
                },
               $this->http->FindSingleNode("td[1]", $tr)
              );
        }
    }

    public function parseAirPlain(&$itineraries)
    {
        $matches = [];

        $itineraries['Kind'] = 'T';
        $itineraries['RecordLocator'] = $this->http->FindPreg('/use reference code ([^\ ]*)/');

        if (preg_match_all('/Passenger Name:([^\<]*)/', $this->http->Response['body'], $matches)) {
            $itineraries['Passengers'] = join(', ', $matches[1]);
        }
        $itineraries['TotalCharge'] = $this->floatval($this->http->FindPreg('/Total:[^\d]*([\d\,\.]+)/u'));
        $itineraries['Tax'] = $this->floatval($this->http->FindPreg('/Taxes\s*\+\s*Airline[^\d]+([\d\,\.]+)/'));
        $itineraries['BaseFare'] = $itineraries['TotalCharge'] - $itineraries['Tax'];

        $segments = [];

        $email = preg_split('/\n/', $this->http->XPath->query('//body')->item(0)->textContent);

        $currentSegment = [];
        $date = new \DateTime();

        while (count($email) > 0) {
            $matches = [];
            $row = $email[0];
            $row2 = $email[1] ?? '';

            if (preg_match("#For your boarding pass#i", $row)) {
//                $date = trim($email[1]);
                $date = trim($row2);

                if (!strtotime($date) && isset($email[2])) {
                    $date = trim($email[2]);
                }
                array_shift($email);
                array_shift($email);

                continue;
            }

            if (preg_match("#^\s*Flight:\s*(?P<airline>.*)\s*Flight\s*(?P<number>[\da-zA-Z]*)[^\(]*\(on\s*(?P<craft>[^\)\n]+)#u", $row . $row2, $matches)) {
                $currentSegment['AirlineName'] = trim($matches['airline']);
                $currentSegment['FlightNumber'] = trim($matches['number']);
                $currentSegment['Aircraft'] = trim($matches['craft']);
                array_shift($email);

                continue;
            }

            if (preg_match('/Connection Time:\s*(.*)/u', $row, $matches)) {
                $currentSegment['Duration'] = preg_replace('/\s+/', ' ', trim($matches[1]));
                array_shift($email);
                $segments[] = $currentSegment;
                $currentSegment = [];

                continue;
            }

            if (preg_match('/Total Travel Time:(.*)/', $row, $matches)) {
                $segments[] = $currentSegment;
                $currentSegment = [];
                array_shift($email);

                continue;
            }

            if (preg_match('/Depart:\s*(?P<time>.*),\s*(?P<city>.*),\s*(?P<country>.*)\s*\((?P<code>.*)\)/u', $row, $matches)) {
                $currentSegment['DepDate'] = $this->stringDateToUnixtime($date . ', ' . trim($matches['time']), $this::CAR_MIX_DATE_FORMAT);
                $currentSegment['DepName'] = trim($matches['city']);
                $currentSegment['DepCode'] = trim($matches['code']);
                array_shift($email);

                continue;
            }

            if (preg_match('/Arrive:\s*(?P<time>.*),\s*(?P<city>.*),\s*(?P<country>.*)\s*\((?P<code>.*)\)/u', $row, $matches)) {
                $currentSegment['ArrDate'] = $this->stringDateToUnixtime($date . ', ' . trim($matches['time']), $this::CAR_MIX_DATE_FORMAT);
                $currentSegment['ArrName'] = trim($matches['city']);
                $currentSegment['ArrCode'] = trim($matches['code']);
                array_shift($email);

                continue;
            }

            if (preg_match('/Requested Seats\:\s*(.*)/u', $row, $matches)) {
                $currentSegment['Seats'] = trim($matches[1]);
                array_shift($email);

                continue;
            }

            if (stripos($row, "TSA Travel Information") !== false) {
                break;
            }
            array_shift($email);
        }

        $itineraries['TripSegments'] = $segments;
    }

    /**
     * @param $it \DOMNode
     *
     * @return string
     */
    public function getItType($it)
    {
        $content = $it->textContent;

        if (stripos($content, 'Hotel:') !== false) {
            return 'hotel';
        }

        if (stripos($content, 'Pick-up Location:') !== false) {
            return 'car';
        }

        return 'unknown';
    }

    public function parseMix(&$itineraries)
    {
        $its = $this->http->XPath->query('//span[contains(text(), "Itinerary")]/../../../../following-sibling::tr[1]//table');

        foreach ($its as $it) {
            switch ($this->getItType($it)) {
                case 'hotel': $itineraries[] = $this->parseItHotel($it);

break;

                case 'car': $itineraries[] = $this->parseItCar($it);

break;
            }
        }
    }

    public function parseItHotel($it)
    {
        $content = $it->textContent;
        $matches = [];
        $itineraries = [];
        $itineraries['Kind'] = 'R';

        if (preg_match('/Confirmation \#[^\:]*:\s*([^\ ]*)/', $content, $matches)) {
            $itineraries['ConfirmationNumber'] = trim($matches[1]);
        }
        $itineraries['HotelName'] = $this->http->FindSingleNode('./tr[2]/td[1]/p[1]', $it);
        $itineraries['Address'] = $this->http->FindSingleNode('./tr[2]/td[1]/p[2]', $it);

        if (preg_match('/Telephone:\s*([\d+\.\-\ ]+)/u', $content, $matches)) {
            $itineraries['Phone'] = trim($matches[1]);
        }
        $itineraries['CheckInDate'] = $this->stringDateToUnixtime($this->http->FindSingleNode('.//span[contains(text(), "Check in")]/strong[1]', $it));
        $itineraries['CheckOutDate'] = $this->stringDateToUnixtime($this->http->FindSingleNode('.//span[contains(text(), "Check in")]/strong[2]', $it));

        if (preg_match('/Contact:\s*(.+)/u', $content, $matches)) {
            $itineraries['GuestNames'] = [trim($matches[1])];
        }

        if (preg_match('/(\d+)\s*Rooms?,/', $this->http->Response['body'], $matches)) {
            $itineraries['Rooms'] = intval($matches[1]);
        }
        $itineraries['RoomType'] = $this->http->FindSingleNode('./tr[2]/td[2]//span[contains(text(), "Room 1:")]/../following-sibling::span[1]', $it);

        return $itineraries;
    }

    public function parseItCar($it)
    {
        $content = $it->textContent;
        $matches = [];
        $itineraries = [];
        $itineraries['Kind'] = 'L';

        if (preg_match('/Confirmation\s*\#\s*:\s*([^\ ]*)/', $content, $matches)) {
            $itineraries['Number'] = trim($matches[1]);
        }
        $itineraries['PickupDatetime'] = $this->stringDateToUnixtime($this->http->FindSingleNode('.//span[contains(text(), "Pick-up")]/strong[1]', $it), $this::CAR_MIX_DATE_FORMAT);
        $itineraries['DropoffDatetime'] = $this->stringDateToUnixtime($this->http->FindSingleNode('//span[contains(text(), "Pick-up")]/strong[2]', $it), $this::CAR_MIX_DATE_FORMAT);
        $itineraries['PickupLocation'] = $itineraries['DropoffLocation'] = $this->http->FindSingleNode('.//span[contains(text(), "Pick-up Location:")]/../following-sibling::span[1]', $it);

        if (preg_match('/Telephone:\s*([\d+\.\-\ ]+)/u', $content, $matches)) {
            $itineraries['PickupPhone'] = trim($matches[1]);
        }
        $itineraries['RentalCompany'] = $this->http->FindSingleNode('./tr[2]/td[1]/p[1]', $it);
        $itineraries['CarType'] = $this->http->FindSingleNode('./tr[2]/td[2]/p[1]/strong', $it);
        $itineraries['CarModel'] = $this->http->FindSingleNode('./tr[2]/td[2]/p[1]/span[2]', $it, true, '/\((.*)\)/');

        if (preg_match('/Contact:\s*(.+)/u', $content, $matches)) {
            $itineraries['RenterName'] = trim($matches[1]);
        }

        return $itineraries;
    }

    public function stringDateToUnixtime($date, $format = self::DATE_FORMAT)
    {
        //return $date;
        $date = date_parse_from_format($format, $date);

        return mktime($date['hour'], $date['minute'], 0, $date['month'], $date['day'], $date['year']);
    }

    public function floatval($val)
    {
        return floatval(preg_replace('/,/', '', $val));
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]travelocity\.com$/ims', $from);
    }

    public function mkCost($value)
    {
        if (preg_match("#,#", $value) && preg_match("#\.#", $value)) { // like 1,299.99
            $value = preg_replace("#,#", '', $value);
        }

        $value = preg_replace("#,#", '.', $value);
        $value = preg_replace("#[^\d\.]#", '', $value);

        return is_numeric($value) ? (float) number_format($value, 2, '.', '') : null;
    }

    public function mkDate($date, $reltime = null)
    {
        if (!$reltime) {
            $check = strtotime($this->glue($this->mkText($date), ' '));

            return $check ? $check : null;
        }

        $unix = is_numeric($date) ? $date : strtotime($this->glue($this->mkText($date), ' '));

        if ($unix) {
            $guessunix = strtotime(date('Y-m-d', $unix) . ' ' . $reltime);

            if ($guessunix < $unix) {
                $guessunix += 60 * 60 * 24;
            } // inc day

            return $guessunix;
        }

        return null;
    }

    public function mkText($html, $preserveTabs = false, $stringifyCells = true)
    {
        $html = preg_replace("#&" . "nbsp;#uims", " ", $html);
        $html = preg_replace("#&" . "amp;#uims", "&", $html);
        $html = preg_replace("#&" . "quot;#uims", '"', $html);
        $html = preg_replace("#&" . "lt;#uims", '<', $html);
        $html = preg_replace("#&" . "gt;#uims", '>', $html);

        if ($stringifyCells && $preserveTabs) {
            $html = preg_replace_callback("#(</t(d|h)>)\s+#uims", function ($m) {return $m[1]; }, $html);

            $html = preg_replace_callback("#(<t(d|h)(\s+|\s+[^>]+|)>)(.*?)(<\/t(d|h)>)#uims", function ($m) {
                return $m[1] . preg_replace("#[\r\n\t]+#ums", ' ', $m[4]) . $m[5];
            }, $html);
        }

        $html = preg_replace("#<(td|th)(\s+|\s+[^>]+|)>#uims", "\t", $html);

        $html = preg_replace("#<(p|tr)(\s+|\s+[^>]+|)>#uims", "\n", $html);
        $html = preg_replace("#</(p|tr)>#uims", "\n", $html);

        $html = preg_replace("#\r\n#uims", "\n", $html);
        $html = preg_replace("#<br(/|)>#uims", "\n", $html);
        $html = preg_replace("#<[^>]+>#uims", ' ', $html);

        if ($preserveTabs) {
            $html = preg_replace("#[ \f\r]+#uims", ' ', $html);
        } else {
            $html = preg_replace("#[\t \f\r]+#uims", ' ', $html);
        }

        $html = preg_replace("#\n\s+#uims", "\n", $html);
        $html = preg_replace("#\s+\n#uims", "\n", $html);
        $html = preg_replace("#\n+#uims", "\n", $html);

        return trim($html);
    }

    public function xBase($newInstance)
    {
        $this->xInstance = $newInstance;
    }

    public function xHtml($path, $instance = null)
    {
        if (!$instance) {
            $instance = $this->xInstance;
        }

        return $instance->FindHTMLByXpath($path);
    }

    public function xNode($path, $instance = null)
    {
        if (!$instance) {
            $instance = $this->xInstance;
        }
        $nodes = $instance->FindNodes($path);

        return count($nodes) ? implode("\n", $nodes) : null; //$instance->FindSingleNode($path);
    }

    public function xText($path, $preserveCaret = false, $instance = null)
    {
        if ($preserveCaret) {
            return $this->mkText($this->xHtml($path, $instance));
        } else {
            return $this->xNode($path, $instance);
        }
    }

    public function mkImageUrl($imgTag)
    {
        if (preg_match("#src=(\"|'|)([^'\"]+)(\"|'|)#ims", $imgTag, $m)) {
            return $m[2];
        }

        return null;
    }

    public function glue($str, $with = ", ")
    {
        return implode($with, explode("\n", $str));
    }

    public function re($re, $text = false, $index = 1)
    {
        if (is_numeric($re) && $text == false) {
            return ($this->lastRe && isset($this->lastRe[$re])) ? $this->lastRe[$re] : null;
        }

        $this->lastRe = null;

        if (is_callable($text)) { // we have function
            // go through the text using replace function
            return preg_replace_callback($re, function ($m) use ($text) {
                return $text($m);
            }, $index); // index as text in this case
        }

        if (preg_match($re, $text, $m)) {
            $this->lastRe = $m;

            return $m[$index] ?? $m[0];
        } else {
            return null;
        }
    }

    public function mkNice($text, $glue = false)
    {
        $text = $glue ? $this->glue($text, $glue) : $text;

        $text = $this->mkText($text);
        $text = preg_replace("#,+#ms", ',', $text);
        $text = preg_replace("#\s+,\s+#ms", ', ', $text);
        $text = preg_replace_callback("#([\w\d]),([\w\d])#ms", function ($m) {return $m[1] . ', ' . $m[2]; }, $text);
        $text = preg_replace("#[,\s]+$#ms", '', $text);

        return $text;
    }

    public function mkCurrency($text)
    {
        if (preg_match("#\\$#", $text)) {
            return 'USD';
        }

        if (preg_match("#£#", $text)) {
            return 'GBP';
        }

        if (preg_match("#€#", $text)) {
            return 'EUR';
        }

        if (preg_match("#\bCAD\b#i", $text)) {
            return 'CAD';
        }

        if (preg_match("#\bEUR\b#i", $text)) {
            return 'EUR';
        }

        if (preg_match("#\bUSD\b#i", $text)) {
            return 'USD';
        }

        if (preg_match("#\bBRL\b#i", $text)) {
            return 'BRL';
        }

        if (preg_match("#\bCHF\b#i", $text)) {
            return 'CHF';
        }

        if (preg_match("#\bHKD\b#i", $text)) {
            return 'HKD';
        }

        if (preg_match("#\bSEK\b#i", $text)) {
            return 'SEK';
        }

        if (preg_match("#\bZAR\b#i", $text)) {
            return 'ZAR';
        }

        if (preg_match("#\bIN(|R)\b#i", $text)) {
            return 'INR';
        }

        return null;
    }

    public function arrayTabbed($tabbed, $divRowsRe = "#\n#", $divColsRe = "#\t#")
    {
        $r = [];

        foreach (preg_split($divRowsRe, $tabbed) as $line) {
            if (!$line) {
                continue;
            }
            $arr = [];

            foreach (preg_split($divColsRe, $line) as $item) {
                $arr[] = trim($item);
            }
            $r[] = $arr;
        }

        return $r;
    }

    public function arrayColumn($array, $index)
    {
        $r = [];

        foreach ($array as $in) {
            $r[] = $in[$index] ?? null;
        }

        return $r;
    }

    public function orval()
    {
        $array = func_get_args();
        $n = sizeof($array);

        for ($i = 0; $i < $n; $i++) {
            if (((gettype($array[$i]) == 'array' || gettype($array[$i]) == 'object') && sizeof($array[$i]) > 0) || $i == $n - 1) {
                return $array[$i];
            }

            if ($array[$i]) {
                return $array[$i];
            }
        }

        return '';
    }

    public function mkClear($re, $text, $by = '')
    {
        return preg_replace($re, $by, $text);
    }

    public function grep($pattern, $input, $flags = 0)
    {
        if (gettype($flags) == 'function') {
            $r = [];

            foreach ($input as $item) {
                $res = preg_replace_callback($pattern, $flags, $item);

                if ($res !== false) {
                    $r[] = $res;
                }
            }

            return $r;
        }

        return preg_grep($pattern, $input, $flags);
    }

    public function xPDF($parser, $wildcard = null)
    {
        $pdfs = $parser->searchAttachmentByName($wildcard ? $wildcard : '.*pdf');
        $pdf = "";

        foreach ($pdfs as $pdfo) {
            if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdfo), \PDF::MODE_SIMPLE)) !== null) {
                $pdf .= $html;
            }
        }

        return $pdf;
    }

    public function correctByDate($date, $anchorDate)
    {
        // $anchorDate should be earlier than $date
        // not implemented yet
        return $date;
    }
}
