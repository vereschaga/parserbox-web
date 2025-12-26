<?php

namespace AwardWallet\Engine\rovia\Email;

// TODO: realize parsing all pdf-attachments from it-15112450.eml

class It1805706 extends \TAccountCheckerExtended
{
    public $mailFiles = "rovia/it-1805706.eml, rovia/it-15112450.eml";

    public $reFrom = "#rovia#i";
    public $reProvider = "#@rovia\.#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?rovia#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "Sent by Rovia Holding";
    public $reHtmlRange = "";
    public $xPath = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    private $xpathFragment1 = './following::*[position()<15]';

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$this->setDocument('application/pdf', 'complex')];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#e-ticket\s*number\s*[:]?\s*(\w+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $traveler = node("//*[contains(text(), 'Traveler')]/following::p[1]");

                        return $traveler ? [$traveler] : null;
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $s = 'Date of Issue:';
                        $x = node("//*[contains(text(), '$s')]");
                        $x = re("#$s\s*(.+)#i", $x);

                        return totime(uberDateTime($x));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return $this->http->XPath->query('/descendant::text()[contains(normalize-space(.),"Flight No:")]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $s = 'Flight No:';
                            $x = $this->http->FindSingleNode('./ancestor::*[1]', $node);
                            $x = re("#$s\s*(.+)#i", $x);

                            return $x;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $variants = ['Depart :', 'Departure :'];
                            $rule = $this->contains($variants);
                            $x = node($this->xpathFragment1 . "[{$rule}]", $node);
                            $x = re("#{$this->opt($variants)}\s*(.+)#i", $x);
                            $x = re('#[(](\w+)[)]#', $x);

                            return $x;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $variants = ['Depart :', 'Departure :'];
                            $rule = $this->contains($variants);
                            $date = node($this->xpathFragment1 . "[{$rule}]/following-sibling::p[1]", $node);
                            $date = re("#Date :\s*(.+)#i", $date);
                            $time = node($this->xpathFragment1 . "[{$rule}]/following-sibling::p[2]", $node);
                            $time = re("#Time :\s*(.+)#i", $time);

                            $fmt = 'd M Y, Hi';
                            $dt = \DateTime::createFromFormat($fmt, "$date, $time");

                            return $dt ? $dt->getTimestamp() : null;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $s = 'Arrive :';
                            $x = node($this->xpathFragment1 . "[{$this->contains($s)}]", $node);
                            $x = re("#$s\s*(.+)#i", $x);
                            $x = re('#[(](\w+)[)]#', $x);

                            return $x;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $s = 'Arrive :';
                            $date = node($this->xpathFragment1 . "[{$this->contains($s)}]/following-sibling::p[1]", $node);
                            $date = re("#Date :\s*(.+)#i", $date);
                            $time = node($this->xpathFragment1 . "[{$this->contains($s)}]/following-sibling::p[2]", $node);
                            $time = re("#Time :\s*(.+)#i", $time);

                            $fmt = 'd M Y, Hi';
                            $dt = \DateTime::createFromFormat($fmt, "$date, $time");

                            return $dt ? $dt->getTimestamp() : null;
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            $s = 'Airline :';
                            $x = node("./preceding::*[position()<5][{$this->contains($s)}]", $node);
                            $x = re("#$s\s*(.+)#i", $x);
                            $x = re('#[(](\w+)[)]#', $x);

                            return $x;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $s = 'Class :';
                            $x = node($this->xpathFragment1 . "[{$this->contains($s)}]", $node);
                            $x = re("#$s\s*(.+)#i", $x);

                            return $x;
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            $s = 'Meal Request :';
                            $x = node($this->xpathFragment1 . "[{$this->contains($s)}]", $node);
                            $x = re("#$s\s*(.+)#i", $x);

                            return $x;
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
