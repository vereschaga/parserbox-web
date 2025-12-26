<?php

namespace AwardWallet\Engine\marriott\Email;

class C extends \TAccountChecker
{
    public $mailFiles = "marriott/it-10.eml, marriott/it-15.eml, marriott/it-16.eml, marriott/it-18.eml, marriott/it-21.eml, marriott/it-2392432.eml, marriott/it-3.eml, marriott/it-38.eml, marriott/it-39.eml, marriott/it-4.eml, marriott/it-5.eml, marriott/it-6.eml";

    /**
     * @var \HttpBrowser
     */
    private $oldHttp = null;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@marriott.com') !== false
            || stripos($from, '@renaissancehotels.com') !== false
            || stripos($from, '@courtyard.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Marriott Reservation Confirmation') !== false
            || stripos($headers['subject'], 'The Westfields Marriott Washington Dulles') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        return stripos($body, 'Marriott International') !== false
            || stripos($body, 'We are pleased to confirm your reservation with Marriott') !== false
            || stripos($body, 'All contents ©2009 Marriott International') !== false
            || preg_match('#Thank you for choosing\s+the Westfields Marriott#ims', $body)
            || stripos($body, 'Marriott keeps an official record of all electronic reservations') !== false
            || stripos($body, 'when you make reservations, use Marriott Rewards points') !== false
            || stripos($body, 'Marriott Confirmation Number') !== false
            || $this->http->XPath->query('//node()[contains(normalize-space(.),"This Marriott.com reservation email has been forwarded")]')->length > 0;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $textHtml = $parser->getHTMLBody();

        if (empty($textHtml)) {
            $textPlain = $parser->getPlainBody();
            $this->http->SetEmailBody($textPlain);
        } elseif ($this->http->XPath->query("//img")->length !== 0) {
            $this->logger->debug('go to parse by ReservationConfirmation2014.php or other');

            return null;
        }

        if (
            stripos($parser->getPlainBody(), '---------------')
            || preg_match('/(*UTF8)Reservation\s+Details\s+(·|\*)/iums', $parser->getPlainBody())
            || preg_match("/Fax:[^\n\r]+[\s>]+Guest\s+name:/ims", $parser->getPlainBody())
        ) {
            $this->oldHttp = clone $this->http;
            $this->http->SetEmailBody($parser->getPlainBody());

            return [
                "emailType"  => "reservation",
                "parsedData" => [
                    "Itineraries" => [$this->parseEmail()],
                ],
            ];
        }
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'R'];
        $text = $this->http->Response['body'];
        $text = str_replace("\r", '', $text);
        $text = preg_replace("#&" . "nbsp;#i", ' ', $text);
        $text = preg_replace("#<br/*>#i", "\n", $text);
        $text = preg_replace("#<[^>]+>#", ' ', $text);
        $text = preg_replace("#^>#", '', $text);
        $text = preg_replace("#\*#", ' ', $text); // buggy "stars"
        $text = preg_replace("#\n\s*>+[ \t]*#ms", "\n", $text); // "un"forward

        $it['ConfirmationNumber'] = preg_match("#Confirmation Number\s*[:]*\s*([^\n]+)#i", $text, $m) ? trim($m[1]) : null;
        $it['AccountNumbers'] = preg_match("#Your Rewards number:\s*([^\n]+)#i", $text, $m) ? trim($m[1]) : null;

        $it['HotelName'] = preg_match("#Your hotel:\s*([^\n]+)#i", $text, $m) ? trim($m[1]) : null;

        if (preg_match("#((?:To|wrote):[^\n]+\s+(.*?)\s+)?Phone:\s*([^\n]+)\s*Fax:\s*([^\n]+)#ims", $text, $m)) {
            if (!empty($m[1])) {
                if (preg_match("#Confirmation\s*\#\d+(.+)$#ms", $m[2], $in)) {
                    $m[2] = $in[1];
                }

                if (preg_match("#\[[^\]]+\]\s*(.+)$#ms", $m[2], $in)) {
                    $m[2] = $in[1];
                }

                $it['Address'] = preg_replace("#,*[\r\n]+#", ', ', $m[2]);
            }
            $it['Phone'] = $m[3];
            $it['Fax'] = $m[4];

            $i = 10;

            if (isset($it['Address'])) {
                while (($pos = strpos($it['Address'], $it['HotelName'])) !== false) {
                    $it['Address'] = trim(substr($it['Address'], $pos + strlen($it['HotelName'])), ', ');

                    if ($i-- < 0) {
                        break;
                    }
                }
            }
        }

        // it-18.eml, it-21.eml
        if (empty($it['HotelName']) && empty($it['Address']) && preg_match('#This Marriott.com reservation email[^\r\n]+\s+(?:[^\r\n]+)?\s+[\r\n]([^\r\n]+)\s+(([^\r\n]+\n){3})#is', $text, $m)) {
            $it['HotelName'] = $m[1];
            $it['Address'] = preg_replace('/,*\n+/', ', ', trim($m[2]));
        }

        if (preg_match("#Check-in:\s*([^\n]+)#i", $text, $m)) {
            $s = $m[1];
            $s = preg_replace("#[\(\)]#", '', $s);
            $s = str_replace('Check-in time: ', '', $s);
            $it['CheckInDate'] = strtotime($s);
        }

        if (preg_match("#Check-out:\s*([^\n]+)#i", $text, $m)) {
            $s = $m[1];
            $s = preg_replace(["#[\(\)]#", '/(Number of guests\s*:\s*.+)/i'], ['', ''], $s);
            $s = trim(str_replace('Check-out time: ', '', $s));
            $it['CheckOutDate'] = strtotime($s);
        }

        // RoomType
        $it['RoomType'] = preg_match("#(?:Room type:|Room Preferences & Description:)\s*(.*?)\s+(?:Number of rooms:|Room \d{1,3}:)#ims", $text, $m) ? trim($m[1]) : null;
        $it['RoomType'] = trim(preg_match("#^(.*?)http://#ims", $it['RoomType'], $m) ? $m[1] : $it['RoomType']);
        $it['RoomType'] = preg_replace("#[\n·]#", '', $it['RoomType']);

        // RoomTypeDescription
        if (preg_match('/Room \d{1,3}:\s*(.+?)\s*Summary of Room Charges/s', $text, $matches)) {
            $it['RoomTypeDescription'] = preg_replace('/\s+/', ' ', $matches[1]);
        }

        $it['Rooms'] = preg_match("#Number of rooms:\s*(\d+)#i", $text, $m) ? ((int) trim($m[1])) : null;
        $it['Guests'] = preg_match("#(?:Guests per room|Number of guests):\s*(\d+)#i", $text, $m) ? ((int) trim($m[1])) : null;
        $it['GuestNames'][] = preg_match("#Guest name:\s*([^\n]+)#i", $text, $m) ? trim($m[1]) : null;
        $it['ReservationDate'] = preg_match("#Reservation confirmed:\s*([^\n]+)#i", $text, $m) ? strtotime(preg_replace("#[\(\)]#", '', $m[1])) : null;

        // Rate
        if (preg_match("#Cost per night (?:per room \(\w+\)|\(per room\):\s+)\s*([^\n]+)#i", $text, $matches)) {
            $it['Rate'] = preg_match('/(\d[,.\d]*)[ ]*([A-Z]{3}\b|$)/', $matches[1], $m) ? $m[1] . $m[2] . ' / night' : null;
        }

        // Total
        // Currency
        if (preg_match('/Total for stay.*?\s+(\d[,.\d]*)\s*([A-Z]{3}\b)?/', $text, $m)) { // 1,066.67 USD
            $it['Total'] = $this->normalizeAmount($m[1]);

            if (!empty($m[2])) {
                $it['Currency'] = $m[2];
            }
        }

        if (empty($it['Currency'])) {
            $it['Currency'] = preg_match("#Cost per night per room \(([A-Z]{3})\)#", $text, $m) ? $m[1] : null;
        }

        if (empty($it['Currency'])) {
            $it['Currency'] = preg_match("#Cost per night (?:per room \(\w+\)|\(per room\):\s+)\s*.*\s*([A-Z]{3})#", $text, $m) ? $m[1] : null;
        }

        // Taxes
        $it['Taxes'] = preg_match("#Estimated government taxes and fees\s*(?:\-)?\s*([\d.]+)#i", $text, $m) ? ((float) trim($m[1])) : null;

        // CancellationPolicy
        $ruleCancellation = $this->opt(['Cancelling Your Reservation', 'Canceling Your Reservation']);
        $cancelPolicy = preg_match('/' . $ruleCancellation . '\s*(.*?)\s*Modifying Your Reservation/s', $text, $m) ? $m[1] : '';

        if (!$cancelPolicy) {
            $cancelPolicy = preg_match("#{$ruleCancellation}\s+(.*?)\s+You\s+may\s+modify\s+or\s+cancel\s+your\s+reservation#ims", $text, $m) ? $m[1] : '';
        }
        $cancelPolicy = trim(preg_replace("#[^\d\w \.,\t:!@\#\$%^&*\(\)\[\]\"\'\-]#ms", ' ', $cancelPolicy));
        $cancelPolicy = trim(preg_replace('/\s+/', ' ', $cancelPolicy));

        if ($cancelPolicy) {
            if (mb_strlen($cancelPolicy) > 1000) {
                for ($i = 0; $i < 20; $i++) {
                    $cancelPolicy = preg_replace('/^(.+\w\s*\.).+?\.$/s', '$1', $cancelPolicy);

                    if (mb_strlen($cancelPolicy) < 1001) {
                        break;
                    }
                }
            }
            $it['CancellationPolicy'] = $cancelPolicy;
        }

        if (!isset($it['HotelName']) || !$it['HotelName']) {
            $it['HotelName'] = preg_match("#\n\s*Your Resort:\s*([^\n]+)#", $text, $m) ? $m[1] : '';
        }

        if (!isset($it['Address']) || !$it['Address']) {
            $it['Address'] = preg_match("#([^\n]+\s+[^\n]+)\s*Phone:#", $text, $m) ? preg_replace("#\n#", ' ', trim($m[1])) : '';
            $it['Address'] = trim(preg_replace("#{$it['HotelName']}#i", '', $it['Address']));
        }

        if (empty($it['Address']) || !$it['Address']) {
            $it['Address'] = implode(', ', $this->oldHttp->FindNodes("//h4[contains(., 'Hotel:')]/following::*[1]/descendant::text()[position() = 1 or position() = 2]"));
        }

        if (empty($it['Address']) && empty($it['Address']) && preg_match('/([\d\D]+?)\n([\d\D]+?)\n(.+)\nFax:\s*(.+)/', $text, $m)) {
            $it['HotelName'] = trim($m[1]);
            $it['Address'] = preg_replace('/\s+/', ' ', $m[2]);
            $it['Phone'] = trim($m[3]);
            $it['Fax'] = trim($m[4]);
        }

        if (
            (empty($it['Phone']) || empty($it['Fax']))
            && preg_match('/\n\s*([\d\-+]+)\s+Fax:\s+(.*\d)/i', $text, $matches)
        ) {
            $it['Phone'] = $matches[1];
            $it['Fax'] = $matches[2];
        }

        return $it;
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $string): string
    {
        $string = preg_replace('/\s+/', '', $string);             // 11 507.00  ->  11507.00
        $string = preg_replace('/[,.\'](\d{3})/', '$1', $string); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);    // 18800,00   ->  18800.00

        return $string;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
