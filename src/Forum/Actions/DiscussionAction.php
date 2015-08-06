<?php namespace Flarum\Forum\Actions;

use Psr\Http\Message\ServerRequestInterface as Request;
use Flarum\Http\RouteNotFoundException;

class DiscussionAction extends ClientAction
{
    /**
     * {@inheritdoc}
     */
    public function render(Request $request, array $routeParams = [])
    {
        $view = parent::render($request, $routeParams);

        $queryParams = $request->getQueryParams();
        $page = max(1, array_get($queryParams, 'page'));

        $params = [
            'id' => array_get($routeParams, 'id'),
            'page' => [
                'near' => array_get($routeParams, 'near'),
                'offset' => ($page - 1) * 20,
                'limit' => 20
            ]
        ];

        $document = $this->preload($params);

        $getResource = function ($link) use ($document) {
            return array_first($document->included, function ($key, $value) use ($link) {
                return $value->type === $link->type && $value->id === $link->id;
            });
        };

        $url = function ($newQueryParams) use ($queryParams, $document) {
            $newQueryParams = array_merge($queryParams, $newQueryParams);
            $queryString = [];

            foreach ($newQueryParams as $k => $v) {
                $queryString[] = $k . '=' . $v;
            }

            return app('Flarum\Http\UrlGeneratorInterface')
                ->toRoute('flarum.forum.discussion', ['id' => $document->data->id]) .
                ($queryString ? '?' . implode('&', $queryString) : '');
        };

        $posts = [];

        foreach ($document->included as $resource) {
            if ($resource->type === 'posts' && isset($resource->relationships->discussion) && isset($resource->attributes->contentHtml)) {
                $posts[] = $resource;
            }
        }

        $view->setTitle($document->data->attributes->title);
        $view->setDocument($document);
        $view->setContent(app('view')->make('flarum.forum::discussion', compact('document', 'page', 'getResource', 'posts', 'url')));

        return $view;
    }

    /**
     * Get the result of an API request to show a discussion.
     *
     * @param array $params
     * @return object
     */
    protected function preload(array $params)
    {
        $actor = app('flarum.actor');
        $action = 'Flarum\Api\Actions\Discussions\ShowAction';

        $response = $this->apiClient->send($actor, $action, $params);
        $statusCode = $response->getStatusCode();

        if ($statusCode === 404) {
            throw new RouteNotFoundException;
        }

        return $response->getBody();
    }
}
