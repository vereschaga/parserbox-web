<?php

namespace AwardWallet\Engine\preferred\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = ["riverstreetinn.com"];
    public $reBody = [
        'en' => ['Thank you for choosing', 'Your roomtype is'],
    ];
    public $reSubject = [
        '#Confirmation: [A-Z\d]{5,}#',
    ];
    public $subject;
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->subject = $parser->getSubject();
        $this->AssignLang();

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'riverstreetinn.com')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $flag = false;

        if (isset($headers['from'])) {
            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;

                    break;
                }
            }
        }

        if ($flag && isset($this->reSubject)) {
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
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        $text = implode("\n", $this->http->FindNodes("//text()[normalize-space(.)]"));

        $it = ['Kind' => 'R'];
        $it['ConfirmationNumber'] = $this->re('#Confirmation:\s+([A-Z\d]{5,})#', $this->subject);
        $it['HotelName'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'{$this->t('Thank you for choosing')}')]", null, true, "#Thank you for choosing\s+(.+?)\s+for your stay in#");

        $str1 = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'{$this->t('Thank you for choosing')}')]/preceding::img[1]/preceding::text()[normalize-space(.)][1]");
        $str2 = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'{$this->t('Thank you for choosing')}')]/preceding::a[1]//text()[normalize-space(.)]");

        if (empty($str1)) {
            $addr = $text;
        } else {
            $addr = str_replace($str1, "", strstr($text, $str1));
        }
        $addr = strstr($addr, $str2, true);

        if (preg_match("#^\s*(.+)\n([\(\)\d \-\+]+)\s*$#s", $addr, $m)) {
            $it['Address'] = str_replace("\n", " ", $m[1]);
            $it['Phone'] = $m[2];
        }
        $it['RoomTypeDescription'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'You have reserved')]/following::text()[normalize-space(.)][1]");
        $it['RoomType'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'Your roomtype is')]", null, true, "#Your roomtype is\s+(.+)#");
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Grand Total')]/following::text()[normalize-space(.)][1]"));

        if (!empty($tot['Total'])) {
            $it['Total'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Total Tax Amount')]/following::text()[normalize-space(.)][1]"));

        if (!empty($tot['Total'])) {
            $it['Taxes'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Room Sub-Total')]/following::text()[normalize-space(.)][1]"));

        if (!empty($tot['Total'])) {
            $it['Cost'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Check In Date')]/following::text()[normalize-space(.)][1]")));
        $node = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Our check-in time is')]/following::text()[normalize-space(.)][1]");

        if (preg_match("#^\s*(\d+:\d+\s*(?:[ap]m)?)\s*$#i", $node, $m)) {
            $it['CheckInDate'] = strtotime($m[1], $it['CheckInDate']);
        }

        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Check Out Date')]/following::text()[normalize-space(.)][1]")));
        $node = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'our check-out time is')]/following::text()[normalize-space(.)][1]");

        if (preg_match("#^\s*(\d+:\d+\s*(?:[ap]m)?)\s*$#i", $node, $m)) {
            $it['CheckOutDate'] = strtotime($m[1], $it['CheckOutDate']);
        }

        $it['CancellationPolicy'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'cancellation policy.')]/ancestor::*[1]", null, true, "#cancellation policy.\s+(.+)#");

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            //Tuesday, 05/30/2017
            '#.+\s+(\d+)\/(\d+)\/(\d+)\s*$#',
        ];
        $out = [
            '$3-$1-$2',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
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
        $node = str_replace("$", "USD", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
