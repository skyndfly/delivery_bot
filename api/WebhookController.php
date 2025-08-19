<?php

namespace api;

use Exception;
use repositories\UserRepository;

class WebhookController
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * POST /api/webhook-add-user
     * Body: { "id": 123 }
     */
    public function addUser(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if(!isset($data['id'])){
                http_response_code(400);
                echo json_encode(['error' => 'id is required']);
                throw new Exception('Ошибка сохранения пользователя');
            }

            $userId = (int) $data['id'];
            $this->userRepository->addUser($userId);

            echo json_encode(['status' => 'ok', 'userId' => $userId]);
            log_dump('Добавлен пользователь: ' . $userId, 'WebhookController');
        }catch (Exception $exception){
            log_dump($exception->getMessage(), 'WebhookController');
        }
    }
}