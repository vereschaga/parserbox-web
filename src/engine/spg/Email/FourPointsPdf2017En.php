<?php

// bcdtravel

namespace AwardWallet\Engine\spg\Email;

class FourPointsPdf2017En extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('[\w\s]+\.pdf');

        if (empty($pdf)) {
            $this->http->Log('Pdf is not found!', LOG_LEVEL_ERROR);

            return false;
        }

        $text = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));

        if (stripos($text, 'spg.') === false || $this->stripos($text, ['Thank you for choosing the']) == false) {
            $this->http->Log('Pdf is not found!', LOG_LEVEL_ERROR);

            return false;
        }

        $its[] = $this->parseHotel($this->findCutSection($text, null, 'FOURPOINTS.COM/TAICANG'));

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "reservation",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('[\w\s]+\.pdf');
        $text = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));

        return stripos($text, 'spg.') !== false && $this->stripos($text, ['Thank you for choosing the']);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@v-it.com') !== false;
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT\n(.*?)RIGHT')</i> 0.300 sec.
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

    protected function parseHotel($text)
    {
        $i = ['Kind' => 'R'];
        $i['Status'] = $this->match('#^\s*(Confirmation)#', $text);
        $i['ReservationDate'] = strtotime($this->match('#\n\s*Date:\s*(\d+-\w+-\d+)#', $text));
        $i['ConfirmationNumber'] = $this->match('/Confirmation No:\s*(\d+)\n/', $text);
        $i['GuestNames'][] = $this->match('/Guest Name:\s*([[:alpha:]\s]+)\n/', $text);
        $i['HotelName'] = $this->match('/Thank you for choosing the\s*(.+?)\. We/', $text);
        $footer = $this->match("/{$i['HotelName']}\n.+?\n([\w.,\s-]+)\n.+?\n\s*Tel:\s*([\d\s]+)(?:Fax:\s*([\d\s]+))?/iu", $text, true);

        if (!empty($footer)) {
            $i['Address'] = $footer[0];
            $i['Phone'] = $footer[1];
            $i['Fax'] = $footer[2];
        }

        $checkInTime = $this->match('/Check in time is\s*(\d+:\d+)/', $text);
        $i['CheckInDate'] = strtotime($this->match('#Arrival Date:\s*(\d+/\d+/\d+)#', $text) . ', ' . $checkInTime, false);
        $checkOutTime = $this->match('/Check out time is\s*(\d+:\d+)/', $text);
        $i['CheckOutDate'] = strtotime($this->match('#Departure Date:\s*(\d+/\d+/\d+)#', $text) . ', ' . $checkOutTime, false);

        $i['RoomType'] = $this->match('/Room Type:\s*([A-Z]+)/', $text);
        $i['Rate'] = $this->match('/Daily Rate Per Room:\s*([A-Z\s\d.,]+)\n/', $text);
        $i['CancellationPolicy'] = $this->match('/cancellation made\s+[\w\s.,]+\./', $text);

        return $i;
    }

    //========================================
    // Auxiliary methods
    //========================================

    protected function match($pattern, $text, $allMatches = false)
    {
        if (preg_match($pattern, $text, $matches)) {
            if ($allMatches) {
                array_shift($matches);

                return array_map([$this, 'normalizeText'], $matches);
            } else {
                return $this->normalizeText(count($matches) > 1 ? $matches[1] : $matches[0]);
            }
        }
    }

    protected function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    protected function stripos($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
