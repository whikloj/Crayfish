<?php

namespace Islandora\Recast\Tests;

use Islandora\Crayfish\Commons\Client\GeminiClient;
use Islandora\Chullo\IFedoraApi;
use Islandora\Recast\Controller\RecastController;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class RecastControllerTest extends TestCase
{

    private $fedora_prophecy;

    private $gemini_prophecy;

    private $logger_prophecy;

    public function setUp()
    {
        $this->fedora_prophecy = $this->prophesize(IFedoraApi::class);
        $this->gemini_prophecy = $this->prophesize(GeminiClient::class);
        $this->logger_prophecy = $this->prophesize(Logger::class);
    }


    public function testImageAdd()
    {
        $resource_id = 'http://localhost:8080/fcrepo/rest/object1';
        $input_resource = realpath(__DIR__ . '/../../../resources/drupal_image.json');
        $output_resource = realpath(__DIR__ . '/../../../resources/drupal_image_add.json');


        $this->gemini_prophecy->findByUri('http://localhost:8000/user/1?_format=jsonld')
          ->willReturn(null);
        $this->gemini_prophecy->findByUri('http://localhost:8000/media/1?_format=jsonld')
          ->willReturn(null);
        $this->gemini_prophecy->findByUri('http://localhost:8000/node/1?_format=jsonld')
          ->willReturn('http://localhost:8080/fcrepo/rest/collection1');

        $mock_silex_app = new Application();
        $mock_silex_app['crayfish.drupal_base_url'] = 'http://localhost:8000';

        $prophecy = $this->prophesize(StreamInterface::class);
        $prophecy->isReadable()->willReturn(true);
        $prophecy->isWritable()->willReturn(false);
        $prophecy->__toString()->willReturn(file_get_contents($input_resource));
        $mock_stream = $prophecy->reveal();

        // Mock a Fedora response.
        $prophecy = $this->prophesize(ResponseInterface::class);
        $prophecy->getStatusCode()->willReturn(200);
        $prophecy->getBody()->willReturn($mock_stream);
        $prophecy->getHeader('Content-type')->willReturn('application/ld+json');
        $mock_fedora_response = $prophecy->reveal();

        $controller = new RecastController(
            $this->gemini_prophecy->reveal(),
            $this->logger_prophecy->reveal()
        );

        $request = Request::create(
            "/add",
            "GET"
        );
        $request->headers->set('Authorization', 'some_token');
        $request->headers->set('Apix-Ldp-Resource', $resource_id);
        $request->headers->set('Accept', 'application/ld+json');
        $request->attributes->set('fedora_resource', $mock_fedora_response);

        $response = $controller->recast($request, $mock_silex_app, 'add');
        $this->assertEquals(200, $response->getStatusCode(), "Invalid status code");
        $json = json_decode($response->getContent(), true);

        $expected = json_decode(file_get_contents($output_resource), true);
        $this->assertEquals($expected, $json, "Response does not match expected additions.");
    }
}
