<?php
namespace App;

use Silex\Api\ControllerProviderInterface;
use Silex\Application as App;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ControllerProvider implements ControllerProviderInterface
{
    private $app;

    public function connect(App $app)
    {
        $this->app = $app;

        $app->error([$this, 'error']);

        $controllers = $app['controllers_factory'];

        $controllers
            ->get('/', [$this, 'homepage'])
            ->bind('homepage');

        $controllers
            ->get('/blog', [$this, 'blog'])
            ->bind('blog');

        return $controllers;
    }

    public function homepage(App $app)
    {
        $result['state'] = 'homepage1';
        return $app->json($result);
    }

    public function blog(App $app)
    {
        $result['state'] = 'blog';
        return $app->json($result);
    }

    public function error(\Exception $e, Request $request, $code)
    {
        if ($this->app['debug']) {
            return;
        }

        switch ($code) {
            case 404:
                $message = 'The requested page could not be found.';
                break;
            default:
                $message = 'We are sorry, but something went terribly wrong.';
        }

        return new Response($message, $code);
    }

}