<?php

namespace Hsk99\Arms;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use Zipkin\TracingBuilder;
use Zipkin\Samplers\BinarySampler;
use Zipkin\Endpoint;

class Arms implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $endpoint = Endpoint::create(config('plugin.hsk99.arms.app.app_name'), $request->getRealIp(), null, 2555);
        $logger = new \Monolog\Logger('log');
        $logger->pushHandler(new \Monolog\Handler\ErrorLogHandler());
        $reporter = new \Zipkin\Reporters\Http([
            'endpoint_url' => config('plugin.hsk99.arms.app.endpoint_url')
        ]);
        $sampler = BinarySampler::createAsAlwaysSample();
        $tracing = TracingBuilder::create()
            ->havingLocalEndpoint($endpoint)
            ->havingSampler($sampler)
            ->havingReporter($reporter)
            ->build();
        $tracer   = $tracing->getTracer();
        $rootSpan = $tracer->newTrace();
        $rootSpan->setName($request->controller . "::" . $request->action);
        $rootSpan->start();

        $response = $next($request);

        $rootSpan->tag('http.url', $request->fullUrl());
        $rootSpan->tag('http.method', $request->method());
        $rootSpan->tag('http.status_code', $response->getStatusCode());
        if ($response->getStatusCode() >= 400) {
            $rootSpan->tag('error', true);
        }
        $rootSpan->finish();
        $tracer->flush();

        return $response;
    }
}
