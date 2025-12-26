<?php

namespace AwardWallet\Engine\tripadvisor\Email;

class Tour extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "members@e.tripadvisor.com";
    public $reBody = [
        'en' => ['Thanks for booking on TripAdvisor', 'Your Tour/Activity Details'],
    ];
    public $reSubject = [
        'Booked! Reservation details for',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'tripadvisor.com')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'E'];
        $it['ConfNo'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),\"Confirmation Number\")]/following::text()[string-length(normalize-space(.))>3][1]", null, true, "#[\s:]*([A-Z\d\-]+)#");

        if ($this->http->XPath->query('//text()[starts-with(normalize-space(.),"Your reservation is paid and confirmed")]')->length > 0) {
            $it['Status'] = 'Confirmed';
        }
        $it['Name'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),\"Your Tour/Activity Details\")]/following::text()[string-length(normalize-space(.))>3][1]");
        $it['Address'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),\"Your Tour/Activity Details\")]/following::text()[string-length(normalize-space(.))>3][2]");
        $it['StartDate'] = strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Activity Start Date')]/following::text()[normalize-space(.)][1][contains(translate(.,'0123456789','dddddddddd'),'dddd')]"));
        $time = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Departure Time')]/following::text()[normalize-space(.)][1][contains(translate(.,'0123456789','dddddddddd'),'d:dd')]");

        if (!empty($time)) {
            $it['StartDate'] = strtotime($time, $it['StartDate']);
        }

        $it['Guests'] = $this->http->FindSingleNode("//img[contains(@src,'person')]/following::text()[normalize-space(.)][1]", null, true, "#(\d+)\s*adult#i");
        $it['EventType'] = EVENT_EVENT;
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Total Price:')]/following::text()[normalize-space(.)][1]"));

        if (empty($tot['Total'])) {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Total Price:')]"));
        }

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = preg_replace('#^\s*(USD)\s*\$\s*(.+)\s*$#', '$1 $2', $node);
        $this->http->Log($node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = str_replace(" ", "", $m['t']);
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
