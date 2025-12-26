<?php

namespace AwardWallet\Engine\preferred\Email;

// bcdtravel
class BostonparkplazaHtml2017En extends \TAccountChecker
{
    public $mailFiles = "preferred/it-35443109.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = [];

        if (stripos($parser->getHTMLBody(), 'will be our guest at Boston Park Plaza') !== false) {
            $result[] = $this->parseHotel();
        } elseif (stripos($parser->getHTMLBody(), 'The above rate(s) may not reflect') !== false && stripos($parser->getHTMLBody(), 'bostonparkplaza') !== false) {
            $result[] = $this->parseHotel2();
        } elseif (stripos($parser->getHTMLBody(), 'Thank you for reserving your room at') !== false) {
            $result[] = $this->parseHotel3();
        } elseif (stripos($parser->getHTMLBody(), 'Thank you for choosing') !== false) {
            $result[] = $this->parseHotel4();
        }

        return [
            'emailType'  => 'BostonParkPlazaOrGeorgianTerrace',
            'parsedData' => ['Itineraries' => $result],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && (
                stripos($headers['from'], '@bostonparkplaza.com') !== false
                || stripos($headers['from'], '@nwlr.com') !== false
            )
                && isset($headers['subject']) && (
                stripos($headers['subject'], 'Boston Park Plaza - Reservation Confirmation #') !== false
                || stripos($headers['subject'], 'Nemacolin Woodlands Resort - Booking Confirmation') !== false
                || stripos($headers['subject'], 'Boston Park Plaza: Your Reservation Confirmation') !== false
                );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $type1 = stripos($parser->getHTMLBody(), 'bostonparkplaza') !== false && (
                strpos($parser->getHTMLBody(), 'will be our guest at Boston Park Plaza') !== false
                || strpos($parser->getHTMLBody(), 'The above rate(s) may not reflect') !== false
                );
        $type2 = stripos($parser->getHTMLBody(), 'Georgian Terrace') !== false && (
                strpos($parser->getHTMLBody(), 'Your Reservation Confirmation') !== false
                || strpos($parser->getHTMLBody(), 'Thank you for reserving your room at') !== false
                );
        $type2 = stripos($parser->getHTMLBody(), 'Nemacolin Woodlands Resort') !== false && (
                strpos($parser->getHTMLBody(), 'Thank you for choosing') !== false
                );

        return $type1 || $type2;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@bostonparkplaza.com') !== false || stripos($from, '@nwlr.com') !== false;
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT\n(.*)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * </p>
     * <pre>
     * findСutSection($plainText, 'LEFT', 'RIGHT')
     * findСutSection($plainText, 'LEFT', ['RIGHT', PHP_EOL])
     * </pre>.
     */
    public function findCutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;

        if (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($input, $searchFinish, true);
        } else {
            $inputResult = $input;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 4;
    }

    protected function parseHotel4()
    {
        $this->http->Log('parseHotel4');
        $it['Kind'] = 'R';
        $it['ConfirmationNumber'] = $this->http->FindSingleNode('//td[normalize-space(.)="Confirmation Number"]/following-sibling::td[1]');
        $it['GuestNames'] = $this->http->FindNodes('//td[starts-with(normalize-space(.),"Guest Name")]/following-sibling::td[1]');
        $it['CheckInDate'] = $this->normalizeDate($this->http->FindSingleNode('//td[starts-with(normalize-space(.),"Arrival Date")]/following-sibling::td[1]'));
        $it['CheckOutDate'] = $this->normalizeDate($this->http->FindSingleNode('//td[starts-with(normalize-space(.),"Departure Date")]/following-sibling::td[1]'));
        $it['RoomType'] = $this->http->FindSingleNode('//td[starts-with(normalize-space(.),"Room Type")]/following-sibling::td[1]');
        $it['RateType'] = $this->http->FindSingleNode('//td[starts-with(normalize-space(.),"Room Rate")]/following-sibling::td[1]');

        if (!empty($time = str_replace(".", "", $this->http->FindSingleNode('//td[starts-with(normalize-space(.),"Check-In Time")]/following-sibling::td[1]')))) {
            $it['CheckInDate'] = strtotime($time, $it['CheckInDate']);
        }

        if (!empty($time = str_replace(".", "", $this->http->FindSingleNode('//td[starts-with(normalize-space(.),"Check-Out Time")]/following-sibling::td[1]')))) {
            $it['CheckOutDate'] = strtotime($time, $it['CheckOutDate']);
        }

        if (empty($it['HotelName'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Thank you for choosing')]/ancestor::*[1]", null, true, "#Thank you for choosing\s+(.+?)\s*[\!\.]#"))) {
            $it['HotelName'] = $this->http->FindSingleNode("//img[contains(@src,'_Logo.jpg')]/@alt");
        }
        $node = implode("\n", $this->http->FindNodes("(//text()[normalize-space(.)='{$it['HotelName']}'])[last()]/following::table[1][contains(.,'RES')]//text()[normalize-space(.)!='']"));

        if (preg_match("#(.+?)\s*RES\.\s+([\d \-\+\(\)\.]+)#", $node, $m)) {
            $it['Address'] = preg_replace("#\s+#", ' ', $m[1]);

            if (isset($m[2]) && !empty($m[2])) {
                $it['Phone'] = str_replace(".", "-", $m[2]);
            }
        }

        return $it;
    }

    protected function parseHotel3()
    {
        $this->http->Log('parseHotel3');
        $it['Kind'] = 'R';
        $it['ConfirmationNumber'] = $this->http->FindSingleNode('//td[normalize-space(.)="Confirmation Number"]/following-sibling::td[1]');
        $it['GuestNames'] = $this->http->FindNodes('//td[starts-with(normalize-space(.),"Guest Name")]/following-sibling::td[1]');
        $it['CheckInDate'] = $this->normalizeDate($this->http->FindSingleNode('//td[starts-with(normalize-space(.),"Arrival Date")]/following-sibling::td[1]'));
        $it['CheckOutDate'] = $this->normalizeDate($this->http->FindSingleNode('//td[starts-with(normalize-space(.),"Departure Date")]/following-sibling::td[1]'));
        $it['RoomType'] = $this->http->FindSingleNode('//td[starts-with(normalize-space(.),"Room Type")]/following-sibling::td[1]');
        $it['RateType'] = $this->http->FindSingleNode('//td[starts-with(normalize-space(.),"Rate Description")]/following-sibling::td[1]');
        $it['CancellationPolicy'] = $this->http->FindSingleNode('//td[starts-with(normalize-space(.),"Cancellation Policy")]/following-sibling::td[1]');

        if (!empty($time = str_replace(".", "", $this->http->FindSingleNode('//td[starts-with(normalize-space(.),"Check-In Time")]/following-sibling::td[1]')))) {
            $it['CheckInDate'] = strtotime($time, $it['CheckInDate']);
        }

        if (!empty($time = str_replace(".", "", $this->http->FindSingleNode('//td[starts-with(normalize-space(.),"Check-Out Time")]/following-sibling::td[1]')))) {
            $it['CheckOutDate'] = strtotime($time, $it['CheckOutDate']);
        }

        if (empty($it['HotelName'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Thank you for reserving your room at')]", null, true, "#Thank you for reserving your room at\s+(.+?)\s*(?:Hotel|\.)#"))) {
            $it['HotelName'] = $this->http->FindSingleNode("//img[contains(@src,'_Logo.jpg')]/@alt");
        }
        $node = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(.),'For more information on')]/following::text()[normalize-space(.)='{$it['HotelName']}']/ancestor::td[1]//text()[normalize-space()!='' and not(normalize-space(.)='{$it['HotelName']}')]"));

        if (preg_match("#(.+?)\s*(?:PH:\s+([\d \-\+\(\)\.]+)|$)#is", $node, $m)) {
            $it['Address'] = preg_replace("#\s+#", ' ', $m[1]);

            if (isset($m[2]) && !empty($m[2])) {
                $it['Phone'] = str_replace(".", "-", $m[2]);
            }
        }

        return $it;
    }

    protected function parseHotel2()
    {
        $this->http->Log('parseHotel2');
        $it['Kind'] = 'R';
        $it['ConfirmationNumber'] = $this->http->FindSingleNode('//td[normalize-space(.)="Confirmation #"]/following-sibling::td[1]');
        $it['GuestNames'] = $this->http->FindNodes('//td[normalize-space(.)="Guest Name:"]/following-sibling::td[1]');
        $it['CheckInDate'] = $this->normalizeDate($this->http->FindSingleNode('//td[normalize-space(.)="Arrival Date:"]/following-sibling::td[1]'));
        $it['CheckOutDate'] = $this->normalizeDate($this->http->FindSingleNode('//td[normalize-space(.)="Departure Date:"]/following-sibling::td[1]'));
        $it['RoomType'] = $this->http->FindSingleNode('//td[normalize-space(.)="Room Type:"]/following-sibling::td[1]');
        $it['Rate'] = $this->http->FindSingleNode('//td[normalize-space(.)="Nightly Rate:"]/following-sibling::td[1]');

        $it['HotelName'] = $this->http->FindSingleNode("//td[normalize-space(.)='Valet Parking:']/following-sibling::td[1]", null, true, "#(The Boston Park Plaza Hotel) offers#");
        $it['Address'] = trim($this->http->FindSingleNode("//text()[normalize-space(.)='Entrance Address']/following::text()[normalize-space(.)][1]"), '- ');

        return $it;
    }

    protected function parseHotel()
    {
        $this->http->Log('parseHotel');
        $it['Kind'] = 'R';
        $it['ConfirmationNumber'] = $this->http->FindSingleNode('(//text()[contains(., "Confirmation #")])[1]', null, false, '/Confirmation #:?\s*(\w+)/');
        $it['GuestNames'] = $this->http->FindNodes('//td[contains(text(), "Guest Name:")]/following-sibling::td[1]');
        $it['CheckInDate'] = $this->normalizeDate($this->http->FindSingleNode('//td[contains(text(), "Check-in from:")]/following-sibling::td[1]'));
        $it['CheckOutDate'] = $this->normalizeDate($this->http->FindSingleNode('//td[contains(text(), "Check-out by:")]/following-sibling::td[1]'));
        $it['RoomType'] = $this->http->FindSingleNode('//td[contains(text(), "Room Type:")]/following-sibling::td[1]');

        $guests = $this->http->FindSingleNode('//td[contains(text(), "Number of Guests:")]/following-sibling::td[1]');

        if (preg_match('/Adults: (\d+), Children: (\d+)/', $guests, $matches)) {
            $it['Guests'] = (int) $matches[1];
            $it['Kids'] = (int) $matches[2];
        }

        $total = $this->http->FindSingleNode('//td[contains(text(), "Total:")]/following-sibling::td[1]');
        $it['Total'] = (float) preg_replace('/[^\d.]+/', '', $total);
        $it['Currency'] = preg_replace(['/[\d.,\s]+/', '/^\$$/'], ['', 'USD'], $total);

        $it['CancellationPolicy'] = $this->http->FindSingleNode('//td[contains(text(), "Reservation Policies:")]/following-sibling::td[1]');

        foreach ($this->http->XPath->query('//text()[contains(., "website:")]/ancestor::p[1]') as $root) {
            $it['HotelName'] = $this->http->FindSingleNode('span[1]', $root);

            if (preg_match('/' . $it['HotelName'] . '\s+(.+?)\s+(?:Phone:|E-mail:|website:)/u', $root->nodeValue, $matches)) {
                $it['Address'] = $matches[1];
            }

            if (preg_match('/Phone:\s*([-+\d\s.]+)/', $root->nodeValue, $matches)) {
                $it['Phone'] = trim($matches[1]);
            }
        }

        return $it;
    }

    //========================================
    // Auxiliary methods
    //========================================

    protected function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    protected function normalizeDate($string)
    {
        return strtotime(preg_replace(
                        // 3:00 PM, Monday, 6 February, 2017
                        ['/(\d+:\d+\s*(?:[AP]M)?), \w+, (\d+ \w+), (\d{4})/'], ['$2 $3, $1'], $string));
    }

    protected function htmlToText($string, $view = false)
    {
        $text = str_replace(' ', ' ', preg_replace('/<[^>]+>/', "\n", html_entity_decode($string)));

        if ($view) {
            return preg_replace('/\n{2,}/', "\n", $text);
        }

        return $text;
    }
}
