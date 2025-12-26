<?php

namespace AwardWallet\Engine\preferred\Email;

class HotelAvantiPDF extends \TAccountChecker
{
    public $mailFiles = ""; // +1 mail from bcd (pdf)[fr]

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Hotel Casablanca') !== false
            && stripos($headers['subject'], 'Maroc') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'’<>?~`!@\#$%^&*\[\]=\(\)\-{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);

            if (stripos($textPdf, 'AVANTI') !== false && stripos($textPdf, 'HOTEL') !== false && stripos($textPdf, 'Numéro de Confirmation') !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        foreach ($parser->searchAttachmentByName('.*pdf') as $pdf) {
            $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
            $htmlPdf = str_replace(['&#160;', '&nbsp;', '  '], ' ', $htmlPdf);
            $htmlPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'’<>?~`!@\#$%^&*\[\]=\(\)\-{}£¥₣₤₧€\$\|]#imsu", ' ', $htmlPdf);
            $this->pdf = clone $this->http;
            $this->pdf->SetBody($htmlPdf);
            $this->year = getdate(strtotime($parser->getHeader('date')))['year'];

            if ($it = $this->parsePdf()) {
                return [
                    'parsedData' => [
                        'Itineraries' => [$it],
                    ],
                    'emailType' => 'HotelAvantiPDF',
                ];
            }
        }

        return null;
    }

    public static function getEmailLanguages()
    {
        return ['fr'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function normalizeDate($string)
    {
        if (preg_match('/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s*$/', $string, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if ($day && $month && $year) {
            if (preg_match('/^\s*\d{1,2}\s*$/', $month)) {
                return $day . '.' . $month . '.' . $year;
            }
        }

        return false;
    }

    protected function parsePdf()
    {
        $patterns = [
            'date' => '/^\s*(\d{1,2}\/\d{1,2}\/\d{4})\s*$/',
        ];

        // убираем пустые параграфы по бокам двоеточий
        $nodesToStip = $this->pdf->XPath->query('//*[count(./*)=1 and ./*[(name()="b" or name()="strong") and .=" "] and (./following-sibling::*[normalize-space(.)=":" and position()=1] or ./preceding-sibling::*[normalize-space(.)=":" and position()=1])]');

        foreach ($nodesToStip as $nodeToStip) {
            $nodeToStip->parentNode->removeChild($nodeToStip);
        }

        $it = [];
        $it['Kind'] = 'R';

        $hotelNameNodes = $this->pdf->XPath->query('//*[starts-with(normalize-space(.),"AVANTI MOHAMMEDIA HOTEL") and contains(.,"confirme votre réservation")]');

        if ($hotelNameNodes->length > 0) {
            $it['HotelName'] = 'AVANTI MOHAMMEDIA HOTEL';
            $it['Address'] = 'Avenue Moulay Youssef, La Corniche, Mohammédia - Grand Casablanca - Maroc'; // This text from image!
        }

        $it['ConfirmationNumber'] = $this->pdf->FindSingleNode('//*[starts-with(normalize-space(.),"Numéro de Confirmation")]/following-sibling::*[normalize-space(.)=":" and position()=1]/following-sibling::*[normalize-space(.) and position()=1]', null, true, '/^\s*(\d{2,})\s*$/');

        $clientName = $this->pdf->FindSingleNode('//*[starts-with(normalize-space(.),"Nom du Client")]/following-sibling::*[normalize-space(.)=":" and position()=1]/following-sibling::*[normalize-space(.) and position()=1]');

        if ($clientName) {
            $it['GuestNames'] = [$clientName];
        }

        $checkInDate = $this->pdf->FindSingleNode('//*[starts-with(normalize-space(.),"Date D’arrivée")]/following-sibling::*[normalize-space(.)=":" and position()=1]/following-sibling::*[normalize-space(.) and position()=1]', null, true, $patterns['date']);

        if ($checkInDate) {
            if ($checkInDate = $this->normalizeDate($checkInDate)) {
                $it['CheckInDate'] = strtotime($checkInDate);
            }
        }

        $checkOutDate = $this->pdf->FindSingleNode('//*[starts-with(normalize-space(.),"Date de Départ")]/following-sibling::*[normalize-space(.)=":" and position()=1]/following-sibling::*[normalize-space(.) and position()=1]', null, true, $patterns['date']);

        if ($checkOutDate) {
            if ($checkOutDate = $this->normalizeDate($checkOutDate)) {
                $it['CheckOutDate'] = strtotime($checkOutDate);
            }
        }

        $rooms = $this->pdf->FindSingleNode('//*[starts-with(normalize-space(.),"Nombre de Chambres")]/following-sibling::*[normalize-space(.)=":" and position()=1]/following-sibling::*[normalize-space(.) and position()=1]', null, true, '/^\s*(\d{1,3})\s*$/');

        if ($rooms) {
            $it['Rooms'] = $rooms;
        }

        $roomType = $this->pdf->FindSingleNode('//*[starts-with(normalize-space(.),"Type de Chambre")]/following-sibling::*[normalize-space(.)=":" and position()=1]/following-sibling::*[normalize-space(.) and position()=1]');

        if ($roomType) {
            $it['RoomType'] = $roomType;
        }

        $adults = $this->pdf->FindSingleNode('//*[starts-with(normalize-space(.),"Nombre d’Adultes")]/following-sibling::*[normalize-space(.)=":" and position()=1]/following-sibling::*[normalize-space(.) and position()=1]', null, true, '/^\s*(\d{1,3})person/');

        if ($adults) {
            $it['Guests'] = $adults;
        }

        $rate = $this->pdf->FindSingleNode('//*[starts-with(normalize-space(.),"Tarif par Chambre et par nuit")]/following-sibling::*[normalize-space(.)=":" and position()=1]/following-sibling::*[normalize-space(.) and position()=1]', null, true, '/^\s*([,.\d\s]+[A-Z]{3})\s*$/');

        if ($rate) {
            $it['Rate'] = $rate;
        }

        $roomDescTexts = $this->pdf->FindNodes('//*[starts-with(normalize-space(.),"Package Inclus")]/following-sibling::*[normalize-space(.)=":" and position()=1]/following-sibling::*[./following-sibling::*[starts-with(normalize-space(.),"Agence / Compagnie")]]');
        $roomDescValues = array_values(array_filter($roomDescTexts));

        if (count($roomDescValues)) {
            $roomDesc = '';

            foreach ($roomDescValues as $roomDescValue) {
                if (strpos($roomDescValue, ':') !== false) {
                    break;
                } else {
                    $roomDesc .= ' ' . $roomDescValue;
                }
            }
            $roomDesc = trim($roomDesc);

            if ($roomDesc) {
                $it['RoomTypeDescription'] = str_replace('  ', ' ', $roomDesc);
            }
        }

        $agence = $this->pdf->FindSingleNode('//*[starts-with(normalize-space(.),"Agence / Compagnie")]/following-sibling::*[normalize-space(.)=":" and position()=1]/following-sibling::*[normalize-space(.) and position()=1]');

        if ($agence) {
            $it['2ChainName'] = $agence;
        }

        $cancelPolicyTexts = $this->pdf->FindNodes('//*[starts-with(normalize-space(.),"Conditions d’Annulation")]/following-sibling::*[normalize-space(.)=":" and position()=1]/following-sibling::*[./following-sibling::*[starts-with(normalize-space(.),"Coordonnées Bancaires")]]');
        $cancelPolicyValues = array_values(array_filter($cancelPolicyTexts));

        if (count($cancelPolicyValues)) {
            $cancelPolicy = '';

            foreach ($cancelPolicyValues as $cancelPolicyValue) {
                if (strpos($cancelPolicyValue, ':') !== false) {
                    break;
                } else {
                    $cancelPolicy .= ' ' . $cancelPolicyValue;
                }
            }
            $cancelPolicy = trim($cancelPolicy);

            if ($cancelPolicy) {
                $it['CancellationPolicy'] = str_replace('  ', ' ', $cancelPolicy);
            }
        }

        return $it;
    }
}
