<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\JsonResponse;

class ApiController extends BaseController
{
    /**
     * Return a successful JSON response.
     *
     * @param mixed $data
     * @param array $meta
     * @param int $status
     * @return JsonResponse
     */
    protected function success($data, $meta = [], $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => $meta,
        ], $status);
    }

    /**
     * Return an error JSON response.
     *
     * @param string|array $errors
     * @param int $status
     * @return JsonResponse
     */
    protected function error($errors, $status = 400): JsonResponse
    {
        // If errors is a string, wrap it in an array
        if (is_string($errors)) {
            $errors = [['message' => $errors]];
        }
        // If errors is an array but not field-level, wrap it
        elseif (!isset($errors[0]['field']) && !isset($errors[0]['message'])) {
            $errors = [['message' => $errors]];
        }

        return response()->json([
            'errors' => $errors,
        ], $status);
    }

    /**
     * Return a 201 Created response.
     *
     * @param mixed $data
     * @param array $meta
     * @return JsonResponse
     */
    protected function created($data, $meta = []): JsonResponse
    {
        return $this->success($data, $meta, 201);
    }

    /**
     * Return a 204 No Content response.
     *
     * @return JsonResponse
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }
}
