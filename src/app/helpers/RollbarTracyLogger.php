<?php

namespace App\helpers;

use Tracy\ILogger;
use Rollbar\Rollbar;
use Rollbar\Payload\Level;
class RollbarTracyLogger implements ILogger
{
    private $originalLogger;

    public function __construct(?ILogger $originalLogger)
    {
        $this->originalLogger = $originalLogger;
    }

    
    public function log($value, string $level = self::INFO): void
    {
        if ($this->originalLogger) {
            $this->originalLogger->log($value, $level);
        }

        $this->sendToRollbar($value, $level);
    }

    
    private function sendToRollbar($value, string $tracyLevel): void
    {
        $rollbarLevel = $this->mapTracyLevelToRollbar($tracyLevel);

        if ($value instanceof \Throwable) {
            Rollbar::log($rollbarLevel, $value);
        } else {
            $message = is_string($value) ? $value : json_encode($value);
            Rollbar::log($rollbarLevel, $message);
        }
    }

    
    private function mapTracyLevelToRollbar(string $tracyLevel): string
    {
        $mapping = [
            ILogger::DEBUG => Level::DEBUG,
            ILogger::INFO => Level::INFO,
            ILogger::WARNING => Level::WARNING,
            ILogger::ERROR => Level::ERROR,
            ILogger::EXCEPTION => Level::ERROR,
            ILogger::CRITICAL => Level::CRITICAL,
        ];

        return $mapping[$tracyLevel] ?? Level::INFO;
    }
}
