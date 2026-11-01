<?php

namespace App\Shared\UI\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends ApiInterface
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->responseData(['app' => 'GESTAD API', 'doc' => '/api/doc']);
    }
}
