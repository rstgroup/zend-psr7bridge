<?php
/**
 * @see       http://github.com/zendframework/zend-psr7bridge for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-psr7bridge/blob/master/LICENSE.md New BSD License
 */

namespace RstGroupTest\Psr7Bridge;

use Asika\Http\Response;
use Asika\Http\Stream\Stream;
use PHPUnit\Framework\TestCase as TestCase;
use RstGroup\Psr7Bridge\Psr7Response;
use Zend\Http\Response as ZendResponse;

class Psr7ResponseTest extends TestCase
{
    public function getResponseData()
    {
        return array(
            array( 'Test!', 200, array( 'Content-Type' => array( 'text/html' ) ) ),
            array( '', 204, array() ),
            array( 'Test!', 200, array(
                'Content-Type'   => array( 'text/html; charset=utf-8' ),
                'Content-Length' => array( '5' )
            )),
            array( 'Test!', 202, array(
                'Content-Type'   => array( 'text/html; level=1', 'text/html' ),
                'Content-Length' => array( '5' )
            )),
        );
    }

    /**
     * @dataProvider getResponseData
     */
    public function testResponseToZend($body, $status, $headers)
    {
        $stream = new Stream('php://temp', 'wb+');
        $stream->write($body);

        $psr7Response = new Response($stream, $status, $headers);
        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $psr7Response);

        $zendResponse = Psr7Response::toZend($psr7Response);
        $this->assertInstanceOf('Zend\Http\Response', $zendResponse);
        $this->assertEquals($body, (string)$zendResponse->getBody());
        $this->assertEquals($status, $zendResponse->getStatusCode());

        $zendHeaders = $zendResponse->getHeaders()->toArray();
        foreach ($headers as $type => $values) {
            foreach ($values as $value) {
                $this->assertContains($value, $zendHeaders[$type]);
            }
        }
    }

    /**
     * @dataProvider getResponseData
     */
    public function testResponseToZendWithMemoryStream($body, $status, $headers)
    {
        $stream = new Stream('php://memory', 'wb+');
        $stream->write($body);

        $psr7Response = new Response($stream, $status, $headers);
        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $psr7Response);

        $zendResponse = Psr7Response::toZend($psr7Response);
        $this->assertInstanceOf('Zend\Http\Response', $zendResponse);
        $this->assertEquals($body, (string)$zendResponse->getBody());
        $this->assertEquals($status, $zendResponse->getStatusCode());

        $zendHeaders = $zendResponse->getHeaders()->toArray();
        foreach ($headers as $type => $values) {
            foreach ($values as $value) {
                $this->assertContains($value, $zendHeaders[$type]);
            }
        }
    }

    /**
     * @dataProvider getResponseData
     */
    public function testResponseToZendFromRealStream($body, $status, $headers)
    {
        $stream = new Stream(tempnam(sys_get_temp_dir(), 'Test'), 'wb+');
        $stream->write($body);

        $psr7Response = new Response($stream, $status, $headers);
        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $psr7Response);

        $zendResponse = Psr7Response::toZend($psr7Response);
        $this->assertInstanceOf('Zend\Http\Response', $zendResponse);
        $this->assertEquals($body, (string)$zendResponse->getBody());
        $this->assertEquals($status, $zendResponse->getStatusCode());

        $zendHeaders = $zendResponse->getHeaders()->toArray();
        foreach ($headers as $type => $values) {
            foreach ($values as $value) {
                $this->assertContains($value, $zendHeaders[$type]);
            }
        }
    }

    public function getResponseString()
    {
        return array(
            array( "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=utf-8\r\n\r\nTest!" ),
            array( "HTTP/1.1 204 OK\r\n\r\n" ),
            array( "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nContent-Length: 5\r\n\r\nTest!" ),
            array( "HTTP/1.1 200 OK\r\nContent-Type: text/html, text/xml\r\nContent-Length: 5\r\n\r\nTest!" ),
        );
    }

    /**
     * @dataProvider getResponseString
     */
    public function testResponseFromZend($response)
    {
        $zendResponse = ZendResponse::fromString($response);
        $this->assertInstanceOf('Zend\Http\Response', $zendResponse);
        $psr7Response = Psr7Response::fromZend($zendResponse);
        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $psr7Response);
        $this->assertEquals((string)$psr7Response->getBody(), $zendResponse->getBody());
        $this->assertEquals($psr7Response->getStatusCode(), $zendResponse->getStatusCode());

        $zendHeaders = $zendResponse->getHeaders()->toArray();
        foreach ($psr7Response->getHeaders() as $type => $values) {
            foreach ($values as $value) {
                $this->assertContains($value, $zendHeaders[$type]);
            }
        }
    }

    /**
     * @requires PHP 7
     */
    public function testPrivateConstruct()
    {
        $this->setExpectedException('Error', sprintf('Call to private %s::__construct', 'RstGroup\Psr7Bridge\Psr7Response'));

        new Psr7Response();
    }
}
