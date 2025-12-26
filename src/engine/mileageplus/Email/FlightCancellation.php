<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightCancellation extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-134764355.eml, mileageplus/it-56023814.eml, mileageplus/it-59346821.eml";

    public static $dictionary = [
        "en" => [
            "Cancelled"    => ["Your cancellation was successfully processed", "Your canceled itinerary", "We’re sorry to let you know we’ve canceled flight"],
            "BodyText"     => ["Change fees will apply when you use the remaining future flight credit", "the award ticket you recently canceled", "We’re sorry to let you know we’ve canceled flight"],
            "Confirmation" => ["Confirmation number:", "confirmation number:", "United confirmation number:"],
        ],
    ];

    private $detects = [
        'Thank you for choosing',
        'We look forward to seeing you on board United when you resume travel',
        'We’ve canceled your flight to',
    ];

    private $from = '/[@\.]united\.com/';

    private $prov = 'United Airlines';

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $text = $this->http->FindSingleNode("//text()[{$this->contains($this->t('BodyText'))}]");

        if (!empty($text)) {
            $flight = $email->add()->flight();

            $confNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation'))}]/following::text()[normalize-space()][1]", null, true, '/^([A-Z\d]+)$/');
            $confDesc = trim($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation'))}]"), ':');
            if (empty($confNumber) && preg_match("/^\s*(Confirmation number:)\s*([A-Z\d]+)\s*$/", $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation'))}]"), $m)) {
                $confNumber = $m[2];
                $confDesc = $m[1];
            }

            $flight->general()
                ->confirmation($confNumber, $confDesc);

            $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Cancelled'))}]");

            if (!empty($status)) {
                $flight->general()
                    ->status('cancelled')
                    ->cancelled();
            }

            $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'We understand')]/preceding::text()[normalize-space()][1]");

            if (!empty($traveller)) {
                $flight->general()
                    ->traveller(trim($traveller, ','), false);
            }

            $depDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Departure date')]/following::text()[normalize-space()][1]");

            if (!empty($depDate)) {
                $segment = $flight->addSegment();

                $segment->departure()
                    ->day($this->normalizeDate($depDate))
                    ->noCode();

                $arrName = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'To:')]/following::text()[normalize-space()][1]");

                if (empty($arrName)) {
                    $arrName = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your canceled itinerary')]/following::text()[starts-with(normalize-space(), 'To:')]/following::text()[normalize-space()][1]");
                }
                $segment->arrival()
                    ->name($this->re("/^(\D+)\s\(/", $arrName))
                    ->code($this->re("/\(([A-Z]{3})\)/", $arrName))
                    ->noDate();
            }
        }

        $c = explode('\\', __CLASS__);
        $email->setType(end($c) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = empty($parser->getPlainBody()) ? $parser->getHTMLBody() : $parser->getPlainBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
                return 'starts-with(normalize-space(.),"' . $s . '")';
            }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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
        $in = [
            "#^\s?(\w+)\s(\d+)\,\s+(\d{4})$#", //April 1, 2020
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

        return strtotime($str);
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
