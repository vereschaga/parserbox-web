<?php

namespace AwardWallet\Engine\hotels\Email;

class Pdf2016 extends \TAccountChecker
{
    public $mailFiles = "hotels/it-5225523.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.+?\.pdf');

        if (empty($pdf)) {
            $this->http->Log('Pdf is not found or is empty!');

            return false;
        }

        foreach ($pdf as $value) {
            $text = str_replace(' ', ' ', \PDF::convertToHtml($parser->getAttachmentBody($value)));

            if (stripos($text, 'Número de confirmación de Hoteles.com') !== false || stripos($text, 'Hotels.com Confirmation Number') !== false) {
                $this->parseReservations($this->htmlToText($this->findСutSection($text, null, ['2016 Hoteles.com'])));

                continue;
            }
        }

        return [
            'emailType'  => 'PDF for provider Hoteles',
            'parsedData' => ['Itineraries' => $this->result],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.+?\.pdf');

        foreach ($pdf as $value) {
            $text = str_replace(' ', ' ', \PDF::convertToText($parser->getAttachmentBody($value)));

            if (stripos($text, 'Número de confirmación de Hoteles.com') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'es'];
    }

    public static function getEmailTypesCount()
    {
        return 3;
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
    public function findСutSection($input, $searchStart, $searchFinish = null)
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

    protected function parseReservations($text)
    {
        $splitter = 'Número de confirmación de|Hotels\.com Confirmation Number|Tú número de confirmación de';
        $array = $this->splitter("/({$splitter})/u", $text);

        foreach ($array as $value) {
            if (stripos($value, 'Tú número de confirmación de') !== false) {
                $this->result[] = $this->parseReservation2($value);
            } else {
                $this->result[] = $this->parseReservation1($value);
            }
        }
    }

    protected function parseReservation2($text)
    {
        $result['Kind'] = 'R';

        if (preg_match('/^.+?[es:]+\s+(\d+)/', $text, $matches)) {
            $result['ConfirmationNumber'] = $matches[1];
        }

        if (preg_match('/la reserva\s+(Hotel\s+.+?)\n(.+?)\s*(\+\d+)/us', $text, $matches)) {
            $result['HotelName'] = $matches[1];
            $result['Address'] = $this->normalizeText($matches[2]);
            $result['Phone'] = $matches[3];
        }

        if (preg_match('/Habitaciones totales:\s*\w+,(.+?)\n\s*\w+,(.+?)\n\s*\d+ noches\s*\n(.+?)\n+\s*(\d+)/u', $text, $matches)) {
            $result['CheckInDate'] = $this->normalizeDate(trim($matches[1]));
            $result['CheckOutDate'] = $this->normalizeDate(trim($matches[2]));
            $result['RoomType'] = $matches[3];
            $result['Rooms'] = $matches[4];
        }

        return $result;
    }

    protected function parseReservation1($text)
    {
        $result['Kind'] = 'R';

        if (preg_match('/^.+?[es:]+\s+(\d+)/', $text, $matches)) {
            $result['ConfirmationNumber'] = $matches[1];
        }

        $reg = '(?:Nombre del huésped|Guest Name):(.+?)';
        $reg .= '(?:Tipo de habitación|Room Type):(.+?)(?:Llegada|Check­in)';

        if (preg_match("/{$reg}/us", $text, $matches)) {
            $result['GuestNames'][] = trim($matches[1]);
            $result['RoomType'] = $this->normalizeText($matches[2]);
        }

        // Date
        $reg = '(?:Llegada|Check­in):\s+\w+,(.+?)(?:Salida|Check­out):\s+\w+,(.+?)(?:Número de noches|Number of Nights)';

        if (preg_match("/{$reg}/us", $text, $matches)) {
            $result['CheckInDate'] = $this->normalizeDate(trim($matches[1]));
            $result['CheckOutDate'] = $this->normalizeDate(trim($matches[2]));
        }

        // Address s ­ Ho
        if (preg_match('/(?:Detalles del hotel|Hotel Details)\s*:(.+?)\s*(\+\d+)\s+(?:Datos de la reserva|Booking Details)/us', $text, $matches)) {
            $result['HotelName'] = $result['Address'] = $this->normalizeText($matches[1]);
            $hotelName = preg_split('/[,.­]/u', $result['Address']);

            if (!empty($hotelName[0])) {
                $result['HotelName'] = $hotelName[0];
            }
            $result['Phone'] = $matches[2];
        }

        if (preg_match('/(?:Número de habitaciones|Number of Rooms)\s*:\s*(\d+)/us', $text, $matches)) {
            $result['Rooms'] = trim($matches[1]);
        }

        if (preg_match('/(?:Precio total|Total Price)\s*:\s*(.+?)\n/us', $text, $matches)) {
            $result['Total'] = preg_replace('/[^\d.,]+/', '', $matches[1]);
            $result['Currency'] = preg_replace(['/^\$$/', '/^€$/'], ['USD', 'EUR'], preg_replace('/[\d.,]+/', '', $matches[1]));
        }

        return $result;
    }

    //========================================
    // Auxiliary methods
    //========================================

    protected function normalizeDate($string)
    {
        $string = preg_replace('/\s+de\s+/', ' ', $this->normalizeText($string));
        $months['es'] = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        foreach ($months as $value) {
            $date = str_ireplace($value, ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'], $string);

            if ($date !== $string) {
                return strtotime($date);
            }
        }

        return strtotime($string);
    }

    protected function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    protected function htmlToText($string)
    {
        return preg_replace('/<[^>]+>/', "\n", html_entity_decode($string));
    }
}
