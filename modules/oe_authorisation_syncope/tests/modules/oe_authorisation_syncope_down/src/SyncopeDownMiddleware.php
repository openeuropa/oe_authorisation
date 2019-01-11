<?php

declare(strict_types = 1);

namespace Drupal\oe_authorisation_syncope_down;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Promise\FulfilledPromise;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * A Guzzle middleware to simulate Syncope being down.
 */
class SyncopeDownMiddleware {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * SyncopeDownMiddleware constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * HTTP middleware that simulates down of Syncope service.
   */
  public function __invoke() {
    return function ($handler) {
      $endpoint = $this->configFactory->get('oe_authorisation_syncope.settings')->get('endpoint');
      return function (RequestInterface $request, array $options) use ($handler, $endpoint) {
        $uri = $request->getUri();
        $parsed_endpoint = parse_url($endpoint);
        if ($uri->getHost() === $parsed_endpoint['host']) {
          $response = new Response(0, []);
          return new FulfilledPromise($response);
        }

        // Otherwise, no intervention. We defer to the handler stack.
        return $handler($request, $options)
          ->then(function (ResponseInterface $response) use ($request) {
            return $response;
          });
      };
    };
  }

}
