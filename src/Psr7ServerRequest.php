<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace RstGroup\Psr7Bridge;

use Asika\Http\ServerRequest;
use Asika\Http\Stream\Stream;
use Asika\Http\UploadedFile;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Zend\Http\PhpEnvironment\Request as ZendPhpEnvironmentRequest;
use Zend\Http\Request as ZendRequest;

final class Psr7ServerRequest
{
    /**
     * Convert a PSR-7 ServerRequest to a Zend\Http server-side request.
     *
     * @param ServerRequestInterface $psr7Request
     * @param bool $shallow Whether or not to convert without body/file
     *     parameters; defaults to false, meaning a fully populated request
     *     is returned.
     * @return Zend\Request
     */
    public static function toZend(ServerRequestInterface $psr7Request, $shallow = false)
    {
        $queryParams = $psr7Request->getQueryParams();
        $serverParams = $psr7Request->getServerParams();
        $cookieParams = $psr7Request->getCookieParams();

        if ($shallow) {
            return new Zend\Request(
                $psr7Request->getMethod(),
                $psr7Request->getUri(),
                $psr7Request->getHeaders(),
                $psr7Request->getCookieParams(),
                $queryParams ? $queryParams : array(),
                array(),
                array(),
                $serverParams ? $serverParams : array()
            );
        }

        $zendRequest = new Zend\Request(
            $psr7Request->getMethod(),
            $psr7Request->getUri(),
            $psr7Request->getHeaders(),
            $cookieParams ? $cookieParams : array(),
            $queryParams ? $queryParams : array(),
            $psr7Request->getParsedBody() ?: array(),
            self::convertUploadedFiles($psr7Request->getUploadedFiles()),
            $serverParams ? $serverParams : array()
        );
        $zendRequest->setContent($psr7Request->getBody());

        return $zendRequest;
    }

    /**
     * Convert a Zend\Http\Response in a PSR-7 response, using zend-diactoros
     *
     * @param  ZendRequest $zendRequest
     * @return ServerRequest
     */
    public static function fromZend(ZendRequest $zendRequest)
    {
        $body = new Stream('php://memory', 'wb+');
        $body->write($zendRequest->getContent());

        $headers = empty($zendRequest->getHeaders()) ? array() : $zendRequest->getHeaders()->toArray();
        $query   = empty($zendRequest->getQuery()) ? array() : $zendRequest->getQuery()->toArray();
        $post    = empty($zendRequest->getPost()) ? array() : $zendRequest->getPost()->toArray();
        $files   = empty($zendRequest->getFiles()) ? array() : $zendRequest->getFiles()->toArray();

        $request = new ServerRequest(
            $zendRequest instanceof ZendPhpEnvironmentRequest ? iterator_to_array($zendRequest->getServer()) : array(),
            self::convertFilesToUploaded($files),
            $zendRequest->getUriString(),
            $zendRequest->getMethod(),
            $body,
            $headers
        );
        $request = $request->withQueryParams($query);

        $cookie = $zendRequest->getCookie();
        if (false !== $cookie) {
            $request = $request->withCookieParams($cookie->getArrayCopy());
        }

        return $request->withParsedBody($post);
    }

    /**
     * Convert a PSR-7 uploaded files structure to a $_FILES structure
     *
     * @param UploadedFileInterface[]
     * @return array
     */
    private static function convertUploadedFiles(array $uploadedFiles)
    {
        $files = array();
        foreach ($uploadedFiles as $name => $upload) {
            if (is_array($upload)) {
                $files[$name] = self::convertUploadedFiles($upload);
                continue;
            }

            $uploadError = $upload->getError();
            $isUploadError = $uploadError !== UPLOAD_ERR_OK;

            $files[$name] = array(
                'name'     => $upload->getClientFilename(),
                'type'     => $upload->getClientMediaType(),
                'size'     => $upload->getSize(),
                'tmp_name' => ! $isUploadError ? $upload->getStream()->getMetadata('uri') : '',
                'error'    => $uploadError,
            );
        }
        return $files;
    }

    /**
     * Convert a Zend\Http file structure to PSR-7 uploaded files
     *
     * @param array
     * @return UploadedFile[]
     */
    private static function convertFilesToUploaded(array $files)
    {
        $uploadedFiles = array();
        foreach ($files as $name => $value) {
            if (is_array($value)) {
                $uploadedFiles[$name] = self::convertFilesToUploaded($value);
                continue;
            }
            return new UploadedFile(
                $files['tmp_name'],
                $files['size'],
                $files['error'],
                $files['name'],
                $files['type']
            );
        }
        return $uploadedFiles;
    }

    /**
     * Do not allow instantiation.
     */
    private function __construct()
    {
    }
}
