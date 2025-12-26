<?php

namespace AwardWallet\Engine\marriott\Email;

class It3053383 extends \TAccountCheckerExtended
{
    public $mailFiles = "marriott/it-3053383.eml";

    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@]marriott|courtyard-res\.com[>\s]+wrote:|From:\s*reservations@springhillsuites\-res.com|MARRIOTT INTERNATIONAL#i', 'us', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@]marriott#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@]marriott#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "28.08.2015, 15:47";
    public $crDate = "";
    public $xPath = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return clear("#\s+#", re("#预订确认\s+(\d+)#i"));
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return node("//img[contains(@src, 'ico_map') or contains(@alt, 'Hotel address')]/ancestor::table[1]/preceding-sibling::table[1]");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $data = preg_replace("#^.*(\d{4})\D+(\d+)\D+(\d+).*$#", "\\3.\\2.\\1", re("#\n\s*登记入住日期\s+([^\n]+)#")) . ' ' . re("#(\d+:\d+)#", re("#\n\s*登记入住时间\s+([^\n]+)#"));

                        return strtotime($data);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $data = preg_replace("#^.*(\d{4})\D+(\d+)\D+(\d+).*$#", "\\3.\\2.\\1", re("#\n\s*退房日期\s+([^\n]+)#")) . ' ' . re("#(\d+:\d+)#", re("#\n\s*退房时间\s+([^\n]+)#"));

                        return strtotime($data);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = node("//img[contains(@src, 'ico_map') or contains(@alt, 'Hotel address')]/ancestor::td[1]/following-sibling::td[1]");
                        $addr = preg_replace('/\[\[\[.*\]\]\]/', '', $addr);

                        return nice($addr);
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return node("//img[contains(@src, 'ico_phone') or contains(@alt, 'Telephone number')]/ancestor::td[1]/following-sibling::td[1]");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [beautifulName(re("#\n\s*关于 先生\s+([^\n]+)#"))];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#每房宾客人数\s*(\d+)#i");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*客房数量\s+(\d+)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $policy = trim(clear("#房价及取消详情#i", glue(nodes("//*[contains(text(), '房价及取消详情')]/ancestor::table[contains(., '房价保证限制')][1]"))));
                        $policy = preg_replace('/a€?/iu', '*', $policy);

                        return $policy;
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $type = node("//*[normalize-space(text())='客房类型']/ancestor::table[1]/following-sibling::*[1]");
                        $type = explode(',', $type);

                        return [
                            'RoomType'            => nice(implode(',', array_slice($type, 0, 2))),
                            'RoomTypeDescription' => nice(implode(',', array_slice($type, 2))),
                        ];
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(node("//*[contains(text(), '预估政府税款和费用')]/following-sibling::*[1]"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(node("//*[contains(text(), '总住宿费用') and contains(text(), '（所有客房）')]/following-sibling::*[1]"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(node("//*[contains(text(), '总住宿费用') and contains(text(), '（所有客房）')]/following-sibling::*[1]"));
                    },
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
        return ["zh"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
