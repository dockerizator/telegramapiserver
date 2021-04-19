<?php

namespace TelegramApiServer\Server;

use Amp;
use TelegramApiServer\Client;
use TelegramApiServer\Config;
use TelegramApiServer\Logger;

class Server
{
    /**
     * Server constructor.
     *
     * @param array $options
     * @param array|null $sessionFiles
     */
    public function __construct(array $options, ?array $sessionFiles)
    {
        Amp\Loop::defer(function () use ($options, $sessionFiles) {
            $server = new Amp\Http\Server\Server(
                $this->getServerAddresses(static::getConfig($options)),
                (new Router())->getRouter(),
                Logger::getInstance(),
                (new Amp\Http\Server\Options())
                    ->withCompression()
                    ->withBodySizeLimit(2*1000*1024*1024)//2Gb
                    ->withHttp1Timeout(600)
                    ->withHttp2Timeout(600)
            );

            yield from Client::getInstance()->connect($sessionFiles);
            $server->start();

            $this->registerShutdown($server);
        });

        while (true) {
            Amp\Loop::run();
        }

    }

    private static function getServerAddresses(array $config): array
    {
        return [
            Amp\Socket\Server::listen("{$config['address']}:{$config['port']}"),
        ];
    }

    /**
     * Stop the server gracefully when SIGINT is received.
     * This is technically optional, but it is best to call Server::stop().
     *
     * @param Amp\Http\Server\Server $server
     *
     * @throws Amp\Loop\UnsupportedFeatureException
     */
    private static function registerShutdown(Amp\Http\Server\Server $server)
    {
        if (defined('SIGINT')) {
            Amp\Loop::onSignal(SIGINT, static function (string $watcherId) use ($server) {
                emergency('Got SIGINT');
                Amp\Loop::cancel($watcherId);
                yield $server->stop();
                exit;
            });
        }
    }

    /**
     * Установить конфигурацию для http-сервера
     *
     * @param array $config
     * @return array
     */
    private function getConfig(array $config = []): array
    {
        $config =  array_filter($config);

        $config = array_merge(
            Config::getInstance()->get('server', []),
            $config
        );

        return $config;
    }

}