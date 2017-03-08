<?php

namespace ChadicusTest\Psr\Log;

use Chadicus\Psr\Log\MongoLogger;
use MongoDB\BSON\UTCDateTime;
use Psr\Log\LogLevel;

/**
 * Unit tests for the \Chadicus\Psr\Log\MongoLogger class.
 *
 * @coversDefaultClass \Chadicus\Psr\Log\MongoLogger
 * @covers ::<private>
 * @covers ::__construct
 */
final class MongoLoggerTest extends \PHPUnit_Framework_TestCase
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
        $test = $this;
        $insertOneCallback = function ($document, $options) use ($test) {
            $test->assertInstanceOf('\\MongoDB\\BSON\\UTCDateTime', $document['timestamp']);
            $test->assertLessThanOrEqual(time(), $document['timestamp']->toDateTime()->getTimestamp());
            $test->assertSame(
                [
                    'timestamp' => $document['timestamp'],
                    'level' => LogLevel::WARNING,
                    'message' => 'this is a test',
                    'extra' => ['some' => ['nested' => ['data']]],
                ],
                $document
            );
            $test->assertSame(['w' => 0], $options);
        };

        $collectionMock = $this->getMockBuilder('\\MongoDB\\Collection')->disableOriginalConstructor()->getMock();
        $collectionMock->expects($this->once())->method('insertOne')->will($this->returnCallback($insertOneCallback));
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
        $test = $this;
        $insertOneCallback = function ($document, $options) use ($test) {
            $test->assertInstanceOf('\\MongoDB\\BSON\\UTCDateTime', $document['timestamp']);
            $test->assertLessThanOrEqual(time(), $document['timestamp']->toDateTime()->getTimestamp());
            $test->assertSame(
                [
                    'timestamp' => $document['timestamp'],
                    'level' => LogLevel::INFO,
                    'message' => 'user chadicus created',
                    'extra' => ['username' => 'chadicus'],
                ],
                $document
            );
            $test->assertSame(['w' => 0], $options);
        };

        $collectionMock = $this->getMockBuilder('\\MongoDB\\Collection')->disableOriginalConstructor()->getMock();
        $collectionMock->expects($this->once())->method('insertOne')->will($this->returnCallback($insertOneCallback));

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
        $test = $this;
        $insertOneCallback = function ($document, $options) use ($test) {
            $test->assertInstanceOf('\\MongoDB\\BSON\\UTCDateTime', $document['timestamp']);
            $test->assertLessThanOrEqual(time(), $document['timestamp']->toDateTime()->getTimestamp());
            $test->assertSame(
                [
                    'timestamp' => $document['timestamp'],
                    'level' => LogLevel::INFO,
                    'message' => __FILE__,
                    'extra' => ['some' => ['nested' => ['data']]],
                ],
                $document
            );
            $test->assertSame(['w' => 0], $options);
        };

        $collectionMock = $this->getMockBuilder('\\MongoDB\\Collection')->disableOriginalConstructor()->getMock();
        $collectionMock->expects($this->once())->method('insertOne')->will($this->returnCallback($insertOneCallback));
        (new MongoLogger($collectionMock))->log(
            LogLevel::INFO,
            new \SplFileInfo(__FILE__),
            ['some' => ['nested' => ['data']]]
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
        $test = $this;
        $insertOneCallback = function ($document, $options) use ($test) {
            $test->assertInstanceOf('\\MongoDB\\BSON\\UTCDateTime', $document['timestamp']);
            $test->assertLessThanOrEqual(time(), $document['timestamp']->toDateTime()->getTimestamp());
            $test->assertSame(
                [
                    'timestamp' => $document['timestamp'],
                    'level' => LogLevel::INFO,
                    'message' => 'this is a test',
                    'extra' => [
                        'stdout' => 'resource',
                        'object' => 'stdClass',
                        'file' => __FILE__,
                    ],
                ],
                $document
            );
            $test->assertSame(['w' => 0], $options);
        };

        $collectionMock = $this->getMockBuilder('\\MongoDB\\Collection')->disableOriginalConstructor()->getMock();
        $collectionMock->expects($this->once())->method('insertOne')->will($this->returnCallback($insertOneCallback));

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
        $test = $this;
        $insertOneCallback = function ($document, $options) use ($test, $exception) {
            $test->assertInstanceOf('\\MongoDB\\BSON\\UTCDateTime', $document['timestamp']);
            $test->assertLessThanOrEqual(time(), $document['timestamp']->toDateTime()->getTimestamp());
            $test->assertSame(
                [
                    'timestamp' => $document['timestamp'],
                    'level' => LogLevel::INFO,
                    'message' => 'this is a test',
                    'exception' => [
                        'type' => 'RuntimeException',
                        'message' => 'a message',
                        'code' => 21,
                        'file' => __FILE__,
                        'line' => $exception->getLine(),
                        'trace' => $exception->getTraceAsString(),
                        'previous' => null,
                    ],
                    'extra' => [],
                ],
                $document
            );
            $test->assertSame(['w' => 0], $options);
        };

        $collectionMock = $this->getMockBuilder('\\MongoDB\\Collection')->disableOriginalConstructor()->getMock();
        $collectionMock->expects($this->once())->method('insertOne')->will($this->returnCallback($insertOneCallback));

        (new MongoLogger($collectionMock))->log(
            LogLevel::INFO,
            'this is a test',
            [
                'exception' => $exception,
            ]
        );
    }
}
