<?php
namespace Chadicus\Psr\Log;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Driver\Exception\UnexpectedValueException;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * PSR-3 Logger implementation writing to a MongoDB.
 */
final class MongoLogger extends AbstractLogger implements LoggerInterface
{
    /**
     * Collection containing logs.
     *
     * @var Collection
     */
    private $collection;

    /**
     * Create a new instance of MongoLogger.
     *
     * @param Collection $collection Mongo collection to which the logs should be written.
     */
    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param string $level   A valid RFC 5424 log level.
     * @param string $message The base log message.
     * @param array  $context Any extraneous information that does not fit well in a string.
     *
     * @return void
     *
     * @throws InvalidArgumentException Thrown if $level is not a valid PSR-3 Log level.
     */
    public function log($level, $message, array $context = [])
    {
        if (!is_string($level) || !defined('\\Psr\\Log\\LogLevel::' . strtoupper($level))) {
            throw new InvalidArgumentException('Given $level was not a known LogLevel');
        }

        if (!is_scalar($message) && !(is_object($message) && method_exists($message, '__toString'))) {
            throw new InvalidArgumentException('Given $message was a valid string value');
        }

        $this->collection->insertOne(
            [
                'timestamp' => new UTCDateTime((int)(microtime(true) * 1000)),
                'level' => $level,
                'message' => self::interpolate((string)$message, $context),
                'context' => $context,
            ],
            ['w' => 0]
        );
    }

    /**
     * Interpolates context values into the message placeholders.
     *
     * @param string $message The string containing the placeholders.
     * @param array  $context The replacement values.
     *
     * @return string
     */
    private static function interpolate($message, array $context)
    {
        foreach ($context as $key => $value) {
            if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $message = str_replace("{{$key}}", (string)$value, $message);
            }
        }

        return $message;
    }
}
