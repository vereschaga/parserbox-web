<?php

namespace AwardWallet\Engine\s7\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightChange extends \TAccountChecker
{
    public $mailFiles = "s7/it-57658324.eml, s7/it-57658326.eml";
    public $from = '@s7.ru';
    public $header = 'Flight change S7';
    public $body = ['S7 Airlines уведомляет пассажиров', 'Новый вариант перелета'];
    public $lang = 'ru';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = true;
        $flight = $email->add()->flight();
        $flight->general()
            ->noConfirmation()
            ->travellers($this->http->FindNodes("//text()[starts-with(normalize-space(), 'S7 Airlines уведомляет пассажиров')]/ancestor::tr[1]/following-sibling::tr[not(contains(normalize-space(), 'отмене рейса'))]"), true);

        if (!empty($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Новый вариант перелета')]"))) {
            $flight->general()
                ->status('изменен');
        }

        $xpath = "//text()[starts-with(normalize-space(), 'Новый вариант перелета')]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $segment = $flight->addSegment();

            $depDate = $this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Новая дата и время')]/ancestor::table[1]/preceding::table[normalize-space()][1]", $root));
            $arrDate = $this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Новая дата и время')]/ancestor::table[1]/following::table[normalize-space()][1]", $root));
            $newFlight = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Новая дата и время')]/ancestor::table[1]/preceding::table[normalize-space()][2]", $root);
            $this->logger->warning($newFlight);

            if (preg_match('/^\D+\s+[(](?<depCode>[A-Z]{3})[)]\s+\D+[(]((?<arrCode>[A-Z]{3}))[)]\D+(?<flightName>[A-Z\d]{2})\s+(?<flightNumber>\d{2,4})/s', $newFlight, $m)) {
                $segment->departure()
                    ->code($m['depCode'])
                    ->date($depDate);
                $segment->arrival()
                    ->code($m['arrCode'])
                    ->date($arrDate);
                $segment->airline()
                    ->name($m['flightName'])
                    ->number($m['flightNumber']);
            }

            if (preg_match('/^(?<depName>\D+)\s(?<arrName>\D+)\s+(?<arrName2>\D+)\s+рейсами\s+(?<flightName>[A-Z\d]+)\s+(?<flightNumber>\d{2,4})\s+\/\s+(?<flightName2>[A-Z\d]+)\s+(?<flightNumber2>\d{2,4})/s', $newFlight, $m)) {
                $segment->departure()
                    ->noCode()
                    ->name($m['depName'])
                    ->date($depDate);
                $segment->arrival()
                    ->noCode()
                    ->name($m['arrName'])
                    ->noDate();
                $segment->airline()
                    ->name($m['flightName'])
                    ->number($m['flightNumber']);

                $segment = $flight->addSegment();

                $segment->departure()
                    ->noCode()
                    ->name($m['arrName'])
                    ->noDate();
                $segment->arrival()
                    ->noCode()
                    ->name($m['arrName2'])
                    ->date($arrDate);
                $segment->airline()
                    ->name($m['flightName2'])
                    ->number($m['flightNumber2']);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->from) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["subject"], $this->header) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach ($this->body as $body) {
            if (strpos($this->http->Response["body"], $body) !== false) {
                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^([\d\:]+)\s+\D+[,]\s+(\D+)\s+(\d{1,2})[,]\s+(\d{4})$#s", // 21:35 Вт, мая 26, 2020
        ];
        $out = [
            "$3 $2 $4, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            $this->logger->warning($m[1]);

            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
