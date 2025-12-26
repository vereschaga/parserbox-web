<?php

namespace AwardWallet\Engine\goldpassport\Email;

class It5873671 extends \TAccountChecker
{
    public $mailFiles = "goldpassport/it-1.eml, goldpassport/it-1651637.eml, goldpassport/it-1734878.eml, goldpassport/it-1737380.eml, goldpassport/it-1737382.eml, goldpassport/it-1737750.eml, goldpassport/it-1839304.eml, goldpassport/it-1892097.eml, goldpassport/it-2240129.eml, goldpassport/it-2756654.eml, goldpassport/it-3184816.eml, goldpassport/it-3200138.eml, goldpassport/it-3200140.eml, goldpassport/it-3544535.eml, goldpassport/it-3545475.eml, goldpassport/it-3804335.eml, goldpassport/it-3864929.eml, goldpassport/it-3874037.eml, goldpassport/it-3964026.eml, goldpassport/it-4878552.eml, goldpassport/it-4880000.eml, goldpassport/it-4880033.eml";

    public $reFrom = '@t.hyatt.com';
    public $reSubject = [
        'en' => 'Hyatt',
    ];

    public $langDetectors = [
        'de' => ['Name des Gastes:'],
        'es' => ['Nombre del huésped:'],
        'zh' => ['房客姓名：'],
        'en' => ['Guest Name:', 'GUEST NAME:'],
    ];

    public static $dictionary = [
        'de' => [
            'Confirmation Number:'=> ['Bestätigungsnummer:'],
            //            'Cancellation Number:'=>['Cancellation Number:'],
            'Check-In Date:'       => ['Check-in-Datum:'],
            'Hotel Check-In Time:' => ['Check-in-Zeit des Hotels:'],
            'Check-Out Date:'      => ['Check-out-Datum:'],
            'Hotel Check-Out Time:'=> ['Check-out-Zeit des Hotels:'],
            "Tel:"                 => ["Tel.:"],
            "Fax:"                 => ["Fax:"],
            "Guest Name:"          => ["Name des Gastes:"],
            "Number of Adults:"    => ["Anzahl der Erwachsenen:"],
            "Number of Children:"  => ["Anzahl der Kinder:"],
            "Number of Rooms:"     => ["Anzahl der Zimmer:"],
            "Type of Rate:"        => ["Preiskategorie:"],
            'Rate Information:'    => 'Preisinformationen:',
            "CANCELLATION POLICY:" => ["STORNIERUNGSBEDINGUNGEN:"],
            "Room(s) Booked:"      => ["Gebuchte(s) Zimmer:"],
            //            "Room Description:"=>"",
            "Nightly Rate per Room:"=> "Zimmerpreis pro Nacht:",
            //            "changed"=>"",
        ],
        'es' => [
            'Confirmation Number:'=> ['Número de confirmación:'],
            // 'Cancellation Number:'=>['Cancellation Number:'],
            'Check-In Date:'       => ['Fecha de llegada:'],
            'Hotel Check-In Time:' => ['Hora de registro de llegada del hotel:'],
            'Check-Out Date:'      => ['Fecha de salida:'],
            'Hotel Check-Out Time:'=> ['Hora de registro de salida del hotel:'],
            "Tel:"                 => ["Tel.:"],
            "Fax:"                 => ["Fax:"],
            "Guest Name:"          => ["Nombre del huésped:"],
            "Number of Adults:"    => ["Número de adultos:"],
            "Number of Children:"  => ["Número de niños:"],
            "Number of Rooms:"     => ["Número de habitaciones:"],
            "Type of Rate:"        => ["Tipo de tarifa:"],
            'Rate Information:'    => 'Información sobre tarifas:',
            "CANCELLATION POLICY:" => ["POLÍTICA DE CANCELACIÓN:"],
            "Room(s) Booked:"      => ["Habitación(es) reservada(s):"],
            //            "Room Description:"=>"",
            "Nightly Rate per Room:"=> "Tarifa por habitación por noche:",
            "changed"               => " ha sido cambiada.",
        ],
        'zh' => [
            'Confirmation Number:'=> ['確認編號：'],
            // 'Cancellation Number:'=>['Cancellation Number:'],
            'Check-In Date:'       => ['登記入住日期：'],
            'Hotel Check-In Time:' => ['酒店登記入住時間：'],
            'Check-Out Date:'      => ['退房日期：'],
            'Hotel Check-Out Time:'=> ['酒店退房時間：'],
            "Tel:"                 => ["電話："],
            // "Fax:"=>"",
            "Guest Name:"           => ["房客姓名："],
            "Number of Adults:"     => ["成人人數："],
            "Number of Children:"   => ["兒童人數："],
            "Number of Rooms:"      => ["房間數目："],
            "Type of Rate:"         => ["房價種類："],
            'Rate Information:'     => '房價資訊：',
            "CANCELLATION POLICY:"  => ["預訂取消政策："],
            "Room(s) Booked:"       => ["預訂房型："],
            "Room Description:"     => "客房說明：",
            "Nightly Rate per Room:"=> "每房每晚房價：",
            //            "changed"=>"",
        ],
        'en' => [
            'Confirmation Number:' => ['Confirmation Number:', 'CONFIRMATION NUMBER:'],
            'Cancellation Number:' => ['Cancellation Number:'],
            'Check-In Date:'       => ['Check-In Date:', 'CHECK-IN DATE:'],
            'Hotel Check-In Time:' => ['Hotel Check-In Time:', 'HOTEL CHECK-IN TIME:'],
            'Check-Out Date:'      => ['Check-Out Date:', 'CHECK-OUT DATE:'],
            'Hotel Check-Out Time:'=> ['Hotel Check-Out Time:', 'HOTEL CHECK-OUT TIME:'],
            // "Tel:"=>["Tel:"],
            // "Fax:"=>["Fax:"],
            "Guest Name:"        => ["Guest Name:", "GUEST NAME:"],
            "Number of Adults:"  => ["Number of Adults:", "NUMBER OF ADULTS:"],
            "Number of Children:"=> ["Number of Children:", "NUMBER OF CHILDREN:"],
            "Number of Rooms:"   => ["Number of Rooms:", "NUMBER OF ROOMS:"],
            "Type of Rate:"      => ["Type of Rate:", "TYPE OF RATE:"],
            //            'Rate Information:'=>'',
            "CANCELLATION POLICY:"  => ["CANCELLATION POLICY:"],
            "Room(s) Booked:"       => ["Room(s) Booked:", "ROOM(S) BOOKED:"],
            "Room Description:"     => ["Room Description:", "ROOM DESCRIPTION:"],
            "Nightly Rate per Room:"=> ['Nightly Rate per Room:', 'NIGHTLY RATE PER ROOM:'],
            "changed"               => [" has been changed.", " has changed."],
        ],
    ];

    public $lang = '';

    public function parseHtml(&$itineraries)
    {
        $xpathFragment1 = 'following::text()[normalize-space(.)][1][not(./ancestor::*[self::b or self::strong])]';

        $it = [];
        $it['Kind'] = 'R';

        // ConfirmationNumber
        if (!$it['ConfirmationNumber'] = $this->nextText($this->t("Confirmation Number:"))) {
            $it['ConfirmationNumber'] = $this->nextText($this->t("Cancellation Number:"));
        }

        $it['HotelName'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Confirmation Number:")) . " or " . $this->eq($this->t("Cancellation Number:")) . "]/ancestor::td[./preceding-sibling::td][1]/../td[1]//img/@alt");

        if (empty($it['HotelName'])) {
            $it['HotelName'] = $this->http->FindSingleNode("//img[@alt!='' and @width='311']/@alt");
        }

        $it['Address'] = implode(', ', $this->http->FindNodes("(//text()[" . $this->eq($this->t("Confirmation Number:")) . " or " . $this->eq($this->t("Cancellation Number:")) . "]/following::text()[contains(normalize-space(.),\"{$it['HotelName']}\")])[1]/following::text()[normalize-space(.)][position()<3]"));

        if (stripos($it['Address'], ',') === false) {
            $this->http->Log('We need to make sure that the address and not the left line!');

            return;
        }

        $inDate = $this->normalizeDate($this->nextText($this->t("Check-In Date:")));
        $outDate = $this->normalizeDate($this->nextText($this->t("Check-Out Date:")));

        if (!empty($inDate) && !empty($outDate)) {
            $it['CheckInDate'] = strtotime($this->normalizeDate($inDate . ',' . $this->nextText($this->t("Hotel Check-In Time:"))));
            $it['CheckOutDate'] = strtotime($this->normalizeDate($outDate . ',' . $this->nextText($this->t("Hotel Check-Out Time:"))));
        }

        // Phone
        $phone = $this->re("#^([\d- \+]+)$#", $this->nextText($this->t("Tel:")));

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Tel:")) . "]", null, true, "#" . implode("|", (array) $this->t("Tel:")) . "\s+(.+)#");
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Tel:")) . "]/following::text()[normalize-space(.)][1]", null, true, "#^([\d- \+]+)$#");
        }

        if (!empty($phone)) {
            $it['Phone'] = $phone;
        }

        // Fax
        $fax = $this->re("#^([\d- \+]+)$#", $this->nextText($this->t("Fax:")));

        if (empty($fax)) {
            $fax = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Fax:")) . "]", null, true, "#" . implode("|", (array) $this->t("Fax:")) . "\s+(.+)#");
        }

        if (empty($fax)) {
            $fax = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Fax:")) . "]/following::text()[normalize-space(.)][1]", null, true, "#^([\d- \+]+)$#");
        }

        if (!empty($fax)) {
            $it['Fax'] = $fax;
        }

        // GuestNames
        $it['GuestNames'] = array_filter([$this->nextText($this->t("Guest Name:"))]);
        // echo ord($this->nextText($this->t("Guest Name:")))."\n";
        // Guests
        $it['Guests'] = $this->nextText($this->t("Number of Adults:"));

        // Kids
        $it['Kids'] = $this->nextText($this->t("Number of Children:"));

        // Rooms
        $it['Rooms'] = $this->nextText($this->t("Number of Rooms:"));

        // Rate
        if ($this->http->XPath->query('//text()[' . $this->eq($this->t('Nightly Rate per Room:')) . ']')->length > 0) {
            $htmlBody = $this->http->Response['body'];
            $textBody = $this->htmlToText($htmlBody);

            if (preg_match('/' . $this->opt($this->t('Nightly Rate per Room:')) . '(.*?)' . $this->opt($this->t('Type of Rate:')) . '/s', $textBody, $matches)) {
                $rateRows = preg_split('/[\n]+/', $matches[1]);
                $rateValues = array_filter($rateRows, function ($n) {
                    return strlen($n) > 1;
                });

                if (count($rateValues)) {
                    $it['Rate'] = str_replace(['<!--', '-->'], '', implode('; ', $rateValues));
                }
            }
        }

        // RateType
        $rateTypeTexts = [];
        $typeOfRateNodes = $this->http->XPath->query('//text()[' . $this->eq($this->t('Type of Rate:')) . ']');

        foreach ($typeOfRateNodes as $typeOfRateNode) {
            $rateType = '';
            $typeOfRate = $this->http->FindSingleNode('./' . $xpathFragment1, $typeOfRateNode);

            if ($typeOfRate) {
                $rateType .= $typeOfRate;
            }
//            $rateInfo = $this->http->FindSingleNode('./following::text()[normalize-space(.)][position()<4][' . $this->eq($this->t('Rate Information:')) . ']/' . $xpathFragment1, $typeOfRateNode);
//            if ($rateInfo)
//                $rateType .= $rateType !== '' ? '; ' . $rateInfo : $rateInfo;
            if ($rateType !== '') {
                $rateTypeTexts[] = $rateType;
            }
        }
        $rateTypeText = implode('; ', array_unique($rateTypeTexts));

        if (!empty($rateTypeText)) {
            $it['RateType'] = $rateTypeText;
        }

        // RoomType
        $roomBookedTexts = $this->http->FindNodes('//text()[' . $this->eq($this->t('Room(s) Booked:')) . ']/following::text()[ ./following::text()[' . $this->eq($this->t('Room Description:')) . '] ][normalize-space(.)]');
        $roomBookedTexts = array_map(function ($n) {
            return preg_replace('/.*?[:：]\s+(.+)/', '$1', $n);
        }, $roomBookedTexts);
        $roomBookedText = implode('; ', array_unique($roomBookedTexts));

        if (empty($roomBookedText)) {
            $roomBookedText = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Room(s) Booked:')) . ']/' . $xpathFragment1);
            $roomBookedText = preg_replace('/.*?[:：]\s+(.+)/', '$1', $roomBookedText);
        }

        if (!empty($roomBookedText)) {
            $it['RoomType'] = $roomBookedText;
        }

        // RoomTypeDescription
        $roomDescriptionTexts = $this->http->FindNodes('//text()[' . $this->eq($this->t('Room Description:')) . ']/following::text()[ ./following::text()[' . $this->eq($this->t('Nightly Rate per Room:')) . '] ][normalize-space(.)]');
        $roomDescriptionText = implode('; ', $roomDescriptionTexts);

        if (empty($roomDescriptionText)) {
            $roomDescriptionText = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Room Description:')) . ']/' . $xpathFragment1);
        }

        if (!empty($roomDescriptionText)) {
            $it['RoomTypeDescription'] = $roomDescriptionText;
        }

        // CancellationPolicy
        $cancellationPolicyTexts = $this->http->FindNodes('//text()[' . $this->eq($this->t('CANCELLATION POLICY:')) . ']/' . $xpathFragment1);
        $cancellationPolicyText = implode('; ', array_unique($cancellationPolicyTexts));

        if (!empty($cancellationPolicyText)) {
            $it['CancellationPolicy'] = $cancellationPolicyText;
        }

        if ($this->http->FindSingleNode("//text()[" . $this->contains($this->t("changed")) . "]")) {
            $it['Status'] = 'changed';
        } elseif ($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Confirmation Number:")) . "]")) {
            $it['Status'] = 'confirmed';
        } elseif ($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Cancellation Number:")) . "]")) {
            $it['Status'] = 'cancelled';
        }

        if ($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Cancellation Number:")) . "]")) {
            $it['Cancelled'] = true;
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && !self::detectEmailFromProvider($headers['from'])) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, 'Hyatt') === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = true;

        if ($this->assignLang() === false) {
            $this->logger->debug("Can't determine a language!");
            return false;
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'HotelConfirmation' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4}),(\d+:\d+\s*[AP]M)$#",
            "#^[^\d\s]+,\s+(\d+)\.\s+([^\d\s]+)\s+(\d{4}),(\d+:\d+)\s+Uhr$#",
            "#^[^\d\s]+,\s+(\d+)\s+de\s+([^\d\s]+)\s+de\s+(\d{4}),(\d+:\d+\s*[AP]M)$#",
            "#^[^\s\d]+,\s+(\d{4})年(\d+)月(\d+)日,上午\s+(\d+)\s+時$#",
            "#^[^\s\d]+,\s+(\d{4})年(\d+)月(\d+)日,下午\s+(\d+)\s+時$#",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
            "$3.$2.$1, $4:00 AM",
            "$3.$2.$1, $4:00 PM",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);
        $t1 = 'qwertyuiopasdfghjklzxcvbnmQWQERTYUIOPASDFGHJKLZXCVBNM1234567890';
        $t2 = '111111111111111111111111111111111111111111111111111111111111111';

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[string-length(normalize-space(.))>1 or contains(translate(normalize-space(.), '{$t1}', '{$t2}'),'1')][1]", $root);
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function htmlToText($string = ''): string
    {
        $string = str_replace("\n", '', $string);
        $string = preg_replace('/<br\b[ ]*\/?>/i', "\n", $string); // only <br> tags
        $string = preg_replace('/<[A-z]+\b.*?\/?>/', '', $string); // opening tags
        $string = preg_replace('/<\/[A-z]+\b[ ]*>/', '', $string); // closing tags
        $string = htmlspecialchars_decode($string);

        return $string;
    }
}
