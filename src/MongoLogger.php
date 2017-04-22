<?php
namespace Chadicus\Psr\Log;

use Chadicus\Util\Exception;
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
    use LevelValidatorTrait;
    use MessageInterpolationTrait;

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
     */
    public function log($level, $message, array $context = [])
    {
        $this->validateLevel($level);

        self::validateMessage($message);

        $document = [
            'timestamp' => new UTCDateTime((int)(microtime(true) * 1000)),
            'level' => $level,
            'message' => $this->interpolateMessage((string)$message, $context),
        ];

        $exceptionClass = version_compare(phpversion(), '7.0.0', '<') ? '\Exception' : '\Throwable';

        if (isset($context['exception']) && is_a($context['exception'], $exceptionClass)) {
            $document['exception'] = Exception::toArray($context['exception'], true);
            unset($context['exception']);
        }

        $document['extra'] = self::normalizeContext($context);

        $this->collection->insertOne($document, ['w' => 0]);
    }

    /**
     * Helper method to ensure the log message is a string.
     *
     * @param string $message The base log message to validate.
     *
     * @return void
     *
     * @throws InvalidArgumentException $message can not be cast to a string value.
     */
    private static function validateMessage($message)
    {
        if (is_scalar($message)) {
            return;
        }

        if (is_object($message) && method_exists($message, '__toString')) {
            return;
        }

        throw new InvalidArgumentException('Given $message was a valid string value');
    }

    /**
     * Helper method to convert log context into scalar types.
     *
     * @param array $context Any extraneous information that does not fit well in a string.
     *
     * @return array
     */
    private static function normalizeContext(array $context)
    {
        $normalized = [];
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $normalized[$key] = self::normalizeContext($value);
                continue;
            }

            if (is_scalar($value)) {
                $normalized[$key] = $value;
                continue;
            }

            if (!is_object($value)) {
                $normalized[$key] = gettype($value);
                continue;
            }

            if (!method_exists($value, '__toString')) {
                $normalized[$key] = get_class($value);
                continue;
            }

            $normalized[$key] = (string)$value;
        }

        return $normalized;
    }
}
