<?php

namespace AwardWallet\ExtensionWorker;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger;

class ParserLogFileFormatter implements FormatterInterface
{

    private const LOG_COLORS = [
        Logger::ERROR => 'red',
        Logger::INFO => 'black', // do not change
        Logger::NOTICE => '#8A2BE2',
        Logger::DEBUG => '#686868'
    ];

    private LineFormatter $lineFormatter;

    public function __construct()
    {
        $this->lineFormatter = new LineFormatter("<span class=\"time\">%datetime%</span> %message% %context% %extra%");
    }

    public function format(array $record)
    {
        $context = $record['context'];
        $message = $record['message'];

        $logClassName = 'awlog-' . strtolower($record['level_name']);
        if (isset($context['HtmlClass'])) {
            $context['HtmlClass'] .= $context['HtmlClass'] . ' ' . $logClassName;
        } else {
            $context['HtmlClass'] = $logClassName;
        }

        $htmlEncode = isset($context['HtmlEncode']) ? $context['HtmlEncode'] : true;

        if (isset($context['Header'])) {
            $htmlEncode = false;
        }

        if($htmlEncode){
            $message = htmlspecialchars($message);
        }

        $htmlClass = isset($context['HtmlClass']) ? $context['HtmlClass'] : null;
        $pre = isset($context['pre']) ? $context['pre'] : false;
        $htmlAttributes = $htmlClass ? ' class="' . $htmlClass . '"' : '';
        $logContext = $context;
        unset($logContext['HtmlEncode'], $logContext['HtmlClass'], $logContext['pre'], $logContext['Header']);

        if (!empty($logContext)) {
            $message = $this->lineFormatter->format([
                'message' => $message,
                'context' => $logContext,
                'extra' => [],
                'datetime' => date("H:i:s"),
                "channel" => "app",
                "level_name" => $record['level_name']
            ]);
        } elseif (!isset($context['Header'])) {
            $message = '<span class="time">' . date("H:i:s") . '</span> ' . $message;
        }

        if ($pre) {
            $message = '<pre>' . $message . '</pre>';
        }

        if (isset($context['Header'])) {
            $headerLevel = $context['Header'];
            $htmlEncode = false;
            $message = "<h{$headerLevel}{$htmlAttributes}>$message</h{$headerLevel}>\n";
        } elseif (!$htmlEncode) {
            $message = "<span{$htmlAttributes}>$message</span><br/>\n";
        } else {
            $message .= "</br>";
        }

        $color = self::LOG_COLORS[$record['level']] ?? 'black';
        $message = ($color == 'black') ? $message : "<span style='color: ".$color.";'>$message</span>";

        return $message;
    }

    public function formatBatch(array $records)
    {
        $message = '';
        foreach ($records as $record) {
            $message .= $this->format($record);
        }

        return $message;
    }

}