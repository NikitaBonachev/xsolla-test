<?php

namespace App;

use Silex\Api\ControllerProviderInterface;
use Silex\Application as App;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Data\FilesStorage;
use App\Data\DataManager;

/**
 * Class ControllerProvider
 * @package App
 */
class ControllerProvider implements ControllerProviderInterface
{
    private $app;


    /**
     * @param App $app
     * @return mixed
     */
    public function connect(App $app)
    {
        $this->app = $app;

        $app->error([$this, 'error']);

        $controllers = $app['controllers_factory'];

        // Get all files
        $controllers
            ->get('/files', [$this, 'getFiles'])
            ->bind('files');

        // Upload new file
        $controllers
            ->post('/files', [$this, 'uploadNewFile']);

        // Download one file
        $controllers
            ->get('/files/{id}', [$this, 'getFile']);

        // Get file meta
        $controllers
            ->get('/files/{id}/meta', [$this, 'getFileMeta']);

        // Update file (I think it's little bit wrong to use POST here)
        $controllers
            ->post('/files/{id}/content', [$this, 'updateFile']);

        // Update file name
        $controllers
            ->put('/files/{id}/name', [$this, 'updateFileName']);

        // Delete file
        $controllers
            ->delete('/files/{id}', [$this, 'deleteFile']);

        return $controllers;
    }


    /**
     * Get all files
     *
     * @param App $app
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getFiles(App $app)
    {
        $db = $app['db'];
        $dataProvider = new DataManager($db);
        $result['list'] = $dataProvider->getFilesList();
        return $app->json($result);
    }


    /**
     * Update file
     *
     * @param App $app
     * @param Request $request
     * @param $id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function updateFile(App $app, Request $request, $id)
    {
        $file = $request->files->get('upload_file');
        $id = ApiUtils::checkRequestId($id);
        if (ApiUtils::checkRequestFile($file) && $id) {

            // Try to update
            $result['id'] = FilesStorage::updateFile($file, $id, $app);
            if ($result['id'] == 0) {

                return $app->json(
                    [
                        "code" => Response::HTTP_NOT_FOUND,
                        "message" => "File with this ID not found",
                        "request" => $request->getContent()
                    ],
                    Response::HTTP_NOT_FOUND
                );

            } else {
                return $app->json($result, Response::HTTP_OK);
            }

        } else {

            return $app->json(
                [
                    "code" => Response::HTTP_BAD_REQUEST,
                    "message" => "File missing or non valid ID",
                    "request" => $request->getContent()
                ],
                Response::HTTP_BAD_REQUEST
            );

        }
    }


    /**
     * Update file name
     *
     * @param App $app
     * @param Request $request
     * @param $id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function updateFileName(App $app, Request $request, $id)
    {
        $newName = json_decode($request->getContent(), true)['name'];
        $id = ApiUtils::checkRequestId($id);

        if (!$id) {

            $errorResponse = [
                "code" => Response::HTTP_BAD_REQUEST,
                "message" => "Bad Id or new file name",
                "request" => $request->getContent()
            ];

            return $app->json($errorResponse, Response::HTTP_BAD_REQUEST);

        }

        $result = FilesStorage::updateFileName($id, $newName, $app);

        if ($result > 0) {
            // Success update file name
            return $app->json(['id' => $id], Response::HTTP_OK);

        } elseif ($result == -1) {

            // File with this ID doesn't exist
            $errorResponse = [
                "code" => Response::HTTP_NOT_FOUND,
                "message" => "File with this id not found. Id = " . $id,
                "request" => $request->getContent()
            ];

            return $app->json($errorResponse, Response::HTTP_NOT_FOUND);

        } else {

            // File with this name already exist
            $errorResponse = [
                "code" => Response::HTTP_BAD_REQUEST,
                "message" => "File with this name already exists",
                "request" => $request->getContent()
            ];

            return $app->json($errorResponse, Response::HTTP_BAD_REQUEST);
        }
    }


    /**
     * Create new file
     *
     * @param App $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function uploadNewFile(App $app, Request $request)
    {
        $file = $request->files->get('upload_file');

        if (ApiUtils::checkRequestFile($file)) {

            $result['id'] = FilesStorage::createFile($file, $app);
            if ($result['id'] == -1) {
                // Wrong file name
                return $app->json(
                    [
                        "code" => Response::HTTP_BAD_REQUEST,
                        "message" => "File with this name already exists",
                        "request" => ""
                    ],
                    Response::HTTP_NOT_FOUND
                );

            } else {
                // Success
                return $app->json($result, Response::HTTP_CREATED);
            }

        } else {

            return $app->json(
                [
                    "code" => Response::HTTP_BAD_REQUEST,
                    "message" => "File missing",
                    "request" => ""
                ],
                Response::HTTP_NOT_FOUND
            );

        }
    }


    /**
     * Download one file
     *
     * @param App $app
     * @param $id
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function getFile(App $app, $id)
    {
        $fileInfo = FilesStorage::getFile($id, $app);
        return $app->sendFile(
            $fileInfo['filePath'],
            Response::HTTP_OK,
            [
                'Content-Type' => $fileInfo['Content-Type'],
                'Content-Disposition' => 'inline',
                'filename' => $fileInfo['filename']
            ]
        );
    }


    /**
     * File meta
     *
     * @param App $app
     * @param $id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getFileMeta(App $app, $id)
    {
        $id = ApiUtils::checkRequestId($id);
        if (!$id) {
            return $app->json(
                [
                    "code" => Response::HTTP_NOT_FOUND,
                    "message" => "File with this ID doesn't exist. ID = " . $id,
                    "request" => ""
                ],
                Response::HTTP_NOT_FOUND
            );
        }
        $fileMeta = FilesStorage::getFileMeta($id, $app);
        return $app->json($fileMeta);
    }


    /**
     * Delete file
     *
     * @param App $app
     * @param $id
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteFile(App $app, $id)
    {
        if (FilesStorage::deleteFile($id, $app) == 1) {
            return new Response('', Response::HTTP_NO_CONTENT);
        } else {
            return $app->json(
                [
                    "code" => Response::HTTP_NOT_FOUND,
                    "message" => "The requested resource could not be found",
                    "request" => ""
                ],
                Response::HTTP_NOT_FOUND
            );
        }
    }


    /**
     * Errors
     *
     * @param \Exception $e
     * @param Request $request
     * @param $code
     * @return Response
     */
    public function error(\Exception $e, Request $request, $code)
    {
        switch ($code) {
            case Response::HTTP_NOT_FOUND:
                $message = 'The requested resource could not be found.';
                break;
            default:
                $message = $e->getMessage();
        }

        return $this->app->json(
            [
                "code" => $code,
                "message" => $message,
                "request" => $request->getContent()
            ],
            $code
        );
    }
}
