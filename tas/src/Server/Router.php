<?php

namespace TelegramApiServer\Server;

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use TelegramApiServer\Controllers\LogController;
use TelegramApiServer\Controllers\SystemController;
use TelegramApiServer\Controllers\ApiController;
use TelegramApiServer\Controllers\EventsController;
use Amp\Http\Status;
use TelegramApiServer\MadelineProtoExtensions\ApiExtensions;
use TelegramApiServer\MadelineProtoExtensions\SystemApiExtensions;
use function Amp\Http\Server\Middleware\stack;

class Router
{
    private \Amp\Http\Server\Router $router;

    public function __construct()
    {
        $this->router = new \Amp\Http\Server\Router();
        $this->setRoutes();
        $this->setFallback();
    }

    public function getRouter(): \Amp\Http\Server\Router
    {
        return $this->router;
    }

    private function setFallback(): void
    {
        $this->router->setFallback(new CallableRequestHandler(static function (Request $request) {
            return ErrorResponses::get(Status::NOT_FOUND, 'Path not found');
        }));
    }

    private function setRoutes(): void
    {
        $authorization = new Authorization();
        $apiHandler = stack(ApiController::getRouterCallback(ApiExtensions::class), $authorization);
        $systemApiHandler = stack(SystemController::getRouterCallback(SystemApiExtensions::class), $authorization);
        $eventsHandler = stack(EventsController::getRouterCallback(), $authorization);
        $logHandler = stack(LogController::getRouterCallback(), $authorization);

        foreach (['GET', 'POST'] as $method) {
            $this->router->addRoute($method, '/api/{method}[/]', $apiHandler);
            $this->router->addRoute($method, '/api/{session:.*?[^/]}/{method}[/]', $apiHandler);

            $this->router->addRoute($method, '/system/{method}[/]', $systemApiHandler);
        }

        $this->router->addRoute('GET', '/events[/]', $eventsHandler);
        $this->router->addRoute('GET', '/events/{session:.*?[^/]}[/]', $eventsHandler);

        $this->router->addRoute('GET', '/log[/]', $logHandler);
        $this->router->addRoute('GET', '/log/{level:.*?[^/]}[/]', $logHandler);
    }


}