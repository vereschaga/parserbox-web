<?php

namespace AwardWallet\Engine\tripadvisor\Email;

use AwardWallet\Engine\MonthTranslate;

// parsers with similar formats: viator/YourViatorBooking

class Event extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "Members@e.tripadvisor.com";
    public $reBody = [
        'en' => ['Thanks for booking on TripAdvisor', 'Please note this email is not your voucher'],
        'de' => ['Vielen Dank für Ihre Buchung auf TripAdvisor', 'Diese E-Mail ist nicht Ihr Voucher'],
    ];
    public $reSubject = [
        'Your TripAdvisor Booking',
        'de' => 'Ihre TripAdvisor-Buchung',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            //			'Booking Reference' => '',
            //			'Confirmation Number' => '',
            //			'Your reservation is confirmed' => '',
            //			'Total:' => '',
            //			'adult' => '',
        ],
        'de' => [
            'Booking Reference'             => 'Buchungsnummer',
            'Confirmation Number'           => 'Bestätigungsnummer',
            'Your reservation is confirmed' => 'Ihre Reservierung wurde bestätigt',
            'Total:'                        => 'Gesamtsumme:',
            'adult'                         => 'Erwachsene',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();
        $class = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($class) . ucfirst($this->lang),
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
        $it['ConfNo'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking Reference")) . "]/following::text()[string-length(normalize-space(.))>3][1]", null, true, "#[\s:]*([A-Z\d\-]+)#");
        $it['TripNumber'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Confirmation Number")) . "]/following::text()[string-length(normalize-space(.))>3][1]", null, true, "#[\s:]*([A-Z\d]+)#");

        if ($this->http->XPath->query("//text()[" . $this->starts($this->t("Your reservation is confirmed")) . "]")->length > 0) {
            $it['Status'] = 'Confirmed';
        }
        $it['Name'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Confirmation Number")) . "]/following::text()[string-length(normalize-space(.))>3][2]", null, true, "#^.+?:\s+(.+)#");

        if (empty($it['Name'])) {
            $it['Name'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Confirmation Number")) . "]/following::text()[string-length(normalize-space(.))>3][2]");
        }
        $it['Address'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Confirmation Number")) . "]/following::text()[string-length(normalize-space(.))>3][2]", null, true, "#^(.+?):#");

        if (empty($it['Address'])) {
            $it['Address'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Confirmation Number")) . "]/following::text()[string-length(normalize-space(.))>3][2]");
        }
        $it['StartDate'] = strtotime($this->http->FindSingleNode("//img[contains(@src,'time')]/following::text()[normalize-space(.)][1][contains(translate(.,'0123456789','dddddddddd'),'dddd')]"));

        if (empty($it['StartDate'])) {
            $it['StartDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//img[contains(@src,'date')]/following::text()[normalize-space(.)][1][contains(translate(.,'0123456789','dddddddddd'),'dddd')]")));
        }
        $it['Guests'] = $this->http->FindSingleNode("//img[contains(@src,'riders') or contains(@src, 'guests')]/following::text()[normalize-space(.)][1]", null, true, "#(\d+)\s*" . $this->t('adult') . "#i");
        $it['EventType'] = EVENT_SHOW;
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Total:")) . "]/following::text()[normalize-space(.)][1]"));

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
        $node = preg_replace('#^\s*(EUR)\s*\€\s*(.+)\s*$#', '$1 $2', $node);
        //		$this->http->Log($node);
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

    private function normalizeDate($str)
    {
        //		$this->http->log($str);
        $in = [
            "#^[^\s\d]+,\s*([^\s\d]+)\s+(\d+),\s*(\d{4})$#", //Do, Jun 28, 2018
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
