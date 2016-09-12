<?php

namespace Skyads\Logger;

use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\LogstashFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SlackHandler;

class Logger
{
    const CHANNEL_MO = 'mo';
    const CHANNEL_MO_ERROR = 'mo_error';
    const CHANNEL_MT_SUCCESS = 'mt_success';
    const CHANNEL_MT_ERROR = 'mt_error';
    const CHANNEL_SLACK = 'slack';

    public static $CHANNELS = [
        self::CHANNEL_MO => [
            'file' => '',
            'level' => \Monolog\Logger::INFO
        ],
        self::CHANNEL_MO_ERROR => [
            'file' => '',
            'level' => \Monolog\Logger::ALERT
        ],
        self::CHANNEL_MT_SUCCESS => [
            'file' => '',
            'level' => \Monolog\Logger::INFO
        ],
        self::CHANNEL_MT_ERROR => [
            'file' => '',
            'level' => \Monolog\Logger::ALERT
        ],
    ];

    private $rootLogDir;
    private $slackToken;
    private $slackChannel;
    private $slackUsername;

    public function __construct($rootLogDir, $slackToken, $slackChannel, $slackUsername)
    {
        $this->rootLogDir = $rootLogDir;
        $this->slackToken = $slackToken;
        $this->slackChannel = $slackChannel;
        $this->slackUsername = $slackUsername;

        self::$CHANNELS[self::CHANNEL_MO]['file'] = $rootLogDir.'/mo/mo.log';
        self::$CHANNELS[self::CHANNEL_MO_ERROR]['file'] = $rootLogDir.'/mo/mo_error.log';
        self::$CHANNELS[self::CHANNEL_MT_SUCCESS]['file'] = $rootLogDir.'/mt/mt_success.log';
        self::$CHANNELS[self::CHANNEL_MT_ERROR]['file'] = $rootLogDir.'/mt/mt_error.log';
    }

    public function logMo($message, $data = [], $isNoticeSlack = false)
    {
        return $this->log(self::CHANNEL_MO, 'info', $message, $data, $isNoticeSlack);
    }

    public function logMoError($message, $data = [], $isNoticeSlack = false)
    {
        return $this->log(self::CHANNEL_MO_ERROR, 'alert', $message, $data, $isNoticeSlack);
    }

    public function logMtSuccess($message, $data = [], $isNoticeSlack = false)
    {
        return $this->log(self::CHANNEL_MT_SUCCESS, 'info', $message, $data, $isNoticeSlack);
    }

    public function logMtError($message, $data = [], $isNoticeSlack = false)
    {
        return $this->log(self::CHANNEL_MT_ERROR, 'alert', $message, $data, $isNoticeSlack);
    }

    public function noticeSlack($message, $data = [])
    {
        /** @var \Monolog\Logger $logger */
        $logger = $this->getSlackLogger('slack');
        $logger->alert($message, $data);

        return $this;
    }

    private function log($channel, $level, $message, $data = [], $isNoticeSlack = false)
    {
        /** @var \Monolog\Logger $logger */
        $logger = $this->getLogger($channel);
        $logger->$level($message, $data);

        if ($isNoticeSlack) {
            $this->noticeSlack($message, $data);
        }

        return $this;
    }

    /**
     * @param $channel
     *
     * @return \Monolog\Logger
     */
    private function getLogger($channel)
    {
        $log = new \Monolog\Logger($channel);
        $maxFiles = 1000;

        $handler = new RotatingFileHandler(self::$CHANNELS[$channel]['file'], $maxFiles, self::$CHANNELS[$channel]['level']);
        $handler->setFormatter(new LogstashFormatter('app', null, null, '', LogstashFormatter::V1));

        $log->pushHandler($handler);

        return $log;
    }

    private function getSlackLogger($channel)
    {
        $log = new \Monolog\Logger($channel);

        $slackHandler = new SlackHandler(
            $this->slackToken,
            $this->slackChannel,
            $this->slackUsername,
            true,
            ':clap:',
            \Monolog\Logger::WARNING,
            true,
            true,
            true
        );
        $slackHandler->setFormatter(new LineFormatter());
        $slackHandler->setLevel(\Monolog\Logger::ALERT);

        $log->pushHandler($slackHandler);

        return $log;
    }
}
