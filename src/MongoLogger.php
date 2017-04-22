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
    use MessageValidatorTrait;

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
    public function log($level, $message, array $context = [])//@codingStandardsIgnoreLine Ignore missing type hints
    {
        $this->validateLevel($level);
        $this->validateMessage($message);

        $document = $this->buildBasicDocument($level, $message, $context);
        $document['exception'] = $this->getExceptionData($context);

        unset($context['exception']);

        $document['extra'] = $this->getNormalizeContext($context);
        $this->collection->insertOne($document, ['w' => 0]);
    }

    private function buildBasicDocument($level, $message, array $context = [])
    {
        return [
            'timestamp' => new UTCDateTime((int)(microtime(true) * 1000)),
            'level' => $level,
            'message' => $this->interpolateMessage((string)$message, $context),
        ];
    }

    private function getExceptionData($context)
    {
        $exceptionClass = version_compare(phpversion(), '7.0.0', '<') ? '\Exception' : '\Throwable';
        if (isset($context['exception']) && is_a($context['exception'], $exceptionClass)) {
            return Exception::toArray($context['exception'], true);
        }

        return null;
    }

    private function getNormalizeContext(array $context)
    {
        $normalized = [];
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $normalized[$key] = $this->getNormalizeContext($value);
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
