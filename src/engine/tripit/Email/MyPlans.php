<?php

namespace AwardWallet\Engine\tripit\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Rental;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MyPlans extends \TAccountChecker
{
    public $mailFiles = "tripit/it-1.eml, tripit/it-127069928.eml, tripit/it-130811502.eml, tripit/it-1741053.eml, tripit/it-17825279.eml, tripit/it-2.eml, tripit/it-2273728.eml, tripit/it-2307365.eml, tripit/it-2307366.eml, tripit/it-3114602.eml, tripit/it-3184160.eml, tripit/it-3226076.eml, tripit/it-3297727.eml, tripit/it-4.eml, tripit/it-49306501.eml, tripit/it-5.eml, tripit/it-51416066.eml, tripit/it-8011087.eml, tripit/it-8568834.eml";
    public $lang = 'en';
    public $anchor = 0;
    public $traveller;

    public $detectLang = [
        'en' => ['message was sent', 'Arrive'],
    ];

    public static $dictionary = [
        "en" => [
            'Arrive'   => ['Arrive', 'Check In'],
            'Pick-up'  => ['Pick-up', 'Pick-Up', 'Pick up', 'Pick Up'],
            'Drop-off' => ['Drop-off', 'Drop-Off', 'Drop off', 'Drop Off'],
        ],
    ];
    private $xpathFragmentBold = "ancestor::*[self::b or self::strong or contains(@style,'bold')]";
    private $eventName = [' Dining', ' dining', 'Restaurant', 'restaurant', ' Visit', ' visit'];

    private $patterns = [
        'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->assignLang() == true) {
            $this->traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('TripIt user,'))}]", null, true, "/{$this->opt($this->t('TripIt user,'))}\s*(\D+)\,/");

            if (empty($this->traveller)) {
                $this->traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('is going to'))}]", null, true, "/^([\D\d]+)\s{$this->opt($this->t('is going to'))}/");
            }

            $bodyHeader = implode("\n", $this->http->FindNodes("//h1[{$this->contains($this->t('from'))}] | //thead[contains(@style,'#e8e8e8') or contains(@style,'#E8E8E8')]/descendant::text()[normalize-space()]"));

            if (preg_match("/(?:^|{$this->opt($this->t('from'))}\s+)([-[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4})\s+-\s+[-[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4}[,.;:!? ]*$/mu", $bodyHeader, $m)) {
                // Aug 23, 2018 - Sep 9, 2018
                $this->anchor = strtotime($m[1]);
            } elseif (preg_match("/(?:^|{$this->opt($this->t('from'))}\s+)([-[:alpha:]]+\s+\d{1,2})\s+-\s+[-[:alpha:]]+\s+\d{1,2}\s*,\s*(\d{4})[,.;:!? ]*$/mu", $bodyHeader, $m)
                || preg_match("/(?:^|{$this->opt($this->t('from'))}\s+)([-[:alpha:]]+\s+\d{1,2})\s+-\s+\d{1,2}\s*,\s*(\d{4})[,.;:!? ]*$/mu", $bodyHeader, $m)
            ) {
                // Aug 23 - Sep 9, 2018    |    Feb 17 - 21, 2014
                $this->anchor = strtotime($m[1] . ', ' . $m[2]);
            } elseif (($fYear = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(),'All rights reserved')][1]", null, true, "/©\s*(?:\d{4}-)?(\d{4})\b/"))) {
                // © 2006-2019, Concur Technologies, Inc. All rights reserved.
                $this->anchor = strtotime($fYear . '-01-01');
            }
            // else {
            //     $this->anchor = strtotime($parser->getHeader('date'));
            // }

            $html = $this->http->Response['body'];
            $html = preg_replace('/�/iu', '', $html); // breaks xpath
            $html = preg_replace('/39\;/iu', '', $html); // breaks xpath
            $html = preg_replace('/&#0\b/iu', '', $html); // breaks xpath
            $html = preg_replace('/&#0\b/iu', '', $html); // breaks xpath
            $html = preg_replace('/﻿|(?<=;)\b39;/iu', '', $html); // just cleanup, first option is invisible symbol
            $html = str_ireplace(['&#65279;', '﻿'], '', $html); // del ZERO WIDTH NO-BREAK SPACE
            $this->http->SetEmailBody($html);

            //for it-17825279.eml
            $kostyl = "[not((descendant::text()[{$this->contains($this->t('Pick-up'))}] and preceding-sibling::tr[normalize-space()][1][{$this->contains($this->t('Pick-up'))}]) or (descendant::text()[{$this->contains($this->t('Drop-off'))}] and preceding-sibling::tr[normalize-space()][1][{$this->contains($this->t('Drop-off'))}]))]";
            //$segments = $this->http->XPath->query("//img[contains(@src, 'trip_item') or contains(@alt, 'Rail') or contains(@alt, 'Car') or contains(@alt, 'Ground Transportation') or contains(@alt, 'Meeting') or contains(@alt, 'Restaurant') or contains(@alt, 'Theatre') or contains(@src, 'lodging.png') or contains(@alt, 'Image removed by sender. Lodging') or contains(@src, 'restaurant.png') or contains(@src, 'flight.png')]/ancestor::tr[1]{$kostyl}");

            //Flight
            $nodesFlight = $this->http->XPath->query("//img[contains(@src, 'flight.png')]/ancestor::tr[1]{$kostyl}");

            if ($nodesFlight->length > 0) {
                $this->ParseFlight($email, $nodesFlight);
            }

            //Hotel
            $nodesHotel = $this->http->XPath->query("//img[contains(@src, 'lodging.png') or contains(@alt, 'Image removed by sender. Lodging')]/ancestor::tr[1]{$kostyl}");

            if ($nodesHotel->length > 0) {
                $this->ParseHotel($email, $nodesHotel);
            }

            //Car
            $nodesCar = $this->http->XPath->query("//img[contains(@alt, 'Car')]/ancestor::tr[1]{$kostyl}");

            if ($nodesCar->length > 0) {
                $this->ParseCar($email, $nodesCar);
            }

            //Event
            $nodesEvent = $this->http->XPath->query("//img[contains(@alt, 'Meeting') or contains(@alt, 'Restaurant') or contains(@alt, 'Theatre') or contains(@alt, 'Tour') or contains(@src, 'restaurant.png') or contains(@src, '_tour.png') or contains(@src, 'activity.png')]/ancestor::tr[1]{$kostyl}");

            if ($nodesEvent->length > 0) {
                $this->ParseEvent($email, $nodesEvent);
            }

            //Train
            $nodesTrain = $this->http->XPath->query("//img[contains(@alt, 'Rail') or contains(@src, 'email_icon_rail.png')]/ancestor::tr[1]{$kostyl}");

            if ($nodesTrain->length > 0) {
                $this->ParseTrain($email, $nodesTrain);
            }

            //Transfer
            $nodesTransfer = $this->http->XPath->query("//img[contains(@src, 'taxi.png')]/ancestor::tr[1]{$kostyl}");

            if ($nodesTransfer->length > 0) {
                $this->ParseTransfer($email, $nodesTransfer);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseTransfer(Email $email, \DOMNodeList $nodesTransfer): void
    {
        foreach ($nodesTransfer as $segment) {
            $nodes = $this->http->FindNodes('descendant::*[(self::p or self::pre) and normalize-space()][preceding-sibling::p[normalize-space()] or following-sibling::p[normalize-space()]]', $segment);

            if (count($nodes) === 0) {
                $nodes = $this->http->FindNodes('descendant::text()[normalize-space()]', $segment);
            }

            if (count($nodes) !== 5) {
                $this->logger->debug("other format transfer");

                return;
            } else {
                $tr = $email->add()->transfer();

                $tr->general()
                    ->noConfirmation();

                if (!empty($this->traveller)) {
                    $tr->general()
                        ->traveller($this->traveller);
                }

                $text = implode("\n", $nodes);

                $dateDep = $this->http->FindSingleNode('./preceding::td[@colspan][1]', $segment);

                $date = $this->parseDate($dateDep, $this->anchor);

                if (empty($date)) {
                    $dateDep = strtotime($dateDep, $this->anchor);
                    // no trip longer than a year
                    if ($dateDep < $this->anchor) {
                        $dateDep = strtotime('+1 year', $dateDep);
                    }
                } else {
                    $dateDep = $date;
                }

                $dateArr = $dateDep;

                $s = $tr->addSegment();

                $depName = $this->re("/Depart (.+)/u", $text);
                $arrName = $this->re("/Arrive (.+)/u", $text);

                $timeDep = $this->re("/^\s*({$this->patterns['time']})/", $text);
                $timeArr = $this->re("/\s*({$this->patterns['time']}).+\nArrive/i", $text);
                $dt1 = (!empty($dateDep)) ? strtotime($timeDep, $dateDep) : null;
                $dt2 = (!empty($dateArr)) ? strtotime($timeArr, $dateArr) : null;

                $filledDate = false;

                if ($dt1 === $dt2) {
                    $prev = $this->http->FindSingleNode("./preceding::text()[contains(translate(.,'0123456789','##########'), '#:##')][1]", $segment);
                    $foll = $this->http->FindSingleNode("./following::text()[contains(translate(.,'0123456789','##########'), '#:##')][1]", $segment);

                    if (stripos($prev, $timeDep) === 0 && stripos($foll, $timeArr) === false) {
                        $name = $this->http->FindSingleNode("./preceding::text()[contains(translate(.,'0123456789','##########'), '#:##')][1]/following::text()[normalize-space()][1]", $segment,
                            true, "/^\s*Arrive\s+(.+\([A-Z]{3}\))\s*$/");

                        if (!empty($name)) {
                            $depName = $name . ', ' . $depName;
                        }
                        $s->departure()
                            ->date($dt1);

                        $s->arrival()
                            ->noDate();
                        $filledDate = true;
                    } elseif (stripos($prev, $timeDep) === false && stripos($foll, $timeArr) === 0) {
                        $s->departure()
                            ->noDate();

                        $s->arrival()
                            ->date($dt2);
                        $filledDate = true;
                    }
                }

                if (empty($depName) && empty($arrName) && preg_match("/Arrive\s*$/", $text)) {
                    $tr->removeSegment($s);
                    $email->removeItinerary($tr);

                    return;
                }

                $s->departure()
                    ->name($depName);

                $s->arrival()
                    ->name($arrName);

                if ($filledDate !== true) {
                    $s->departure()
                        ->date($dt1);

                    $s->arrival()
                        ->date($dt2);
                }
            }
        }
    }

    public function ParseTrain(Email $email, \DOMNodeList $nodesTrain): void
    {
        foreach ($nodesTrain as $segment) {
            $t = $email->add()->train();

            $t->general()
                ->noConfirmation();

            if (!empty($this->traveller)) {
                $t->general()
                    ->traveller($this->traveller);
            }

            $s = $t->addSegment();
            $depInfo = implode("\n", $this->http->FindNodes('descendant::*[(self::p or self::pre) and normalize-space()][preceding-sibling::p[normalize-space()] or following-sibling::p[normalize-space()]]', $segment));

            if (empty($depInfo)) {
                $depInfo = implode("\n", $this->http->FindNodes('descendant::text()[normalize-space()]', $segment));
            }

            if (preg_match("/\s+Nº Billete: *(\d{10,})\s*(?:\n|$)/", $depInfo, $mat)) {
                $t->addTicketNumber($mat[1], false);
            }

            if (preg_match("/^\s*(?<time>{$this->patterns['time']})(?<timezone>[ ]+[A-Z]{3,4})?(?<service>\n+.+)?\n+Depart\s+(?<station>.{3,})/i", $depInfo, $m)
                || preg_match("/^\s*(?<time>{$this->patterns['time']})(?<timezone>[ ]+[A-Z]{3,4})?(?<service>\n+.+)?\n+(?<station>.*Station.*)/i", $depInfo, $m)
            ) {
                if (preg_match("/^\s*(?:Renfe) (\d{1,5})(?: - |$)/", $m['service'], $mat)
                    || preg_match("/\s(\d{2,5})\s*\-/us", $m['service'], $mat)) {
                    $s->extra()
                        ->number($mat[1]);
                } else {
                    $s->extra()
                        ->noNumber();
                }
                $m['station'] = preg_replace("/^\s*(.+) \\1\s*$/", '$1', $m['station']);
                $s->departure()->name(preg_replace('/^Metro\s+/i', '', $m['station']));

                $date = $this->http->FindSingleNode('./preceding::td[@colspan][1]', $segment);
                $date = $this->parseDate($date, $this->anchor);
                // no trip longer than a year
                if ($date < $this->anchor) {
                    $date = strtotime('+1 year', $date);
                }

                $time1 = $m['time'];
                $info_arr = implode("\n", $this->http->FindNodes('./following::tr[contains(.,"Arrive")][1]/descendant::*[(self::p or self::pre) and normalize-space()][preceding::p[normalize-space()] or following::p[normalize-space()]]', $segment));

                if (empty($info_arr)) {
                    $info_arr = implode("\n", $this->http->FindNodes("./descendant::text()[contains(normalize-space(), 'Arrive')]/ancestor::tr[1]/descendant::text()[normalize-space()]", $segment));
                }

                if (preg_match("/^\s*(?<time>{$this->patterns['time']})(?<timezone>[ ]+[A-Z]{3,4})?\n+Arrive\s+(?<station>.{3,})$/i", $info_arr, $m2)
                    || preg_match("/\n(?<time>{$this->patterns['time']})(?<timezone>[ ]+[A-Z]{3,4}\-*\d*)?\n+Arrive\s+(?<station>.{3,})(?:\s*\n\s*CERCANIAS\/TRAM|\n|$)/i", $depInfo, $m2)
                ) {
                    $m2['station'] = preg_replace("/^\s*(.+) \\1\s*$/", '$1', $m2['station']);
                    $s->arrival()->name($m2['station']);

                    $time2 = $m2['time'];

                    $dt1 = strtotime($time1, $date);

                    if (!empty($time2)) {
                        $dt2 = strtotime($time2, $date);

                        if ($dt2 < $dt1) {
                            $dt2 = strtotime('+1 day', $dt2);
                        }
                    } else {
                        $s->arrival()
                        ->noDate();
                    }
                }

                $s->departure()
                    ->date($dt1);

                $s->arrival()
                    ->date($dt2);
            } else {
                $email->removeItinerary($t);
            }
        }
    }

    public function ParseEvent(Email $email, \DOMNodeList $nodesEvent): void
    {
        foreach ($nodesEvent as $segment) {
            $e = $email->add()->event();

            $text = implode("\n", $this->http->FindNodes('descendant::*[(self::p or self::pre) and normalize-space()][preceding-sibling::p[normalize-space()] or following-sibling::p[normalize-space()]]', $segment));

            if (empty($text)) {
                $text = implode("\n", $this->http->FindNodes('descendant::text()[normalize-space()]', $segment));
            }

            if (preg_match("/^(.+)$/", $text)) {
                $email->removeItinerary($e);

                return;
            }

            if (!empty($this->traveller)) {
                $e->general()
                    ->traveller($this->traveller);
            }

            $e->general()
                ->noConfirmation();

            $eventName = $this->http->FindSingleNode("./descendant::text()[normalize-space()][2][$this->xpathFragmentBold]", $segment);

            if (empty($eventName)) {
                $eventName = $this->http->FindSingleNode(".//*[contains(text(),' to ')]/preceding::*[self::p or self::div][ descendant::text()[$this->xpathFragmentBold] ][1]", $segment);
            }

            if (empty($eventName)) {
                $eventName = $this->http->FindSingleNode(".//*[self::p or self::div][ descendant::text()[$this->xpathFragmentBold] ][{$this->contains($this->eventName)}]", $segment);
            }

            if (empty($eventName)) {
                $eventName = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $segment);
                $address = $this->http->FindSingleNode("./descendant::text()[normalize-space()][2]", $segment);
            }

            $e->place()
                ->name($eventName);

            $dateText = $this->http->FindSingleNode('./preceding::td[@colspan][1]/descendant::text()[normalize-space()][1]', $segment);
            $date = $this->parseDate($dateText, $this->anchor);

            if (empty($date)) {
                $date = strtotime($dateText, $this->anchor);
                // no trip longer than a year
                if ($date < $this->anchor) {
                    $date = strtotime('+1 year', $date);
                }
            }

            $time1 = '';
            $time2 = '';

            if (preg_match("/({$this->patterns['time']})\s*to\s*({$this->patterns['time']})/", $text, $m)) {
                $time1 = $m[1];
                $time2 = $m[2];
            }

            if (empty($time1) && empty($time2)) {
                $time = $this->re("/^\s*({$this->patterns['time']})/", $text);

                if (!empty($time)) {
                    $e->booked()
                        ->start(strtotime($time, $date))
                        ->noEnd();
                } else {
                    $e->booked()
                        ->noStart()
                        ->noEnd();
                }
            } else {
                $e->booked()
                    ->start(strtotime($time1, $date))
                    ->end(strtotime($time2, $date));
            }

            //Address
            $s = $this->http->FindSingleNode(".//*[contains(text(),' to ')]/preceding::*[self::p or self::div][normalize-space()][1][ not(descendant::text()[$this->xpathFragmentBold]) ]", $segment);

            if (empty($s)) {
                $str = $this->http->FindSingleNode(".//*[self::p or self::div][ descendant::text()[$this->xpathFragmentBold] ][{$this->contains($this->eventName)}]/following::*[self::p or self::div][normalize-space()][1]", $segment);

                if (preg_match("#^(.+?)[ ]+([+)(\d][-.\d)(]{5,}[\d)(])$#", $str, $m)) {
                    $e->place()
                        ->address($m[1]);

                    if (!empty(trim($m[2]))) {
                        $e->place()
                            ->phone($m[2]);
                    }
                } elseif (!empty($str) && strlen($str) > 5) {
                    $e->place()
                        ->address($str);
                } else {
                    $str = $this->http->FindSingleNode("./descendant::text()[normalize-space()][2][$this->xpathFragmentBold]/following::text()[normalize-space()][1][ancestor::a]", $segment);

                    if (!empty($str)) {
                        $e->place()
                            ->address($str);
                    }
                    $str = $this->http->FindSingleNode(".//*[self::p or self::div][ descendant::text()[$this->xpathFragmentBold] ][normalize-space()=\"" . $e->getName() . "\"]/following::*[self::p or self::div][normalize-space()][1]", $segment);

                    if (preg_match("/^\d{4,} .+$/", $str)) {
                        $e->place()
                            ->address($str);
                    }
                }
            } elseif (preg_match("#^(.+?)[ ]+([+)(\d][-.\d)(]{5,}[\d)(])$#", $s, $m)) {
                $e->place()
                    ->address($m[1]);

                if (!empty($m[2])) {
                    $e->place()
                        ->phone($m[2]);
                }
            } elseif (!empty($s) && strlen($s) > 5) {
                $e->place()
                    ->address($s);
            }

            $e->setEventType(4);

            if (empty($e->getPhone())) {
                if (!empty(trim($phone = $this->re("/\n\s*([\+\d\s\-\(\)]+)$/u", $text)))) {
                    $e->setPhone($phone);
                }
            }

            if (empty($e->getAddress()) && !empty($address)) {
                $e->setAddress($address);
            }

            if (empty($e->getAddress()) || (empty($e->getStartDate()) && empty($e->getEndDate()))) {
                $email->removeItinerary($e);
            }
        }
    }

    public function ParseHotel(Email $email, \DOMNodeList $nodesHotel): void
    {
        foreach ($nodesHotel as $segment) {
            $text = implode("\n", $this->http->FindNodes('descendant::*[(self::p or self::pre) and normalize-space()][preceding-sibling::p[normalize-space()] or following-sibling::p[normalize-space()]]', $segment));

            if (empty($text)) {
                $text = implode("\n", $this->http->FindNodes('descendant::text()[normalize-space()]', $segment));
            }

            $name = $this->re("/((?:Arrivel?|Check\s*In)\s*.+)/iu", $text);
            $name = preg_replace('/﻿/', '', $name);

            if (preg_match("/{$this->opt($this->t('Arrive'))}/ui", $name)) {
                $q = white('(?:Arrivel?|Depart|Check In|Check Out)\s*(.+)');

                if (preg_match("/$q/isu", $name, $m)) {
                    $name = $m[1];
                }
                $addr = trim($this->http->FindSingleNode('(.//a[contains(@href, "maps")])[1]', $segment));

                if (empty($addr)) {
                    // it-8011087.eml
                    // Jeju Astar Hotel 129, Seosa-ro, Jeju-si, Jeju-do, Korea Tel: 82-64-710-1100
                    $addr = trim($this->re("/[^\d\w\s]\s+([^\d\s\W].*?)\s+Tel/", $this->http->FindSingleNode('./descendant::text()[normalize-space()][last()][not(ancestor-or-self::*[contains(@style,"bold;")])]', $segment)));
                }

                if (empty($addr) && $name) {
                    $addr = $this->re("/{$name}\s*(.{8,}?)\s*(?:Phone|[\d-]{8,})/", $text);
                }

                if (empty($addr) && $name) {
                    $addr = $this->re("/{$name}\s*(.{8,}?)\s*Check\-in\s*time:/", $text);
                }

                if (empty($addr)) {
                    $addr = $this->re("/(.{8,}?)\s+Check-in\s*time:/", $text);
                }

                if (empty($addr)) {
                    // Vilbeler Str. 2, 60313 Frankfurt am Main, Germania
                    // Puntarenas Province, Quepos, Costa Rica
                    // Jalan Teluk Berembang, Laguna Bintan, Lagoi 29155, Bintan Resort Lagoi, Bintan Island, Indonesia
                    // 2/59 O'Sullivan Road, Rose Bay, NSW 2029, Australia
                    // Hohenbuehlstrasse 10, Opfikon 8152, CH
                    $keywords = [' Str.', ' Province', ' Island', ' Road', 'Opfikon'];
                    $addr = $this->http->FindSingleNode('descendant::text()[normalize-space()][last()][not(ancestor-or-self::*[contains(@style,"bold;")])][contains(.,",")]', $segment, true, "/^.*{$this->opt($keywords)}.*$/");

                    if (empty($addr)) {
                        $addr = $this->http->FindSingleNode('descendant::text()[normalize-space()][last()][not(ancestor-or-self::*[contains(@style,"bold;")])][contains(.,",")]', $segment, true, "/^\d{4,} .+$/");
                    }

                    if (empty($addr)) {
                        $addr = $this->http->FindSingleNode('descendant::text()[normalize-space()][last()][not(ancestor-or-self::*[contains(@style,"bold;")])][starts-with(normalize-space(),"Stand 2 ")]', $segment);
                    }
                }

                $name = preg_replace('/as your resort destination\s*/', '', $name);

                $h = $email->add()->hotel();

                if (!empty($this->traveller)) {
                    $h->general()
                        ->traveller($this->traveller);
                }

                $h->general()
                    ->noConfirmation();

                $h->hotel()
                    ->name($name);

                if (!empty($addr)) {
                    $h->hotel()
                        ->address($addr);
                } else {
                    $h->hotel()
                        ->noAddress();
                }

                $dateCheckIn = $this->getDate($segment);

                if (!empty($dateCheckIn)) {
                    $h->booked()
                        ->checkIn($dateCheckIn);
                }

                //Phone
                $s = implode("\n", $this->http->FindNodes('descendant::text()[normalize-space()]', $segment));
                $x = orval(
                    re_white('Phone: ([(\d) +-]+)', $s),
                    re_white('Tel: ([(\d) +-]+)', $s),
                    re_white('(?:^|\n)([+)(\d][- \d)(]{5,}[\d)(])(?:\n|$)', $s),
                    re_white('Tel - ([(\d) +-]+)', $s)
                );
                $phone = nice($x);

                if (!empty($phone)) {
                    $h->hotel()
                        ->phone($phone);
                }

                //Fax
                $s = implode("\n", $this->http->FindNodes('descendant::text()[normalize-space()]', $segment));
                $x = re_white('Fax: ([(\d) +-]+)', $s);
                $fax = nice($x);

                if (!empty($fax)) {
                    $h->hotel()
                        ->fax($fax);
                }

                $node = $this->http->FindSingleNode('.//pre[1]', $segment);
                $node = preg_replace("#Check-(?:out|in) time:\s*\d+:\d+\s+#", '', $node);

                if (preg_match('#(.+?)(?::|,|(?<=Room) with )\s*(.+)#', $node, $ms)) {
                    $roomType = preg_match('#bed|room|double|standard#i', $ms[1], $_) ? $ms[1] : '';
                    $roomTypeDescription = strlen($ms[2]) > 2000
                    || stripos($h->getAddress(), $ms[2]) !== false ? null : $ms[2];

                    if (!empty($roomType) || !empty($roomTypeDescription)) {
                        $room = $h->addRoom();
                    }

                    if (!empty($roomType)) {
                        $room->setType($roomType);
                    }

                    if (!empty($roomTypeDescription)) {
                        $room->setDescription($roomTypeDescription);
                    }
                }

                if (!empty($h->getHotelName())) {
                    $segmentCheckOut = $this->http->XPath->query("//img[contains(@src, 'lodging.png') or contains(@alt, 'Image removed by sender. Lodging')]/ancestor::tr[1]/descendant::*[not(.//tr) and contains(normalize-space(), 'Depart') and {$this->contains($h->getHotelName())}]/ancestor::tr[1]");

                    if ($segmentCheckOut->length === 0) {
                        $segmentCheckOut = $this->http->XPath->query("//img[contains(@src, 'lodging.png') or contains(@alt, 'Image removed by sender. Lodging')]/ancestor::tr[1]/descendant::*[not(.//tr) and contains(normalize-space(), 'Check Out') and {$this->contains($h->getHotelName())}]/ancestor::tr[1]");
                    }

                    foreach ($segmentCheckOut as $seg) {
                        $dateCheckOut = $this->getDate($seg);

                        if ($dateCheckOut === 'none') {
                            $email->removeItinerary($h);
                        }

                        if (!empty($dateCheckOut)) {
                            $h->booked()->checkOut($dateCheckOut);
                        }
                    }
                }

                if (empty($h->getCheckInDate()) && empty($h->getCheckOutDate())) {
                    $email->removeItinerary($h);
                }
            }
        }
    }

    public function ParseCar(Email $email, \DOMNodeList $nodesCar): void
    {
        $carStack = [];

        foreach ($nodesCar as $segment) {
            // TODO: uncomment below

            // $info = implode("\n", $this->http->FindNodes('descendant::*[(self::p or self::pre) and normalize-space()][preceding-sibling::p[normalize-space()] or following-sibling::p[normalize-space()]]', $segment));
            // if ( empty($info) ) {
            $info = implode("\n", $this->http->FindNodes('descendant::text()', $segment));
            // }

            if (preg_match("/^[ ]*Pick[- ]*up/im", $info) > 0) {
                $r = $email->add()->rental();

                if (!empty($this->traveller)) {
                    $r->general()
                        ->traveller($this->traveller);
                }

                $r->general()
                    ->noConfirmation();

                $this->ParseCarSegment($r, $info, 'pickup');
                $r->pickup()
                    ->date($this->getDate($segment));

                $carStack[] = $r;
            }

            if (count($carStack) && preg_match("/^[ ]*Drop[- ]*off/im", $info) > 0) {
                $r = array_shift($carStack);
                $this->ParseCarSegment($r, $info, 'dropoff');
                $dateDropoff = $this->getDate($segment);

                if ($dateDropoff === 'none') {
                    $r->dropoff()->noDate();
                } else {
                    $r->dropoff()->date($dateDropoff);
                }
            }

            if (isset($r) && $r->getNoDropOffLocation() === true && $r->getNoPickupLocation() === true) {
                $email->removeItinerary($r);
            }
        }
    }

    public function ParseCarSegment(Rental $r, string $info, string $action): void
    {
        if ($action === 'dropoff' && !empty($r->getCompany())
            && preg_match("/\s*Drop Off\s+{$r->getCompany()}(?:\s+Car Rental)?\s*$/i", $info)
        ) {
            /* Drop Off Thrifty Car Rental Car Rental */
            $r->dropoff()->noLocation();

            return;
        }

        if (preg_match("/(?:{$this->addSpacesWord('Pick')}(?:-| ){$this->addSpacesWord('up')}|{$this->addSpacesWord('Drop')}(?:-| ){$this->addSpacesWord('off')})(?P<RentalCompany>\w.+?)(?:\s*{$this->addSpacesWord('Car Rental')})?\s*(?:\s*Pick-up\s*|\s*Drop-off):\s*\d+:\d+[^\n]+?[ ]*\n+[ ]*(?<location>.{3,}?)\n\s*(?<phone>[(\d) -]{6,})?,?(?<hours>[ ]+[[:alpha:]]+[ ]+-[ ]+[[:alpha:]]+ .+)?/ius", $info, $m)) {
            $this->logger->debug('Segment-1');

            if ($action == 'pickup') {
                $r->setCompany(str_replace("\n", "", $m['RentalCompany']));

                if (preg_match("/\d{1,2}:\d{2}/", $m['location'])) {
                    $r->pickup()->noLocation();
                } else {
                    $r->pickup()->location($m['location']);
                }

                if (!empty($m['phone'])) {
                    $r->pickup()
                        ->phone($m['phone']);
                }

                if (!empty($m['hours'])) {
                    $r->pickup()
                        ->openingHours($m['hours']);
                }
            }

            if ($action == 'dropoff') {
                if (preg_match("/\d{1,2}:\d{2}/", $m['location'])) {
                    $r->dropoff()->noLocation();
                } else {
                    $r->dropoff()->location($m['location']);
                }

                if (!empty($m['phone'])) {
                    $r->dropoff()
                        ->phone($m['phone']);
                }

                if (!empty($m['hours'])) {
                    $r->dropoff()
                        ->openingHours($m['hours']);
                }
            }
        } elseif (preg_match("/(?:Pick-up|Drop-off)(?<RentalCompany> \w.+?)\s*\n\s*(?:\s*Car Rental)?\s*(?:\s*Pick-up\s*|\s*Drop-off)\:\s*\d+:\d+\s*a?p?m?\s*\n\s*(?<location>.+)\s*(?<phone>[(\d) -]{6,})?,?(?<hours> .+)?/ius", $info, $m)) {
            $this->logger->debug('Segment-2');

            if ($action == 'pickup') {
                $r->setCompany($m['RentalCompany']);
                $r->pickup()
                    ->location($m['location']);

                if (isset($m['phone'])) {
                    $r->pickup()
                        ->phone($m['phone']);
                }

                if (isset($m['hours'])) {
                    $r->pickup()
                        ->openingHours($m['hours']);
                }
            }

            if ($action == 'dropoff') {
                $r->dropoff()
                    ->location($m['location']);

                if (isset($m['phone'])) {
                    $r->dropoff()
                        ->phone($m['phone']);
                }

                if (isset($m['hours'])) {
                    $r->dropoff()
                        ->openingHours($m['hours']);
                }
            }
        } elseif (preg_match("/(?:Pick(?:-| )up|Drop(?:-| )off)(?P<RentalCompany> \w.+?)(?:\s*Car Rental)?\s*(?:\s*Pick-up\s*|\s*Drop-off)\:\s*\d+:\d+[^\n]+?\s*\n\s*(?<phone>[(\d) -]{6,})?,?(?<hours> .+)?/ius", $info, $m)) {
            $this->logger->debug('Segment-3');

            if ($action == 'pickup') {
                $r->setCompany($m['RentalCompany']);
                $r->pickup()
                    ->noLocation();

                if (isset($m['phone'])) {
                    $r->pickup()
                        ->phone($m['phone']);
                }

                if (isset($m['hours'])) {
                    $r->pickup()
                        ->openingHours($m['hours']);
                }
            }

            if ($action == 'dropoff') {
                $r->dropoff()
                    ->noLocation();

                if (isset($m['phone'])) {
                    $r->dropoff()
                        ->phone($m['phone']);
                }

                if (isset($m['hours'])) {
                    $r->dropoff()
                        ->openingHours($m['hours']);
                }
            }
        } elseif (preg_match("/(?:Pick-up|Drop-off)(?<RentalCompany> \w.+?)\s*\n\s*(?:\s*Car Rental)?\s*\n\s*(?<location>.+)\s(?<phone>[(\d)\-\s]{6,})?\,(?<hours> .+)?/ius", $info, $m)) {
            $this->logger->debug('Segment-4');

            if ($action == 'pickup') {
                $r->setCompany($m['RentalCompany']);
                $r->pickup()
                    ->location($m['location']);

                if (isset($m['phone'])) {
                    $r->pickup()
                        ->phone($m['phone']);
                }

                if (isset($m['hours'])) {
                    $r->pickup()
                        ->openingHours($m['hours']);
                }
            }

            if ($action == 'dropoff') {
                $r->dropoff()
                    ->location($m['location']);

                if (isset($m['phone'])) {
                    $r->dropoff()
                        ->phone($m['phone']);
                }

                if (isset($m['hours'])) {
                    $r->dropoff()
                        ->openingHours($m['hours']);
                }
            }
        } elseif (preg_match("/(?:Pick(?:-| )up|Drop(?:-| )off)(?P<RentalCompany> \w.+?)(?:\s*Car Rental)?\s*(?:\s*Pick-up\s*|\s*Drop-off)\:\s*\d+:\d+/ius", $info, $m)) {
            $this->logger->debug('Segment-5');

            if ($action == 'pickup') {
                $r->setCompany($m['RentalCompany']);
                $r->pickup()
                    ->noLocation();
            }

            if ($action == 'dropoff') {
                $r->dropoff()
                    ->noLocation();
            }
        } elseif (preg_match("/(?:Pick-up|Drop-off)(?<RentalCompany> \w.+?)\s*\n\s*(?:\s*Car Rental)?\s*\n\s*(?<location>.+)/ius", $info, $m)) {
            $this->logger->debug('Segment-4');

            if ($action == 'pickup') {
                $r->setCompany($m['RentalCompany']);
                $r->pickup()
                    ->location($m['location']);
            }

            if ($action == 'dropoff') {
                $r->dropoff()
                    ->location($m['location']);
            }
        }
    }

    public function ParseFlight(Email $email, \DOMNodeList $nodesFlight): void
    {
        $f = $email->add()->flight();

        if (!empty($this->traveller)) {
            $f->general()
                ->traveller($this->traveller, true);
        }
        $f->general()
            ->noConfirmation();

        foreach ($nodesFlight as $segment) {
            $text = implode("\n", $this->http->FindNodes('descendant::*[(self::p or self::pre) and normalize-space()][preceding-sibling::p[normalize-space()] or following-sibling::p[normalize-space()]]', $segment));

            if (empty($text)) {
                $text = implode("\n", $this->http->FindNodes('descendant::text()[normalize-space()]', $segment));
            }

            $s = $f->addSegment();

            if (preg_match('/(?:^|\n)(?<airportDep>.{3,})(?:[ ]+to[ ]+|\s*-\s*|\s+[→]\s*)(?<airportArr>.{3,})\n+(?<airline>.*?)[ ]*(?<flightNumber>\d+)(?:\s+\(.*?\))?(?:,\s*(?i)Terminal\s+(\w+))?/u', $text, $m)) {
                if (preg_match('/^[A-Z]{3}$/', $m['airportDep'])) {
                    $s->departure()->code($m['airportDep']);
                } else {
                    $s->departure()->name($m['airportDep'])->noCode();
                }

                if (preg_match('/^[A-Z]{3}$/', $m['airportArr'])) {
                    $s->arrival()->code($m['airportArr']);
                } else {
                    $s->arrival()->name($m['airportArr'])->noCode();
                }

                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber']);

                if (!empty($m[5])) {
                    $s->departure()->terminal($m[5]);
                }

                //Dep|Arr Date
                $xpathFragment1 = 'following-sibling::tr[ normalize-space(.) and ./*[2] ][1]';

                $dateDep = $this->http->FindSingleNode('./preceding::td[@colspan][1]', $segment);
                $dateArr = $this->http->FindSingleNode('./descendant-or-self::tr[contains(.,"Arrive")]/preceding::td[@colspan][1]', $segment); // it-8568834.eml

                if (!$dateArr) {
                    $dateArr = $this->http->FindSingleNode("./{$xpathFragment1}/preceding::td[@colspan][1]", $segment); // it-2.eml
                }

                $date = $this->parseDate($dateDep, $this->anchor);

                if (empty($date)) {
                    $dateDep = strtotime($dateDep, $this->anchor);
                // no trip longer than a year
                } else {
                    $dateDep = $date;
                }

                if ($dateDep < $this->anchor) {
                    $dateDep = strtotime('+1 year', $dateDep);
                }

                $date = $this->parseDate($dateArr, $this->anchor);

                if (empty($date)) {
                    $dateArr = strtotime($dateArr, $this->anchor);
                // no trip longer than a year
                } else {
                    $dateArr = $date;
                }

                if ($dateArr < $this->anchor) {
                    $dateArr = strtotime('+1 year', $dateArr);
                }

                $timeDep = $this->re("/^\s*({$this->patterns['time']})/", $text);

                if (!$info_arr = $this->http->FindSingleNode('.//text()[contains(., "Arrive")]/preceding::text()[normalize-space(.)][1]', $segment)) {
                    $info_arr = $this->http->FindSingleNode('./following-sibling::tr[contains(., "Arrive")][1]', $segment);
                }
                $timeArr = uberTime($info_arr, 1);

                $dt1 = strtotime($timeDep, $dateDep);
                $dt2 = strtotime($timeArr, $dateArr);

                $s->departure()
                    ->date($dt1);
                $s->arrival()
                    ->date($dt2);

                $xpathFragment2 = 'descendant::text()[contains(.,"Arrive")]/following::text()[normalize-space(.)][1][contains(.,"Terminal")]';

                $terminalArr = $this->http->FindSingleNode('./' . $xpathFragment2, $segment, true, '/Terminal\s+(\w+)/');

                if ($terminalArr === null) {
                    $terminalArr = $this->http->FindSingleNode("./{$xpathFragment1}/{$xpathFragment2}", $segment, true, '/Terminal\s+(\w+)/');
                }

                if ($terminalArr !== null) {
                    $s->arrival()
                        ->terminal($terminalArr);
                }
            }
        }
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Check out my travel plans on TripIt for') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->assignLang() == true) {
            // Detect Provider
            $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Use TripIt on your smartphone") or contains(normalize-space(.),"All rights reserved. TripIt") or contains(.,"@tripit.com")]')->length === 0;
            $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.tripit.com") or contains(@href,"//help.tripit.com")]')->length === 0;
            $condition3 = $this->http->XPath->query('//img[contains(@src,".tripit.com/") and ( contains(@src,"/itinerary_email_icon_flight.") or contains(@src,"/itinerary_email_icon_lodging.") or contains(@src,"/itinerary_email_icon_car."))]')->length === 0;

            if ($condition1 && $condition2 && $condition3) {
                return false;
            }

            // Detect Format
            $phrases = [
                'Check out all the details in the TripIt travel itinerary below or online at',
                'I use TripIt to organize and share travel plans',
                'who provided your email to TripIt for purposes of sending this',
                'Check out my travel plans on TripIt',
                'Check-Out:',
            ];

            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@tripit.com') !== false;
    }

    private function parseDate($dateText, $anchor)
    {
        // Fri Sep 24    |    Fri, Sep 24
        // Fri 24 Sep    |    Fri, 24 Sep
        $dateValid = preg_match('/^\s*(?<wday>[[:alpha:]]{2,})[,\s]+(?<month>[[:alpha:]]{3,})\s*(?<day>\d{1,2})$/u', $dateText, $m) > 0
            || preg_match('/^\s*(?<wday>[[:alpha:]]{2,})[,\s]+(?<day>\d{1,2})\.?\s+(?<month>[[:alpha:]]{3,})$/u', $dateText, $m) > 0;

        if (!$dateValid) {
            return null;
        }

        if (($monthEn = MonthTranslate::translate($m['month'], $this->lang))) {
            $m['month'] = $monthEn;
        }

        if (($wdayEn = WeekTranslate::translate($m['wday'], $this->lang, true))) {
            $m['wday'] = $wdayEn;
        }

        $date = $m['day'] . ' ' . $m['month'] . ' ' . date('Y', $anchor);
        $weeknum = WeekTranslate::number1($m['wday']);

        return EmailDateHelper::parseDateUsingWeekDay($date, $weeknum);
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function getDate($segment)
    {
        $dateText = $this->http->FindSingleNode('./preceding::td[@colspan][1]/descendant::text()[normalize-space()][1]', $segment);

        if (stripos($dateText, 'Undated Items') === 0) {
            return 'none';
        }
        $date = $this->parseDate($dateText, $this->anchor);

        if (empty($date) && preg_match('/\w+\.[ ]+(\d{1,2})\.[ ]+(\w+)\./', $dateText, $m)) {
            $date = strtotime($m[1] . ' ' . $m[2], $this->anchor);
        }

        if (!empty($date)) {
            //$date = strtotime($date, $this->anchor);
            // no trip longer than a year
            if ($date < $this->anchor) {
                $date = strtotime('+1 year', $date);
            }
        }

        $text = implode("\n", $this->http->FindNodes('./descendant::text()', $segment));

        $time = $this->re("/^\s*({$this->patterns['time']})/i", $text);

        if (empty($time) && !preg_match("/(^|\D)\d{1,2}[\W]{1,4}\d{2}(?:\D|$)/", $text)) {
            $dt = $date;
        } else {
            $dt = strtotime($time, $date);
        }

        return $dt;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//*[{$this->contains($this->t($word))}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function addSpacesWord($text)
    {
        return preg_replace("#(\w)#u", '$1\s*', $text);
    }
}
