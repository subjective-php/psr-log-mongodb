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
        LoggerHelper::validateLevel($level);

        if (!is_scalar($message) && !(is_object($message) && method_exists($message, '__toString'))) {
            throw new InvalidArgumentException('Given $message was a valid string value');
        }

        $this->collection->insertOne(
            [
                'timestamp' => new UTCDateTime((int)(microtime(true) * 1000)),
                'level' => $level,
                'message' => LoggerHelper::interpolateMessage((string)$message, $context),
                'context' => $context,
            ],
            ['w' => 0]
        );
    }
}
