<?php

namespace Skyads\Logger;

use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\LogstashFormatter;
use Monolog\Handler\SlackHandler;
use Monolog\Handler\StreamHandler;
use Skyads\Permission;

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
    private $permissionMode = '';
    private $permissionOwner = '';
    private $permissionGroup = '';

    public function __construct(
        $rootLogDir,
        $slackToken,
        $slackChannel,
        $slackUsername,
        $env = 'prod',
        $permissionMode = 0774,
        $permissionOwner = 'nginx',
        $permissionGroup = 'duc'
    ) {
        $this->rootLogDir = $rootLogDir;
        $this->slackToken = $slackToken;
        $this->slackChannel = $slackChannel;
        $this->slackUsername = $slackUsername;
        $this->permissionMode = $permissionMode;
        $this->permissionOwner = $permissionOwner;
        $this->permissionGroup = $permissionGroup;

        $env = 'prod' == $env ? '' : '/' . $env;
        self::$CHANNELS[self::CHANNEL_MO]['file'] = $rootLogDir . '/mo' . $env . '/mo.log';
        self::$CHANNELS[self::CHANNEL_MO_ERROR]['file'] = $rootLogDir . '/mo' . $env . '/mo_error.log';
        self::$CHANNELS[self::CHANNEL_MT_SUCCESS]['file'] = $rootLogDir . '/mt' . $env . '/mt_success.log';
        self::$CHANNELS[self::CHANNEL_MT_ERROR]['file'] = $rootLogDir . '/mt' . $env . '/mt_error.log';

        //Create sub-dir if it's not exist
        $arr = ['mo', 'mt', 'mo/dev', 'mt/dev'];
        foreach ($arr as $item) {
            if (!is_dir($rootLogDir . '/' . $item)) {
                mkdir($rootLogDir . '/' . $item, 0777, true);
            }
        }
    }

    public function logMo($message, $data = [], $isNoticeSlack = false, $date = null)
    {
        return $this->log(self::CHANNEL_MO, 'info', $message, $data, $isNoticeSlack, $date);
    }

    public function logMoError($message, $data = [], $isNoticeSlack = false, $date = null)
    {
        return $this->log(self::CHANNEL_MO_ERROR, 'alert', $message, $data, $isNoticeSlack, $date);
    }

    public function logMtSuccess($message, $data = [], $isNoticeSlack = false, $date = null)
    {
        return $this->log(self::CHANNEL_MT_SUCCESS, 'info', $message, $data, $isNoticeSlack, $date);
    }

    public function logMtError($message, $data = [], $isNoticeSlack = false, $date = null)
    {
        return $this->log(self::CHANNEL_MT_ERROR, 'alert', $message, $data, $isNoticeSlack, $date);
    }

    public function noticeSlack($message, $data = [])
    {
        /** @var \Monolog\Logger $logger */
        $logger = $this->getSlackLogger('slack');
        try {
            $logger->alert($message, $data);
        } catch (\Exception $ex) {
            echo $ex->getMessage().PHP_EOL;
        }

        return $this;
    }

    /**
     * @return mixed
     */
    public function getRootLogDir()
    {
        return $this->rootLogDir;
    }

    /**
     * @param mixed $rootLogDir
     */
    public function setRootLogDir($rootLogDir)
    {
        $this->rootLogDir = $rootLogDir;
    }

    /**
     * @return mixed
     */
    public function getSlackUsername()
    {
        return $this->slackUsername;
    }

    /**
     * @param mixed $slackUsername
     */
    public function setSlackUsername($slackUsername)
    {
        $this->slackUsername = $slackUsername;
    }

    /**
     * @return mixed
     */
    public function getSlackToken()
    {
        return $this->slackToken;
    }

    /**
     * @param mixed $slackToken
     */
    public function setSlackToken($slackToken)
    {
        $this->slackToken = $slackToken;
    }

    /**
     * @return mixed
     */
    public function getSlackChannel()
    {
        return $this->slackChannel;
    }

    /**
     * @param mixed $slackChannel
     */
    public function setSlackChannel($slackChannel)
    {
        $this->slackChannel = $slackChannel;
    }

    /**
     * @return int|string
     */
    public function getPermissionMode()
    {
        return $this->permissionMode;
    }

    /**
     * @param int|string $permissionMode
     */
    public function setPermissionMode($permissionMode)
    {
        $this->permissionMode = $permissionMode;
    }

    /**
     * @return string
     */
    public function getPermissionOwner()
    {
        return $this->permissionOwner;
    }

    /**
     * @param string $permissionOwner
     */
    public function setPermissionOwner($permissionOwner)
    {
        $this->permissionOwner = $permissionOwner;
    }

    /**
     * @return string
     */
    public function getPermissionGroup()
    {
        return $this->permissionGroup;
    }

    /**
     * @param string $permissionGroup
     */
    public function setPermissionGroup($permissionGroup)
    {
        $this->permissionGroup = $permissionGroup;
    }

    private function log($channel, $level, $message, $data = [], $isNoticeSlack = false, $date = null)
    {
        if (null == $date) {
            $date = date('Y-m-d');
        }

        $file = $this->getLogFileFormatted(self::$CHANNELS[$channel]['file'], $date);
        $this->setPermission($file);

        /** @var \Monolog\Logger $logger */
        $logger = $this->getLogger($channel, $date);
        $logger->$level($message, $data);

        if ($isNoticeSlack) {
            $this->noticeSlack($message, $data);
        }

        return $this;
    }

    /**
     * @param string $channel
     * @param string $date
     *
     * @return \Monolog\Logger
     */
    private function getLogger($channel, $date = null)
    {
        $log = new \Monolog\Logger($channel);

        $handler = new StreamHandler($this->getLogFileFormatted(self::$CHANNELS[$channel]['file'], $date), self::$CHANNELS[$channel]['level'], true, null);
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

    private function getLogFileFormatted($file, $suffix = null)
    {
        if (null == $suffix) {
            return $file;
        }

        $info = pathinfo($file);
        $suffix = '-' . ltrim($suffix, '-_');

        return $info['dirname'] . '/' . $info['filename'] . $suffix . '.' . $info['extension'];
    }

    private function setPermission($file)
    {
        Permission::chownRecursive($file, $this->getPermissionOwner());
        Permission::chmodRecursive($file, $this->getPermissionMode());
        Permission::chgrpRecursive($file, $this->getPermissionGroup());
    }
}
