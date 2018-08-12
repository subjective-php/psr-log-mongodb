<?php

namespace SubjectivePHPTest\Psr\Log;

use MongoDB\BSON\UTCDateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use SubjectivePHP\Psr\Log\MongoLogger;

/**
 * Unit tests for the \SubjectivePHP\Psr\Log\MongoLogger class.
 *
 * @coversDefaultClass \SubjectivePHP\Psr\Log\MongoLogger
 * @covers ::<private>
 * @covers ::__construct
 */
final class MongoLoggerTest extends TestCase
{
    /**
     * Verify basic behavior of log().
     *
     * @test
     * @covers ::log
     *
     * @return void
     */
    public function log()
    {
        $collectionMock = $this->getMongoCollectionMockWithAsserts(
            LogLevel::WARNING,
            'this is a test',
            ['some' => ['nested' => ['data']]],
            null
        );
        (new MongoLogger($collectionMock))->log(
            LogLevel::WARNING,
            'this is a test',
            ['some' => ['nested' => ['data']]]
        );
    }

    /**
     * Verify behavior of log() with message interpolation.
     *
     * @test
     * @covers ::log
     *
     * @return void
     */
    public function logWithInterpolation()
    {
        $collectionMock = $this->getMongoCollectionMockWithAsserts(
            LogLevel::INFO,
            'user chadicus created',
            ['username' => 'chadicus'],
            null
        );
        (new MongoLogger($collectionMock))->log(LogLevel::INFO, 'user {username} created', ['username' => 'chadicus']);
    }

    /**
     * Verify behavior of log() when $level is not known.
     *
     * @test
     * @covers ::log
     * @expectedException \Psr\Log\InvalidArgumentException
     * @expectedExceptionMessage Given $level was not a known LogLevel
     *
     * @return void
     */
    public function logUnknownLevel()
    {
        $collectionMock = $this->getMockBuilder('\\MongoDB\\Collection')->disableOriginalConstructor()->getMock();
        $collectionMock->method('insertOne')->will($this->throwException(new \Exception('insertOne was called.')));
        (new MongoLogger($collectionMock))->log('unknown', 'this is a test');
    }

    /**
     * Verify behavior of log() when $message is not a valid string value.
     *
     * @test
     * @covers ::log
     * @expectedException \Psr\Log\InvalidArgumentException
     * @expectedExceptionMessage Given $message was a valid string value
     *
     * @return void
     */
    public function logNonStringMessage()
    {
        $collectionMock = $this->getMockBuilder('\\MongoDB\\Collection')->disableOriginalConstructor()->getMock();
        $collectionMock->method('insertOne')->will($this->throwException(new \Exception('insertOne was called.')));
        (new MongoLogger($collectionMock))->log(LogLevel::INFO, new \StdClass());
    }

    /**
     * Verify behavior of log() when $message is an object with __toString().
     *
     * @test
     * @covers ::log
     *
     * @return void
     */
    public function logObjectMessage()
    {
        $collectionMock = $this->getMongoCollectionMockWithAsserts(
            LogLevel::INFO,
            __FILE__,
            ['foo' => 'bar'],
            null
        );
        (new MongoLogger($collectionMock))->log(
            LogLevel::INFO,
            new \SplFileInfo(__FILE__),
            ['foo' => 'bar']
        );
    }

    /**
     * Verify context is normalized when log().
     *
     * @test
     * @covers ::log
     *
     * @return void
     */
    public function logNormalizesContext()
    {
        $collectionMock = $this->getMongoCollectionMockWithAsserts(
            LogLevel::INFO,
            'this is a test',
            [
                'stdout' => 'resource',
                'object' => 'stdClass',
                'file' => __FILE__,
            ],
            null
        );
        (new MongoLogger($collectionMock))->log(
            LogLevel::INFO,
            'this is a test',
            [
                'stdout' => STDOUT,
                'object' => new \StdClass(),
                'file' => new \SplFileInfo(__FILE__),
            ]
        );
    }

    /**
     * Verify exception is handled properly.
     *
     * @test
     * @covers ::log
     *
     * @return void
     */
    public function logWithException()
    {
        $exception = new \RuntimeException('a message', 21);
        $collectionMock = $this->getMongoCollectionMockWithAsserts(
            LogLevel::INFO,
            'this is a test',
            [],
            [
                'type' => 'RuntimeException',
                'message' => 'a message',
                'code' => 21,
                'file' => __FILE__,
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'previous' => null,
            ]
        );

        (new MongoLogger($collectionMock))->log(
            LogLevel::INFO,
            'this is a test',
            [
                'exception' => $exception,
            ]
        );
    }

    private function getMongoCollectionMockWithAsserts($level, $message, $extra, $exception)
    {
        $test = $this;
        $insertOneCallback = function ($document, $options) use ($test, $level, $message, $extra, $exception) {
            $test->assertInstanceOf('\\MongoDB\\BSON\\UTCDateTime', $document['timestamp']);
            $test->assertLessThanOrEqual(time(), $document['timestamp']->toDateTime()->getTimestamp());
            $test->assertSame(
                [
                    'timestamp' => $document['timestamp'],
                    'level' => $level,
                    'message' => $message,
                    'exception' => $exception,
                    'extra' => $extra,
                ],
                $document
            );
            $test->assertSame(['w' => 0], $options);
        };
        $collectionMock = $this->getMockBuilder('\\MongoDB\\Collection')->disableOriginalConstructor()->getMock();
        $collectionMock->expects($this->once())->method('insertOne')->will($this->returnCallback($insertOneCallback));
        return $collectionMock;
    }
}
