<?php
namespace Chadicus\Psr\Log;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Driver\Exception\UnexpectedValueException;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use SubjectivePHP\Psr\Log\ExceptionExtractorTrait;
use SubjectivePHP\Psr\Log\LevelValidatorTrait;
use SubjectivePHP\Psr\Log\MessageInterpolationTrait;
use SubjectivePHP\Psr\Log\MessageValidatorTrait;
use SubjectivePHP\Util\Exception;

/**
 * PSR-3 Logger implementation writing to a MongoDB.
 */
final class MongoLogger extends AbstractLogger implements LoggerInterface
{
    use ExceptionExtractorTrait;
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

        $document['extra'] = $this->getNormalizeArray($context);
        $this->collection->insertOne($document, ['w' => 0]);
    }

    private function buildBasicDocument(string $level, string $message, array $context = [])
    {
        return [
            'timestamp' => new UTCDateTime((int)(microtime(true) * 1000)),
            'level' => $level,
            'message' => $this->interpolateMessage((string)$message, $context),
        ];
    }

    private function getExceptionData(array $context = [])
    {
        $exception = $this->getExceptionFromContext($context);
        if ($exception === null) {
            return null;
        }

        return Exception::toArray($exception, true);
    }

    private function getNormalizeArray(array $array = [])
    {
        $normalized = [];
        foreach ($array as $key => $value) {
            $normalized[$key] = $this->getNormalizedValue($value);
        }

        return $normalized;
    }

    private function getNormalizedValue($value)
    {
        if (is_array($value)) {
            return $this->getNormalizeArray($value);
        }

        if (is_object($value)) {
            return  method_exists($value, '__toString') ? "{$value}" : get_class($value);
        }

        return is_scalar($value) ? $value : gettype($value);
    }
}
