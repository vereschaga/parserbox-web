<?php

namespace AwardWallet\Engine\goldcrown\Email;

class ConfirmationText2017En extends \TAccountChecker
{
    public $mailFiles = "goldcrown/it-19151218.eml, goldcrown/it-21823508.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (empty($textBody = $parser->getPlainBody())) {
            $textBody = $this->htmlToText($parser->getHTMLBody());
        }
        $this->http->SetEmailBody($textBody);

        $its[] = $this->parseHotel($this->findCutSection($textBody, null, ['INFORMATION:']));

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "reservation",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && isset($headers['subject'])
                && stripos($headers['from'], '@cs.bestwestern.com') !== false
                && stripos($headers['subject'], 'Best Western(r) Hotels &') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'BEST WESTERN(R) HOTELS &') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'bestwestern.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
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

    protected function parseHotel($text)
    {
        $i = ['Kind' => 'R'];
        $i['ConfirmationNumber'] = $this->match('/RESERVATION NUMBER:\s*(\d+)/', $text);
        $i['GuestNames'] = $this->match('/GUEST NAME:\s*([A-z][-.\'A-z ]*[A-z])\n/', $text);

        // 3:00 p.m.
        $time = str_replace('.', '', $this->match('/CHECK IN TIME:\s*(\d+:\d+(?:\s*[ap]\.?m)?)/', $text));
        $i['CheckInDate'] = strtotime($this->match('/DATE OF ARRIVAL:\s*(\d+ \w+ \d{4})/', $text) . ', ' . $time);
        $nights = $this->match('/NUMBER OF NIGHTS:\s*(\d+)\n/', $text);
        $i['CheckOutDate'] = strtotime("+{$nights} days", $i['CheckInDate']);

        $i['HotelName'] = $this->match('#HOTEL NAME:\s*([-,&\'\/A-z\s]+)\n#', $text);
        $i['Address'] = $this->match('#ADDRESS:\s*([\w/\s,-]+)DIRECTIONS#', $text);
        $i['Phone'] = $this->match('#TELEPHONE:\s*([/\d+\s+-]+)\n#', $text);
        $i['Fax'] = $this->match('#FAX:\s*([/\d+\s+-]+)\n#', $text);
        $i['Rooms'] = (int) $this->match('/NUMBER OF ROOMS:\s*(\d+)/', $text);
        $i['Rate'] = $this->match('/ROOM RATE PER NIGHT:\s*([\w.,\s]+)\n/', $text);
        $i['RoomType'] = $this->match('/ROOM TYPE:\s*([-,.\w\s]+)\n/u', $text);
        $i['CancellationPolicy'] = $this->match('#CANCELLATION POLICY:\s*([\w.,\s-/]+)\.#u', $text);

        return $i;
    }

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

    //========================================
    // Auxiliary methods
    //========================================

    private function htmlToText($string = ''): string
    {
        $string = str_replace("\n", '', $string);
        $string = preg_replace('/<br\b[ ]*\/?>/i', "\n", $string); // only <br> tags
        $string = preg_replace('/<[A-z]+\b.*?\/?>/', '', $string); // opening tags
        $string = preg_replace('/<\/[A-z]+\b[ ]*>/', '', $string); // closing tags
        $string = htmlspecialchars_decode($string);

        return trim($string);
    }
}
